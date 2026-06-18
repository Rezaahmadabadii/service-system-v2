<?php
namespace Core\Services;

class ExcelSearchService
{
    private $cachePath;
    private $cityCodePath;
    private $maxResults;
    private $priorities;
    private $allData;
    private $headers;
    private $cityData;

    public function __construct($filePath, $cachePath, $maxResults = 5, $priorities = [], $cityCodePath = null)
    {
        $this->cachePath = $cachePath;
        $this->maxResults = $maxResults;
        $this->priorities = $priorities;
        $this->cityCodePath = $cityCodePath;
        
        if (empty($this->priorities)) {
            $this->priorities = ['عنوان' => 100, 'اختصار' => 80, 'عنوان لاتین' => 60, 'توضیحات' => 40];
        }
        
        $this->loadData();
        $this->loadCityData();
    }
    
    private function loadData()
    {
        if (file_exists($this->cachePath)) {
            $cached = include $this->cachePath;
            $this->headers = $cached['headers'];
            $this->allData = $cached['data'];
        } else {
            $this->allData = [];
            $this->headers = [];
        }
    }
    
    private function loadCityData()
    {
        $this->cityData = [];
        
        if (!$this->cityCodePath || !file_exists($this->cityCodePath)) {
            return;
        }
        
        $baseDir = dirname(__DIR__, 2);
        require_once $baseDir . '/vendor/SpreadsheetReader.php';
        
        $readerPath = $baseDir . '/vendor/php-excel-reader';
        set_include_path(get_include_path() . PATH_SEPARATOR . $readerPath);
        
        $excelReaderFile = $readerPath . '/excel_reader2.php';
        if (file_exists($excelReaderFile)) {
            require_once $excelReaderFile;
        }
        
        try {
            $reader = new \SpreadsheetReader($this->cityCodePath);
            $firstRow = true;
            
            foreach ($reader as $row) {
                $cleanRow = [];
                foreach ($row as $cell) {
                    $cell = preg_replace('/<[^>]*>/', '', $cell);
                    $cell = str_replace(['<Cell', '</Cell>', '<Data', '</Data>'], '', $cell);
                    $cleanRow[] = trim($cell);
                }
                
                if ($firstRow) {
                    $firstRow = false;
                    continue;
                }
                
                if (count($cleanRow) >= 2) {
                    // ستون A = نام شهر (کلید جستجو)
                    // ستون B = مقداری که باید برگردانده شود
                    $cityName = $cleanRow[0];
                    $cityValue = $cleanRow[1];
                    
                    if (!empty($cityName)) {
                        if (!isset($this->cityData[$cityName])) {
                            $this->cityData[$cityName] = [];
                        }
                        $this->cityData[$cityName][] = $cityValue;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("City codes read error: " . $e->getMessage());
        }
    }
    
    private function normalize($text)
    {
        if (empty($text)) return '';
        $text = trim($text);
        
        // حذف تمام فاصله‌ها و نیم‌فاصله‌ها
        $text = str_replace([' ', '‌', "\t", "\n", "\r"], '', $text);
        
        $persianMap = [
            'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی',
            'ك' => 'ک', 'ة' => 'ه',
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',
            'ؤ' => 'و',
        ];
        $text = str_replace(array_keys($persianMap), array_values($persianMap), $text);
        
        return mb_strtolower($text, 'UTF-8');
    }
    
    // جستجوی گزینه‌های شهر (برای لیست کشویی)
    public function searchCityOptions($cityName)
    {
        $options = [];
        $normalizedSearch = $this->normalize($cityName);
        
        foreach ($this->cityData as $city => $values) {
            $normalizedCity = $this->normalize($city);
            if (strpos($normalizedCity, $normalizedSearch) !== false) {
                // برای هر مقدار در ستون B، یک گزینه جداگانه اضافه کن
                foreach ($values as $value) {
                    $options[] = $city . '|' . $value;
                }
            }
        }
        
        return array_values(array_unique($options));
    }
    
    // دریافت کد شهر انتخاب شده
    public function getCityCode($selectedItem)
    {
        // selectedItem فرمت: "شهر|مقدار"
        $parts = explode('|', $selectedItem, 2);
        if (count($parts) == 2) {
            return ['found' => true, 'code' => $parts[1]];
        }
        
        // حالت قبلی برای سازگاری
        if (isset($this->cityData[$selectedItem]) && !empty($this->cityData[$selectedItem])) {
            return ['found' => true, 'code' => $this->cityData[$selectedItem][0]];
        }
        return ['found' => false, 'code' => ''];
    }
    
    // جستجوی کد ملی در فایل اصلی (GroupLedger.xls)
    public function searchNationalCodeInExcel($nationalCode)
    {
        if (empty($this->allData)) return null;
        
        $normalizedCode = $this->normalize($nationalCode);
        $results = [];
        
        foreach ($this->allData as $rowIndex => $row) {
            $found = false;
            $foundInColumn = null;
            $foundValue = null;
            
            foreach ($this->headers as $header) {
                $cell = isset($row[$header]) ? $row[$header] : '';
                $normalizedCell = $this->normalize($cell);
                
                if ($normalizedCell === $normalizedCode) {
                    $found = true;
                    $foundInColumn = $header;
                    $foundValue = $cell;
                    break;
                }
                
                if (preg_match('/\d{10}/', $normalizedCell, $matches)) {
                    if ($matches[0] === $normalizedCode) {
                        $found = true;
                        $foundInColumn = $header;
                        $foundValue = $cell;
                        break;
                    }
                }
            }
            
            if ($found) {
                $displayRow = [];
                foreach ($row as $key => $value) {
                    $displayRow[$key] = (empty($value) || trim($value) === '') ? '-' : $value;
                }
                
                $results[] = [
                    'row_number' => $rowIndex + 2,
                    'found_in_column' => $foundInColumn,
                    'found_value' => $foundValue,
                    'data' => $displayRow
                ];
            }
        }
        
        if (count($results) > 0) {
            return ['found' => true, 'count' => count($results), 'results' => $results];
        }
        return null;
    }
    
    // جستجوی عمومی در فایل اصلی (با پشتیبانی از صفحه‌بندی)
    public function search($keyword, $limit = null)
    {
        if (empty($this->allData)) {
            return ['success' => false, 'error' => 'داده‌ای بارگذاری نشده است', 'results' => []];
        }
        
        $normalizedKeyword = $this->normalize($keyword);
        $results = [];
        
        foreach ($this->allData as $rowIndex => $row) {
            $score = 0;
            $displayRow = [];
            
            foreach ($this->headers as $header) {
                $cell = isset($row[$header]) ? $row[$header] : '';
                $normalizedCell = $this->normalize($cell);
                $displayRow[$header] = (empty($cell) || trim($cell) === '') ? '-' : $cell;
                
                if (!empty($normalizedCell) && strpos($normalizedCell, $normalizedKeyword) !== false) {
                    $priority = $this->priorities[$header] ?? 10;
                    $score = max($score, $priority);
                    if ($normalizedCell === $normalizedKeyword) $score += 50;
                }
            }
            
            if ($score > 0) {
                $results[] = [
                    'score' => $score,
                    'row_number' => $rowIndex + 2,
                    'data' => $displayRow
                ];
            }
        }
        
        usort($results, fn($a, $b) => $b['score'] - $a['score']);
        
        // اگر limit نداشت، همه نتایج را برگردان (برای صفحه‌بندی)
        if ($limit === null) {
            return ['success' => true, 'results' => $results, 'count' => count($results)];
        }
        
        $results = array_slice($results, 0, $limit);
        
        return ['success' => true, 'results' => $results, 'count' => count($results)];
    }
}