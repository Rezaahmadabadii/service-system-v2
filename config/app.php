<?php
return [
    'admin_password' => '009009',
    'excel_file_path' => __DIR__ . '/../storage/uploads/exports/GroupLedger.csv',
    'excel_cache_path' => __DIR__ . '/../storage/cache/excel_cache.php',
    'city_codes_path' => __DIR__ . '/../storage/uploads/exports/city_codes.xlsx',
    'national_codes_override_path' => __DIR__ . '/../storage/database/national_codes_override.json',
    'max_search_results' => 10,
    'search_priorities' => [
        'عنوان' => 100,
        'اختصار' => 80,
        'عنوان لاتین' => 60,
        'توضیحات' => 40
    ],
    
    // ========== تنظیمات جدید برای استعلام واریزی‌ها ==========
    'inquiry_base_path' => '\\\\11-pc-srv\\MALI\\مینایی\\حوالجات\\',
    'inquiry_file_pattern' => '/(\d{4})\s*[-_]\s*(\d{1,2})/',
    'inquiry_allowed_ips_file' => __DIR__ . '/../storage/security/allowed_ips.txt',
    'inquiry_history_file' => __DIR__ . '/../storage/logs/search_history.json',
    'inquiry_results_per_page' => 10,
    // ========================================================
];