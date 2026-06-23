<?php
// inquiry_panel.php - پنل استعلام واریزی‌ها

$config = require_once __DIR__ . '/config/app.php';

// دریافت IP کاربر
function getUserIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return trim($ip);
}

// بررسی دسترسی IP
function checkIPAccess($ip) {
    $ipFile = __DIR__ . '/storage/security/allowed_ips.txt';
    if (!file_exists($ipFile)) return false;
    
    $lines = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if ($line === $ip || $line === $ip . '*') return true;
    }
    return false;
}

$ip = getUserIP();

// اگر IP مجاز نیست
if (!checkIPAccess($ip)) {
    $allowedIps = [];
    $ipFile = __DIR__ . '/storage/security/allowed_ips.txt';
    if (file_exists($ipFile)) {
        $lines = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            $allowedIps[] = $line;
        }
    }
    
    die('
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head><meta charset="UTF-8"><title>دسترسی غیرمجاز</title></head>
    <body style="font-family:Tahoma;background:#f5f5f5;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;">
        <div style="background:white;padding:40px;border-radius:20px;box-shadow:0 10px 40px rgba(0,0,0,0.1);text-align:center;max-width:500px;">
            <div style="font-size:60px;margin-bottom:20px;">⛔</div>
            <h2 style="color:#e74c3c;">دسترسی غیرمجاز</h2>
            <p style="color:#666;margin:15px 0;">IP شما (' . htmlspecialchars($ip) . ') در لیست مجاز نیست.</p>
            <hr style="margin:20px 0;border:none;border-top:1px solid #eee;">
            <p style="color:#999;font-size:12px;">لیست IPهای مجاز: ' . implode('، ', $allowedIps) . '</p>
            <a href="index.php" style="display:inline-block;margin-top:20px;padding:10px 30px;background:#667eea;color:white;text-decoration:none;border-radius:30px;">بازگشت به صفحه اصلی</a>
        </div>
    </body>
    </html>
    ');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعلام واریزی‌ها</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);direction:rtl;min-height:100vh;padding:20px}
        .container{max-width:1200px;margin:0 auto}
        .header{background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-radius:20px;padding:16px 24px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;border:1px solid rgba(255,255,255,0.2);box-shadow:0 10px 40px rgba(0,0,0,0.1)}
        .header h1{font-size:1.3rem;color:#2c3e50}
        .header h1 i{color:#667eea;margin-left:8px}
        .back-btn{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;padding:8px 18px;border-radius:30px;cursor:pointer;font-family:inherit;font-size:.8rem;display:flex;align-items:center;gap:6px;text-decoration:none;transition:all .3s ease}
        .back-btn:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(102,126,234,0.4)}
        .card{background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-radius:20px;padding:24px;margin-bottom:20px;border:1px solid rgba(255,255,255,0.2);box-shadow:0 10px 40px rgba(0,0,0,0.1)}
        .card-title{font-size:1.1rem;font-weight:600;margin-bottom:16px;color:#2c3e50;display:flex;align-items:center;gap:8px;border-bottom:2px solid #e9ecef;padding-bottom:12px}
        .form-row{display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end}
        .form-group{flex:1;min-width:180px}
        .form-group label{display:block;font-size:.75rem;font-weight:500;color:#495057;margin-bottom:6px}
        .form-group input,.form-group select{width:100%;padding:10px 14px;border:1.5px solid #e9ecef;border-radius:12px;font-size:.85rem;transition:all .3s ease;background:#f8f9fa;font-family:inherit}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:#667eea;background:#fff;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}
        .btn{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;padding:10px 24px;border-radius:12px;cursor:pointer;font-family:inherit;font-size:.85rem;font-weight:500;transition:all .3s ease;display:inline-flex;align-items:center;gap:8px}
        .btn:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(102,126,234,0.4)}
        .btn-success{background:linear-gradient(135deg,#27ae60 0%,#2ecc71 100%)}
        .btn-warning{background:linear-gradient(135deg,#f39c12 0%,#e67e22 100%)}
        .btn-danger{background:linear-gradient(135deg,#e74c3c 0%,#c0392b 100%)}
        .btn-sm{padding:6px 14px;font-size:.75rem}
        .btn-outline{background:transparent;border:1.5px solid #667eea;color:#667eea}
        .btn-outline:hover{background:#667eea;color:#fff}
        .loading{text-align:center;padding:40px;color:#6c757d}
        .loading i{font-size:2rem;margin-bottom:10px}
        .results-table{width:100%;border-collapse:collapse;font-size:.8rem;margin-top:15px}
        .results-table th{background:#f8f9fa;padding:12px 15px;text-align:right;font-weight:600;color:#2c3e50;border-bottom:2px solid #e9ecef}
        .results-table td{padding:10px 15px;border-bottom:1px solid #e9ecef}
        .results-table tr:hover{background:#f8f9fa}
        .badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:.65rem;font-weight:600}
        .badge-high{background:#d4edda;color:#155724}
        .badge-medium{background:#fff3cd;color:#856404}
        .badge-low{background:#f8d7da;color:#721c24}
        .pagination{display:flex;justify-content:center;gap:8px;margin-top:20px;flex-wrap:wrap}
        .page-btn{padding:6px 12px;border:1px solid #ddd;background:#fff;border-radius:6px;cursor:pointer;font-size:.75rem;transition:all .2s ease}
        .page-btn:hover{background:#667eea;color:#fff;border-color:#667eea}
        .page-btn.active{background:#667eea;color:#fff;border-color:#667eea}
        .results-summary{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:15px;padding:12px 15px;background:#f8f9fa;border-radius:12px;font-size:.85rem}
        .no-result{text-align:center;padding:40px;color:#6c757d}
        .no-result i{font-size:3rem;margin-bottom:15px;opacity:0.5}
        .today-buttons{display:flex;gap:10px;flex-wrap:wrap}
        .history-toggle{color:#667eea;cursor:pointer;font-size:.75rem;text-decoration:underline}
        .history-toggle:hover{color:#764ba2}
        .history-box{background:#f8f9fa;border-radius:12px;padding:15px;margin-top:10px;max-height:300px;overflow-y:auto;display:none}
        .history-box.active{display:block}
        .history-item{padding:6px 0;border-bottom:1px solid #e9ecef;font-size:.75rem;color:#495057}
        .history-item span{color:#999;font-size:.65rem}
        .ip-info{font-size:.7rem;color:#6c757d;display:flex;align-items:center;gap:8px}
        .full-access-badge{background:#27ae60;color:#fff;padding:2px 8px;border-radius:12px;font-size:.6rem;font-weight:600}
        @media(max-width:700px){.form-row{flex-direction:column}.form-group{min-width:auto}}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .fade-in{animation:fadeIn .3s ease}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-file-invoice"></i> استعلام واریزی‌ها</h1>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <span class="ip-info"><i class="fas fa-network-wired"></i> IP: <?php echo htmlspecialchars($ip); ?></span>
            <a href="index.php" class="back-btn"><i class="fas fa-arrow-right"></i> بازگشت</a>
        </div>
    </div>

    <!-- پیام هشدار -->
    <div class="card" id="warningBox">
        <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
            <div style="font-size:40px;">⚠️</div>
            <div style="flex:1;">
                <h3 style="color:#e67e22;font-size:1rem;margin-bottom:5px;">توجه: ثبت تاریخچه جستجوها</h3>
                <p style="color:#555;font-size:.85rem;">تمامی جستجوهای شما در تاریخچه ثبت و نگهداری می‌شود.</p>
            </div>
            <button class="btn btn-success" onclick="hideWarning()"><i class="fas fa-check"></i> ادامه</button>
        </div>
    </div>

    <!-- فرم جستجو -->
    <div class="card">
        <div class="card-title"><i class="fas fa-search"></i> جستجوی واریزی</div>
        <div class="form-row" id="searchForm">
            <div class="form-group">
                <label><i class="fas fa-user"></i> نام و فامیل</label>
                <input type="text" id="searchName" placeholder="مثال: احمد رضایی">
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> سال</label>
                <select id="searchYear"></select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> ماه</label>
                <select id="searchMonth"></select>
            </div>
            <div class="form-group" style="min-width:100px;">
                <label><i class="fas fa-calendar-day"></i> روز (اختیاری)</label>
                <select id="searchDay">
                    <option value="">همه</option>
                    <?php for($i=1;$i<=31;$i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="flex:0 0 auto;">
                <button class="btn" onclick="doSearch()" id="searchBtn"><i class="fas fa-search"></i> جستجو</button>
            </div>
        </div>
    </div>

    <!-- دکمه‌های کل امروز و دیروز (فقط برای IPهای دارای *) -->
    <div class="card" id="fullAccessCard" style="display:none;">
        <div class="card-title"><i class="fas fa-calendar-check"></i> نمایش کل واریزی‌ها</div>
        <div class="today-buttons">
            <button class="btn btn-success" onclick="showAllTransfers('today')"><i class="fas fa-calendar-day"></i> کل امروز</button>
            <button class="btn btn-warning" onclick="showAllTransfers('yesterday')"><i class="fas fa-calendar-day"></i> کل دیروز</button>
            <span style="font-size:.7rem;color:#6c757d;display:flex;align-items:center;margin-right:10px;">
                <i class="fas fa-check-circle" style="color:#27ae60;"></i> دسترسی کامل
            </span>
        </div>
    </div>

    <!-- نتایج -->
    <div class="card" id="resultCard" style="display:none;">
        <div class="card-title">
            <i class="fas fa-list"></i> نتایج
            <span id="resultCount" style="font-weight:normal;font-size:.8rem;color:#6c757d;margin-right:10px;"></span>
            <span id="resultFile" style="font-weight:normal;font-size:.7rem;color:#999;margin-right:10px;"></span>
            <span style="margin-right:auto;">
                <button class="btn btn-sm btn-outline" onclick="toggleHistory()"><i class="fas fa-history"></i> تاریخچه</button>
            </span>
        </div>
        <div id="historyBox" class="history-box">
            <div style="font-weight:600;margin-bottom:10px;font-size:.8rem;">📋 تاریخچه جستجوها</div>
            <div id="historyList"></div>
        </div>
        <div id="resultContent">
            <div class="no-result"><i class="fas fa-search"></i><br>جستجویی انجام نشده است</div>
        </div>
    </div>
</div>

<script>
// ==================== متغیرها ====================
let currentPage = 1;
let allResults = [];
let resultsPerPage = 10;
let historyData = [];
let isFullAccess = <?php 
    $access = checkIPAccess($ip);
    // بررسی دسترسی کامل (IP با *)
    $fullAccess = false;
    $ipFile = __DIR__ . '/storage/security/allowed_ips.txt';
    if (file_exists($ipFile)) {
        $lines = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === $ip . '*') { $fullAccess = true; break; }
        }
    }
    echo $fullAccess ? 'true' : 'false';
?>;

// ==================== توابع ====================
function hideWarning() {
    document.getElementById('warningBox').style.display = 'none';
}

function showToast(message, type) {
    const colors = {
        success: '#27ae60',
        error: '#e74c3c',
        warning: '#f39c12',
        info: '#3498db'
    };
    const toast = document.createElement('div');
    toast.textContent = message;
    toast.style.cssText = `
        position:fixed; bottom:20px; right:20px; 
        background:${colors[type] || '#333'}; 
        color:white; padding:12px 24px; 
        border-radius:12px; z-index:9999; 
        font-size:0.85rem; 
        animation:fadeIn 0.3s ease;
        max-width:90%;
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function showLoading(show) {
    const btn = document.getElementById('searchBtn');
    if (show) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال جستجو...';
        btn.disabled = true;
    } else {
        btn.innerHTML = '<i class="fas fa-search"></i> جستجو';
        btn.disabled = false;
    }
}

function loadYears() {
    fetch('api/inquiry_api.php?action=get_years')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('searchYear');
                select.innerHTML = '';
                data.years.forEach(year => {
                    const opt = document.createElement('option');
                    opt.value = year;
                    opt.textContent = year;
                    select.appendChild(opt);
                });
                // انتخاب سال جاری
                const today = new Date();
                const jalaliYear = today.getFullYear() - 622;
                const currentYear = jalaliYear.toString();
                if (data.years.includes(currentYear)) {
                    select.value = currentYear;
                }
                loadMonths();
            }
        });
}

function loadMonths() {
    const year = document.getElementById('searchYear').value;
    if (!year) return;
    
    fetch(`api/inquiry_api.php?action=get_months&year=${year}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('searchMonth');
                select.innerHTML = '';
                const monthNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
                data.months.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = `${m} - ${monthNames[m-1]}`;
                    select.appendChild(opt);
                });
            }
        });
}

function doSearch() {
    const name = document.getElementById('searchName').value.trim();
    const year = document.getElementById('searchYear').value;
    const month = document.getElementById('searchMonth').value;
    const day = document.getElementById('searchDay').value;
    
    if (!name) {
        showToast('لطفاً نام و فامیل را وارد کنید', 'warning');
        return;
    }
    if (name.length < 2) {
        showToast('نام باید حداقل ۲ کاراکتر باشد', 'warning');
        return;
    }
    if (!year || !month) {
        showToast('لطفاً سال و ماه را انتخاب کنید', 'warning');
        return;
    }
    
    showLoading(true);
    document.getElementById('resultCard').style.display = 'block';
    document.getElementById('resultContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>در حال جستجو...</div>';
    
    let url = `api/inquiry_api.php?action=search&name=${encodeURIComponent(name)}&year=${year}&month=${month}`;
    if (day) url += `&day=${day}`;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            showLoading(false);
            if (data.success) {
                allResults = data.results;
                currentPage = 1;
                displayResults();
                document.getElementById('resultCount').textContent = `${data.count} نتیجه`;
                document.getElementById('resultFile').textContent = `📁 ${data.file || ''}`;
                loadHistory();
            } else {
                document.getElementById('resultContent').innerHTML = `
                    <div class="no-result"><i class="fas fa-exclamation-circle"></i><br>${data.error || 'خطا در جستجو'}</div>
                `;
                showToast(data.error || 'خطا در جستجو', 'error');
            }
        })
        .catch(err => {
            showLoading(false);
            document.getElementById('resultContent').innerHTML = `
                <div class="no-result"><i class="fas fa-exclamation-circle"></i><br>خطا در ارتباط با سرور</div>
            `;
            showToast('خطا در ارتباط با سرور', 'error');
        });
}

function displayResults() {
    const container = document.getElementById('resultContent');
    
    if (!allResults || allResults.length === 0) {
        container.innerHTML = '<div class="no-result"><i class="fas fa-search"></i><br>نتیجه‌ای یافت نشد</div>';
        return;
    }
    
    const totalPages = Math.ceil(allResults.length / resultsPerPage);
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    
    const start = (currentPage - 1) * resultsPerPage;
    const pageResults = allResults.slice(start, start + resultsPerPage);
    
    let html = `
        <div class="results-summary">
            <span>🔍 <strong>${allResults.length}</strong> نتیجه یافت شد</span>
            <span>📅 صفحه ${currentPage} از ${totalPages}</span>
        </div>
        <table class="results-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>روز</th>
                    <th>نام دریافت‌کننده</th>
                    <th>مبلغ (ریال)</th>
                    <th>تطابق</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    pageResults.forEach((item, index) => {
        const realIndex = start + index + 1;
        const similarity = item.similarity || 100;
        let badgeClass = 'badge-high';
        if (similarity < 90) badgeClass = 'badge-medium';
        if (similarity < 80) badgeClass = 'badge-low';
        
        html += `
            <tr>
                <td>${realIndex}</td>
                <td>${item.day || '-'}</td>
                <td><strong>${escapeHtml(item.name)}</strong></td>
                <td>${item.amount}</td>
                <td><span class="badge ${badgeClass}">${similarity}%</span></td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    // صفحه‌بندی
    if (totalPages > 1) {
        html += `<div class="pagination">`;
        if (currentPage > 1) {
            html += `<button class="page-btn" onclick="goToPage(${currentPage - 1})">« قبلی</button>`;
        }
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<button class="page-btn active">${i}</button>`;
            } else if (Math.abs(i - currentPage) <= 2 || i === 1 || i === totalPages) {
                html += `<button class="page-btn" onclick="goToPage(${i})">${i}</button>`;
            } else if (Math.abs(i - currentPage) === 3) {
                html += `<span style="padding:5px;">...</span>`;
            }
        }
        if (currentPage < totalPages) {
            html += `<button class="page-btn" onclick="goToPage(${currentPage + 1})">بعدی »</button>`;
        }
        html += `</div>`;
    }
    
    container.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    displayResults();
}

function showAllTransfers(type) {
    if (!isFullAccess) {
        showToast('شما دسترسی به این بخش ندارید', 'error');
        return;
    }
    
    const label = type === 'today' ? 'امروز' : 'دیروز';
    document.getElementById('resultCard').style.display = 'block';
    document.getElementById('resultContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>در حال دریافت لیست...</div>';
    
    fetch(`api/inquiry_api.php?action=${type === 'today' ? 'today_all' : 'yesterday_all'}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                allResults = data.results.map(r => ({ ...r, similarity: 100 }));
                currentPage = 1;
                document.getElementById('resultCount').textContent = `${data.count} نتیجه`;
                document.getElementById('resultFile').textContent = `📅 ${data.label} - ${data.date}`;
                displayResults();
                loadHistory();
            } else {
                document.getElementById('resultContent').innerHTML = `
                    <div class="no-result"><i class="fas fa-exclamation-circle"></i><br>${data.error || 'خطا'}</div>
                `;
            }
        })
        .catch(err => {
            document.getElementById('resultContent').innerHTML = `
                <div class="no-result"><i class="fas fa-exclamation-circle"></i><br>خطا در ارتباط با سرور</div>
            `;
        });
}

function loadHistory() {
    fetch('api/inquiry_api.php?action=get_history')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                historyData = data.history;
                const list = document.getElementById('historyList');
                if (historyData.length === 0) {
                    list.innerHTML = '<div style="color:#999;font-size:.75rem;">تاریخچه‌ای وجود ندارد</div>';
                } else {
                    list.innerHTML = historyData.slice(0, 20).map(h => `
                        <div class="history-item">
                            <strong>${escapeHtml(h.search_term)}</strong>
                            <span>${h.year}/${h.month} - ${h.results_count} نتیجه</span>
                            <br><span>${h.date}</span>
                        </div>
                    `).join('');
                }
            }
        });
}

function toggleHistory() {
    const box = document.getElementById('historyBox');
    box.classList.toggle('active');
    if (box.classList.contains('active')) {
        loadHistory();
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ==================== رویدادها ====================
document.getElementById('searchYear').addEventListener('change', loadMonths);

document.getElementById('searchName').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') doSearch();
});

// ==================== مقداردهی اولیه ====================
document.addEventListener('DOMContentLoaded', function() {
    loadYears();
    
    // نمایش دکمه‌های دسترسی کامل
    if (isFullAccess) {
        document.getElementById('fullAccessCard').style.display = 'block';
    }
    
    // نمایش IP
    console.log('IP: <?php echo htmlspecialchars($ip); ?>');
});
</script>
</body>
</html>