<?php
// Core/Services/SearchLogger.php

namespace Core\Services;

class SearchLogger
{
    private $logFile;

    public function __construct($logFile)
    {
        $this->logFile = $logFile;
        
        // اطمینان از وجود پوشه
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    /**
     * دریافت IP واقعی کاربر (پشتیبانی از شبکه محلی و پروکسی)
     */
    private function getClientIP()
    {
        // لیست IPهای قابل اعتماد (شبکه محلی شما)
        $trustedProxies = ['192.168.1.0/24', '192.168.48.0/24', '10.0.0.0/8', '172.16.0.0/12'];
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // بررسی Cloudflare
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        
        // بررسی X-Forwarded-For (برای پروکسی و شبکه محلی)
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $firstIP = trim($ips[0]);
            
            // اگر IP اول معتبر است، آن را برگردان
            if (filter_var($firstIP, FILTER_VALIDATE_IP)) {
                return $firstIP;
            }
        }
        
        // بررسی X-Real-IP (برای Nginx)
        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $realIP = $_SERVER['HTTP_X_REAL_IP'];
            if (filter_var($realIP, FILTER_VALIDATE_IP)) {
                return $realIP;
            }
        }
        
        // اگر IP محلی (127.0.0.1 یا ::1) است
        if ($ip === '::1' || $ip === '127.0.0.1' || $ip === 'localhost') {
            // دریافت IP واقعی از طریق نام کامپیوتر
            $hostname = gethostname();
            $localIP = gethostbyname($hostname);
            
            // اگر IP معتبری پیدا شد، برگردان
            if ($localIP && $localIP !== $ip && filter_var($localIP, FILTER_VALIDATE_IP)) {
                return $localIP . ' (localhost)';
            }
            
            // اگر باز هم IPv6 بود، به IPv4 تبدیل کن
            if ($ip === '::1') {
                return '127.0.0.1 (localhost)';
            }
        }
        
        // در نهایت، IP برگردانده شود
        return $ip;
    }

    /**
     * ثبت یک جستجو در لاگ
     */
    public function log($searchType, $keyword, $resultCount, $additionalInfo = [])
    {
        $ip = $this->getClientIP();
        $date = date('Y-m-d H:i:s');
        
        $logEntry = [
            'date' => $date,
            'ip' => $ip,
            'type' => $searchType, // national, general, city
            'keyword' => $keyword,
            'result_count' => $resultCount,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        // اضافه کردن اطلاعات اضافی (مثل استان و شهر)
        if (!empty($additionalInfo)) {
            $logEntry['additional'] = $additionalInfo;
        }
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * دریافت آخرین لاگ‌ها
     */
    public function getRecentLogs($limit = 50)
    {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $lines = file($this->logFile);
        $lines = array_reverse($lines);
        $logs = [];
        
        foreach ($lines as $line) {
            if (count($logs) >= $limit) break;
            $data = json_decode(trim($line), true);
            if ($data) {
                $logs[] = $data;
            }
        }
        
        return $logs;
    }

    /**
     * دریافت آمار کلی جستجوها
     */
    public function getStatistics()
    {
        if (!file_exists($this->logFile)) {
            return [
                'total' => 0,
                'today' => 0,
                'by_type' => [],
                'most_searched' => []
            ];
        }
        
        $lines = file($this->logFile);
        $total = count($lines);
        $today = date('Y-m-d');
        $todayCount = 0;
        $byType = [];
        $keywords = [];
        
        foreach ($lines as $line) {
            $data = json_decode(trim($line), true);
            if ($data) {
                // تعداد امروز
                if (substr($data['date'], 0, 10) === $today) {
                    $todayCount++;
                }
                
                // تعداد بر اساس نوع
                $type = $data['type'] ?? 'unknown';
                $byType[$type] = ($byType[$type] ?? 0) + 1;
                
                // کلمات پرتکرار
                $kw = $data['keyword'] ?? '';
                if ($kw) {
                    $keywords[$kw] = ($keywords[$kw] ?? 0) + 1;
                }
            }
        }
        
        // مرتب‌سازی کلمات پرتکرار
        arsort($keywords);
        $mostSearched = array_slice($keywords, 0, 10);
        
        return [
            'total' => $total,
            'today' => $todayCount,
            'by_type' => $byType,
            'most_searched' => $mostSearched
        ];
    }

    /**
     * پاک کردن فایل لاگ
     */
    public function clearLog()
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
}