<?php
// create_cache.php - نسخه برای CSV

$config = require_once __DIR__ . '/config/app.php';

$csvFile = $config['excel_file_path'];
$cacheFile = $config['excel_cache_path'];

echo "📁 فایل CSV: " . $csvFile . "<br>";

if (!file_exists($csvFile)) {
    die("❌ فایل CSV پیدا نشد");
}

$cacheDir = dirname($cacheFile);
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// خواندن CSV با fgetcsv
$data = [];
$handle = fopen($csvFile, 'r');
if ($handle === false) {
    die("❌ خطا در باز کردن فایل");
}

// خواندن هدرها
$headers = fgetcsv($handle, 0, ',');
if ($headers === false) {
    die("❌ خطا در خواندن هدرها");
}
$headers = array_map('trim', $headers);

echo "✅ هدرها: " . implode(' | ', $headers) . "<br><br>";

// خواندن داده‌ها
$rowCount = 0;
while (($row = fgetcsv($handle, 0, ',')) !== false) {
    $rowData = [];
    foreach ($headers as $idx => $header) {
        $value = isset($row[$idx]) ? trim($row[$idx]) : '';
        $rowData[$header] = $value;
    }
    $data[] = $rowData;
    $rowCount++;
    
    if ($rowCount % 10000 == 0) {
        echo "📊 $rowCount ردیف خوانده شد...<br>";
    }
}
fclose($handle);

echo "📊 کل ردیف‌ها: " . $rowCount . "<br>";

// ذخیره کش
$cacheContent = "<?php\n\n";
$cacheContent .= "// کش داده‌های CSV\n";
$cacheContent .= "// تاریخ ایجاد: " . date('Y-m-d H:i:s') . "\n";
$cacheContent .= "// تعداد رکوردها: " . $rowCount . "\n\n";
$cacheContent .= "return [\n";
$cacheContent .= "    'headers' => " . var_export($headers, true) . ",\n";
$cacheContent .= "    'data' => " . var_export($data, true) . "\n";
$cacheContent .= "];\n";

file_put_contents($cacheFile, $cacheContent);

echo "<br>✅ کش با موفقیت ساخته شد!<br>";
echo "📁 مسیر کش: " . $cacheFile . "<br>";
echo "📊 تعداد رکوردها: " . $rowCount . "<br>";
echo "<br><a href='index.php'>رفتن به صفحه اصلی</a>";
?>