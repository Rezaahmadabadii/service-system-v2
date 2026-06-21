<?php
// index.php - نسخه نهایی با دکمه درخواست اضافه/اصلاح کد ملی (سه رقم) و لاگ جستجوها

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);

$config = require_once __DIR__ . '/config/app.php';
$nationalCodesDatabase = require_once __DIR__ . '/Core/Helpers/national_codes_database.php';

// اضافه کردن کلاس تاریخ شمسی
require_once __DIR__ . '/jdatetime.class.php';

spl_autoload_register(function ($class) {
    $prefix = 'Core\\';
    $base_dir = __DIR__ . '/Core/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = $base_dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});

use Core\Services\NationalCodeService;
use Core\Services\ExcelSearchService;
use Core\Services\SearchLogger;

// دریافت آخرین تاریخ بروزرسانی فایل کش
$cacheFile = $config['excel_cache_path'];
$lastUpdate = '---';
if (file_exists($cacheFile)) {
    $fileTime = filemtime($cacheFile);
    $lastUpdate = jDateTime::date('Y/m/d', $fileTime, false, true);
}

$nationalCodeService = new NationalCodeService($nationalCodesDatabase, $config['national_codes_override_path']);
$excelSearchService = new ExcelSearchService(
    $config['excel_file_path'],
    $config['excel_cache_path'],
    100,
    $config['search_priorities'],
    $config['city_codes_path']
);

// ایجاد لاگر
$logger = new SearchLogger(__DIR__ . '/storage/logs/search.log');

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (isset($_GET['refresh_cache'])) {
        $cacheFile = $config['excel_cache_path'];
        $excelFile = $config['excel_file_path'];
        
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        
        $data = [];
        $handle = fopen($excelFile, 'r');
        if ($handle) {
            $headers = fgetcsv($handle, 0, ',');
            $headers = array_map('trim', $headers);
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $rowData = [];
                foreach ($headers as $idx => $header) {
                    $rowData[$header] = isset($row[$idx]) ? trim($row[$idx]) : '';
                }
                $data[] = $rowData;
            }
            fclose($handle);
            
            $cacheContent = "<?php\n\nreturn [\n    'headers' => " . var_export($headers, true) . ",\n    'data' => " . var_export($data, true) . "\n];\n";
            file_put_contents($cacheFile, $cacheContent);
            
            echo json_encode(['success' => true, 'message' => 'کش با موفقیت بروزرسانی شد'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در خواندن فایل'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    if (isset($_GET['national_code'])) {
        $code = preg_replace('/[^0-9]/', '', $_GET['national_code']);
        $validation = $nationalCodeService->validate($code);
        $location = $nationalCodeService->getLocationInfo($code);
        $excelSearchResult = $excelSearchService->searchNationalCodeInExcel($code);
        
        $cityOptions = [];
        $isUnknown = false;
        $isNationalUnknown = ($location['province'] === '-' || $location['province'] === 'نامشخص');
        
        if ($validation['valid'] && $location['city'] !== '-') {
            $cityOptions = $excelSearchService->searchCityOptions($location['city']);
            if (empty($cityOptions)) {
                $isUnknown = true;
            }
        }
        
        // ثبت لاگ جستجوی کد ملی
        $resultCount = ($excelSearchResult && $excelSearchResult['found']) ? $excelSearchResult['count'] : 0;
        $logger->log('national', $code, $resultCount, [
            'province' => $location['province'],
            'city' => $location['city'],
            'valid' => $validation['valid']
        ]);
        
        echo json_encode([
            'valid' => $validation['valid'],
            'message' => $validation['message'],
            'code' => $code,
            'province' => $location['province'],
            'city' => $location['city'],
            'city_options' => $cityOptions,
            'is_unknown' => $isUnknown,
            'is_national_unknown' => $isNationalUnknown,
            'excel_found' => ($excelSearchResult && $excelSearchResult['found']),
            'excel_results' => $excelSearchResult ? $excelSearchResult['results'] : null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (isset($_GET['get_city_code'])) {
        $selectedCity = $_GET['city_name'];
        $result = $excelSearchService->getCityCode($selectedCity);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (isset($_GET['manual_city_search'])) {
        $searchTerm = $_GET['manual_city_search'];
        $options = $excelSearchService->searchCityOptions($searchTerm);
        echo json_encode(['found' => !empty($options), 'options' => $options], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (isset($_GET['city_search'])) {
        $searchTerm = $_GET['city_search'];
        $options = $excelSearchService->searchCityOptions($searchTerm);
        
        // ثبت لاگ جستجوی شهر
        $logger->log('city', $searchTerm, count($options));
        
        echo json_encode(['success' => true, 'options' => $options], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (isset($_GET['search'])) {
        $keyword = $_GET['search'];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 10;
        
        $allResults = $excelSearchService->search($keyword, null);
        
        $total = $allResults['count'];
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        
        $paginatedResults = array_slice($allResults['results'], $offset, $perPage);
        
        // ثبت لاگ جستجوی عمومی
        $logger->log('general', $keyword, $total);
        
        echo json_encode([
            'success' => true,
            'results' => $paginatedResults,
            'count' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'keyword' => $keyword
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (isset($_GET['request_city'])) {
        $code = $_GET['code'];
        $province = $_GET['province'];
        $city = $_GET['city'];
        $suggestedProvince = $_GET['suggested_province'];
        $suggestedCity = $_GET['suggested_city'];
        
        $requestsFile = __DIR__ . '/storage/database/city_requests.json';
        $requests = [];
        if (file_exists($requestsFile)) {
            $requests = json_decode(file_get_contents($requestsFile), true);
        }
        
        $requests[] = [
            'id' => uniqid(),
            'code' => $code,
            'province' => $province,
            'city' => $city,
            'suggested_province' => $suggestedProvince,
            'suggested_city' => $suggestedCity,
            'date' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ];
        
        file_put_contents($requestsFile, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode(['success' => true, 'message' => 'درخواست شما با موفقیت ثبت شد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (isset($_GET['request_national_code'])) {
        $code = $_GET['code'];
        $prefix = $_GET['prefix'];
        $suggestedProvince = $_GET['suggested_province'];
        $suggestedCity = $_GET['suggested_city'];
        
        $requestsFile = __DIR__ . '/storage/database/national_code_requests.json';
        $requests = [];
        if (file_exists($requestsFile)) {
            $requests = json_decode(file_get_contents($requestsFile), true);
        }
        
        $requests[] = [
            'id' => uniqid(),
            'code' => $code,
            'prefix' => $prefix,
            'suggested_province' => $suggestedProvince,
            'suggested_city' => $suggestedCity,
            'date' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ];
        
        file_put_contents($requestsFile, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode(['success' => true, 'message' => 'درخواست شما با موفقیت ثبت شد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>اعتبارسنجی کد ملی و استعلام در برهان</title>
	<link rel="icon" type="image/x-icon" href="/service-system-v2/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            direction: rtl;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 16px 24px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .logo h1 {
            font-size: 1.3rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo p {
            font-size: 0.7rem;
            color: #6c757d;
        }
        
        .menu-buttons {
            display: flex;
            gap: 10px;
        }
        
        .menu-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 30px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .menu-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        
        .three-columns {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .column {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }
        
        .column:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .column-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 14px 18px;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .column-header i {
            margin-left: 8px;
        }
        
        .column-body {
            padding: 18px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 0.75rem;
            color: #2c3e50;
        }
        
        .input-wrapper {
            display: flex;
            gap: 8px;
        }
        
        input[type="text"] {
            flex: 1;
            padding: 10px 14px;
            border: 1.5px solid #e9ecef;
            border-radius: 12px;
            font-size: 0.85rem;
            font-family: monospace;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .clear-btn {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .clear-btn:hover {
            background: #c0392b;
            transform: scale(1.02);
        }
        
        .small-text {
            font-size: 0.65rem;
            color: #6c757d;
            margin-top: 6px;
        }
        
        .result-container {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .result-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 12px 18px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .result-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .result-badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
        }
        
        .result-content {
            padding: 18px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .result-box {
            padding: 14px;
            border-radius: 14px;
            border-right: 3px solid;
            margin-bottom: 14px;
        }
        
        .result-valid {
            background: #e8f5e9;
            border-right-color: #28a745;
        }
        
        .result-invalid {
            background: #ffebee;
            border-right-color: #dc3545;
        }
        
        .result-status {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .location-box {
            background: white;
            padding: 10px;
            border-radius: 10px;
            margin-top: 10px;
            border: 1px solid #e9ecef;
            font-size: 0.8rem;
        }
        
        .city-selector select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.8rem;
            margin: 8px 0;
        }
        
        .city-code-result {
            background: #e9ecef;
            padding: 10px;
            border-radius: 10px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .copy-btn, .request-btn, .request-national-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.7rem;
            transition: all 0.3s ease;
        }
        
        .copy-btn:hover, .request-btn:hover, .request-national-btn:hover {
            transform: scale(1.02);
        }
        
        .request-btn {
            background: #f39c12;
            margin-top: 10px;
            width: 100%;
        }
        
        .request-national-btn {
            background: #9b59b6;
            margin-top: 10px;
            width: 100%;
        }
        
        .table-wrapper {
            overflow-x: auto;
            max-height: 350px;
            overflow-y: auto;
            margin-top: 10px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }
        
        .excel-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
            min-width: 500px;
        }
        
        .excel-table th, .excel-table td {
            border: 1px solid #e9ecef;
            padding: 8px 10px;
            text-align: right;
        }
        
        .excel-table th {
            background: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        .loading {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        .no-result {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        hr {
            margin: 12px 0;
            border: none;
            border-top: 1px solid #e9ecef;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 24px;
            max-width: 400px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin-bottom: 16px;
            font-size: 1.1rem;
            color: #2c3e50;
        }
        
        .modal-content input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        
        .modal-buttons button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-save {
            background: #28a745;
            color: white;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .footer {
            text-align: center;
            padding: 16px;
            margin-top: 20px;
            color: rgba(255,255,255,0.8);
            font-size: 0.65rem;
        }
        
        @media (max-width: 1000px) {
            .three-columns {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 700px) {
            .three-columns {
                grid-template-columns: 1fr;
            }
            body {
                padding: 12px;
            }
            .header {
                flex-direction: column;
                text-align: center;
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease;
        }
		        /* ========== استایل صفحه‌بندی ========== */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .page-btn {
            padding: 6px 12px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s ease;
        }
        
        .page-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        /* ========== استایل انتخاب شهر و نتیجه ========== */
        .city-selector {
            background: #fff3e0;
            padding: 12px;
            border-radius: 12px;
            margin-top: 10px;
        }
        
        .city-code-result {
            background: #fff9c4;
            padding: 8px 12px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            word-break: break-all;
            border-right: 3px solid #f9a825;
        }

        /* ========== استایل لوگو با انیمیشن زوم و محو نرم ========== */
        .footer img {
            max-height: 40px;
            width: auto;
            transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            position: relative;
        }

        .footer img:hover {
            transform: scale(1.5);
        }

        .footer img.zoom-fade {
            animation: zoomFadeOut 3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes zoomFadeOut {
            0% {
                transform: scale(1.5);
                opacity: 1;
            }
            100% {
                transform: scale(12);
                opacity: 0;
            }
        }

        .footer img.zoom-fade-back {
            animation: zoomFadeIn 2.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        @keyframes zoomFadeIn {
            0% {
                transform: scale(0.1);
                opacity: 0;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        /* ======================================================== */
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo">
            <h1><i class="fas fa-id-card"></i> اعتبارسنجی کدملی و استعلام اطلاعات کد های تفصیل ثبت شده در برهان</h1>
            <p>اعتبارسنجی | تشخیص استان و شهر | جستجو در دیتابیس کدهای تفصیل برهان</p>
        </div>
        <div class="menu-buttons">
            <div style="font-size: 0.7rem; color: #2c3e50; background: #e9ecef; padding: 6px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fas fa-calendar-alt"></i> آخرین بروزرسانی: <span id="lastUpdateDate">---</span>
            </div>
            <button class="menu-btn" id="refreshCacheBtn" style="background:#e67e22;">
                <i class="fas fa-sync-alt"></i> بروزرسانی کدهای تفصیل
            </button>
            <button class="menu-btn" onclick="openAdminModal()">
                <i class="fas fa-lock"></i> پنل مدیریت
            </button>
        </div>
    </div>
    
    <div class="three-columns">
        <div class="column">
            <div class="column-header">
                <i class="fas fa-id-card"></i> استعلام و اعتبارسنجی کد ملی (جستجوی خودکار در برهان)
            </div>
            <div class="column-body">
                <div class="form-group">
                    <label>کد ملی ۱۰ رقمی</label>
                    <div class="input-wrapper">
                        <input type="text" id="nationalCode" maxlength="10" placeholder="مثال: 0630010099">
                        <button type="button" class="clear-btn" id="clearNationalBtn"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="small-text"><i class="fas fa-magic"></i> پس از وارد کردن دهمین رقم، نتیجه نمایش داده می‌شود</div>
                </div>
            </div>
        </div>
        
        <div class="column">
            <div class="column-header">
                <i class="fas fa-search"></i> جستجوی عمومی در برهان
            </div>
            <div class="column-body">
                <div class="form-group">
                    <label>عبارت جستجو (نام و نام خانوادگی، کد/شناسه ملی، شماره اقتصادی، کد تفصیل، تلفن و ...)</label>
                    <div class="input-wrapper">
                        <input type="text" id="generalSearch" placeholder="مثال: احمدآبادی یا 09353984864">
                        <button type="button" class="clear-btn" id="clearGeneralBtn"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="small-text"><i class="fas fa-search"></i> جستجو در همه ستون‌ها ( با رفع باگ فاصله بین کلمات )</div>
                </div>
            </div>
        </div>
        
        <div class="column">
            <div class="column-header">
                <i class="fas fa-city"></i> جستجوی کد شهر و استان (خودکار)
            </div>
            <div class="column-body">
                <div class="form-group">
                    <label>نام شهر را وارد کنید</label>
                    <div class="input-wrapper">
                        <input type="text" id="citySearch" placeholder="  مثال: مشهد یا اسفراین">
                        <button type="button" class="clear-btn" id="clearCityBtn"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="small-text"><i class="fas fa-arrow-right"></i> پس از وارد کردن حداقل ۲ کاراکتر، نتایج نمایش داده می‌شوند</div>
                </div>
                <div id="citySearchResult"></div>
            </div>
        </div>
    </div>
    
    <div class="result-container">
        <div class="result-header">
            <div class="result-title"><i class="fas fa-clipboard-list"></i> نتیجه جستجو  :</div>
            <div class="result-badge" id="resultBadge">در انتظار جستجو</div>
        </div>
        <div class="result-content" id="resultContent">
            <div class="no-result"><i class="fas fa-search"></i> جستجویی انجام نشده است</div>
        </div>
    </div>
    
    <div class="footer">
        <p><i class="fas fa-code"></i> Dev : Reza.ahmadabadi | <i class="fas fa-phone"></i> 09353984864</p>
        <div style="margin-top: 10px;">
            <img src="/invoice-system-v2/assets/images/logo.png" alt="لوگو" style="max-height: 40px; width: auto; transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); cursor: pointer; position: relative;" onmouseover="this.style.transform='scale(1.5)'" onmouseout="this.style.transform='scale(1)'" onerror="this.style.display='none'">
        </div>
    </div>
</div>

<div id="adminModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-lock"></i> ورود به پنل مدیریت</h3>
        <p style="font-size:0.7rem;color:#6c757d;margin-bottom:12px;">برای اصلاح دیتابیس کدهای ملی</p>
        <input type="password" id="adminPassword" placeholder="رمز عبور مدیریت" style="text-align:center;">
        <div class="modal-buttons">
            <button class="btn-save" onclick="checkAdminLogin()"><i class="fas fa-sign-in-alt"></i> ورود</button>
            <button class="btn-cancel" onclick="closeAdminModal()"><i class="fas fa-times"></i> انصراف</button>
        </div>
    </div>
</div>

<div id="requestCityModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-edit"></i> درخواست اصلاح اطلاعات شهر</h3>
        <p style="font-size:0.7rem;color:#6c757d;margin-bottom:12px;">لطفاً اطلاعات صحیح استان و شهر را وارد کنید</p>
        <input type="hidden" id="requestCode">
        <input type="hidden" id="requestProvince">
        <input type="hidden" id="requestCity">
        <div class="form-group">
            <label>استان صحیح</label>
            <input type="text" id="suggestedProvince" placeholder="مثال: خراسان رضوی">
        </div>
        <div class="form-group">
            <label>شهر صحیح</label>
            <input type="text" id="suggestedCity" placeholder="مثال: مشهد">
        </div>
        <div class="modal-buttons">
            <button class="btn-save" onclick="submitCityRequest()"><i class="fas fa-paper-plane"></i> ارسال درخواست</button>
            <button class="btn-cancel" onclick="closeRequestModal()"><i class="fas fa-times"></i> انصراف</button>
        </div>
    </div>
</div>

<div id="requestNationalCodeModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-plus-circle"></i> درخواست اضافه/اصلاح کد ملی</h3>
        <p style="font-size:0.7rem;color:#6c757d;margin-bottom:12px;">اطلاعات صحیح استان و شهر را برای سه رقم اول کد ملی وارد کنید</p>
        <input type="hidden" id="requestNationalCodeValue">
        <div class="form-group">
            <label>سه رقم اول کد ملی</label>
            <input type="text" id="requestNationalCodePrefix" readonly style="background:#e9ecef; font-weight:bold;">
        </div>
        <div class="form-group">
            <label>استان صحیح</label>
            <input type="text" id="requestNationalProvince" placeholder="مثال: خراسان رضوی">
        </div>
        <div class="form-group">
            <label>شهر صحیح</label>
            <input type="text" id="requestNationalCity" placeholder="مثال: مشهد">
        </div>
        <div class="modal-buttons">
            <button class="btn-save" onclick="submitNationalCodeRequest()"><i class="fas fa-paper-plane"></i> ارسال درخواست</button>
            <button class="btn-cancel" onclick="closeNationalCodeRequestModal()"><i class="fas fa-times"></i> انصراف</button>
        </div>
    </div>
</div>

<script>
// ==================== متغیرها ====================
let nationalTimeout = null;
let searchTimeout = null;
let citySearchTimeout = null;
let lastNationalCode = '';

// ========== متغیرهای صفحه‌بندی (اضافه شود) ==========
let currentPage = 1;
let totalPages = 1;
let currentKeyword = '';
let currentSearchType = 'none';
// ==================================================
const nationalInput = document.getElementById('nationalCode');
const generalInput = document.getElementById('generalSearch');
const citySearchInput = document.getElementById('citySearch');
const resultContent = document.getElementById('resultContent');
const resultBadge = document.getElementById('resultBadge');
const refreshCacheBtn = document.getElementById('refreshCacheBtn');
const clearNationalBtn = document.getElementById('clearNationalBtn');
const clearGeneralBtn = document.getElementById('clearGeneralBtn');
const clearCityBtn = document.getElementById('clearCityBtn');
const adminModal = document.getElementById('adminModal');
const requestCityModal = document.getElementById('requestCityModal');
// نمایش آخرین تاریخ بروزرسانی
const lastUpdateSpan = document.getElementById('lastUpdateDate');
if (lastUpdateSpan) {
    lastUpdateSpan.textContent = '<?php echo $lastUpdate; ?>';
}
// ==================== دکمه‌های پاک کردن ====================
clearNationalBtn.onclick = () => {
    nationalInput.value = '';
    nationalInput.focus();
    resultContent.innerHTML = '<div class="no-result">🔍 جستجویی انجام نشده است</div>';
    resultBadge.textContent = 'در انتظار جستجو';
    lastNationalCode = '';
};

clearGeneralBtn.onclick = () => {
    generalInput.value = '';
    generalInput.focus();
    resultContent.innerHTML = '<div class="no-result">🔍 جستجویی انجام نشده است</div>';
    resultBadge.textContent = 'در انتظار جستجو';
};

if (clearCityBtn) {
    clearCityBtn.onclick = () => {
        citySearchInput.value = '';
        citySearchInput.focus();
        document.getElementById('citySearchResult').innerHTML = '';
    };
}

// ==================== توابع کمکی ====================
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function copyToClipboard(text) {
    // روش جایگزین برای کپی
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast('✅ کد با موفقیت کپی شد');
        } else {
            showToast('❌ خطا در کپی');
        }
    } catch (err) {
        showToast('❌ خطا در کپی');
    }
    
    document.body.removeChild(textarea);
}

function showToast(message) {
    let toast = document.createElement('div');
    toast.textContent = message;
    toast.style.cssText = 'position:fixed; bottom:20px; left:20px; background:#28a745; color:white; padding:8px 16px; border-radius:12px; z-index:9999; font-size:0.8rem; z-index:9999;';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
}

// ==================== مودال‌ها ====================
function openAdminModal() {
    adminModal.style.display = 'flex';
    document.getElementById('adminPassword').value = '';
}

function closeAdminModal() {
    adminModal.style.display = 'none';
}

function openRequestModal(code, province, city) {
    document.getElementById('requestCode').value = code;
    document.getElementById('requestProvince').value = province;
    document.getElementById('requestCity').value = city;
    document.getElementById('suggestedProvince').value = '';
    document.getElementById('suggestedCity').value = '';
    requestCityModal.style.display = 'flex';
}

function closeRequestModal() {
    requestCityModal.style.display = 'none';
}

function submitCityRequest() {
    const code = document.getElementById('requestCode').value;
    const province = document.getElementById('requestProvince').value;
    const city = document.getElementById('requestCity').value;
    const suggestedProvince = document.getElementById('suggestedProvince').value.trim();
    const suggestedCity = document.getElementById('suggestedCity').value.trim();
    
    if (!suggestedProvince || !suggestedCity) {
        showToast('⚠️ لطفاً استان و شهر صحیح را وارد کنید');
        return;
    }
    
    fetch(`?ajax=1&request_city=1&code=${code}&province=${encodeURIComponent(province)}&city=${encodeURIComponent(city)}&suggested_province=${encodeURIComponent(suggestedProvince)}&suggested_city=${encodeURIComponent(suggestedCity)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('✅ درخواست شما با موفقیت ثبت شد');
                closeRequestModal();
            } else {
                showToast('❌ خطا در ثبت درخواست');
            }
        })
        .catch(() => showToast('❌ خطا در ارتباط با سرور'));
}

function openNationalCodeRequestModal(code) {
    const prefix = code.substring(0, 3);
    document.getElementById('requestNationalCodeValue').value = code;
    document.getElementById('requestNationalCodePrefix').value = prefix;
    document.getElementById('requestNationalProvince').value = '';
    document.getElementById('requestNationalCity').value = '';
    document.getElementById('requestNationalCodeModal').style.display = 'flex';
}

function closeNationalCodeRequestModal() {
    document.getElementById('requestNationalCodeModal').style.display = 'none';
}

function submitNationalCodeRequest() {
    const code = document.getElementById('requestNationalCodeValue').value;
    const prefix = document.getElementById('requestNationalCodePrefix').value;
    const suggestedProvince = document.getElementById('requestNationalProvince').value.trim();
    const suggestedCity = document.getElementById('requestNationalCity').value.trim();
    
    if (!suggestedProvince || !suggestedCity) {
        showToast('⚠️ لطفاً استان و شهر صحیح را وارد کنید');
        return;
    }
    
    fetch(`?ajax=1&request_national_code=1&code=${code}&prefix=${prefix}&suggested_province=${encodeURIComponent(suggestedProvince)}&suggested_city=${encodeURIComponent(suggestedCity)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('✅ درخواست شما با موفقیت ثبت شد');
                closeNationalCodeRequestModal();
            } else {
                showToast('❌ خطا در ثبت درخواست');
            }
        })
        .catch(() => showToast('❌ خطا در ارتباط با سرور'));
}

function checkAdminLogin() {
    const password = document.getElementById('adminPassword').value;
    if (password === '009009') {
        window.location.href = 'admin.php';
    } else {
        showToast('❌ رمز عبور اشتباه است');
        document.getElementById('adminPassword').value = '';
    }
}

function getCityCode(selectedValue) {
    if (!selectedValue) return;
    
    fetch(`?ajax=1&get_city_code&city_name=${encodeURIComponent(selectedValue)}`)
        .then(res => res.json())
        .then(data => {
            if (data.found && data.code) {
                // به div اصلی margin-top اضافه کنید
                let html = '<div style="display: flex; align-items: center; gap: 10px; background: #e9ecef; padding: 8px 12px; border-radius: 8px; margin-top: 18px;">';
                html += '<span style="color: #e74c3c; font-family: monospace; font-size: 0.75rem;">' + escapeHtml(data.code) + '</span>';
                html += '<button class="copy-btn" onclick="copyToClipboard(\'' + escapeHtml(data.code).replace(/'/g, "\\'") + '\')" style="padding: 4px 10px; background: #1a73e8; color: white; border: none; border-radius: 5px; cursor: pointer;">📋 کپی</button>';
                html += '</div>';
                document.getElementById('cityCodeResult').innerHTML = html;
            } else {
                document.getElementById('cityCodeResult').innerHTML = '<div style="color: #e74c3c; font-size: 0.7rem; margin-top: 18px;">❌ کدی یافت نشد</div>';
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('cityCodeResult').innerHTML = '<div style="color: #e74c3c; font-size: 0.7rem; margin-top: 18px;">❌ خطا در دریافت کد</div>';
        });
}

function selectCityFromSearch(selectedValue) {
    if (!selectedValue) return;
    let parts = selectedValue.split('|');
    let code = parts[1];
    let html = '<div class="city-code-result">';
    html += `<span dir="ltr">${escapeHtml(code)}</span>`;
    html += `<button class="copy-btn" onclick="copyToClipboard('${escapeHtml(code).replace(/'/g, "\\'")}')">📋 کپی</button>`;
    html += '</div>';
    let resultDiv = document.getElementById('citySearchCodeResult');
    if (resultDiv) resultDiv.innerHTML = html;
}

function displayNationalResult(data) {
    if (!data) return;
    
    let html = '';
    let statusClass = data.valid ? 'result-valid' : 'result-invalid';
    html += '<div class="result-box ' + statusClass + ' fade-in">';
    html += '<div class="result-status">' + (data.valid ? '✅' : '❌') + ' ' + (data.valid ? 'کد ملی معتبر است' : data.message) + '</div>';
    
    if (data.valid && data.province && data.province !== '-') {
        html += '<div class="location-box">';
        html += '<div><strong><i class="fas fa-map-marker-alt"></i> استان:</strong> ' + data.province + '</div>';
        html += '<div><strong><i class="fas fa-city"></i> شهر:</strong> ' + data.city + '</div>';
        html += '</div>';
        
        if (data.is_unknown) {
            html += '<button class="request-btn" onclick="openRequestModal(\'' + data.code + '\', \'' + data.province + '\', \'' + data.city + '\')">';
            html += '<i class="fas fa-edit"></i> اطلاعات شهر صحیح نیست؟ درخواست اصلاح دهید</button>';
        }
    }
    
    if (data.valid && data.is_national_unknown) {
        html += '<button class="request-national-btn" onclick="openNationalCodeRequestModal(\'' + data.code + '\')">';
        html += '<i class="fas fa-plus-circle"></i> استان و شهر این کد ملی در دیتابیس نیست؟ درخواست اضافه دهید</button>';
    }
      
    if (data.city_options && data.city_options.length > 0) {
        html += '<div class="city-selector" style="display: flex; align-items: center; gap: 15px;">';
        html += '    <div style="flex: 1; min-width: 180px;">';
        html += '        <div style="font-size:0.75rem; margin-bottom:6px;">🔽 گزینه‌های موجود برای شهر "' + data.city + '":</div>';
        html += '        <select id="citySelect" onchange="getCityCode(this.value)" style="width:100%; padding:8px; border-radius:8px; border:1px solid #ccc; background:#fff;">';
        html += '            <option value="">-- انتخاب کنید --</option>';
        for (let i = 0; i < data.city_options.length; i++) {
            let parts = data.city_options[i].split('|');
            let cityName = parts[0];
            let cityCode = parts[1] || '';
            let shortCode = cityCode.length > 35 ? cityCode.substring(0, 35) + '...' : cityCode;
            html += '                <option value="' + escapeHtml(data.city_options[i]) + '">' + escapeHtml(cityName) + ' - ' + escapeHtml(shortCode) + '</option>';
        }
        html += '        </select>';
        html += '    </div>';
        html += '    <div id="cityCodeResult" style="flex: 1; min-width: 180px;"></div>';
        html += '</div>';
    } else if (data.valid && data.city && data.city !== '-') {
        html += '<div class="city-selector" style="background:#e8f4fd;">';
        html += '    <div style="font-size:0.75rem; margin-bottom:8px;">⚠️ عبارت "' + data.city + '" در فایل یافت نشد.</div>';
        html += '    <div style="font-size:0.75rem; margin-bottom:8px;">🔍 لطفاً عبارت دقیق جستجو را وارد کنید:</div>';
        html += '    <input type="text" id="manualCitySearch" placeholder="مثال: مشهد" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:8px; margin-bottom:8px;">';
        html += '    <button onclick="searchManualCity()" class="copy-btn" style="width:100%; background:#1a73e8;">جستجو</button>';
        html += '    <div id="manualCityResult"></div>';
        html += '</div>';
    }
    
    if (data.excel_found && data.excel_results && data.excel_results.length > 0) {
        html += '<hr><div style="margin-top:12px;">';
        html += '<strong style="font-size:0.8rem;"><i class="fas fa-file-excel"></i> نتایج جستجوی کد ملی در برهان  (' + data.excel_results.length + ' مورد):</strong>';
        html += '<div class="table-wrapper"><table class="excel-table"><thead>';
        
        let headers = Object.keys(data.excel_results[0].data);
        html += '<tr>';
        for (let h = 0; h < headers.length; h++) {
            html += '<th>' + escapeHtml(headers[h]) + '</th>';
        }
        html += '</thead><tbody>';
        
        for (let r = 0; r < data.excel_results.length; r++) {
            html += '</tr>';
            for (let c = 0; c < headers.length; c++) {
                let val = data.excel_results[r].data[headers[c]] || '-';
                html += '<td>' + escapeHtml(val);
                if (headers[c] === 'عنوان لاتین' && val.length > 5 && /^\d+$/.test(val)) {
                    html += '<button class="copy-btn" style="margin-right:8px;" onclick="copyToClipboard(\'' + escapeHtml(val).replace(/'/g, "\\'") + '\')"><i class="far fa-copy"></i></button>';
                }
                html += '</div>';
            }
            html += '</div>';
        }
        html += '</tbody>;</div></div>';
    }
    
    html += '</div>';
    resultContent.innerHTML = html;
    resultBadge.textContent = 'کد ملی: ' + (data.code || '');
}

// ==================== بررسی کد ملی ====================
function checkNationalCode(code) {
    if (code.length !== 10) return;
    if (code === lastNationalCode) return;
    lastNationalCode = code;
    
    resultContent.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> در حال بررسی کد ملی...</div>';
    resultBadge.textContent = 'در حال بررسی...';
    
    fetch('?ajax=1&national_code=' + code)
        .then(res => res.json())
        .then(data => displayNationalResult(data))
        .catch(err => {
            console.error(err);
            resultContent.innerHTML = '<div class="result-box result-invalid">❌ خطا در ارتباط با سرور</div>';
            resultBadge.textContent = 'خطا';
        });
}

function performSearch(keyword, page = 1) {
    if (keyword.length < 2) {
        resultContent.innerHTML = '<div class="no-result">🔍 حداقل ۲ کاراکتر وارد کنید</div>';
        resultBadge.textContent = 'در انتظار جستجو';
        return;
    }
    
    currentKeyword = keyword;
    currentPage = page;
    currentSearchType = 'general';
    
    resultContent.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> در حال جستجو...</div>';
    resultBadge.textContent = 'در حال جستجو...';
    
    fetch(`?ajax=1&search=${encodeURIComponent(keyword)}&page=${page}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.count > 0) {
                let html = '<div style="margin-bottom:10px; font-size:0.75rem;">🔍 ' + data.count + '  نتیجه در برهان یافت شد</div>';
                html += '<div class="table-wrapper"><table class="excel-table"><thead>';
                let headers = Object.keys(data.results[0].data);
                for (let h = 0; h < headers.length; h++) {
                    html += '<th>' + escapeHtml(headers[h]) + '</th>';
                }
                html += '</thead><tbody>';
                for (let r = 0; r < data.results.length; r++) {
                    html += '<tr>';
                    for (let c = 0; c < headers.length; c++) {
                        let val = data.results[r].data[headers[c]] || '-';
                        html += '<td>' + escapeHtml(val);
                        if (headers[c] === 'عنوان لاتین' && val.length > 5 && /^\d+$/.test(val)) {
                            html += '<button class="copy-btn" style="margin-right:8px;" onclick="copyToClipboard(\'' + escapeHtml(val).replace(/'/g, "\\'") + '\')"><i class="far fa-copy"></i></button>';
                        }
                        html += '</td>';
                    }
                    html += '</tr>';
                }
                html += '</tbody></table></div>';
                
                // صفحه‌بندی
                if (data.totalPages > 1) {
                    html += '<div class="pagination" style="display:flex; justify-content:center; gap:8px; margin-top:15px; flex-wrap:wrap;">';
                    if (data.page > 1) {
                        html += '<button class="page-btn" onclick="performSearch(\'' + escapeHtml(keyword) + '\', ' + (data.page - 1) + ')" style="padding:5px 10px; border:1px solid #ddd; background:white; border-radius:5px; cursor:pointer;">« قبلی</button>';
                    }
                    for (let i = 1; i <= data.totalPages; i++) {
                        if (i === data.page) {
                            html += '<button class="page-btn active" style="padding:5px 10px; border:1px solid #667eea; background:#667eea; color:white; border-radius:5px;">' + i + '</button>';
                        } else if (Math.abs(i - data.page) <= 2 || i === 1 || i === data.totalPages) {
                            html += '<button class="page-btn" onclick="performSearch(\'' + escapeHtml(keyword) + '\', ' + i + ')" style="padding:5px 10px; border:1px solid #ddd; background:white; border-radius:5px; cursor:pointer;">' + i + '</button>';
                        } else if (Math.abs(i - data.page) === 3) {
                            html += '<span style="padding:5px;">...</span>';
                        }
                    }
                    if (data.page < data.totalPages) {
                        html += '<button class="page-btn" onclick="performSearch(\'' + escapeHtml(keyword) + '\', ' + (data.page + 1) + ')" style="padding:5px 10px; border:1px solid #ddd; background:white; border-radius:5px; cursor:pointer;">بعدی »</button>';
                    }
                    html += '</div>';
                }
                
                resultContent.innerHTML = html;
                resultBadge.textContent = 'جستجوی عمومی: ' + data.count + ' نتیجه';
            } else {
                resultContent.innerHTML = '<div class="no-result">🔍 نتیجه‌ای برای "' + escapeHtml(keyword) + '" یافت نشد</div>';
                resultBadge.textContent = 'نتیجه‌ای یافت نشد';
            }
        })
        .catch(err => {
            console.error(err);
            resultContent.innerHTML = '<div class="result-box result-invalid">❌ خطا در جستجو</div>';
            resultBadge.textContent = 'خطا';
        });
}

// ==================== جستجوی خودکار شهر ====================
if (citySearchInput) {
    citySearchInput.addEventListener('input', function(e) {
        const keyword = this.value.trim();
        const citySearchResultDiv = document.getElementById('citySearchResult');
        
        if (citySearchTimeout) clearTimeout(citySearchTimeout);
        
        if (keyword.length < 2) {
            citySearchResultDiv.innerHTML = '<div class="no-result" style="padding:15px;">🔍 حداقل ۲ کاراکتر وارد کنید</div>';
            return;
        }
        
        citySearchResultDiv.innerHTML = '<div class="loading" style="padding:15px;"><i class="fas fa-spinner fa-spin"></i> در حال جستجو...</div>';
        
        citySearchTimeout = setTimeout(() => {
            fetch('?ajax=1&city_search=' + encodeURIComponent(keyword))
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.options && data.options.length > 0) {
                        let html = '<div style="margin-top:10px;">';
                        html += '<select id="citySearchSelect" class="city-select" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:8px; margin-bottom:8px;" onchange="selectCityFromSearch(this.value)">';
                        html += '<option value="">-- ' + data.options.length + ' نتیجه یافت شد --</option>';
                        for (let i = 0; i < data.options.length; i++) {
                            let parts = data.options[i].split('|');
                            let cityName = parts[0];
                            let cityCode = parts[1] || '';
                            let shortCode = cityCode.length > 40 ? cityCode.substring(0, 40) + '...' : cityCode;
                            html += '<option value="' + escapeHtml(data.options[i]) + '">' + escapeHtml(cityName) + ' - ' + escapeHtml(shortCode) + '</option>';
                        }
                        html += '</select><div id="citySearchCodeResult"></div></div>';
                        citySearchResultDiv.innerHTML = html;
                    } else {
                        citySearchResultDiv.innerHTML = '<div class="no-result" style="padding:15px;">🔍 نتیجه‌ای برای "' + escapeHtml(keyword) + '" یافت نشد</div>';
                    }
                })
                .catch(() => {
                    citySearchResultDiv.innerHTML = '<div class="no-result" style="padding:15px;">❌ خطا در جستجو</div>';
                });
        }, 300);
    });
}

// ==================== رویدادهای ورودی ====================
nationalInput.addEventListener('input', function(e) {
    let value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    this.value = value;
    
    if (nationalTimeout) clearTimeout(nationalTimeout);
    
    if (value.length === 10) {
        nationalTimeout = setTimeout(() => checkNationalCode(value), 50);
    } else if (value.length > 0) {
        resultContent.innerHTML = '<div class="result-box result-invalid">⚠️ کد ملی باید ۱۰ رقم باشد (' + value.length + '/10)</div>';
        resultBadge.textContent = 'کد ملی ناقص';
        lastNationalCode = '';
    } else {
        resultContent.innerHTML = '<div class="no-result">🔍 جستجویی انجام نشده است</div>';
        resultBadge.textContent = 'در انتظار جستجو';
        lastNationalCode = '';
    }
});

generalInput.addEventListener('input', function(e) {
    const keyword = this.value.trim();
    
    if (searchTimeout) clearTimeout(searchTimeout);
    
    if (keyword.length === 0) {
        resultContent.innerHTML = '<div class="no-result">🔍 جستجویی انجام نشده است</div>';
        resultBadge.textContent = 'در انتظار جستجو';
        return;
    }
    
    searchTimeout = setTimeout(() => performSearch(keyword, 1), 300);
});

// جستجوی دستی شهر
function searchManualCity() {
    var searchTerm = document.getElementById('manualCitySearch').value.trim();
    if (searchTerm.length < 2) {
        showToast('⚠️ حداقل ۲ کاراکتر وارد کنید');
        return;
    }
    
    document.getElementById('manualCityResult').innerHTML = '<div class="loading" style="padding:10px;">⏳ در حال جستجو...</div>';
    
    fetch('?ajax=1&manual_city_search=' + encodeURIComponent(searchTerm))
        .then(res => res.json())
        .then(data => {
            if (data.found && data.options && data.options.length > 0) {
                var html = '<div style="margin-top:10px;">';
                html += '<select id="manualCitySelect" class="city-select" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:8px; margin-bottom:8px;" onchange="updateManualCityCode(this.value)">';
                html += '<option value="">-- انتخاب کنید --</option>';
                for (var i = 0; i < data.options.length; i++) {
                    var parts = data.options[i].split('|');
                    var cityName = parts[0];
                    var cityCode = parts[1] || '';
                    var shortCode = cityCode.length > 40 ? cityCode.substring(0, 40) + '...' : cityCode;
                    var displayText = cityName + ' - ' + shortCode;
                    html += '<option value="' + escapeHtml(data.options[i]) + '">' + escapeHtml(displayText) + '</option>';
                }
                html += '</select>';
                html += '<div id="manualCityCodeResult"></div>';
                html += '</div>';
                document.getElementById('manualCityResult').innerHTML = html;
            } else {
                document.getElementById('manualCityResult').innerHTML = '<div style="color:#e74c3c; font-size:0.7rem; padding:8px;">❌ نتیجه‌ای برای "' + escapeHtml(searchTerm) + '" یافت نشد</div>';
            }
        })
        .catch(err => {
            document.getElementById('manualCityResult').innerHTML = '<div style="color:#e74c3c; font-size:0.7rem;">❌ خطا در جستجو</div>';
        });
}

function updateManualCityCode(selectedValue) {
    if (!selectedValue) return;
    var parts = selectedValue.split('|');
    var code = parts[1];
    var html = '<div class="city-code-result">';
    html += '<span dir="ltr">' + escapeHtml(code) + '</span>';
    html += '<button class="copy-btn" onclick="copyToClipboard(\'' + escapeHtml(code).replace(/'/g, "\\'") + '\')">📋 کپی</button>';
    html += '</div>';
    document.getElementById('manualCityCodeResult').innerHTML = html;
}

// ==================== بروزرسانی کش در پس‌زمینه ====================
if (refreshCacheBtn) {
    refreshCacheBtn.onclick = function() {
        if (confirm('آیا از بروزرسانی کش اطمینان دارید؟')) {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال بروزرسانی...';
            this.disabled = true;
            
            fetch('?ajax=1&refresh_cache=1')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('✅ کش با موفقیت بروزرسانی شد');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('❌ ' + (data.message || 'خطا در بروزرسانی کش'));
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('❌ خطا در ارتباط با سرور');
                    this.innerHTML = originalText;
                    this.disabled = false;
                });
        }
    };
}

// بستن مودال با کلیک خارج
window.onclick = function(e) {
    if (e.target === adminModal) closeAdminModal();
    if (e.target === requestCityModal) closeRequestModal();
    if (e.target === document.getElementById('requestNationalCodeModal')) closeNationalCodeRequestModal();
};

// ==================== انیمیشن زوم و محو لوگو ====================
document.addEventListener('DOMContentLoaded', function() {
    const logo = document.querySelector('.footer img');
    if (logo) {
        logo.addEventListener('click', function(e) {
            // جلوگیری از چندبار کلیک همزمان
            if (this.classList.contains('zoom-fade')) return;
            
            // حذف کلاس‌های قبلی
            this.classList.remove('zoom-fade', 'zoom-fade-back');
            
            // اجرای انیمیشن محو شدن
            this.classList.add('zoom-fade');
            
            // بعد از پایان محو شدن (2 ثانیه)، شروع به بازگشت کن
            setTimeout(() => {
                this.classList.remove('zoom-fade');
                this.classList.add('zoom-fade-back');
            }, 2000);
            
            // بعد از بازگشت (1.5 ثانیه)، کلاس بازگشت را حذف کن
            setTimeout(() => {
                this.classList.remove('zoom-fade-back');
            }, 3500);
        });
    }
});

</script>
</body>
</html>