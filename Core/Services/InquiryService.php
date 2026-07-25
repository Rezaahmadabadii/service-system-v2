<?php
// Core/Services/InquiryService.php

namespace Core\Services;

use Shuchkin\SimpleXLSX;

class InquiryService
{
    private $basePath;

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * پیدا کردن پوشه سال
     */
    private function findYearFolder($year)
    {
        if (!is_dir($this->basePath)) {
            return null;
        }

        $folders = scandir($this->basePath);
        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') continue;
            $fullPath = $this->basePath . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($fullPath)) continue;
            
            // جستجوی دقیق‌تر برای پوشه سال
            if (preg_match('/(\d{4})/', $folder, $matches)) {
                if ($matches[1] == $year) {
                    return $fullPath;
                }
            }
            // اگر نام پوشه دقیقاً سال باشد
            if ($folder == $year) {
                return $fullPath;
            }
        }
        return null;
    }

    /**
     * پیدا کردن فایل مربوط به سال و ماه مشخص
     */
    public function findFile($year, $month)
    {
        $yearFolder = $this->findYearFolder($year);
        if (!$yearFolder) {
            return null;
        }

        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $files = scandir($yearFolder);

        foreach ($files as $file) {
            if (!$this->isValidExcelFile($file)) continue;
            
            $info = $this->extractYearMonth($file);
            if ($info && $info['year'] == $year && $info['month'] == $month) {
                $fullPath = $yearFolder . DIRECTORY_SEPARATOR . $file;
                if (file_exists($fullPath) || is_file($fullPath)) {
                    return $fullPath;
                }
                return $fullPath;
            }
        }

        return null;
    }

    /**
     * دریافت لیست سال‌های موجود
     */
    public function getAvailableYears()
    {
        $years = [];
        if (!is_dir($this->basePath)) {
            return $years;
        }

        $folders = scandir($this->basePath);
        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') continue;
            $fullPath = $this->basePath . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($fullPath)) continue;
            
            // استخراج سال از نام پوشه
            if (preg_match('/(\d{4})/', $folder, $matches)) {
                $year = $matches[1];
                // بررسی کنید که آیا در این پوشه حداقل یک فایل وجود دارد
                $files = scandir($fullPath);
                $hasFile = false;
                foreach ($files as $file) {
                    if ($this->isValidExcelFile($file)) {
                        $hasFile = true;
                        break;
                    }
                }
                if ($hasFile) {
                    $years[] = $year;
                }
            }
        }

        // مرتب‌سازی نزولی (جدیدترین سال اول)
        rsort($years);
        return $years;
    }

    /**
     * دریافت لیست ماه‌های موجود برای یک سال
     */
    public function getAvailableMonths($year)
    {
        $months = [];
        $yearFolder = $this->findYearFolder($year);
        if (!$yearFolder) return $months;

        $files = scandir($yearFolder);
        $monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        
        foreach ($files as $file) {
            if (!$this->isValidExcelFile($file)) continue;
            
            // تلاش برای استخراج ماه از نام فایل
            $monthFound = null;
            
            // الگوی 1: عدد ماه (01 تا 12)
            if (preg_match('/^(\d{2})\.(xlsx?|csv)$/i', $file, $matches)) {
                $monthFound = (int)$matches[1];
            }
            // الگوی 2: عدد ماه بدون صفر (1 تا 12)
            elseif (preg_match('/^(\d{1,2})\.(xlsx?|csv)$/i', $file, $matches)) {
                $monthFound = (int)$matches[1];
            }
            // الگوی 3: نام ماه به فارسی
            else {
                foreach ($monthNames as $index => $name) {
                    if (strpos($file, $name) !== false) {
                        $monthFound = $index + 1;
                        break;
                    }
                }
            }
            
            // الگوی 4: برج سال XXXX - YY
            if (!$monthFound) {
                $info = $this->extractYearMonth($file);
                if ($info && $info['year'] == $year) {
                    $monthFound = (int)$info['month'];
                }
            }
            
            if ($monthFound && $monthFound >= 1 && $monthFound <= 12) {
                $months[] = $monthFound;
            }
        }

        // حذف تکراری‌ها و مرتب‌سازی
        $months = array_unique($months);
        sort($months);
        return $months;
    }

    /**
     * جستجوی نام در فایل اکسل با SimpleXLSX
     */
    public function searchInFile($filePath, $searchName, $specificDay = null)
    {
        if (!file_exists($filePath) && !is_file($filePath)) {
            return [];
        }

        // کپی به پوشه موقت
        $tempDir = sys_get_temp_dir() . '/excel_search_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        $tempFile = $tempDir . '/' . basename($filePath);
        copy($filePath, $tempFile);

        require_once __DIR__ . '/../Helpers/simplexlsx.php';

        $results = [];
        $normalizedSearch = $this->normalizeText($searchName);

        if ($xlsx = SimpleXLSX::parse($tempFile)) {
            $sheetNames = $xlsx->sheetNames();

            foreach ($sheetNames as $sheetIndex => $dayName) {
                if (!is_numeric($dayName)) continue;
                $dayNumber = (int)$dayName;

                if ($specificDay !== null && $dayNumber != $specificDay) continue;

                $rows = $xlsx->rows($sheetIndex);

                // شروع از ردیف 2 (رد کردن هدرها)
                for ($rowIndex = 2; $rowIndex < count($rows); $rowIndex++) {
                    $row = $rows[$rowIndex];
                    
                    if (empty($row)) continue;

                    $name = isset($row[2]) ? trim($row[2]) : '';
                    $code = isset($row[1]) ? trim($row[1]) : '';
                    $amount = isset($row[4]) ? trim($row[4]) : '';

                    if (empty($name)) continue;
                    if ($name === 'نام خانوادگی ونام') continue;
                    if ($name === 'حواله روزانه') continue;
                    if ($name === 'ردیف') continue;

                    $normalizedName = $this->normalizeText($name);

                    if (strpos($normalizedName, $normalizedSearch) !== false) {
                        $results[] = [
                            'day' => $dayNumber,
                            'code' => $code,
                            'name' => $name,
                            'amount' => $this->formatAmount($amount),
                            'similarity' => 100
                        ];
                    }
                }
            }
        }

        // پاکسازی پوشه موقت
        $files = glob($tempDir . '/*');
        foreach ($files as $f) {
            if (is_file($f)) unlink($f);
        }
        @rmdir($tempDir);

        return $results;
    }

    /**
     * دریافت کل واریزی‌های یک روز خاص
     */
    public function getAllTransfersForDate($year, $month, $day)
    {
        $filePath = $this->findFile($year, $month);
        if (!$filePath) {
            return [];
        }

        if (!file_exists($filePath) && !is_file($filePath)) {
            return [];
        }

        $tempDir = sys_get_temp_dir() . '/excel_search_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        $tempFile = $tempDir . '/' . basename($filePath);
        copy($filePath, $tempFile);

        require_once __DIR__ . '/../Helpers/simplexlsx.php';

        $results = [];

        if ($xlsx = SimpleXLSX::parse($tempFile)) {
            $sheetNames = $xlsx->sheetNames();

            foreach ($sheetNames as $sheetIndex => $dayName) {
                if (!is_numeric($dayName)) continue;
                if ((int)$dayName != $day) continue;

                $rows = $xlsx->rows($sheetIndex);

                // شروع از ردیف 2 (رد کردن هدرها)
                for ($rowIndex = 2; $rowIndex < count($rows); $rowIndex++) {
                    $row = $rows[$rowIndex];
                    
                    if (empty($row)) continue;

                    $name = isset($row[2]) ? trim($row[2]) : '';
                    $code = isset($row[1]) ? trim($row[1]) : '';
                    $amount = isset($row[4]) ? trim($row[4]) : '';

                    if (empty($name)) continue;
                    if ($name === 'نام خانوادگی ونام') continue;
                    if ($name === 'حواله روزانه') continue;
                    if ($name === 'ردیف') continue;

                    $results[] = [
                        'day' => $day,
                        'code' => $code,
                        'name' => $name,
                        'amount' => $this->formatAmount($amount)
                    ];
                }
            }
        }

        $files = glob($tempDir . '/*');
        foreach ($files as $f) {
            if (is_file($f)) unlink($f);
        }
        @rmdir($tempDir);

        return $results;
    }

    /**
     * بررسی معتبر بودن فایل اکسل
     */
    private function isValidExcelFile($filename)
    {
        if (empty($filename)) return false;
        if ($filename === '.' || $filename === '..') return false;
        if (strpos($filename, '~$') === 0) return false;
        if (!preg_match('/\.(xlsx?|csv)$/i', $filename)) return false;
        return true;
    }

    /**
     * استخراج سال و ماه از نام فایل
     */
    private function extractYearMonth($filename)
    {
        // الگوی: برج سال XXXX - YY
        $pattern = '/برج\s*سال\s*(\d{4})\s*[-_]\s*(\d{1,2})/';
        if (preg_match($pattern, $filename, $matches)) {
            return [
                'year' => $matches[1],
                'month' => str_pad($matches[2], 2, '0', STR_PAD_LEFT)
            ];
        }
        
        // الگوی: سالXXXX-ماهYY
        $pattern2 = '/سال(\d{4})[-_]ماه(\d{1,2})/';
        if (preg_match($pattern2, $filename, $matches)) {
            return [
                'year' => $matches[1],
                'month' => str_pad($matches[2], 2, '0', STR_PAD_LEFT)
            ];
        }
        
        // الگوی: YYYY-MM
        $pattern3 = '/(\d{4})[-_](\d{1,2})/';
        if (preg_match($pattern3, $filename, $matches)) {
            return [
                'year' => $matches[1],
                'month' => str_pad($matches[2], 2, '0', STR_PAD_LEFT)
            ];
        }
        
        return null;
    }

    /**
     * نرمالایز کردن متن
     */
    private function normalizeText($text)
    {
        if (empty($text)) return '';
        $text = trim($text);
        $text = str_replace(['ي', 'ك', 'ى', 'ة', 'ئ'], ['ی', 'ک', 'ی', 'ه', 'ی'], $text);
        $text = str_replace(['أ', 'إ', 'آ'], ['ا', 'ا', 'ا'], $text);
        $text = str_replace([' ', '‌'], '', $text);
        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * فرمت کردن مبلغ
     */
    private function formatAmount($amount)
    {
        if (is_numeric($amount)) {
            return number_format((int)$amount);
        }
        if (is_string($amount)) {
            $clean = preg_replace('/[^0-9]/', '', $amount);
            if (!empty($clean)) {
                return number_format((int)$clean);
            }
        }
        return $amount ?: 'نامشخص';
    }

    /**
     * تبدیل تاریخ میلادی به شمسی
     */
    public function getJalaliToday()
    {
        return $this->gregorianToJalali(date('Y'), date('n'), date('j'));
    }

    public function getJalaliYesterday()
    {
        $yesterday = time() - 86400;
        return $this->gregorianToJalali(
            date('Y', $yesterday),
            date('n', $yesterday),
            date('j', $yesterday)
        );
    }

    private function gregorianToJalali($gy, $gm, $gd)
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gy + 1;
        if ($gm <= 2) $gy2 = $gy;
        
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
}