<?php
// Core/Services/NationalCodeService.php

namespace Core\Services;

class NationalCodeService
{
    private $database;
    private $overridePath;

    public function __construct($nationalCodesDatabase, $overridePath = null)
    {
        $this->database = $nationalCodesDatabase;
        $this->overridePath = $overridePath;
    }

    /**
     * اعتبارسنجی کد ملی (الگوریتم استاندارد ایران)
     */
    public function validate($nationalCode)
    {
        // حذف کاراکترهای غیرعددی
        $code = preg_replace('/[^0-9]/', '', $nationalCode);

        if (strlen($code) != 10) {
            return ['valid' => false, 'message' => 'کد ملی باید ۱۰ رقم باشد'];
        }

        // بررسی اعداد تکراری (مثل 1111111111)
        if ($code === str_repeat($code[0], 10)) {
            return ['valid' => false, 'message' => 'کد ملی معتبر نیست (تکراری)'];
        }

        $digits = str_split($code);
        $checkDigit = (int)$digits[9];
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$digits[$i] * (10 - $i);
        }

        $remainder = $sum % 11;

        $isValid = ($remainder < 2 && $checkDigit == $remainder) || ($checkDigit == (11 - $remainder));

        return [
            'valid' => $isValid,
            'message' => $isValid ? 'کد ملی معتبر است' : 'کد ملی معتبر نیست',
            'code' => $code
        ];
    }

    /**
     * دریافت اطلاعات استان و شهر از روی ۳ رقم اول کد ملی
     */
    public function getLocationInfo($nationalCode)
    {
        $code = preg_replace('/[^0-9]/', '', $nationalCode);
        
        if (strlen($code) < 3) {
            return ['province' => '-', 'city' => '-', 'source' => 'none'];
        }

        $prefix = substr($code, 0, 3);

        // ابتدا بررسی فایل override (تغییرات دستی)
        $info = $this->getFromOverride($prefix);
        if ($info) {
            return $info;
        }

        // سپس دیتابیس اصلی
        if (isset($this->database[$prefix])) {
            return [
                'province' => $this->database[$prefix]['province'],
                'city' => $this->database[$prefix]['city'],
                'source' => 'database'
            ];
        }

        return [
            'province' => '-',
            'city' => '-',
            'source' => 'none'
        ];
    }

    /**
     * دریافت از فایل override (تغییرات دستی مدیر)
     */
    private function getFromOverride($prefix)
    {
        if (!$this->overridePath || !file_exists($this->overridePath)) {
            return null;
        }

        $overrides = json_decode(file_get_contents($this->overridePath), true);
        
        if (isset($overrides[$prefix])) {
            return [
                'province' => $overrides[$prefix]['province'],
                'city' => $overrides[$prefix]['city'],
                'source' => 'override'
            ];
        }

        return null;
    }

    /**
     * ذخیره یا بروزرسانی اطلاعات یک کد (فقط با رمز مدیریت)
     */
    public function saveOrUpdate($prefix, $province, $city, $adminPassword, $configPassword)
    {
        if ($adminPassword !== $configPassword) {
            return ['success' => false, 'message' => 'رمز عبور مدیریت اشتباه است'];
        }

        $prefix = str_pad($prefix, 3, '0', STR_PAD_LEFT);

        if (!$this->overridePath) {
            return ['success' => false, 'message' => 'مسیر ذخیره‌سازی تنظیم نشده است'];
        }

        // اطمینان از وجود پوشه
        $dir = dirname($this->overridePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // خواندن فایل موجود
        $overrides = [];
        if (file_exists($this->overridePath)) {
            $overrides = json_decode(file_get_contents($this->overridePath), true);
        }

        // ذخیره یا بروزرسانی
        $overrides[$prefix] = [
            'province' => $province,
            'city' => $city,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->overridePath, json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['success' => true, 'message' => 'اطلاعات با موفقیت ذخیره شد'];
    }

    /**
     * حذف یک کد از override (بازگشت به دیتابیس اصلی)
     */
    public function deleteOverride($prefix, $adminPassword, $configPassword)
    {
        if ($adminPassword !== $configPassword) {
            return ['success' => false, 'message' => 'رمز عبور مدیریت اشتباه است'];
        }

        if (!$this->overridePath || !file_exists($this->overridePath)) {
            return ['success' => false, 'message' => 'فایل override وجود ندارد'];
        }

        $overrides = json_decode(file_get_contents($this->overridePath), true);
        
        if (isset($overrides[$prefix])) {
            unset($overrides[$prefix]);
            file_put_contents($this->overridePath, json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ['success' => true, 'message' => 'کد به حالت پیش‌فرض بازگشت'];
        }

        return ['success' => false, 'message' => 'کد مورد نظر در override یافت نشد'];
    }

    /**
     * دریافت لیست تمام override ها
     */
    public function getAllOverrides()
    {
        if (!$this->overridePath || !file_exists($this->overridePath)) {
            return [];
        }

        return json_decode(file_get_contents($this->overridePath), true) ?: [];
    }
}