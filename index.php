<?php
// index.php - نسخه نهایی بدون خط موج‌دار

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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            direction: rtl;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
            background: #ffffff;
        }
        
        /* ========== پس‌زمینه زرد ملایم ========== */
        .bg-glow {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            background-image: radial-gradient(circle at center, #FFF991 0%, transparent 70%);
            opacity: 0.6;
            mix-blend-mode: multiply;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .header {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 16px 24px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            border: 1px solid rgba(255,255,255,0.5);
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }
        
        .logo h1 {
            font-size: 1.3rem;
            color: #2d3436;
        }
        
        .logo p {
            font-size: 0.7rem;
            color: #636e72;
        }
        
        .menu-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
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
        
        /* ============================================
           استایل کارت‌های گلو (Glow Card)
           ============================================ */
        .glow-card {
            --base: 220;
            --spread: 200;
            --radius: 20;
            --border: 2;
            --backdrop: rgba(255,255,255,0.7);
            --backup-border: rgba(255,255,255,0.3);
            --size: 200;
            --outer: 1;
            --border-size: calc(var(--border) * 1px);
            --spotlight-size: calc(var(--size) * 1px);
            --hue: calc(var(--base) + (var(--xp, 0) * var(--spread, 0)));
            
            position: relative;
            touch-action: none;
            border: var(--border-size) solid var(--backup-border);
            border-radius: calc(var(--radius) * 1px);
            background: var(--backdrop);
            background-image: radial-gradient(
                var(--spotlight-size) var(--spotlight-size) at
                calc(var(--x, 0) * 1px)
                calc(var(--y, 0) * 1px),
                hsl(var(--hue, 210) 100% 70% / 0.08),
                transparent
            );
            background-size: calc(100% + (2 * var(--border-size))) calc(100% + (2 * var(--border-size)));
            background-position: 50% 50%;
            background-attachment: fixed;
            padding: 0;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            overflow: hidden;
            min-height: 200px;
        }

        .glow-card::before,
        .glow-card::after {
            pointer-events: none;
            content: "";
            position: absolute;
            inset: calc(var(--border-size) * -1);
            border: var(--border-size) solid transparent;
            border-radius: calc(var(--radius) * 1px);
            background-attachment: fixed;
            background-size: calc(100% + (2 * var(--border-size))) calc(100% + (2 * var(--border-size)));
            background-repeat: no-repeat;
            background-position: 50% 50%;
            mask: linear-gradient(transparent, transparent), linear-gradient(white, white);
            mask-clip: padding-box, border-box;
            mask-composite: intersect;
            transition: all 0.3s ease;
        }

        .glow-card::before {
            background-image: radial-gradient(
                calc(var(--spotlight-size) * 0.75) calc(var(--spotlight-size) * 0.75) at
                calc(var(--x, 0) * 1px)
                calc(var(--y, 0) * 1px),
                hsl(var(--hue, 210) 100% 50% / 0.6),
                transparent 100%
            );
            filter: brightness(2);
        }

        .glow-card::after {
            background-image: radial-gradient(
                calc(var(--spotlight-size) * 0.5) calc(var(--spotlight-size) * 0.5) at
                calc(var(--x, 0) * 1px)
                calc(var(--y, 0) * 1px),
                hsl(0 100% 100% / 0.3),
                transparent 100%
            );
        }

        .glow-card .glow-inner {
            position: absolute;
            inset: 0;
            will-change: filter;
            opacity: var(--outer, 1);
            border-radius: calc(var(--radius) * 1px);
            pointer-events: none;
            border: none;
            background: none;
        }

        .glow-card .glow-inner::before {
            content: '';
            position: absolute;
            inset: -10px;
            border-radius: calc(var(--radius) * 1px);
            border-width: 10px;
            border-style: solid;
            border-color: transparent;
            background: radial-gradient(
                calc(var(--spotlight-size) * 0.6) calc(var(--spotlight-size) * 0.6) at
                calc(var(--x, 0) * 1px)
                calc(var(--y, 0) * 1px),
                hsl(var(--hue, 210) 100% 60% / 0.15),
                transparent 100%
            );
            filter: blur(20px);
        }

        .glow-card.blue { --base: 220; --spread: 200; }
        .glow-card.purple { --base: 280; --spread: 300; }
        .glow-card.green { --base: 120; --spread: 200; }
        .glow-card.red { --base: 0; --spread: 200; }
        .glow-card.orange { --base: 30; --spread: 200; }

        .glow-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
        }

        .glow-card .column-header {
            background: transparent;
            padding: 18px 20px 10px 20px;
            color: #2d3436;
            font-weight: 700;
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }

        .glow-card .column-body {
            padding: 10px 20px 20px 20px;
            position: relative;
            z-index: 1;
        }

        .glow-card .column-header .icon {
            font-size: 1.2rem;
            margin-left: 8px;
        }

        /* ============================================
           دکمه پاک کردن فیلد (Bluetooth Style)
           ============================================ */
        .clear-btn-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .clear-btn-wrap .clear-btn-input {
            display: none;
        }

        .clear-btn-wrap .clear-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid rgba(239, 68, 68, 0.2);
            background: linear-gradient(145deg, #ffffff, #f0f0f5);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .clear-btn-wrap .clear-btn:hover {
            transform: scale(1.08);
            border-color: rgba(239, 68, 68, 0.5);
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.15);
        }

        .clear-btn-wrap .clear-btn:active {
            transform: scale(0.92);
        }

        .clear-btn-wrap .clear-btn .corner {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1px solid rgba(239, 68, 68, 0.06);
            pointer-events: none;
        }

        .clear-btn-wrap .clear-btn .inner {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .clear-btn-wrap .clear-btn .inner .icon {
            width: 20px;
            height: 20px;
            color: #ef4444;
            transition: all 0.3s ease;
        }

        .clear-btn-wrap .clear-btn:hover .inner .icon {
            transform: scale(1.1);
        }

        .clear-btn-wrap .led {
            position: absolute;
            top: -3px;
            right: -3px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.2);
            transition: all 0.3s ease;
            z-index: 3;
        }

        .clear-btn-wrap .clear-btn:hover .led {
            background: #ef4444;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.4);
        }

        .clear-btn-wrap .bg {
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            pointer-events: none;
            overflow: hidden;
        }

        .clear-btn-wrap .bg .shine-1 {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.4), transparent 60%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .clear-btn-wrap .clear-btn:hover .bg .shine-1 {
            opacity: 1;
        }

        .clear-btn-wrap .bg .shine-2 {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 70% 70%, rgba(239, 68, 68, 0.05), transparent 60%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .clear-btn-wrap .clear-btn:hover .bg .shine-2 {
            opacity: 1;
        }

        .clear-btn-wrap .bg-glow {
            position: absolute;
            inset: -12px;
            border-radius: 50%;
            filter: blur(16px);
            opacity: 0;
            transition: opacity 0.5s ease;
            pointer-events: none;
        }

        .clear-btn-wrap .clear-btn:hover .bg-glow {
            opacity: 1;
            background: rgba(239, 68, 68, 0.04);
        }

        .clear-btn-wrap .noise {
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            overflow: hidden;
            z-index: 1;
        }

        .clear-btn-wrap .clear-btn:hover .noise {
            opacity: 0.3;
        }

        .clear-btn-wrap .noise svg {
            width: 100%;
            height: 100%;
        }

        .clear-btn-wrap .clear-btn .ripple {
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 3px solid #ef4444;
            opacity: 0;
            pointer-events: none;
            z-index: 0;
        }

        .clear-btn-wrap .clear-btn:active .ripple {
            animation: ripplePing 0.4s ease-out;
        }

        @keyframes ripplePing {
            0% {
                transform: scale(0.8);
                opacity: 1;
            }
            100% {
                transform: scale(1.8);
                opacity: 0;
            }
        }
        
        .three-columns {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 0.75rem;
            color: #2d3436;
        }
        
        .input-wrapper {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        input[type="text"] {
            flex: 1;
            padding: 10px 14px;
            border: 1.5px solid #e9ecef;
            border-radius: 12px;
            font-size: 0.85rem;
            font-family: monospace;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.7);
            color: #2d3436;
            height: 44px;
        }
        
        input[type="text"]::placeholder {
            color: #adb5bd;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .small-text {
            font-size: 0.65rem;
            color: #636e72;
            margin-top: 6px;
        }
        
        .result-container {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.5);
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
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
            color: #2d3436;
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
            background: white;
            color: #2d3436;
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
        
        /* ========== استایل فوتر ========== */
        .footer {
            text-align: center;
            padding: 16px;
            margin-top: 20px;
            color: #636e72;
            font-size: 0.65rem;
        }

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
            0% { transform: scale(1.5); opacity: 1; }
            100% { transform: scale(12); opacity: 0; }
        }

        .footer img.zoom-fade-back {
            animation: zoomFadeIn 2.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        @keyframes zoomFadeIn {
            0% { transform: scale(0.1); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ============================================
           انیمیشن Vaporize برای متن فوتر
           ============================================ */
        .footer-text-vaporize {
            display: inline-block;
            cursor: pointer;
            position: relative;
        }

        .footer-text-vaporize .char {
            display: inline-block;
            opacity: 1;
            transition: none;
        }

        .footer-text-vaporize .dev-label {
            color: #adb5bd;
        }

        .footer-text-vaporize .highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .footer-text-vaporize .dev-phone {
            color: #2d3436;
        }

        .footer-text-vaporize.vaporizing .char {
            animation: vaporizeChar 1.2s ease-out forwards;
        }

        .footer-text-vaporize.fading-in .char {
            animation: fadeInChar 0.8s ease-in forwards;
        }

        @keyframes vaporizeChar {
            0% {
                opacity: 1;
                transform: translate(0, 0) scale(1) rotate(0deg);
                filter: blur(0px);
            }
            100% {
                opacity: 0;
                transform: translate(var(--tx, 50px), var(--ty, -30px)) scale(0.2) rotate(var(--rot, 180deg));
                filter: blur(6px);
            }
        }

        @keyframes fadeInChar {
            0% {
                opacity: 0;
                transform: translateY(20px) scale(0.5);
                filter: blur(4px);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0px);
            }
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
            color: #2d3436;
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
        
        /* ========== استایل توضیحات زیر فیلدها ========== */
        .small-text {
            color: #636e72;
        }
    </style>
</head>
<body>

<!-- ========== پس‌زمینه زرد ملایم ========== -->
<div class="bg-glow"></div>

<div class="container">
    <div class="header">
        <div class="logo">
            <h1>🪪 اعتبارسنجی کدملی و استعلام اطلاعات کد های تفصیل ثبت شده در برهان</h1>
            <p>✅ اعتبارسنجی | 🗺️ تشخیص استان و شهر | 📊 جستجو در دیتابیس کدهای تفصیل برهان</p>
        </div>
        <div class="menu-buttons">
            <div style="font-size: 0.7rem; color: #2c3e50; background: #e9ecef; padding: 6px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;">
                📅 آخرین بروزرسانی: <span id="lastUpdateDate">---</span>
            </div>
            <button class="menu-btn" id="refreshCacheBtn" style="background:#e67e22;">
                🔄 بروزرسانی کدهای تفصیل
            </button>
            <button class="menu-btn" onclick="openAdminModal()">
                🔒 پنل مدیریت
            </button>
            <a href="inquiry_panel.php" class="menu-btn" style="background:linear-gradient(135deg,#27ae60 0%,#2ecc71 100%);">
                📄 استعلام واریزی‌ها
            </a>
        </div>
    </div>
    
    <div class="three-columns">
        <!-- ========== ستون اول - کارت آبی ========== -->
        <div class="glow-card blue">
            <div class="glow-inner"></div>
            <div class="column-header">
                <span class="icon">🪪</span> استعلام و اعتبارسنجی کد ملی (جستجوی خودکار در برهان)
            </div>
            <div class="column-body">
                <div class="form-group">
                    <label>کد ملی ۱۰ رقمی</label>
                    <div class="input-wrapper">
                        <input type="text" id="nationalCode" maxlength="10" placeholder="مثال: 0630010099">
                        <div class="clear-btn-wrap">
                            <input id="clearNationalBtn" class="clear-btn-input" type="checkbox" />
                            <button class="clear-btn" onclick="clearInput(this)">
                                <div class="corner"></div>
                                <div class="inner">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                    </svg>
                                </div>
                                <div class="led"></div>
                                <div class="bg">
                                    <div class="shine-1"></div>
                                    <div class="shine-2"></div>
                                </div>
                                <div class="bg-glow"></div>
                                <div class="ripple"></div>
                                <div class="noise">
                                    <svg height="100%" width="100%">
                                        <defs>
                                            <pattern height="100" width="100" patternUnits="userSpaceOnUse" id="noise-pattern-1">
                                                <filter y="0" x="0" id="noise-1">
                                                    <feTurbulence stitchTiles="stitch" numOctaves="2" baseFrequency="0.65" type="fractalNoise" />
                                                    <feBlend mode="screen" />
                                                </filter>
                                                <rect filter="url(#noise-1)" height="100" width="100" />
                                            </pattern>
                                        </defs>
                                        <rect fill="url(#noise-pattern-1)" height="100%" width="100%" />
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>
                    <div class="small-text">✨ پس از وارد کردن دهمین رقم، نتیجه نمایش داده می‌شود</div>
                </div>
            </div>
        </div>
        
        <!-- ========== ستون دوم - کارت بنفش ========== -->
        <div class="glow-card purple">
            <div class="glow-inner"></div>
            <div class="column-header">
                <span class="icon">🔍</span> جستجوی عمومی در برهان
            </div>
            <div class="column-body">
                <div class="form-group">
                    <label>عبارت جستجو (نام و نام خانوادگی، کد/شناسه ملی، شماره اقتصادی، کد تفصیل، تلفن و ...)</label>
                    <div class="input-wrapper">
                        <input type="text" id="generalSearch" placeholder="مثال: احمدآبادی یا 09353984864">
                        <div class="clear-btn-wrap">
                            <input id="clearGeneralBtn" class="clear-btn-input" type="checkbox" />
                            <button class="clear-btn" onclick="clearInput(this)">
                                <div class="corner"></div>
                                <div class="inner">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                    </svg>
                                </div>
                                <div class="led"></div>
                                <div class="bg">
                                    <div class="shine-1"></div>
                                    <div class="shine-2"></div>
                                </div>
                                <div class="bg-glow"></div>
                                <div class="ripple"></div>
                                <div class="noise">
                                    <svg height="100%" width="100%">
                                        <defs>
                                            <pattern height="100" width="100" patternUnits="userSpaceOnUse" id="noise-pattern-2">
                                                <filter y="0" x="0" id="noise-2">
                                                    <feTurbulence stitchTiles="stitch" numOctaves="2" baseFrequency="0.65" type="fractalNoise" />
                                                    <feBlend mode="screen" />
                                                </filter>
                                                <rect filter="url(#noise-2)" height="100" width="100" />
                                            </pattern>
                                        </defs>
                                        <rect fill="url(#noise-pattern-2)" height="100%" width="100%" />
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>
                    <div class="small-text">🔍 جستجو در همه ستون‌ها (با رفع باگ فاصله بین کلمات)</div>
                </div>
            </div>
        </div>
        
        <!-- ========== ستون سوم - کارت سبز ========== -->
        <div class="glow-card green">
            <div class="glow-inner"></div>
            <div class="column-header">
                <span class="icon">🏙️</span> جستجوی کد شهر و استان (خودکار)
            </div>
            <div class="column-body">
                <div class="form-group">
                    <label>نام شهر را وارد کنید</label>
                    <div class="input-wrapper">
                        <input type="text" id="citySearch" placeholder="مثال: مشهد یا اسفراین">
                        <div class="clear-btn-wrap">
                            <input id="clearCityBtn" class="clear-btn-input" type="checkbox" />
                            <button class="clear-btn" onclick="clearInput(this)">
                                <div class="corner"></div>
                                <div class="inner">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                    </svg>
                                </div>
                                <div class="led"></div>
                                <div class="bg">
                                    <div class="shine-1"></div>
                                    <div class="shine-2"></div>
                                </div>
                                <div class="bg-glow"></div>
                                <div class="ripple"></div>
                                <div class="noise">
                                    <svg height="100%" width="100%">
                                        <defs>
                                            <pattern height="100" width="100" patternUnits="userSpaceOnUse" id="noise-pattern-3">
                                                <filter y="0" x="0" id="noise-3">
                                                    <feTurbulence stitchTiles="stitch" numOctaves="2" baseFrequency="0.65" type="fractalNoise" />
                                                    <feBlend mode="screen" />
                                                </filter>
                                                <rect filter="url(#noise-3)" height="100" width="100" />
                                            </pattern>
                                        </defs>
                                        <rect fill="url(#noise-pattern-3)" height="100%" width="100%" />
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>
                    <div class="small-text">➡️ پس از وارد کردن حداقل ۲ کاراکتر، نتایج نمایش داده می‌شوند</div>
                </div>
                <div id="citySearchResult"></div>
            </div>
        </div>
    </div>
    
    <div class="result-container">
        <div class="result-header">
            <div class="result-title">📋 نتیجه جستجو :</div>
            <div class="result-badge" id="resultBadge">در انتظار جستجو</div>
        </div>
        <div class="result-content" id="resultContent">
            <div class="no-result">🔍 جستجویی انجام نشده است</div>
        </div>
    </div>
    
    <div class="footer">
        <div>
            <span class="footer-text-vaporize" id="footerVaporize">
                <span class="dev-label">💻 Dev :</span>
                <span class="highlight">Reza.ahmadabadi</span>
                <span style="color:#adb5bd;margin:0 6px;">|</span>
                <span style="color:#2d3436;">📞</span>
                <span class="dev-phone">09353984864</span>
            </span>
        </div>
        
        <!-- لوگو -->
        <div style="margin-top: 10px;">
            <img src="/invoice-system-v2/assets/images/logo.png" alt="لوگو" style="max-height: 40px; width: auto; transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); cursor: pointer; position: relative;" onmouseover="this.style.transform='scale(1.5)'" onmouseout="this.style.transform='scale(1)'" onerror="this.style.display='none'">
        </div>
    </div>
</div>

<div id="adminModal" class="modal">
    <div class="modal-content">
        <h3>🔒 ورود به پنل مدیریت</h3>
        <p style="font-size:0.7rem;color:#6c757d;margin-bottom:12px;">برای اصلاح دیتابیس کدهای ملی</p>
        <input type="password" id="adminPassword" placeholder="رمز عبور مدیریت" style="text-align:center;">
        <div class="modal-buttons">
            <button class="btn-save" onclick="checkAdminLogin()">🔑 ورود</button>
            <button class="btn-cancel" onclick="closeAdminModal()">✕ انصراف</button>
        </div>
    </div>
</div>

<div id="requestCityModal" class="modal">
    <div class="modal-content">
        <h3>✏️ درخواست اصلاح اطلاعات شهر</h3>
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
            <button class="btn-save" onclick="submitCityRequest()">📤 ارسال درخواست</button>
            <button class="btn-cancel" onclick="closeRequestModal()">✕ انصراف</button>
        </div>
    </div>
</div>

<div id="requestNationalCodeModal" class="modal">
    <div class="modal-content">
        <h3>➕ درخواست اضافه/اصلاح کد ملی</h3>
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
            <button class="btn-save" onclick="submitNationalCodeRequest()">📤 ارسال درخواست</button>
            <button class="btn-cancel" onclick="closeNationalCodeRequestModal()">✕ انصراف</button>
        </div>
    </div>
</div>

<script>
// ============================================
// تابع پاک کردن فیلد
// ============================================
function clearInput(btn) {
    // پیدا کردن والد .input-wrapper
    const wrapper = btn.closest('.input-wrapper');
    if (!wrapper) return;
    
    // پیدا کردن input داخل wrapper
    const input = wrapper.querySelector('input[type="text"]');
    if (!input) return;
    
    // پاک کردن مقدار input
    input.value = '';
    input.focus();
    
    // اضافه کردن انیمیشن به دکمه
    btn.classList.add('active');
    setTimeout(() => btn.classList.remove('active'), 400);
    
    // اگر input مربوط به کد ملی بود، نتیجه را پاک کن
    if (input.id === 'nationalCode') {
        document.getElementById('resultContent').innerHTML = '<div class="no-result">🔍 جستجویی انجام نشده است</div>';
        document.getElementById('resultBadge').textContent = 'در انتظار جستجو';
        window.lastNationalCode = '';
    }
    
    // اگر input مربوط به جستجوی عمومی بود
    if (input.id === 'generalSearch') {
        document.getElementById('resultContent').innerHTML = '<div class="no-result">🔍 جستجویی انجام نشده است</div>';
        document.getElementById('resultBadge').textContent = 'در انتظار جستجو';
    }
    
    // اگر input مربوط به جستجوی شهر بود
    if (input.id === 'citySearch') {
        document.getElementById('citySearchResult').innerHTML = '';
    }
}

// ============================================
// اسکریپت افکت گلو (Glow Effect)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.glow-card');
    
    function syncPointer(e) {
        const x = e.clientX;
        const y = e.clientY;
        
        cards.forEach(card => {
            const rect = card.getBoundingClientRect();
            const relativeX = x - rect.left;
            const relativeY = y - rect.top;
            
            card.style.setProperty('--x', relativeX.toFixed(2));
            card.style.setProperty('--xp', (x / window.innerWidth).toFixed(2));
            card.style.setProperty('--y', relativeY.toFixed(2));
            card.style.setProperty('--yp', (y / window.innerHeight).toFixed(2));
        });
    }
    
    document.addEventListener('pointermove', syncPointer);
});

// ============================================
// انیمیشن لوگو (Zoom Fade)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const logo = document.querySelector('.footer img');
    if (logo) {
        logo.addEventListener('click', function(e) {
            if (this.classList.contains('zoom-fade')) return;
            
            this.classList.remove('zoom-fade', 'zoom-fade-back');
            this.classList.add('zoom-fade');
            
            setTimeout(() => {
                this.classList.remove('zoom-fade');
                this.classList.add('zoom-fade-back');
            }, 2000);
            
            setTimeout(() => {
                this.classList.remove('zoom-fade-back');
            }, 3500);
        });
    }
});

// ============================================
// انیمیشن Vaporize برای متن فوتر (هر 1 دقیقه یک بار)
// ============================================
(function() {
    const container = document.getElementById('footerVaporize');
    if (!container) return;
    
    let isAnimating = false;
    let animationTimer = null;
    let intervalTimer = null;
    
    // تابع برای تبدیل متن به کاراکترهای جداگانه با span
    function wrapChars(element) {
        const text = element.textContent;
        element.innerHTML = '';
        
        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            const span = document.createElement('span');
            span.className = 'char';
            span.textContent = char;
            span.style.display = 'inline-block';
            
            // تنظیمات تصادفی برای هر کاراکتر
            const tx = (Math.random() - 0.5) * 120;
            const ty = (Math.random() - 0.5) * 80 - 20;
            const rot = (Math.random() - 0.5) * 360;
            span.style.setProperty('--tx', tx + 'px');
            span.style.setProperty('--ty', ty + 'px');
            span.style.setProperty('--rot', rot + 'deg');
            
            // تاخیر تصادفی برای هر کاراکتر
            span.style.animationDelay = (Math.random() * 0.5) + 's';
            
            element.appendChild(span);
        }
    }
    
    // تابع برای بازگرداندن متن به حالت عادی با حفظ استایل رنگ
    function resetText() {
        const text = container.textContent;
        container.innerHTML = text;
        container.classList.remove('vaporizing', 'fading-in');
        isAnimating = false;
    }
    
    // تابع اجرای انیمیشن تبخیر
    function startVaporizeAnimation() {
        if (isAnimating) return;
        isAnimating = true;
        
        // پاک کردن تایمر قبلی
        if (animationTimer) {
            clearTimeout(animationTimer);
            animationTimer = null;
        }
        
        // دریافت متن فعلی
        const fullText = container.textContent;
        if (!fullText || fullText.trim() === '') return;
        
        // تبدیل به کاراکترهای جداگانه
        wrapChars(container);
        
        // شروع انیمیشن تبخیر
        container.classList.add('vaporizing');
        
        // بعد از اتمام تبخیر، شروع به fade-in کنید
        const vaporizeDuration = 1200; // 1.2 ثانیه
        setTimeout(() => {
            container.classList.remove('vaporizing');
            container.classList.add('fading-in');
            
            // بعد از اتمام fade-in، متن را به حالت عادی برگردانید
            const fadeDuration = 800; // 0.8 ثانیه
            setTimeout(() => {
                resetText();
                isAnimating = false;
            }, fadeDuration);
        }, vaporizeDuration);
    }
    
    // شروع انیمیشن با کلیک
    container.addEventListener('click', function() {
        if (!isAnimating) {
            startVaporizeAnimation();
        }
    });
    
    // شروع خودکار بعد از 2 ثانیه بارگذاری
    setTimeout(function() {
        if (!isAnimating) {
            startVaporizeAnimation();
        }
    }, 2000);
    
    // تنظیم تایمر برای اجرای هر 1 دقیقه (60000 میلی‌ثانیه)
    function startInterval() {
        if (intervalTimer) {
            clearInterval(intervalTimer);
            intervalTimer = null;
        }
        
        intervalTimer = setInterval(function() {
            if (!isAnimating) {
                startVaporizeAnimation();
            }
        }, 60000); // 1 دقیقه
    }
    
    startInterval();
    
    // در صورت خروج از صفحه، تایمر را پاک کنید
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (intervalTimer) {
                clearInterval(intervalTimer);
                intervalTimer = null;
            }
        } else {
            startInterval();
        }
    });
})();

// ==================== متغیرها ====================
let nationalTimeout = null;
let searchTimeout = null;
let citySearchTimeout = null;
let lastNationalCode = '';

let currentPage = 1;
let totalPages = 1;
let currentKeyword = '';
let currentSearchType = 'none';

const nationalInput = document.getElementById('nationalCode');
const generalInput = document.getElementById('generalSearch');
const citySearchInput = document.getElementById('citySearch');
const resultContent = document.getElementById('resultContent');
const resultBadge = document.getElementById('resultBadge');
const refreshCacheBtn = document.getElementById('refreshCacheBtn');
const adminModal = document.getElementById('adminModal');
const requestCityModal = document.getElementById('requestCityModal');

const lastUpdateSpan = document.getElementById('lastUpdateDate');
if (lastUpdateSpan) {
    lastUpdateSpan.textContent = '<?php echo $lastUpdate; ?>';
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
        html += '<div><strong>🗺️ استان:</strong> ' + data.province + '</div>';
        html += '<div><strong>🏙️ شهر:</strong> ' + data.city + '</div>';
        html += '</div>';
        
        if (data.is_unknown) {
            html += '<button class="request-btn" onclick="openRequestModal(\'' + data.code + '\', \'' + data.province + '\', \'' + data.city + '\')">';
            html += '✏️ اطلاعات شهر صحیح نیست؟ درخواست اصلاح دهید</button>';
        }
    }
    
    if (data.valid && data.is_national_unknown) {
        html += '<button class="request-national-btn" onclick="openNationalCodeRequestModal(\'' + data.code + '\')">';
        html += '➕ استان و شهر این کد ملی در دیتابیس نیست؟ درخواست اضافه دهید</button>';
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
        html += '    <button onclick="searchManualCity()" class="copy-btn" style="width:100%; background:#1a73e8;">🔍 جستجو</button>';
        html += '    <div id="manualCityResult"></div>';
        html += '</div>';
    }
    
    if (data.excel_found && data.excel_results && data.excel_results.length > 0) {
        html += '<hr><div style="margin-top:12px;">';
        html += '<strong style="font-size:0.8rem;">📊 نتایج جستجوی کد ملی در برهان (' + data.excel_results.length + ' مورد):</strong>';
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
                    html += '<button class="copy-btn" style="margin-right:8px;" onclick="copyToClipboard(\'' + escapeHtml(val).replace(/'/g, "\\'") + '\')">📋</button>';
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
    
    resultContent.innerHTML = '<div class="loading">⏳ در حال بررسی کد ملی...</div>';
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
    
    resultContent.innerHTML = '<div class="loading">⏳ در حال جستجو...</div>';
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
                            html += '<button class="copy-btn" style="margin-right:8px;" onclick="copyToClipboard(\'' + escapeHtml(val).replace(/'/g, "\\'") + '\')">📋</button>';
                        }
                        html += '</td>';
                    }
                    html += '</tr>';
                }
                html += '</tbody></table></div>';
                
                if (data.totalPages > 1) {
                    html += '<div class="pagination">';
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

if (citySearchInput) {
    citySearchInput.addEventListener('input', function(e) {
        const keyword = this.value.trim();
        const citySearchResultDiv = document.getElementById('citySearchResult');
        
        if (citySearchTimeout) clearTimeout(citySearchTimeout);
        
        if (keyword.length < 2) {
            citySearchResultDiv.innerHTML = '<div class="no-result" style="padding:15px;">🔍 حداقل ۲ کاراکتر وارد کنید</div>';
            return;
        }
        
        citySearchResultDiv.innerHTML = '<div class="loading" style="padding:15px;">⏳ در حال جستجو...</div>';
        
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

if (refreshCacheBtn) {
    refreshCacheBtn.onclick = function() {
        if (confirm('آیا از بروزرسانی کش اطمینان دارید؟')) {
            const originalText = this.innerHTML;
            this.innerHTML = '⏳ در حال بروزرسانی...';
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

window.onclick = function(e) {
    if (e.target === adminModal) closeAdminModal();
    if (e.target === requestCityModal) closeRequestModal();
    if (e.target === document.getElementById('requestNationalCodeModal')) closeNationalCodeRequestModal();
};
</script>
</body>
</html>