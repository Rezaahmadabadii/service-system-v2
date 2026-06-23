<?php
// Core/Services/InquiryService.php

namespace Core\Services;

class InquiryService
{
    private $basePath;
    private $filePattern;

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        $this->filePattern = '/(\d{4})\s*[-_]\s*(\d{1,2})/';
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

        $files = scandir($this->basePath);
        foreach ($files as $file) {
            if ($this->isValidExcelFile($file)) {
                $info = $this->extractYearMonth($file);
                if ($info && !in_array($info['year'], $years)) {
                    $years[] = $info['year'];
                }
            }
        }

        sort($years, SORT_DESC);
        return $years;
    }

    /**
     * دریافت لیست ماه‌های موجود برای یک سال خاص
     */
    public function getAvailableMonths($year)
    {
        $months = [];
        if (!is_dir($this->basePath)) {
            return $months;
        }

        $files = scandir($this->basePath);
        foreach ($files as $file) {
            if ($this->isValidExcelFile($file)) {
                $info = $this->extractYearMonth($file);
                if ($info && $info['year'] == $year) {
                    $months[] = (int)$info['month'];
                }
            }
        }

        sort($months);
        return array_unique($months);
    }

    /**
     * بررسی معتبر بودن فایل اکسل
     */
    private function isValidExcelFile($filename)
    {
        if (empty($filename)) return false;
        if ($filename === '.' || $filename === '..') return false;
        if (strpos($filename, '~$') === 0) return false;
        if (!preg_match('/\.xlsx?$/i', $filename)) return false;
        
        return true;
    }

    /**
     * استخراج سال و ماه از نام فایل
     */
    private function extractYearMonth($filename)
    {
        if (preg_match($this->filePattern, $filename, $matches)) {
            return [
                'year' => $matches[1],
                'month' => str_pad($matches[2], 2, '0', STR_PAD_LEFT)
            ];
        }
        return null;
    }

    /**
     * پیدا کردن فایل مربوط به سال و ماه مشخص
     */
    public function findFile($year, $month)
    {
        if (!is_dir($this->basePath)) {
            return null;
        }

        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $files = scandir($this->basePath);

        foreach ($files as $file) {
            if (!$this->isValidExcelFile($file)) continue;
            
            $info = $this->extractYearMonth($file);
            if ($info && $info['year'] == $year && $info['month'] == $month) {
                return $this->basePath . $file;
            }
        }

        return null;
    }

    /**
     * جستجوی نام در فایل اکسل
     */
    public function searchInFile($filePath, $searchName, $specificDay = null)
    {
        if (!file_exists($filePath)) {
            return [];
        }

        require_once __DIR__ . '/../../vendor/SpreadsheetReader.php';

        $readerPath = __DIR__ . '/../../vendor/php-excel-reader';
        set_include_path(get_include_path() . PATH_SEPARATOR . $readerPath);

        $excelReaderFile = $readerPath . '/excel_reader2.php';
        if (file_exists($excelReaderFile)) {
            require_once $excelReaderFile;
        }

        $results = [];
        $normalizedSearch = $this->normalizeText($searchName);

        try {
            $reader = new \SpreadsheetReader($filePath);
            
            foreach ($reader as $sheetIndex => $sheetData) {
                // اگر روز خاصی مشخص شده، فقط آن روز را بررسی کن
                if ($specificDay !== null && ($sheetIndex + 1) != $specificDay) {
                    continue;
                }

                foreach ($sheetData as $row) {
                    if (empty($row) || count($row) < 5) continue;
                    
                    $name = isset($row[2]) ? trim($row[2]) : '';
                    $amount = isset($row[4]) ? trim($row[4]) : '';
                    
                    if (empty($name)) continue;
                    
                    $normalizedName = $this->normalizeText($name);
                    
                    // بررسی تطابق دقیق یا شباهت بالا
                    if (strpos($normalizedName, $normalizedSearch) !== false) {
                        $similarity = 100;
                    } else {
                        similar_text($normalizedName, $normalizedSearch, $similarity);
                    }
                    
                    if ($similarity >= 80) {
                        $results[] = [
                            'day' => $sheetIndex + 1,
                            'name' => $name,
                            'amount' => $this->formatAmount($amount),
                            'raw_amount' => $amount,
                            'similarity' => round($similarity)
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("InquiryService error: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * دریافت کل واریزی‌های یک روز خاص (امروز یا دیروز)
     */
    public function getAllTransfersForDate($year, $month, $day)
    {
        $filePath = $this->findFile($year, $month);
        if (!$filePath) {
            return [];
        }

        if (!file_exists($filePath)) {
            return [];
        }

        require_once __DIR__ . '/../../vendor/SpreadsheetReader.php';

        $readerPath = __DIR__ . '/../../vendor/php-excel-reader';
        set_include_path(get_include_path() . PATH_SEPARATOR . $readerPath);

        $excelReaderFile = $readerPath . '/excel_reader2.php';
        if (file_exists($excelReaderFile)) {
            require_once $excelReaderFile;
        }

        $results = [];

        try {
            $reader = new \SpreadsheetReader($filePath);
            
            foreach ($reader as $sheetIndex => $sheetData) {
                // فقط روز مورد نظر
                if (($sheetIndex + 1) != $day) continue;

                foreach ($sheetData as $row) {
                    if (empty($row) || count($row) < 5) continue;
                    
                    $name = isset($row[2]) ? trim($row[2]) : '';
                    $amount = isset($row[4]) ? trim($row[4]) : '';
                    
                    if (empty($name)) continue;
                    
                    $results[] = [
                        'day' => $sheetIndex + 1,
                        'name' => $name,
                        'amount' => $this->formatAmount($amount),
                        'raw_amount' => $amount
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log("InquiryService getAllTransfers error: " . $e->getMessage());
        }

        return $results;
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
        $text = preg_replace('/\s+/', ' ', $text);
        
        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * فرمت کردن مبلغ
     */
    private function formatAmount($amount)
    {
        if (is_numeric($amount)) {
            return number_format($amount);
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
        return $this->gregorianToJalali(
            date('Y'), 
            date('n'), 
            date('j')
        );
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