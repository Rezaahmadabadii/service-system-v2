<?php
// api/inquiry_api.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../Core/Services/InquiryService.php';

header('Content-Type: application/json; charset=utf-8');

// دریافت IP کاربر
function getUserIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return trim($ip);
}

// بررسی دسترسی IP
function checkIPAccess($ip, $requiredAccess = 'search') {
    $ipFile = __DIR__ . '/../storage/security/allowed_ips.txt';
    if (!file_exists($ipFile)) {
        return ['allowed' => false, 'message' => 'فایل IP مجاز یافت نشد'];
    }
    
    $lines = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $hasSearchAccess = false;
    $hasFullAccess = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        if ($line === $ip) {
            $hasSearchAccess = true;
        }
        if ($line === $ip . '*') {
            $hasSearchAccess = true;
            $hasFullAccess = true;
        }
    }
    
    if ($requiredAccess === 'search') {
        return ['allowed' => $hasSearchAccess, 'has_full_access' => $hasFullAccess];
    }
    
    if ($requiredAccess === 'full') {
        return ['allowed' => $hasFullAccess, 'has_full_access' => $hasFullAccess];
    }
    
    return ['allowed' => false, 'has_full_access' => false];
}

// ============================================
// پردازش درخواست‌ها
// ============================================

$action = $_GET['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'عملیات مشخص نشده است']);
    exit;
}

$ip = getUserIP();
$config = require __DIR__ . '/../config/app.php';
$inquiryService = new Core\Services\InquiryService($config['inquiry_base_path']);

// ============================================
// جستجوی نام
// ============================================
if ($action === 'search') {
    $name = $_GET['name'] ?? '';
    $year = $_GET['year'] ?? '';
    $month = $_GET['month'] ?? '';
    $day = $_GET['day'] ?? null;
    
    if (empty($name) || empty($year) || empty($month)) {
        echo json_encode(['success' => false, 'error' => 'نام، سال و ماه الزامی است']);
        exit;
    }
    
    // بررسی دسترسی IP
    $access = checkIPAccess($ip, 'search');
    if (!$access['allowed']) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به این بخش ندارید']);
        exit;
    }
    
    $filePath = $inquiryService->findFile($year, $month);
    if (!$filePath) {
        echo json_encode(['success' => false, 'error' => 'فایلی برای سال و ماه مورد نظر یافت نشد']);
        exit;
    }
    
    $results = $inquiryService->searchInFile($filePath, $name, $day);
    
    // ذخیره تاریخچه
    $historyFile = __DIR__ . '/../storage/logs/search_history.json';
    $history = [];
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true) ?? [];
    }
    
    $history[] = [
        'date' => date('Y-m-d H:i:s'),
        'ip' => $ip,
        'search_term' => $name,
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'results_count' => count($results)
    ];
    
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'file' => basename($filePath)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// دریافت سال‌های موجود
// ============================================
if ($action === 'get_years') {
    $access = checkIPAccess($ip, 'search');
    if (!$access['allowed']) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به این بخش ندارید']);
        exit;
    }
    
    $years = $inquiryService->getAvailableYears();
    echo json_encode(['success' => true, 'years' => $years]);
    exit;
}

// ============================================
// دریافت ماه‌های موجود برای یک سال
// ============================================
if ($action === 'get_months') {
    $year = $_GET['year'] ?? '';
    if (empty($year)) {
        echo json_encode(['success' => false, 'error' => 'سال مشخص نشده است']);
        exit;
    }
    
    $access = checkIPAccess($ip, 'search');
    if (!$access['allowed']) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به این بخش ندارید']);
        exit;
    }
    
    $months = $inquiryService->getAvailableMonths($year);
    echo json_encode(['success' => true, 'months' => $months]);
    exit;
}

// ============================================
// نمایش کل واریزی‌های امروز
// ============================================
if ($action === 'today_all') {
    $access = checkIPAccess($ip, 'full');
    if (!$access['allowed']) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به این بخش ندارید']);
        exit;
    }
    
    list($year, $month, $day) = $inquiryService->getJalaliToday();
    $filePath = $inquiryService->findFile($year, $month);
    
    if (!$filePath) {
        echo json_encode(['success' => false, 'error' => 'فایل امروز یافت نشد']);
        exit;
    }
    
    $results = $inquiryService->getAllTransfersForDate($year, $month, $day);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'date' => "$year/$month/$day",
        'label' => 'امروز'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// نمایش کل واریزی‌های دیروز
// ============================================
if ($action === 'yesterday_all') {
    $access = checkIPAccess($ip, 'full');
    if (!$access['allowed']) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به این بخش ندارید']);
        exit;
    }
    
    list($year, $month, $day) = $inquiryService->getJalaliYesterday();
    $filePath = $inquiryService->findFile($year, $month);
    
    if (!$filePath) {
        echo json_encode(['success' => false, 'error' => 'فایل دیروز یافت نشد']);
        exit;
    }
    
    $results = $inquiryService->getAllTransfersForDate($year, $month, $day);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'date' => "$year/$month/$day",
        'label' => 'دیروز'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// عملیات نامشخص
// ============================================
echo json_encode(['success' => false, 'error' => 'عملیات نامشخص']);
exit;