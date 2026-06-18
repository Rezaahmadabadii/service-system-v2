<?php
// admin.php - پنل مدیریت با طراحی زیبا و بخش درخواست‌های اصلاح شهر و کد ملی + نمایش لاگ

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);

$config = require_once __DIR__ . '/config/app.php';
$nationalCodesDatabase = require_once __DIR__ . '/Core/Helpers/national_codes_database.php';

spl_autoload_register(function ($class) {
    $prefix = 'Core\\';
    $base_dir = __DIR__ . '/Core/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = $base_dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});

use Core\Services\NationalCodeService;
use Core\Services\SearchLogger;

$nationalCodeService = new NationalCodeService($nationalCodesDatabase, $config['national_codes_override_path']);
$overrides = $nationalCodeService->getAllOverrides();
$message = null;

// ایجاد لاگر برای نمایش تاریخچه جستجوها
$logger = new SearchLogger(__DIR__ . '/storage/logs/search.log');
$recentLogs = $logger->getRecentLogs(50);
$logStats = $logger->getStatistics();

// خواندن درخواست‌های اصلاح شهر
$requestsFile = __DIR__ . '/storage/database/city_requests.json';
$requests = [];
if (file_exists($requestsFile)) {
    $requests = json_decode(file_get_contents($requestsFile), true);
}
$pendingRequests = array_filter($requests, fn($r) => $r['status'] === 'pending');
$approvedRequests = array_filter($requests, fn($r) => $r['status'] === 'approved');
$rejectedRequests = array_filter($requests, fn($r) => $r['status'] === 'rejected');

// خواندن درخواست‌های اصلاح کد ملی (سه رقم)
$nationalRequestsFile = __DIR__ . '/storage/database/national_code_requests.json';
$nationalRequests = [];
if (file_exists($nationalRequestsFile)) {
    $nationalRequests = json_decode(file_get_contents($nationalRequestsFile), true);
}
$pendingNationalRequests = array_filter($nationalRequests, fn($r) => $r['status'] === 'pending');
$approvedNationalRequests = array_filter($nationalRequests, fn($r) => $r['status'] === 'approved');
$rejectedNationalRequests = array_filter($nationalRequests, fn($r) => $r['status'] === 'rejected');

// پردازش فرم اصلاح کد ملی
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['admin_password'] ?? '';
    $prefix = str_pad($_POST['code_prefix'] ?? '', 3, '0', STR_PAD_LEFT);
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    
    if (isset($_POST['save'])) {
        $result = $nationalCodeService->saveOrUpdate($prefix, $province, $city, $password, $config['admin_password']);
        $message = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
        if ($result['success']) $overrides = $nationalCodeService->getAllOverrides();
    }
    
    if (isset($_POST['delete'])) {
        $result = $nationalCodeService->deleteOverride($prefix, $password, $config['admin_password']);
        $message = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
        if ($result['success']) $overrides = $nationalCodeService->getAllOverrides();
    }
}

// پردازش درخواست‌های اصلاح شهر
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    $password = $_GET['password'] ?? '';
    
    if ($password !== $config['admin_password']) {
        $message = ['type' => 'error', 'text' => '❌ رمز عبور مدیریت اشتباه است'];
    } else {
        foreach ($requests as &$req) {
            if ($req['id'] === $id) {
                if ($action === 'approve') {
                    $codePrefix = substr($req['code'], 0, 3);
                    $result = $nationalCodeService->saveOrUpdate(
                        $codePrefix, 
                        $req['suggested_province'], 
                        $req['suggested_city'], 
                        $password, 
                        $config['admin_password']
                    );
                    if ($result['success']) {
                        $req['status'] = 'approved';
                        $message = ['type' => 'success', 'text' => '✅ درخواست تأیید و به دیتابیس اضافه شد'];
                        $overrides = $nationalCodeService->getAllOverrides();
                    } else {
                        $message = ['type' => 'error', 'text' => '❌ خطا در ذخیره: ' . $result['message']];
                    }
                } elseif ($action === 'reject') {
                    $req['status'] = 'rejected';
                    $message = ['type' => 'success', 'text' => '❌ درخواست رد شد'];
                } elseif ($action === 'edit') {
                    $newProvince = $_GET['edit_province'] ?? $req['suggested_province'];
                    $newCity = $_GET['edit_city'] ?? $req['suggested_city'];
                    $req['suggested_province'] = $newProvince;
                    $req['suggested_city'] = $newCity;
                    $message = ['type' => 'success', 'text' => '✏️ درخواست با موفقیت ویرایش شد'];
                } elseif ($action === 'delete') {
                    unset($req);
                    $message = ['type' => 'success', 'text' => '🗑 درخواست با موفقیت حذف شد'];
                }
                break;
            }
        }
        
        $requests = array_values($requests);
        file_put_contents($requestsFile, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $pendingRequests = array_filter($requests, fn($r) => $r['status'] === 'pending');
        $approvedRequests = array_filter($requests, fn($r) => $r['status'] === 'approved');
        $rejectedRequests = array_filter($requests, fn($r) => $r['status'] === 'rejected');
    }
}

// پردازش درخواست‌های کد ملی (سه رقم)
if (isset($_GET['national_action']) && isset($_GET['id'])) {
    $action = $_GET['national_action'];
    $id = $_GET['id'];
    $password = $_GET['password'] ?? '';
    
    if ($password !== $config['admin_password']) {
        $message = ['type' => 'error', 'text' => '❌ رمز عبور مدیریت اشتباه است'];
    } else {
        foreach ($nationalRequests as &$req) {
            if ($req['id'] === $id) {
                if ($action === 'approve') {
                    $result = $nationalCodeService->saveOrUpdate(
                        $req['prefix'], 
                        $req['suggested_province'], 
                        $req['suggested_city'], 
                        $password, 
                        $config['admin_password']
                    );
                    if ($result['success']) {
                        $req['status'] = 'approved';
                        $message = ['type' => 'success', 'text' => '✅ درخواست کد ملی تأیید و به دیتابیس اضافه شد'];
                        $overrides = $nationalCodeService->getAllOverrides();
                    } else {
                        $message = ['type' => 'error', 'text' => '❌ خطا در ذخیره: ' . $result['message']];
                    }
                } elseif ($action === 'reject') {
                    $req['status'] = 'rejected';
                    $message = ['type' => 'success', 'text' => '❌ درخواست کد ملی رد شد'];
                } elseif ($action === 'edit') {
                    $newProvince = $_GET['edit_province'] ?? $req['suggested_province'];
                    $newCity = $_GET['edit_city'] ?? $req['suggested_city'];
                    $req['suggested_province'] = $newProvince;
                    $req['suggested_city'] = $newCity;
                    $message = ['type' => 'success', 'text' => '✏️ درخواست کد ملی با موفقیت ویرایش شد'];
                } elseif ($action === 'delete') {
                    unset($req);
                    $message = ['type' => 'success', 'text' => '🗑 درخواست کد ملی با موفقیت حذف شد'];
                }
                break;
            }
        }
        
        $nationalRequests = array_values($nationalRequests);
        file_put_contents($nationalRequestsFile, json_encode($nationalRequests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $pendingNationalRequests = array_filter($nationalRequests, fn($r) => $r['status'] === 'pending');
        $approvedNationalRequests = array_filter($nationalRequests, fn($r) => $r['status'] === 'approved');
        $rejectedNationalRequests = array_filter($nationalRequests, fn($r) => $r['status'] === 'rejected');
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>پنل مدیریت - اصلاح دیتابیس کدهای ملی</title>
    <link rel="icon" type="image/x-icon" href="/service-system-v2/favicon.ico">	
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,sans-serif;background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);direction:rtl;min-height:100vh;padding:20px}
        .container{max-width:1400px;margin:0 auto}
        .header{background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-radius:20px;padding:16px 24px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;border:1px solid rgba(255,255,255,0.2);box-shadow:0 10px 40px rgba(0,0,0,0.1)}
        .logo h1{font-size:1.3rem;background:linear-gradient(135deg,#f39c12 0%,#e74c3c 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .logo p{font-size:0.7rem;color:#6c757d}
        .back-btn{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border:none;padding:8px 18px;border-radius:30px;cursor:pointer;font-family:inherit;font-size:0.8rem;display:flex;align-items:center;gap:6px;text-decoration:none;transition:all 0.3s ease}
        .back-btn:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(102,126,234,0.4)}
        .two-columns{display:grid;grid-template-columns:repeat(2,1fr);gap:24px;margin-bottom:24px}
        .card{background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,0.2);transition:all 0.3s ease}
        .card:hover{transform:translateY(-3px);box-shadow:0 20px 40px rgba(0,0,0,0.15)}
        .card-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:14px 18px;color:white;font-weight:600;font-size:0.9rem}
        .card-header i{margin-left:8px}
        .card-body{padding:20px}
        .form-group{margin-bottom:16px}
        label{display:block;margin-bottom:6px;font-weight:500;font-size:0.75rem;color:#2c3e50}
        input[type="text"],input[type="password"]{width:100%;padding:10px 14px;border:1.5px solid #e9ecef;border-radius:12px;font-size:0.85rem;transition:all 0.3s ease;background:#f8f9fa}
        input:focus{outline:none;border-color:#667eea;background:white;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}
        .btn{padding:10px 16px;border:none;border-radius:12px;cursor:pointer;font-family:inherit;font-size:0.8rem;font-weight:500;transition:all 0.3s ease}
        .btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(102,126,234,0.4)}
        .btn-success{background:#28a745;color:white}
        .btn-danger{background:#dc3545;color:white}
        .btn-warning{background:#ffc107;color:#2c3e50}
        .btn-info{background:#17a2b8;color:white}
        .btn-sm{padding:5px 10px;font-size:0.7rem}
        .btn-group{display:flex;gap:10px;flex-wrap:wrap}
        .message{padding:12px;border-radius:12px;margin-bottom:16px;font-size:0.8rem}
        .message-success{background:#d4edda;color:#155724;border-right:3px solid #28a745}
        .message-error{background:#f8d7da;color:#721c24;border-right:3px solid #dc3545}
        .table-wrapper{overflow-x:auto;max-height:500px;overflow-y:auto;border-radius:12px;border:1px solid #e9ecef}
        .data-table{width:100%;border-collapse:collapse;font-size:0.75rem}
        .data-table th,.data-table td{border:1px solid #e9ecef;padding:10px 12px;text-align:right;vertical-align:middle}
        .data-table th{background:#f8f9fa;font-weight:600;position:sticky;top:0}
        .data-table tr:hover{background:#f8f9fa}
        .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.65rem;font-weight:600}
        .badge-pending{background:#ffc107;color:#2c3e50}
        .badge-approved{background:#28a745;color:white}
        .badge-rejected{background:#dc3545;color:white}
        .tab-buttons{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
        .tab-btn{background:#e9ecef;border:none;padding:8px 16px;border-radius:30px;cursor:pointer;font-family:inherit;font-size:0.8rem;transition:all 0.3s ease}
        .tab-btn.active{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
        .tab-content{display:none}
        .tab-content.active{display:block}
        .footer{text-align:center;padding:16px;margin-top:24px;color:rgba(255,255,255,0.6);font-size:0.65rem}
        hr{margin:16px 0;border:none;border-top:1px solid #e9ecef}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);backdrop-filter:blur(5px);z-index:1000;justify-content:center;align-items:center}
        .modal-content{background:white;border-radius:20px;padding:24px;max-width:450px;width:90%}
        .modal-content h3{margin-bottom:16px;font-size:1.1rem}
        .modal-content input{width:100%;padding:10px;border:1px solid #ccc;border-radius:10px;margin-bottom:12px}
        @media (max-width:900px){.two-columns{grid-template-columns:1fr}body{padding:12px}.header{flex-direction:column;text-align:center}}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .fade-in{animation:fadeIn 0.3s ease}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo"><h1><i class="fas fa-lock"></i> پنل مدیریت</h1><p>اصلاح دیتابیس کدهای ملی | مدیریت درخواست‌ها</p></div>
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-right"></i> بازگشت به صفحه اصلی</a>
    </div>
    
    <?php if ($message): ?>
    <div class="message message-<?php echo $message['type']; ?> fade-in"><i class="fas fa-<?php echo $message['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo $message['text']; ?></div>
    <?php endif; ?>
    
    <div class="two-columns">
        <div class="card">
            <div class="card-header"><i class="fas fa-edit"></i> اصلاح یا افزودن کد ملی (سه رقم اول)</div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group"><label>رمز عبور مدیریت</label><input type="password" name="admin_password" required placeholder="رمز عبور مدیریت"></div>
                    <div class="form-group"><label>سه رقم اول کد ملی</label><input type="text" name="code_prefix" id="code_prefix" maxlength="3" pattern="[0-9]{3}" required placeholder="مثال: 063"><small class="small-text" id="prefixPreview" style="font-size:0.65rem; color:#6c757d;"></small></div>
                    <div class="form-group"><label>استان</label><input type="text" name="province" id="province" required placeholder="نام استان"></div>
                    <div class="form-group"><label>شهر</label><input type="text" name="city" id="city" required placeholder="نام شهر"></div>
                    <div class="btn-group"><button type="submit" name="save" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> ذخیره / بروزرسانی</button><button type="submit" name="delete" class="btn btn-danger" style="flex:1;" onclick="return confirm('آیا از حذف این کد اطمینان دارید؟')"><i class="fas fa-trash"></i> حذف override</button></div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-list"></i> کدهای اصلاح شده (override) <span style="background:rgba(255,255,255,0.2); padding:2px 10px; border-radius:20px; font-size:0.7rem; margin-right:10px;"><?php echo count($overrides); ?> مورد</span></div>
            <div class="card-body">
                <div class="table-wrapper"><table class="data-table"><thead><tr><th>سه رقم اول</th><th>استان</th><th>شهر</th><th>تاریخ</th></tr></thead><tbody>
                <?php if (empty($overrides)): ?><tr><td colspan="4" style="text-align:center;">هیچ کدی اصلاح نشده است</td></tr>
                <?php else: foreach ($overrides as $prefix => $info): ?>
                <tr onclick="fillForm('<?php echo $prefix; ?>', '<?php echo addslashes($info['province']); ?>', '<?php echo addslashes($info['city']); ?>')" style="cursor:pointer;"><td><strong><?php echo $prefix; ?></strong></td><td><?php echo htmlspecialchars($info['province']); ?></td><td><?php echo htmlspecialchars($info['city']); ?></td><td><?php echo $info['updated_at'] ?? '-'; ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody></table></div>
                <div class="small-text" style="margin-top:10px; text-align:center;">💡 برای پر کردن فرم، روی هر ردیف کلیک کنید</div>
            </div>
        </div>
    </div>
    
    <!-- بخش درخواست‌های اصلاح شهر -->
    <div class="card" style="margin-top:24px;">
        <div class="card-header"><i class="fas fa-users"></i> درخواست‌های اصلاح شهر از کاربران</div>
        <div class="card-body">
            <div class="tab-buttons"><button class="tab-btn active" onclick="showTab('pending')">⏳ در انتظار تأیید (<?php echo count($pendingRequests); ?>)</button><button class="tab-btn" onclick="showTab('approved')">✅ تأیید شده (<?php echo count($approvedRequests); ?>)</button><button class="tab-btn" onclick="showTab('rejected')">❌ رد شده (<?php echo count($rejectedRequests); ?>)</button></div>
            <div id="pending-tab" class="tab-content active"><div class="table-wrapper"><table class="data-table"><thead><tr><th>کد ملی</th><th>استان فعلی</th><th>شهر فعلی</th><th>استان پیشنهادی</th><th>شهر پیشنهادی</th><th>تاریخ</th><th>عملیات</th></tr></thead><tbody>
            <?php if (empty($pendingRequests)): ?><tr><td colspan="7" style="text-align:center;">هیچ درخواستی در انتظار تأیید نیست</td></tr>
            <?php else: foreach ($pendingRequests as $req): ?>
            <tr><td><?php echo htmlspecialchars($req['code']); ?></td><td><?php echo htmlspecialchars($req['province']); ?></td><td><?php echo htmlspecialchars($req['city']); ?></td><td><strong style="color:#28a745;"><?php echo htmlspecialchars($req['suggested_province']); ?></strong></td><td><strong style="color:#28a745;"><?php echo htmlspecialchars($req['suggested_city']); ?></strong></td><td><?php echo htmlspecialchars($req['date']); ?></td><td><div class="btn-group"><button class="btn btn-info btn-sm" onclick="openEditModal('<?php echo $req['id']; ?>', '<?php echo htmlspecialchars($req['suggested_province']); ?>', '<?php echo htmlspecialchars($req['suggested_city']); ?>')">✏️ ویرایش</button><button class="btn btn-success btn-sm" onclick="processRequest('<?php echo $req['id']; ?>', 'approve')">✅ تأیید</button><button class="btn btn-danger btn-sm" onclick="processRequest('<?php echo $req['id']; ?>', 'reject')">❌ رد</button><button class="btn btn-warning btn-sm" onclick="processRequest('<?php echo $req['id']; ?>', 'delete')">🗑 حذف</button></div></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div></div>
            <div id="approved-tab" class="tab-content"><div class="table-wrapper"><table class="data-table"><thead><tr><th>کد ملی</th><th>استان قبلی</th><th>شهر قبلی</th><th>استان جدید</th><th>شهر جدید</th><th>تاریخ</th></tr></thead><tbody>
            <?php if (empty($approvedRequests)): ?><tr><td colspan="6" style="text-align:center;">هیچ درخواست تأیید شده‌ای وجود ندارد</td></tr>
            <?php else: foreach ($approvedRequests as $req): ?>
            <tr><td><?php echo htmlspecialchars($req['code']); ?></td><td><?php echo htmlspecialchars($req['province']); ?></td><td><?php echo htmlspecialchars($req['city']); ?></td><td><?php echo htmlspecialchars($req['suggested_province']); ?></td><td><?php echo htmlspecialchars($req['suggested_city']); ?></td><td><?php echo htmlspecialchars($req['date']); ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div></div>
            <div id="rejected-tab" class="tab-content"><div class="table-wrapper"><table class="data-table"><thead><tr><th>کد ملی</th><th>استان فعلی</th><th>شهر فعلی</th><th>استان پیشنهادی</th><th>شهر پیشنهادی</th><th>تاریخ</th></tr></thead><tbody>
            <?php if (empty($rejectedRequests)): ?><tr><td colspan="6" style="text-align:center;">هیچ درخواست رد شده‌ای وجود ندارد</td></tr>
            <?php else: foreach ($rejectedRequests as $req): ?>
            <tr><td><?php echo htmlspecialchars($req['code']); ?></td><td><?php echo htmlspecialchars($req['province']); ?></td><td><?php echo htmlspecialchars($req['city']); ?></td><td><?php echo htmlspecialchars($req['suggested_province']); ?></td><td><?php echo htmlspecialchars($req['suggested_city']); ?></td><td><?php echo htmlspecialchars($req['date']); ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div></div>
        </div>
    </div>
    
    <!-- بخش درخواست‌های اصلاح کد ملی (سه رقم) -->
    <div class="card" style="margin-top:24px;">
        <div class="card-header"><i class="fas fa-id-card"></i> درخواست‌های اضافه/اصلاح کد ملی (سه رقم اول)</div>
        <div class="card-body">
            <div class="tab-buttons"><button class="tab-btn active" onclick="showNationalTab('pending')">⏳ در انتظار تأیید (<?php echo count($pendingNationalRequests); ?>)</button><button class="tab-btn" onclick="showNationalTab('approved')">✅ تأیید شده (<?php echo count($approvedNationalRequests); ?>)</button><button class="tab-btn" onclick="showNationalTab('rejected')">❌ رد شده (<?php echo count($rejectedNationalRequests); ?>)</button></div>
            <div id="national-pending-tab" class="tab-content active"><div class="table-wrapper"><table class="data-table"><thead><tr><th>سه رقم</th><th>استان پیشنهادی</th><th>شهر پیشنهادی</th><th>تاریخ</th><th>عملیات</th></tr></thead><tbody>
            <?php if (empty($pendingNationalRequests)): ?><tr><td colspan="5" style="text-align:center;">هیچ درخواستی در انتظار تأیید نیست</td></tr>
            <?php else: foreach ($pendingNationalRequests as $req): ?>
            <tr><td><strong><?php echo htmlspecialchars($req['prefix']); ?></strong></td><td><?php echo htmlspecialchars($req['suggested_province']); ?></td><td><?php echo htmlspecialchars($req['suggested_city']); ?></td><td><?php echo htmlspecialchars($req['date']); ?></td><td><div class="btn-group"><button class="btn btn-info btn-sm" onclick="openNationalEditModal('<?php echo $req['id']; ?>', '<?php echo htmlspecialchars($req['suggested_province']); ?>', '<?php echo htmlspecialchars($req['suggested_city']); ?>')">✏️ ویرایش</button><button class="btn btn-success btn-sm" onclick="processNationalRequest('<?php echo $req['id']; ?>', 'approve')">✅ تأیید</button><button class="btn btn-danger btn-sm" onclick="processNationalRequest('<?php echo $req['id']; ?>', 'reject')">❌ رد</button><button class="btn btn-warning btn-sm" onclick="processNationalRequest('<?php echo $req['id']; ?>', 'delete')">🗑 حذف</button></div></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div></div>
            <div id="national-approved-tab" class="tab-content"><div class="table-wrapper"><table class="data-table"><thead><tr><th>سه رقم</th><th>استان</th><th>شهر</th><th>تاریخ</th></tr></thead><tbody>
            <?php if (empty($approvedNationalRequests)): ?><tr><td colspan="4" style="text-align:center;">هیچ درخواست تأیید شده‌ای وجود ندارد</td></tr>
            <?php else: foreach ($approvedNationalRequests as $req): ?>
            <tr><td><?php echo htmlspecialchars($req['prefix']); ?></td><td><?php echo htmlspecialchars($req['suggested_province']); ?></td><td><?php echo htmlspecialchars($req['suggested_city']); ?></td><td><?php echo htmlspecialchars($req['date']); ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div></div>
            <div id="national-rejected-tab" class="tab-content"><div class="table-wrapper"><table class="data-table"><thead><tr><th>سه رقم</th><th>استان پیشنهادی</th><th>شهر پیشنهادی</th><th>تاریخ</th></tr></thead><tbody>
            <?php if (empty($rejectedNationalRequests)): ?><tr><td colspan="4" style="text-align:center;">هیچ درخواست رد شده‌ای وجود ندارد</td></tr>
            <?php else: foreach ($rejectedNationalRequests as $req): ?>
            <tr><td><?php echo htmlspecialchars($req['prefix']); ?></td><td><?php echo htmlspecialchars($req['suggested_province']); ?></td><td><?php echo htmlspecialchars($req['suggested_city']); ?></td><td><?php echo htmlspecialchars($req['date']); ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table></div></div>
        </div>
    </div>
    
    <!-- بخش تاریخچه جستجوها -->
    <div class="card" style="margin-top:24px;">
        <div class="card-header">
            <i class="fas fa-history"></i> تاریخچه جستجوها (آخرین ۵۰ مورد)
            <span style="background:rgba(255,255,255,0.2); padding:2px 10px; border-radius:20px; font-size:0.7rem; margin-right:10px;">
                <?php echo count($recentLogs); ?> مورد
            </span>
        </div>
        <div class="card-body">
            <div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:15px; font-size:0.75rem;">
                <div style="background:#e9ecef; padding:5px 12px; border-radius:15px;">
                    <i class="fas fa-list"></i> کل: <?php echo $logStats['total']; ?>
                </div>
                <div style="background:#e9ecef; padding:5px 12px; border-radius:15px;">
                    <i class="fas fa-calendar-day"></i> امروز: <?php echo $logStats['today']; ?>
                </div>
                <?php foreach ($logStats['by_type'] as $type => $count): ?>
                <div style="background:#e9ecef; padding:5px 12px; border-radius:15px;">
                    <?php 
                    $typeLabels = ['national' => 'کد ملی', 'general' => 'عمومی', 'city' => 'شهر'];
                    echo $typeLabels[$type] ?? $type; ?>: <?php echo $count; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>IP</th>
                            <th>نوع</th>
                            <th>عبارت</th>
                            <th>تعداد نتایج</th>
                            <th>اطلاعات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">هیچ جستجویی ثبت نشده است</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?php echo htmlspecialchars($log['date']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip']); ?></td>
                            <td>
                                <?php
                                $typeLabels = [
                                    'national' => '<span class="badge" style="background:#667eea; color:white;">کد ملی</span>',
                                    'general' => '<span class="badge" style="background:#28a745; color:white;">عمومی</span>',
                                    'city' => '<span class="badge" style="background:#f39c12; color:white;">شهر</span>'
                                ];
                                echo $typeLabels[$log['type']] ?? $log['type'];
                                ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($log['keyword']); ?></strong></td>
                            <td><?php echo $log['result_count']; ?></td>
                            <td style="font-size:0.7rem;">
                                <?php if (isset($log['additional'])): ?>
                                    <?php if (isset($log['additional']['province'])): ?>
                                        <?php echo htmlspecialchars($log['additional']['province']); ?>
                                        - <?php echo htmlspecialchars($log['additional']['city']); ?>
                                        <?php if (isset($log['additional']['valid'])): ?>
                                            <span style="color:<?php echo $log['additional']['valid'] ? '#28a745' : '#dc3545'; ?>;">
                                                <?php echo $log['additional']['valid'] ? '✅' : '❌'; ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:10px; font-size:0.7rem; color:#6c757d; text-align:center;">
                <i class="fas fa-info-circle"></i> 
                فایل لاگ در مسیر <code>storage/logs/search.log</code> ذخیره می‌شود
            </div>
        </div>
    </div>
    
    <div class="footer"><p><i class="fas fa-shield-alt"></i> پنل مدیریت - فقط با رمز عبور معتبر قابل دسترسی است</p></div>
</div>

<div id="passwordModal" class="modal"><div class="modal-content"><h3><i class="fas fa-lock"></i> تأیید رمز عبور</h3><p style="font-size:0.7rem;color:#6c757d;margin-bottom:12px;">برای انجام این عملیات، رمز مدیریت را وارد کنید</p><input type="password" id="modalPassword" placeholder="رمز عبور مدیریت"><input type="hidden" id="modalRequestId"><input type="hidden" id="modalAction"><div class="btn-group"><button class="btn btn-primary" onclick="submitPassword()" style="flex:1;">تأیید</button><button class="btn btn-danger" onclick="closePasswordModal()" style="flex:1;">انصراف</button></div></div></div>

<div id="editModal" class="modal"><div class="modal-content"><h3><i class="fas fa-edit"></i> ویرایش درخواست</h3><div class="edit-input"><label>استان پیشنهادی</label><input type="text" id="editProvince" placeholder="استان"></div><div class="edit-input"><label>شهر پیشنهادی</label><input type="text" id="editCity" placeholder="شهر"></div><input type="hidden" id="editRequestId"><div class="btn-group"><button class="btn btn-primary" onclick="submitEdit()" style="flex:1;">ذخیره تغییرات</button><button class="btn btn-danger" onclick="closeEditModal()" style="flex:1;">انصراف</button></div></div></div>

<script>
const database = <?php echo json_encode($nationalCodesDatabase, JSON_UNESCAPED_UNICODE); ?>;
document.getElementById('code_prefix').addEventListener('input',function(e){let val=this.value.replace(/[^0-9]/g,'').slice(0,3);this.value=val;const preview=document.getElementById('prefixPreview');if(val.length===3&&database[val]){preview.innerHTML='<span style="color:#28a745;"><i class="fas fa-check-circle"></i> مقدار فعلی: '+database[val].province+' - '+database[val].city+'</span>';document.getElementById('province').value=database[val].province;document.getElementById('city').value=database[val].city;}else if(val.length===3){preview.innerHTML='<span style="color:#f39c12;"><i class="fas fa-plus-circle"></i> کد جدید - در دیتابیس موجود نیست</span>';document.getElementById('province').value='';document.getElementById('city').value='';}else{preview.innerHTML='';}});
function fillForm(prefix,province,city){document.getElementById('code_prefix').value=prefix;document.getElementById('province').value=province;document.getElementById('city').value=city;document.getElementById('code_prefix').dispatchEvent(new Event('input'));}
function showTab(tab){document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));document.querySelectorAll('.tab-btn').forEach(el=>el.classList.remove('active'));document.getElementById(`${tab}-tab`).classList.add('active');event.target.classList.add('active');}
function showNationalTab(tab){document.querySelectorAll('#national-pending-tab,#national-approved-tab,#national-rejected-tab').forEach(el=>el.classList.remove('active'));document.getElementById(`national-${tab}-tab`).classList.add('active');}
let currentRequestId=null,currentAction=null,currentNationalRequestId=null,currentNationalAction=null;
function processRequest(id,action){currentRequestId=id;currentAction=action;document.getElementById('modalPassword').value='';document.getElementById('passwordModal').style.display='flex';}
function processNationalRequest(id,action){currentNationalRequestId=id;currentNationalAction=action;document.getElementById('modalPassword').value='';document.getElementById('passwordModal').style.display='flex';}
function closePasswordModal(){document.getElementById('passwordModal').style.display='none';currentRequestId=null;currentAction=null;currentNationalRequestId=null;currentNationalAction=null;}
function submitPassword(){const password=document.getElementById('modalPassword').value;if(!password){alert('⚠️ لطفاً رمز عبور را وارد کنید');return;}if(currentNationalRequestId){window.location.href=`?national_action=${currentNationalAction}&id=${currentNationalRequestId}&password=${encodeURIComponent(password)}`;}else{window.location.href=`?action=${currentAction}&id=${currentRequestId}&password=${encodeURIComponent(password)}`;}}
function openEditModal(id,province,city){editRequestId=id;document.getElementById('editProvince').value=province;document.getElementById('editCity').value=city;document.getElementById('editModal').style.display='flex';}
function closeEditModal(){document.getElementById('editModal').style.display='none';}
function submitEdit(){const password=prompt('برای ویرایش درخواست، رمز مدیریت را وارد کنید:');if(!password)return;const newProvince=document.getElementById('editProvince').value;const newCity=document.getElementById('editCity').value;window.location.href=`?action=edit&id=${editRequestId}&edit_province=${encodeURIComponent(newProvince)}&edit_city=${encodeURIComponent(newCity)}&password=${encodeURIComponent(password)}`;}
function openNationalEditModal(id,province,city){const newProvince=prompt('استان صحیح را وارد کنید:',province);if(!newProvince)return;const newCity=prompt('شهر صحیح را وارد کنید:',city);if(!newCity)return;const password=prompt('رمز مدیریت را وارد کنید:');if(!password)return;window.location.href=`?national_action=edit&id=${id}&edit_province=${encodeURIComponent(newProvince)}&edit_city=${encodeURIComponent(newCity)}&password=${encodeURIComponent(password)}`;}
window.onclick=function(e){const modal=document.getElementById('passwordModal');const edit=document.getElementById('editModal');if(e.target===modal)closePasswordModal();if(e.target===edit)closeEditModal();};
</script>
</body>
</html>