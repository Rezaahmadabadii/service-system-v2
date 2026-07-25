<?php
// inquiry_panel.php - پنل استعلام واریزی‌ها با تم مانند صفحه اصلی

$config = require_once __DIR__ . '/config/app.php';

// ============================================
// توابع کمکی - ساده و سریع
// ============================================
function getUserIP() {
    // ابتدا از REMOTE_ADDR بگیر
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // اگر 127.0.0.1 یا ::1 بود، از SERVER_ADDR استفاده کن
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
        $ip = $_SERVER['SERVER_ADDR'] ?? 'unknown';
    }
    
    // اگر باز هم ::1 یا 127.0.0.1 بود، IP سرور رو مستقیم برگردان
    if ($ip === '127.0.0.1' || $ip === '::1') {
        $ip = '192.168.48.106';
    }
    
    return $ip;
}

function checkIPAccess($ip) {
    $ipFile = __DIR__ . '/storage/security/allowed_ips.txt';
    if (!file_exists($ipFile)) return false;
    
    $lines = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        // حذف * برای مقایسه
        $cleanIp = str_replace('*', '', $line);
        $cleanIp = trim($cleanIp);
        if ($cleanIp === $ip) return true;
    }
    return false;
}

// بررسی دسترسی کامل (با ستاره)
function checkFullAccess($ip) {
    $ipFile = __DIR__ . '/storage/security/allowed_ips.txt';
    if (!file_exists($ipFile)) return false;
    
    $lines = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        // اگر خط با * ختم می‌شود
        if (substr($line, -1) === '*') {
            $cleanIp = rtrim($line, '*');
            $cleanIp = trim($cleanIp);
            if ($cleanIp === $ip) {
                return true;
            }
        }
    }
    return false;
}

// دریافت سال و ماه جاری شمسی
function getCurrentJalaliDate() {
    function g2j($gy, $gm, $gd) {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + 33 * (int)($days / 12053);
        $days %= 12053;
        $jy += 4 * (int)($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }
    
    $now = getdate();
    list($jy, $jm, $jd) = g2j($now['year'], $now['mon'], $now['mday']);
    return ['year' => $jy, 'month' => $jm, 'day' => $jd];
}

$ip = getUserIP();
$currentDate = getCurrentJalaliDate();

// بررسی دسترسی کامل
$fullAccess = checkFullAccess($ip);

// بررسی دسترسی پایه
if (!checkIPAccess($ip)) {
    die('
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>دسترسی غیرمجاز</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:"Segoe UI",Tahoma,sans-serif;background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);direction:rtl;min-height:100vh;display:flex;justify-content:center;align-items:center;padding:20px}
            .access-card{background:#fff;border-radius:32px;padding:50px 40px;max-width:520px;width:100%;text-align:center;box-shadow:0 30px 80px rgba(0,0,0,0.08);animation:fadeUp 0.6s ease}
            .access-card .icon{font-size:72px;margin-bottom:20px;display:block}
            .access-card h1{color:#2d3436;font-size:1.8rem;margin-bottom:12px;font-weight:700}
            .access-card .subtitle{color:#e17055;font-size:1rem;margin-bottom:20px;font-weight:500}
            .access-card p{color:#636e72;font-size:.9rem;line-height:1.8;margin-bottom:8px}
            .access-card .ip-display{background:#f8f9fa;border-radius:16px;padding:14px;margin:20px 0;border:1px solid #e9ecef}
            .access-card .ip-display span{color:#2d3436;font-weight:600;direction:ltr;display:inline-block}
            .access-card .contact-info{background:#fff5f5;border-radius:16px;padding:16px;margin:16px 0;border:1px solid #fde2e2}
            .access-card .contact-info strong{color:#e17055}
            .access-card .btn-back{display:inline-block;margin-top:20px;padding:12px 40px;background:linear-gradient(135deg,#6c5ce7 0%,#a29bfe 100%);color:#fff;text-decoration:none;border-radius:50px;font-weight:600;transition:all .3s ease;border:none;cursor:pointer}
            .access-card .btn-back:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(108,92,231,0.3)}
            @keyframes fadeUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        </style>
    </head>
    <body>
        <div class="access-card">
            <span class="icon">🔒</span>
            <h1>دسترسی غیرمجاز</h1>
            <p class="subtitle">شما به این بخش دسترسی ندارید</p>
            <div class="ip-display">
                <span style="color:#636e72;font-weight:normal;">IP شما: </span>
                <span>' . htmlspecialchars($ip) . '</span>
            </div>
            <p>لطفاً با ادمین سیستم تماس بگیرید و درخواست دسترسی به این قسمت را ثبت کنید.</p>
            <div class="contact-info">
                <strong>شماره تماس داخلی: 13</strong>
                <span style="display:block;color:#636e72;font-size:.8rem;margin-top:4px;">(احمدآبادی - بخش مالی)</span>
            </div>
            <a href="index.php" class="btn-back">← بازگشت به صفحه اصلی</a>
        </div>
    </body>
    </html>
    ');
}

require_once __DIR__ . '/jdatetime.class.php';

$persianMonths = [
    1 => 'فروردین',
    2 => 'اردیبهشت',
    3 => 'خرداد',
    4 => 'تیر',
    5 => 'مرداد',
    6 => 'شهریور',
    7 => 'مهر',
    8 => 'آبان',
    9 => 'آذر',
    10 => 'دی',
    11 => 'بهمن',
    12 => 'اسفند'
];

// دریافت لیست سال‌ها
$years = [];
try {
    $apiUrl = 'api/inquiry_api.php?action=get_years';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['success'] && isset($data['years'])) {
            $years = $data['years'];
        }
    }
} catch (Exception $e) {
    // خطا را نادیده بگیر
}

if (empty($years)) {
    $years = [$currentDate['year']];
}

$currentYear = $currentDate['year'];
if (!in_array($currentYear, $years)) {
    $years[] = $currentYear;
    sort($years);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعلام واریزی‌ها</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,sans-serif;
            background:linear-gradient(135deg,#f5f7fa 0%,#e8ecf1 100%);
            direction:rtl;
            min-height:100vh;
            padding:20px;
        }
        .container{max-width:1400px;margin:0 auto}
        
        .header{
            background:rgba(255,255,255,0.95);
            backdrop-filter:blur(10px);
            border-radius:20px;
            padding:16px 24px;
            margin-bottom:20px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:16px;
            border:1px solid rgba(255,255,255,0.2);
            box-shadow:0 10px 40px rgba(0,0,0,0.1);
        }
        .header h1{
            font-size:1.3rem;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
            background-clip:text;
        }
        .header-left{
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }
        .ip-badge{
            background:#f0f0f5;
            color:#2d3436;
            padding:6px 18px;
            border-radius:30px;
            font-size:.75rem;
            display:flex;
            align-items:center;
            gap:8px;
            border:1px solid #e9ecef;
        }
        .ip-badge strong{color:#2d3436;direction:ltr}
        .back-btn{
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;
            border:none;
            padding:8px 22px;
            border-radius:40px;
            cursor:pointer;
            font-family:inherit;
            font-size:.75rem;
            display:flex;
            align-items:center;
            gap:6px;
            text-decoration:none;
            transition:all .3s ease;
            font-weight:500;
        }
        .back-btn:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(102,126,234,0.4)}
        
        .glow-card{
            --base:220;
            --spread:200;
            --radius:20;
            --border:2;
            --backdrop:rgba(255,255,255,0.7);
            --backup-border:rgba(255,255,255,0.3);
            --size:200;
            --outer:1;
            --border-size:calc(var(--border) * 1px);
            --spotlight-size:calc(var(--size) * 1px);
            --hue:calc(var(--base) + (var(--xp, 0) * var(--spread, 0)));
            position:relative;
            touch-action:none;
            border:var(--border-size) solid var(--backup-border);
            border-radius:calc(var(--radius) * 1px);
            background:var(--backdrop);
            background-image:radial-gradient(var(--spotlight-size) var(--spotlight-size) at calc(var(--x, 0) * 1px) calc(var(--y, 0) * 1px), hsl(var(--hue, 210) 100% 70% / 0.08), transparent);
            background-size:calc(100% + (2 * var(--border-size))) calc(100% + (2 * var(--border-size)));
            background-position:50% 50%;
            background-attachment:fixed;
            padding:0;
            transition:all .3s ease;
            backdrop-filter:blur(5px);
            overflow:hidden;
            min-height:200px;
        }
        .glow-card::before,
        .glow-card::after{
            pointer-events:none;
            content:"";
            position:absolute;
            inset:calc(var(--border-size) * -1);
            border:var(--border-size) solid transparent;
            border-radius:calc(var(--radius) * 1px);
            background-attachment:fixed;
            background-size:calc(100% + (2 * var(--border-size))) calc(100% + (2 * var(--border-size)));
            background-repeat:no-repeat;
            background-position:50% 50%;
            mask:linear-gradient(transparent, transparent), linear-gradient(white, white);
            mask-clip:padding-box, border-box;
            mask-composite:intersect;
            transition:all .3s ease;
        }
        .glow-card::before{
            background-image:radial-gradient(calc(var(--spotlight-size) * 0.75) calc(var(--spotlight-size) * 0.75) at calc(var(--x, 0) * 1px) calc(var(--y, 0) * 1px), hsl(var(--hue, 210) 100% 50% / 0.6), transparent 100%);
            filter:brightness(2);
        }
        .glow-card::after{
            background-image:radial-gradient(calc(var(--spotlight-size) * 0.5) calc(var(--spotlight-size) * 0.5) at calc(var(--x, 0) * 1px) calc(var(--y, 0) * 1px), hsl(0 100% 100% / 0.3), transparent 100%);
        }
        .glow-card .glow-inner{
            position:absolute;
            inset:0;
            will-change:filter;
            opacity:var(--outer, 1);
            border-radius:calc(var(--radius) * 1px);
            pointer-events:none;
            border:none;
            background:none;
        }
        .glow-card .glow-inner::before{
            content:'';
            position:absolute;
            inset:-10px;
            border-radius:calc(var(--radius) * 1px);
            border-width:10px;
            border-style:solid;
            border-color:transparent;
            background:radial-gradient(calc(var(--spotlight-size) * 0.6) calc(var(--spotlight-size) * 0.6) at calc(var(--x, 0) * 1px) calc(var(--y, 0) * 1px), hsl(var(--hue, 210) 100% 60% / 0.15), transparent 100%);
            filter:blur(20px);
        }
        .glow-card.blue{--base:220;--spread:200}
        .glow-card.purple{--base:280;--spread:300}
        .glow-card.green{--base:120;--spread:200}
        
        .glow-card .card-header{
            background:transparent;
            padding:18px 20px 10px 20px;
            color:#2d3436;
            font-weight:700;
            font-size:.9rem;
            position:relative;
            z-index:1;
        }
        .glow-card .card-body{
            padding:10px 20px 20px 20px;
            position:relative;
            z-index:1;
        }
        .glow-card .card-header .icon{font-size:1.2rem;margin-left:8px}
        
        .main-card{
            margin-bottom:20px;
        }
        
        .search-grid{
            display:grid;
            grid-template-columns:2fr 1fr 1fr 0.8fr;
            gap:14px;
            align-items:end;
        }
        .search-group{
            display:flex;
            flex-direction:column;
            gap:6px;
        }
        .search-group label{
            font-size:.7rem;
            font-weight:600;
            color:#636e72;
            display:flex;
            align-items:center;
            gap:6px;
        }
        .search-group input,
        .search-group select{
            width:100%;
            padding:10px 14px;
            border:2px solid #e9ecef;
            border-radius:14px;
            font-size:.85rem;
            transition:all .3s ease;
            background:#f8f9fa;
            font-family:inherit;
            color:#2d3436;
        }
        .search-group input::placeholder{color:#adb5bd}
        .search-group input:focus,
        .search-group select:focus{
            outline:none;
            border-color:#667eea;
            background:#fff;
            box-shadow:0 0 0 4px rgba(102,126,234,0.1);
        }
        
        .divider{
            border:0;
            height:1px;
            background:linear-gradient(to left, #6c5ce7, #e9ecef, transparent);
            margin:20px 0;
        }
        
        .action-bar{
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:12px;
            margin-top:4px;
        }
        .action-left{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .action-right{
            display:flex;
            gap:10px;
            align-items:center;
        }
        
        .btn{
            background:linear-gradient(135deg,#6c5ce7 0%,#a29bfe 100%);
            color:#fff;
            border:none;
            padding:10px 22px;
            border-radius:14px;
            cursor:pointer;
            font-family:inherit;
            font-size:.8rem;
            font-weight:600;
            transition:all .3s ease;
            display:inline-flex;
            align-items:center;
            gap:8px;
        }
        .btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(108,92,231,0.25)}
        .btn-success{
            background:linear-gradient(135deg,#00b894 0%,#00cec9 100%);
        }
        .btn-success:hover{box-shadow:0 8px 25px rgba(0,184,148,0.25)}
        .btn-warning{
            background:linear-gradient(135deg,#fdcb6e 0%,#f39c12 100%);
            color:#2d3436;
        }
        .btn-warning:hover{box-shadow:0 8px 25px rgba(253,203,110,0.25)}
        .btn-sm{padding:8px 16px;font-size:.7rem;border-radius:10px}
        .btn-outline{
            background:transparent;
            border:1px solid #e9ecef;
            color:#636e72;
        }
        .btn-outline:hover{
            background:#f8f9fa;
            border-color:#6c5ce7;
            box-shadow:none;
            transform:translateY(-2px);
        }
        .btn-reverse{
            background:linear-gradient(135deg,#fd79a8 0%,#e84393 100%);
        }
        .btn-reverse:hover{box-shadow:0 8px 25px rgba(232,67,147,0.25)}
        .btn-reverse.active{
            background:linear-gradient(135deg,#00b894 0%,#00cec9 100%);
        }
        .btn-reverse.active:hover{box-shadow:0 8px 25px rgba(0,184,148,0.25)}
        
        /* ============================================
           دکمه پاک کردن فیلد (Clear Button)
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
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(1.8); opacity: 0; }
        }
        
        .input-wrapper{
            display:flex;
            gap:8px;
            align-items:center;
        }
        
        .results-card{
            background:rgba(255,255,255,0.95);
            backdrop-filter:blur(10px);
            border-radius:20px;
            padding:22px 28px;
            box-shadow:0 10px 40px rgba(0,0,0,0.1);
            border:1px solid rgba(255,255,255,0.2);
            animation:fadeUp .6s ease forwards;
            display:none;
        }
        .results-card .card-title{
            font-size:1rem;
            font-weight:700;
            color:#2d3436;
            display:flex;
            align-items:center;
            gap:10px;
            border-bottom:2px solid #f0f2f5;
            padding-bottom:14px;
            margin-bottom:16px;
            flex-wrap:wrap;
        }
        .results-card .card-title span{color:#6c5ce7}
        .results-card .card-title .title-left{
            display:flex;
            align-items:center;
            gap:14px;
            margin-right:auto;
            flex-wrap:wrap;
        }
        .results-card .card-title .result-count{
            font-weight:400;
            font-size:.8rem;
            color:#adb5bd;
        }
        .results-card .card-title .total-amount-box{
            background:linear-gradient(135deg,#6c5ce7 0%,#a29bfe 100%);
            color:#fff;
            padding:4px 16px;
            border-radius:30px;
            font-size:.75rem;
            font-weight:600;
            display:inline-flex;
            align-items:center;
            gap:6px;
            display:none;
        }
        
        #historyBox{
            display:none;
            margin-bottom:16px;
            background:#f8f9fa;
            border-radius:16px;
            padding:14px 16px;
            border:1px solid #e9ecef;
        }
        #historyBox .history-label{
            font-weight:600;
            margin-bottom:10px;
            font-size:.75rem;
            color:#636e72;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .history-grid{
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
            gap:8px;
        }
        .history-card{
            background:#fff;
            border:1px solid #e9ecef;
            border-radius:12px;
            padding:10px 14px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            transition:all .2s;
        }
        .history-card:hover{
            background:#f8f9fa;
            transform:translateY(-2px);
            box-shadow:0 4px 12px rgba(0,0,0,0.04);
        }
        .history-card .h-term{
            font-weight:600;
            font-size:.75rem;
            color:#2d3436;
        }
        .history-card .h-info{
            font-size:.65rem;
            color:#adb5bd;
            display:flex;
            flex-direction:column;
            align-items:flex-start;
            gap:2px;
        }
        .history-card .h-info span{font-size:.6rem;color:#ced4da}
        
        .results-table{
            width:100%;
            border-collapse:collapse;
            font-size:.8rem;
            margin-top:4px;
        }
        .results-table th{
            background:linear-gradient(135deg,#6c5ce7 0%,#a29bfe 100%);
            color:#fff;
            padding:10px 14px;
            text-align:right;
            font-weight:600;
            font-size:.75rem;
        }
        .results-table th:first-child{border-radius:0 12px 0 0}
        .results-table th:last-child{border-radius:12px 0 0 0}
        .results-table td{
            padding:8px 14px;
            border-bottom:1px solid #f0f2f5;
            color:#2d3436;
            transition:all .2s;
        }
        .results-table tr{transition:all .2s;animation:rowIn .35s ease forwards}
        .results-table tr:hover{background:#f8f9fa}
        .results-table tr:nth-child(1){animation-delay:.05s}
        .results-table tr:nth-child(2){animation-delay:.1s}
        .results-table tr:nth-child(3){animation-delay:.15s}
        .results-table tr:nth-child(4){animation-delay:.2s}
        .results-table tr:nth-child(5){animation-delay:.25s}
        .results-table td strong{color:#2d3436}
        
        .pagination{
            display:flex;
            justify-content:center;
            gap:6px;
            margin-top:18px;
            flex-wrap:wrap;
        }
        .page-btn{
            padding:6px 14px;
            border:2px solid #e9ecef;
            background:#fff;
            border-radius:10px;
            cursor:pointer;
            font-size:.7rem;
            transition:all .2s ease;
            font-weight:500;
            color:#636e72;
        }
        .page-btn:hover{
            background:#6c5ce7;
            color:#fff;
            border-color:#6c5ce7;
            transform:translateY(-2px);
        }
        .page-btn.active{
            background:#6c5ce7;
            color:#fff;
            border-color:#6c5ce7;
        }
        
        .loading-inline{
            display:none;
            text-align:center;
            padding:30px;
            background:#f8f9fa;
            border-radius:16px;
            margin:10px 0;
        }
        .loading-inline.active{display:block;animation:fadeIn .3s ease}
        .loading-inline .spinner{
            width:40px;
            height:40px;
            border:3px solid #e9ecef;
            border-top-color:#6c5ce7;
            border-radius:50%;
            animation:spin 0.8s linear infinite;
            margin:0 auto 12px;
        }
        .loading-inline p{color:#adb5bd;font-size:.85rem}
        
        .no-result{
            text-align:center;
            padding:40px 20px;
            color:#adb5bd;
        }
        .no-result span{font-size:3rem;margin-bottom:12px;opacity:0.3;display:block}
        
        .results-summary{
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:10px;
            margin-top:4px;
            padding:8px 4px;
            color:#adb5bd;
            font-size:.75rem;
        }
        .results-summary span{display:flex;align-items:center;gap:4px}
        .results-summary strong{color:#2d3436}
        
        .modal-overlay{
            display:none;
            position:fixed;
            top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,0.4);
            backdrop-filter:blur(6px);
            z-index:1000;
            justify-content:center;
            align-items:center;
            animation:fadeIn .3s ease;
        }
        .modal-overlay.active{display:flex}
        .modal-box{
            background:#fff;
            border-radius:28px;
            padding:40px;
            max-width:460px;
            width:90%;
            text-align:center;
            box-shadow:0 30px 80px rgba(0,0,0,0.12);
            animation:scaleIn .4s ease;
        }
        .modal-box .icon{font-size:56px;margin-bottom:14px;display:block}
        .modal-box h2{color:#fdcb6e;font-size:1.2rem;margin-bottom:10px}
        .modal-box p{color:#636e72;font-size:.85rem;line-height:1.8;margin-bottom:20px}
        .modal-box .btn{width:100%;justify-content:center}
        
        @keyframes fadeIn{from{opacity:0}to{opacity:1}}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @keyframes slideDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
        @keyframes scaleIn{from{opacity:0;transform:scale(0.92)}to{opacity:1;transform:scale(1)}}
        @keyframes rowIn{from{opacity:0;transform:translateX(-8px)}to{opacity:1;transform:translateX(0)}}
        @keyframes spin{to{transform:rotate(360deg)}}
        
        @media(max-width:992px){.search-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:768px){
            .search-grid{grid-template-columns:1fr}
            .header{flex-direction:column;text-align:center}
            .header-left{width:100%;justify-content:center}
            .action-bar{flex-direction:column;align-items:stretch}
            .action-left,.action-right{justify-content:center}
            .results-table{font-size:.7rem}
            .results-table th,.results-table td{padding:6px 8px}
            .history-grid{grid-template-columns:1fr 1fr}
        }
        @media(max-width:480px){.history-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>

<!-- اسکریپت افکت گلو -->
<script>
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
</script>

<div class="container">

<!-- مودال هشدار -->
<div class="modal-overlay active" id="warningModal">
    <div class="modal-box">
        <span class="icon">🔐</span>
        <h2>⚠️ توجه: ثبت تاریخچه جستجوها</h2>
        <p>
            تمام جستجوهای شما در تاریخچه ثبت و نگهداری می‌شود.<br>
            این اطلاعات برای بهبود عملکرد سیستم استفاده می‌شود.
        </p>
        <button class="btn btn-success" onclick="closeWarningModal()">✔ ادامه</button>
    </div>
</div>

<!-- هدر -->
<div class="header">
    <h1>📄 استعلام واریزی‌ها</h1>
    <div class="header-left">
        <span class="ip-badge">🌐 IP: <strong><?php echo htmlspecialchars($ip); ?></strong></span>
        <a href="index.php" class="back-btn">← بازگشت</a>
    </div>
</div>

<!-- کارت اصلی با افکت گلو -->
<div class="glow-card blue main-card">
    <div class="glow-inner"></div>
    <div class="card-header">
        <span class="icon">🔍</span> جستجوی واریزی
    </div>
    <div class="card-body">
        <div class="search-grid">
            <div class="search-group">
                <label>👤 نام و فامیل</label>
                <div class="input-wrapper">
                    <input type="text" id="searchName" placeholder="مثال: احمدآبادی رضا" autocomplete="off">
                    <div class="clear-btn-wrap">
                        <input id="clearSearchBtn" class="clear-btn-input" type="checkbox" />
                        <button class="clear-btn" onclick="clearSearchInput()">
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
                                        <pattern height="100" width="100" patternUnits="userSpaceOnUse" id="noise-pattern-clear">
                                            <filter y="0" x="0" id="noise-clear">
                                                <feTurbulence stitchTiles="stitch" numOctaves="2" baseFrequency="0.65" type="fractalNoise" />
                                                <feBlend mode="screen" />
                                            </filter>
                                            <rect filter="url(#noise-clear)" height="100" width="100" />
                                        </pattern>
                                    </defs>
                                    <rect fill="url(#noise-pattern-clear)" height="100%" width="100%" />
                                </svg>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
            <div class="search-group">
                <label>📅 سال</label>
                <select id="searchYear">
                    <?php foreach($years as $year): ?>
                    <option value="<?php echo $year; ?>" <?php echo ($year == $currentYear) ? 'selected' : ''; ?>>
                        <?php echo $year; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group">
                <label>📅 ماه</label>
                <select id="searchMonth">
                    <option value="">همه ماه‌ها</option>
                    <?php 
                    $currentMonth = $currentDate['month'];
                    for($m=1; $m<=$currentMonth; $m++): 
                    ?>
                    <option value="<?php echo $m; ?>" <?php echo ($m == $currentMonth) ? 'selected' : ''; ?>>
                        <?php echo $persianMonths[$m]; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="search-group">
                <label>📅 روز</label>
                <select id="searchDay">
                    <option value="">همه</option>
                    <?php for($i=1;$i<=31;$i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <hr class="divider">
        
        <div class="action-bar">
            <div class="action-left" id="fullAccessButtons" style="<?php echo $fullAccess ? '' : 'display:none;'; ?>">
                <button class="btn btn-success btn-sm" onclick="showAllTransfers('today')" id="btnToday">
                    📅 کل واریزی امروز
                </button>
                <button class="btn btn-warning btn-sm" onclick="showAllTransfers('yesterday')" id="btnYesterday">
                    📅 کل واریزی دیروز
                </button>
            </div>
            <div class="action-right">
                <button class="btn btn-reverse btn-sm" onclick="toggleOrder()" id="reverseBtn" title="معکوس کردن ترتیب">
                    🔄 معکوس
                </button>
            </div>
        </div>
    </div>
</div>

<!-- کارت نتایج -->
<div class="results-card" id="resultCard">
    <div class="card-title">
        <span>📋</span> نتایج
        <span class="result-count" id="resultCount"></span>
        <span class="title-left">
            <span class="total-amount-box" id="totalAmount">🧮 جمع: 0</span>
            <button class="btn btn-sm btn-outline" onclick="toggleHistory()">📜 تاریخچه</button>
        </span>
    </div>
    
    <div id="historyBox">
        <div class="history-label">🕐 📋 تاریخچه جستجوها (آخرین ۳۰ مورد)</div>
        <div id="historyList" class="history-grid"></div>
    </div>
    
    <div class="loading-inline" id="loadingInline">
        <div class="spinner"></div>
        <p>⏳ در حال جستجو...</p>
    </div>
    
    <div id="resultContent">
        <div class="no-result"><span>🔍</span>جستجویی انجام نشده است</div>
    </div>
</div>

</div>

<script>
// ============================================
// تابع پاک کردن فیلد جستجو
// ============================================
function clearSearchInput() {
    const input = document.getElementById('searchName');
    if (input) {
        input.value = '';
        input.focus();
        document.getElementById('resultCard').style.display = 'none';
        document.getElementById('resultBadge').textContent = 'در انتظار جستجو';
    }
}

// ============================================
// متغیرها
// ============================================
let currentPage = 1;
let allResults = [];
let resultsPerPage = 20;
let isFullAccess = <?php echo $fullAccess ? 'true' : 'false'; ?>;
let currentOrder = 'desc';
let currentDate = {
    year: <?php echo $currentDate['year']; ?>,
    month: <?php echo $currentDate['month']; ?>,
    day: <?php echo $currentDate['day']; ?>
};

// ============================================
// توابع عمومی
// ============================================
function closeWarningModal() {
    document.getElementById('warningModal').classList.remove('active');
}

function showLoading(show) {
    document.getElementById('loadingInline').classList.toggle('active', show);
}

function showToast(message, type) {
    const colors = {
        success: '#00b894',
        error: '#e17055',
        warning: '#fdcb6e',
        info: '#6c5ce7'
    };
    const toast = document.createElement('div');
    toast.textContent = message;
    toast.style.cssText = `
        position:fixed; bottom:30px; right:30px; 
        background:${colors[type] || '#2d3436'}; 
        color:white; padding:12px 24px; 
        border-radius:14px; z-index:9999; 
        font-size:0.85rem; font-weight:500;
        animation:fadeUp 0.4s ease;
        box-shadow:0 10px 30px rgba(0,0,0,0.1);
        max-width:90%;
        direction:rtl;
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function numberFormat(num) {
    if (!num || num === '0') return '0';
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function toggleOrder() {
    currentOrder = (currentOrder === 'asc') ? 'desc' : 'asc';
    const btn = document.getElementById('reverseBtn');
    if (currentOrder === 'desc') {
        btn.innerHTML = '🔄 معکوس';
        btn.className = 'btn btn-reverse btn-sm';
    } else {
        btn.innerHTML = '🔄 عادی';
        btn.className = 'btn btn-reverse btn-sm active';
    }
    if (allResults.length > 0) {
        allResults.reverse();
        currentPage = 1;
        displayResults();
    }
}

// ============================================
// جستجوی خودکار
// ============================================
let searchTimeout = null;

function autoSearch() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => doSearch(), 350);
}

function doSearch() {
    const name = document.getElementById('searchName').value.trim();
    const year = document.getElementById('searchYear').value;
    const month = document.getElementById('searchMonth').value;
    const day = document.getElementById('searchDay').value;
    
    if (!name || name.length < 2) {
        document.getElementById('resultCard').style.display = 'none';
        return;
    }
    
    if (!year) {
        showToast('لطفاً سال را انتخاب کنید', 'warning');
        return;
    }
    
    showLoading(true);
    document.getElementById('resultCard').style.display = 'block';
    document.getElementById('resultContent').innerHTML = '';
    
    let url = `api/inquiry_api.php?action=search&name=${encodeURIComponent(name)}&year=${year}`;
    if (month) {
        url += `&month=${month}`;
    }
    if (day) {
        url += `&day=${day}`;
    }
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            showLoading(false);
            if (data.success) {
                allResults = data.results || [];
                if (currentOrder === 'desc') allResults.reverse();
                currentPage = 1;
                displayResults();
                document.getElementById('resultCount').textContent = `${data.count} نتیجه`;
                loadHistory();
            } else {
                document.getElementById('resultContent').innerHTML = `
                    <div class="no-result"><span>⚠️</span><br>${data.error || 'خطا در جستجو'}</div>
                `;
                showToast(data.error || 'خطا در جستجو', 'error');
            }
        })
        .catch(() => {
            showLoading(false);
            document.getElementById('resultContent').innerHTML = `
                <div class="no-result"><span>⚠️</span><br>خطا در ارتباط با سرور</div>
            `;
            showToast('خطا در ارتباط با سرور', 'error');
        });
}

// ============================================
// نمایش نتایج
// ============================================
function displayResults() {
    const container = document.getElementById('resultContent');
    
    if (!allResults || allResults.length === 0) {
        document.getElementById('totalAmount').style.display = 'none';
        container.innerHTML = '<div class="no-result"><span>🔍</span><br>نتیجه‌ای یافت نشد</div>';
        return;
    }
    
    const totalPages = Math.ceil(allResults.length / resultsPerPage);
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    
    const start = (currentPage - 1) * resultsPerPage;
    const pageResults = allResults.slice(start, start + resultsPerPage);
    
    let totalSumAll = 0;
    allResults.forEach(item => {
        const cleanAmount = String(item.amount).replace(/[^0-9]/g, '');
        if (cleanAmount) totalSumAll += parseInt(cleanAmount);
    });
    
    const totalEl = document.getElementById('totalAmount');
    if (totalSumAll > 0) {
        totalEl.style.display = 'inline-flex';
        totalEl.innerHTML = `🧮 جمع: ${numberFormat(totalSumAll)} ریال`;
    } else {
        totalEl.style.display = 'none';
    }
    
    let html = `
        <div class="results-summary">
            <span>🔍 <strong>${allResults.length}</strong> نتیجه</span>
            <span>📅 صفحه ${currentPage} از ${totalPages}</span>
        </div>
        <table class="results-table">
            <thead><tr>
                <th>#</th><th>روز</th><th>کد پرسنلی</th><th>نام دریافت‌کننده</th><th>مبلغ (ریال)</th>
            </tr></thead>
            <tbody>
    `;
    
    pageResults.forEach((item, index) => {
        const realIndex = start + index + 1;
        const code = item.code || '-';
        const cleanAmount = String(item.amount).replace(/[^0-9]/g, '');
        const formattedAmount = cleanAmount ? numberFormat(cleanAmount) : item.amount || '0';
        
        html += `
            <tr>
                <td>${realIndex}</td>
                <td>${item.day || '-'}</td>
                <td>${code}</td>
                <td><strong>${escapeHtml(item.name)}</strong></td>
                <td>${formattedAmount}</td>
            </tr>
        `;
    });
    
    html += `</tbody></table>`;
    
    if (totalPages > 1) {
        html += `<div class="pagination">`;
        if (currentPage > 1) {
            html += `<button class="page-btn" onclick="goToPage(${currentPage - 1})">« قبلی</button>`;
        }
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<button class="page-btn active">${i}</button>`;
            } else if (Math.abs(i - currentPage) <= 2 || i === 1 || i === totalPages) {
                html += `<button class="page-btn" onclick="goToPage(${i})">${i}</button>`;
            } else if (Math.abs(i - currentPage) === 3) {
                html += `<span style="padding:5px;color:#adb5bd;">...</span>`;
            }
        }
        if (currentPage < totalPages) {
            html += `<button class="page-btn" onclick="goToPage(${currentPage + 1})">بعدی »</button>`;
        }
        html += `</div>`;
    }
    
    container.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    displayResults();
}

// ============================================
// نمایش کل واریزی امروز/دیروز
// ============================================
function showAllTransfers(type) {
    if (!isFullAccess) {
        showToast('شما دسترسی به این بخش ندارید', 'error');
        return;
    }
    
    document.getElementById('resultCard').style.display = 'block';
    showLoading(true);
    document.getElementById('resultContent').innerHTML = '';
    document.getElementById('searchName').value = '';
    
    fetch(`api/inquiry_api.php?action=${type === 'today' ? 'today_all' : 'yesterday_all'}`)
        .then(res => res.json())
        .then(data => {
            showLoading(false);
            if (data.success) {
                allResults = data.results || [];
                if (currentOrder === 'desc') allResults.reverse();
                currentPage = 1;
                document.getElementById('resultCount').textContent = `${data.count} نتیجه`;
                displayResults();
                loadHistory();
            } else {
                document.getElementById('resultContent').innerHTML = `
                    <div class="no-result"><span>⚠️</span><br>${data.error || 'خطا'}</div>
                `;
            }
        })
        .catch(() => {
            showLoading(false);
            document.getElementById('resultContent').innerHTML = `
                <div class="no-result"><span>⚠️</span><br>خطا در ارتباط با سرور</div>
            `;
        });
}

// ============================================
// تاریخچه
// ============================================
function loadHistory() {
    const list = document.getElementById('historyList');
    list.innerHTML = '<div style="color:#adb5bd;font-size:.75rem;padding:10px;">در حال بارگذاری...</div>';
    
    fetch('api/inquiry_api.php?action=get_history')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.history) {
                const twoDaysAgo = new Date();
                twoDaysAgo.setDate(twoDaysAgo.getDate() - 2);
                
                const filtered = data.history.filter(h => {
                    const hDate = new Date(h.date);
                    return hDate >= twoDaysAgo;
                });
                
                if (filtered.length === 0) {
                    list.innerHTML = '<div style="color:#adb5bd;font-size:.75rem;padding:10px;">تاریخچه‌ای وجود ندارد</div>';
                } else {
                    list.innerHTML = filtered.slice(0, 30).map(h => {
                        const dateObj = new Date(h.date);
                        const jDate = new jDateTime();
                        const jalali = jDate.date('Y/m/d', dateObj.getTime()/1000);
                        return `
                            <div class="history-card">
                                <span class="h-term">${escapeHtml(h.search_term)}</span>
                                <span class="h-info">
                                    <span>${h.results_count} نتیجه</span>
                                    <span>${jalali}</span>
                                </span>
                            </div>
                        `;
                    }).join('');
                }
            }
        })
        .catch(() => {
            list.innerHTML = '<div style="color:#adb5bd;font-size:.75rem;padding:10px;">خطا در بارگذاری تاریخچه</div>';
        });
}

function toggleHistory() {
    const box = document.getElementById('historyBox');
    if (box.style.display === 'none' || box.style.display === '') {
        box.style.display = 'block';
        loadHistory();
    } else {
        box.style.display = 'none';
    }
}

// ============================================
// کلاس jDateTime
// ============================================
class jDateTime {
    constructor() {
        this.g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        this.j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    }
    
    toJalali(gy, gm, gd) {
        let gy2 = (gm > 2) ? gy + 1 : gy;
        let days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + this.g_days_in_month[gm - 1];
        let jy = -1595 + 33 * Math.floor(days / 12053);
        days %= 12053;
        jy += 4 * Math.floor(days / 1461);
        days %= 1461;
        if (days > 365) {
            jy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }
        let jm, jd;
        if (days < 186) {
            jm = 1 + Math.floor(days / 31);
            jd = 1 + (days % 31);
        } else {
            jm = 7 + Math.floor((days - 186) / 30);
            jd = 1 + ((days - 186) % 30);
        }
        return [jy, jm, jd];
    }
    
    date(format, timestamp) {
        const d = new Date(timestamp * 1000);
        const [jy, jm, jd] = this.toJalali(d.getFullYear(), d.getMonth() + 1, d.getDate());
        const months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
        let result = format;
        result = result.replace('Y', jy);
        result = result.replace('m', String(jm).padStart(2, '0'));
        result = result.replace('d', String(jd).padStart(2, '0'));
        result = result.replace('M', months[jm - 1]);
        return result;
    }
}

// ============================================
// رویدادها
// ============================================
document.getElementById('searchYear').addEventListener('change', autoSearch);
document.getElementById('searchName').addEventListener('input', autoSearch);
document.getElementById('searchMonth').addEventListener('change', autoSearch);
document.getElementById('searchDay').addEventListener('change', autoSearch);

// ============================================
// مقداردهی اولیه
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // همه چیز آماده است
});
</script>
</body>
</html>