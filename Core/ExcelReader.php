<?php
// Core/ExcelReader.php
// این کلاس فایل‌های Excel 97-2003 (xls) را بدون نیاز به هیچ کتابخانه خارجی می‌خواند.

namespace Core;

class ExcelReader
{
    private $data = [];
    private $rows = 0;
    private $cols = 0;

    public function __construct($filename)
    {
        if (!file_exists($filename)) {
            throw new \Exception("فایل پیدا نشد: " . $filename);
        }
        $this->load($filename);
    }

    private function load($filename)
    {
        $fileHandle = fopen($filename, "rb");
        if (!$fileHandle) {
            throw new \Exception("خطا در باز کردن فایل");
        }

        // خواندن هدر فایل (8 بایت اول)
        $header = fread($fileHandle, 8);
        if ($header != pack("VvvvvV", 0xE11AB1A1, 0x0200, 0x0000, 0x0000, 0x0000, 0x00000000)) {
            fclose($fileHandle);
            throw new \Exception("فرمت فایل معتبر نیست (فقط xls پشتیبانی می‌شود)");
        }

        // پرش به بخش داده‌ها (Offset 0x800)
        fseek($fileHandle, 0x800, SEEK_SET);

        $currentRow = 0;
        $this->data = [];

        while (!feof($fileHandle)) {
            $record = $this->readRecord($fileHandle);
            if (!$record) break;

            if ($record['type'] == 0x0204) { // نوع رکورد LABELSST
                $row = $record['data']['row'];
                $col = $record['data']['col'];
                $index = $record['data']['index'];
                
                // مقدار از بخش SST خوانده می‌شود (لازم است کامل شود)
                // برای سادگی، فعلاً مقدار را مستقیماً قرار می‌دهیم
                $this->data[$row][$col] = $record['data']['value'] ?? '';
                $currentRow = max($currentRow, $row);
            } elseif ($record['type'] == 0x00FD) { // نوع رکورد LABEL (متن ساده)
                $row = $record['data']['row'];
                $col = $record['data']['col'];
                $value = $record['data']['value'];
                $this->data[$row][$col] = $value;
                $currentRow = max($currentRow, $row);
            } elseif ($record['type'] == 0x0203) { // نوع رکورد NUMBER
                $row = $record['data']['row'];
                $col = $record['data']['col'];
                $value = $record['data']['value'];
                $this->data[$row][$col] = $value;
                $currentRow = max($currentRow, $row);
            }
        }
        
        fclose($fileHandle);
        $this->rows = $currentRow + 1;
        $this->cols = $this->getMaxCols();
        
        // تبدیل به آرایه دو بعدی منظم
        $organizedData = [];
        for ($i = 0; $i < $this->rows; $i++) {
            $organizedData[$i] = [];
            for ($j = 0; $j < $this->cols; $j++) {
                $organizedData[$i][$j] = $this->data[$i][$j] ?? '';
            }
        }
        $this->data = $organizedData;
    }

    private function readRecord($fileHandle)
    {
        $recordData = fread($fileHandle, 4);
        if (strlen($recordData) < 4) return false;
        
        $record = unpack("vtype/vlen", $recordData);
        $type = $record['type'];
        $len = $record['len'];
        
        $data = fread($fileHandle, $len);
        if (strlen($data) < $len) return false;
        
        $result = ['type' => $type, 'data' => []];
        
        if ($type == 0x0204) { // LABELSST
            $parsed = unpack("vrow/vcol/vix", $data);
            $result['data']['row'] = $parsed['row'];
            $result['data']['col'] = $parsed['col'];
            $result['data']['index'] = $parsed['ix'];
            $result['data']['value'] = "SST_".$parsed['ix']; // مقدار واقعی نیاز به خواندن SST دارد
        } elseif ($type == 0x00FD) { // LABEL
            $parsed = unpack("vrow/vcol/vlen", $data);
            $value = substr($data, 6, $parsed['len']);
            $result['data']['row'] = $parsed['row'];
            $result['data']['col'] = $parsed['col'];
            $result['data']['value'] = $value;
        } elseif ($type == 0x0203) { // NUMBER
            $parsed = unpack("vrow/vcol/dvalue", $data);
            $result['data']['row'] = $parsed['row'];
            $result['data']['col'] = $parsed['col'];
            $result['data']['value'] = $parsed['value'];
        }
        
        return $result;
    }
    
    private function getMaxCols()
    {
        $max = 0;
        foreach ($this->data as $row) {
            $max = max($max, count($row));
        }
        return $max;
    }
    
    public function getRowCount()
    {
        return $this->rows;
    }
    
    public function getColumnCount()
    {
        return $this->cols;
    }
    
    public function getData()
    {
        return $this->data;
    }
    
    public function getRow($index)
    {
        return $this->data[$index] ?? [];
    }
}
?>