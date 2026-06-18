<?php
return [
    'admin_password' => '009009',
    'excel_file_path' => __DIR__ . '/../storage/uploads/exports/GroupLedger.csv',
    'excel_cache_path' => __DIR__ . '/../storage/cache/excel_cache.php',
    'city_codes_path' => __DIR__ . '/../storage/uploads/exports/city_codes.xlsx',
    'national_codes_override_path' => __DIR__ . '/../storage/database/national_codes_override.json',
    'max_search_results' => 10,  // ← این مقدار را افزایش دهید
    'search_priorities' => [
        'عنوان' => 100,
        'اختصار' => 80,
        'عنوان لاتین' => 60,
        'توضیحات' => 40
    ]
];