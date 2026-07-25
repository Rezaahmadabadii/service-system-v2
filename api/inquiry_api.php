<?php
// api/inquiry_api.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../Core/Services/InquiryService.php';

header('Content-Type: application/json; charset=utf-8');

// ==================== دریافت action ====================
$action = $_GET['action'] ?? '';

// اگر action خالی بود، خطا برگردان
if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'عملیات مشخص نشده است']);
    exit;
}

// ==================== توابع کمکی ====================
function getUserIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return trim($ip);
}

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
        
        // حذف کامنت‌های درون خط
        $parts = explode('#', $line);
        $cleanLine = trim($parts[0]);
        if (empty($cleanLine)) continue;
        
        if ($cleanLine === $ip) {
            $hasSearchAccess = true;
        }
        if ($cleanLine === $ip . '*') {
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

$ip = getUserIP();
$config = require __DIR__ . '/../config/app.php';
$inquiryService = new Core\Services\InquiryService($config['inquiry_base_path']);

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
    
    // تبدیل ماه‌ها به لیست با نام فارسی
    $monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $monthList = [];
    foreach ($months as $m) {
        $monthList[] = [
            'value' => $m,
            'label' => $m . ' - ' . $monthNames[$m - 1]
        ];
    }
    
    echo json_encode(['success' => true, 'months' => $monthList]);
    exit;
}

// ============================================
// جستجوی نام - با محدودیت تاریخ جاری
// ============================================
if ($action === 'search') {
    $name = $_GET['name'] ?? '';
    $year = $_GET['year'] ?? '';
    $month = $_GET['month'] ?? '';
    $day = $_GET['day'] ?? null;
    
    // فقط نام و سال الزامی هستند - ماه اختیاری است
    if (empty($name) || empty($year)) {
        echo json_encode(['success' => false, 'error' => 'نام و سال الزامی است']);
        exit;
    }
    
    if (strlen($name) < 2) {
        echo json_encode(['success' => false, 'error' => 'نام باید حداقل ۲ کاراکتر باشد']);
        exit;
    }
    
    $access = checkIPAccess($ip, 'search');
    if (!$access['allowed']) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به این بخش ندارید']);
        exit;
    }
    
    // دریافت تاریخ جاری شمسی
    list($currentYear, $currentMonth, $currentDay) = $inquiryService->getJalaliToday();
    
    // اگر ماه خالی بود، همه ماه‌ها رو جستجو کن
    if (empty($month)) {
        $availableMonths = $inquiryService->getAvailableMonths($year);
        
        if (empty($availableMonths)) {
            echo json_encode([
                'success' => true, 
                'results' => [], 
                'count' => 0, 
                'file' => 'همه ماه‌ها'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $allResults = [];
        foreach ($availableMonths as $m) {
            $filePath = $inquiryService->findFile($year, $m);
            if ($filePath) {
                $results = $inquiryService->searchInFile($filePath, $name, $day);
                
                // اعمال محدودیت تاریخ برای ماه جاری
                if ($year == $currentYear && $m == $currentMonth) {
                    $results = array_filter($results, function($item) use ($currentDay) {
                        return intval($item['day']) <= $currentDay;
                    });
                    // بازآرایه‌سازی ایندکس‌ها بعد از فیلتر
                    $results = array_values($results);
                }
                
                $allResults = array_merge($allResults, $results);
            }
        }
        
        $results = $allResults;
        $searchedFile = 'همه ماه‌ها';
    } else {
        // جستجو در یک ماه خاص
        $filePath = $inquiryService->findFile($year, $month);
        if (!$filePath) {
            echo json_encode([
                'success' => true, 
                'results' => [], 
                'count' => 0, 
                'file' => 'فایل یافت نشد'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $results = $inquiryService->searchInFile($filePath, $name, $day);
        
        // اعمال محدودیت تاریخ برای ماه جاری
        if ($year == $currentYear && $month == $currentMonth) {
            $results = array_filter($results, function($item) use ($currentDay) {
                return intval($item['day']) <= $currentDay;
            });
            // بازآرایه‌سازی ایندکس‌ها بعد از فیلتر
            $results = array_values($results);
        }
        
        $searchedFile = basename($filePath);
    }
    
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
        'month' => $month ?: 'همه ماه‌ها',
        'day' => $day,
        'results_count' => count($results)
    ];
    
    if (count($history) > 1000) {
        $history = array_slice($history, -1000);
    }
    
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'file' => $searchedFile
    ], JSON_UNESCAPED_UNICODE);
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
    
    // فقط روز جاری (امروز) رو نمایش بده
    $results = array_filter($results, function($item) use ($day) {
        return intval($item['day']) == $day;
    });
    $results = array_values($results);
    
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
    
    // فقط روز دیروز رو نمایش بده
    $results = array_filter($results, function($item) use ($day) {
        return intval($item['day']) == $day;
    });
    $results = array_values($results);
    
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
// دریافت تاریخچه جستجوها
// ============================================
if ($action === 'get_history') {
    $access = checkIPAccess($ip, 'search');
    if (!$access['allowed']) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به این بخش ندارید']);
        exit;
    }
    
    $historyFile = __DIR__ . '/../storage/logs/search_history.json';
    $history = [];
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true) ?? [];
        $history = array_reverse($history);
    }
    
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}

// ============================================
// عملیات نامشخص
// ============================================
echo json_encode(['success' => false, 'error' => 'عملیات نامشخص']);
exit;
?>