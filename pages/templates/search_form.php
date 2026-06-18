<?php
// pages/templates/search_form.php
// این فایل بین header و footer قرار می‌گیرد
?>

<!-- Tab Navigation -->
<div class="tabs">
    <button class="tab active" onclick="showTab('national-tab')">
        <i class="fas fa-id-card"></i> استعلام کد ملی
    </button>
    <button class="tab" onclick="showTab('general-tab')">
        <i class="fas fa-search"></i> جستجوی عمومی
    </button>
    <button class="tab" onclick="showTab('admin-tab')">
        <i class="fas fa-lock"></i> پنل مدیریت
    </button>
</div>

<!-- ==================== تب 1: استعلام کد ملی ==================== -->
<div id="national-tab" class="tab-content active">
    <div class="card">
        <div class="card-title">
            <i class="fas fa-id-card"></i>
            استعلام کد ملی
        </div>
        
        <form method="POST" action="" id="nationalForm">
            <div class="form-group">
                <label>🔢 کد ملی ۱۰ رقمی</label>
                <input type="text" name="national_code" id="national_code" 
                       class="national-input" maxlength="10" 
                       placeholder="مثال: 0630010099"
                       pattern="[0-9]{10}" required>
                <small style="display: block; margin-top: 5px; color: #7f8c8d;">
                    کد ملی باید ۱۰ رقم باشد
                </small>
            </div>
            <button type="submit" name="check_national" class="btn btn-primary btn-full">
                <i class="fas fa-check-circle"></i> بررسی کد ملی
            </button>
        </form>
        
        <!-- نتیجه استعلام کد ملی -->
        <?php if (isset($nationalResult)): ?>
        <div class="result-box result-<?php echo $nationalResult['status_class']; ?>" style="margin-top: 20px;">
            <div class="result-icon"><?php echo $nationalResult['icon']; ?></div>
            <div class="result-status" style="color: <?php echo $nationalResult['color']; ?>;">
                <?php echo $nationalResult['status']; ?>
            </div>
            <div class="result-detail">
                <?php echo $nationalResult['message']; ?>
            </div>
            
            <?php if (isset($nationalResult['province']) && $nationalResult['province'] != '-'): ?>
            <div style="margin-top: 15px; padding: 10px; background: #e8f4fd; border-radius: 10px;">
                <p><strong>📍 استان:</strong> <?php echo $nationalResult['province']; ?></p>
                <p><strong>🏙️ شهر:</strong> <?php echo $nationalResult['city']; ?></p>
                
                <?php if (isset($nationalResult['excel_description'])): ?>
                <hr style="margin: 10px 0;">
                <p><strong>📄 مقدار یافت شده در فایل اکسل:</strong></p>
                <div style="background: white; padding: 8px; border-radius: 8px; font-family: monospace; direction: ltr; text-align: left;">
                    <?php echo htmlspecialchars($nationalResult['excel_description']); ?>
                    <button class="copy-btn" onclick="copyToClipboard('<?php echo addslashes($nationalResult['excel_description']); ?>')">
                        <i class="far fa-copy"></i> کپی
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($nationalResult['suggest_edit']) && $nationalResult['suggest_edit']): ?>
            <button onclick="showEditDialog()" class="btn btn-warning" style="margin-top: 15px; width: auto;">
                <i class="fas fa-edit"></i> پیشنهاد اصلاح اطلاعات
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== تب 2: جستجوی عمومی ==================== -->
<div id="general-tab" class="tab-content">
    <div class="card">
        <div class="card-title">
            <i class="fas fa-search"></i>
            جستجوی عمومی در فایل اکسل
        </div>
        
        <form method="POST" action="" id="generalForm">
            <div class="form-group">
                <label>🔍 عبارت جستجو</label>
                <input type="text" name="search_keyword" id="search_keyword" 
                       class="search-input" 
                       placeholder="نام، تلفن، کد پرسنلی، شناسه ملی شرکت، ...">
                <small style="display: block; margin-top: 5px; color: #7f8c8d;">
                    می‌توانید نام، نام شرکت، شماره تلفن، کد پرسنلی و ... را جستجو کنید
                </small>
            </div>
            <button type="submit" name="general_search" class="btn btn-primary btn-full">
                <i class="fas fa-search"></i> جستجو
            </button>
        </form>
        
        <!-- نتایج جستجوی عمومی -->
        <?php if (isset($searchResults)): ?>
        <div style="margin-top: 20px;">
            <h4 style="margin-bottom: 15px;">
                <i class="fas fa-list"></i> نتایج جستجو 
                <span class="badge badge-success"><?php echo $searchResults['count']; ?> مورد</span>
            </h4>
            
            <?php if ($searchResults['count'] > 0): ?>
            <div class="results-scroll">
                <?php foreach ($searchResults['results'] as $result): ?>
                <div class="result-item" onclick="showRowDetails(this)">
                    <div class="result-score" style="display: flex; justify-content: space-between;">
                        <span>امتیاز: <?php echo $result['score']; ?></span>
                        <span>ردیف: <?php echo $result['row_number']; ?></span>
                    </div>
                    <div style="font-size: 0.9rem; margin-top: 5px;">
                        <strong><?php echo mb_substr($result['data']['عنوان'] ?? '', 0, 80); ?></strong>
                        <?php if (mb_strlen($result['data']['عنوان'] ?? '') > 80): ?>...<?php endif; ?>
                    </div>
                    <div class="table-container" style="display: none; margin-top: 10px;">
                        <table class="result-table">
                            <?php foreach ($result['data'] as $key => $value): ?>
                            <tr>
                                <th style="width: 120px;"><?php echo htmlspecialchars($key); ?></th>
                                <td><?php echo htmlspecialchars($value); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="result-box result-unknown">
                <div class="result-icon">🔍</div>
                <div class="result-status">موردی یافت نشد</div>
                <div class="result-detail">عبارت "<?php echo htmlspecialchars($searchResults['keyword']); ?>" در فایل اکسل یافت نشد</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== تب 3: پنل مدیریت ==================== -->
<div id="admin-tab" class="tab-content">
    <div class="card">
        <div class="card-title">
            <i class="fas fa-lock"></i>
            پنل مدیریت - اصلاح دیتابیس کدهای ملی
        </div>
        
        <form method="POST" action="" id="adminForm">
            <div class="form-group">
                <label>🔐 رمز عبور مدیریت</label>
                <input type="password" name="admin_password" id="admin_password" 
                       placeholder="رمز مدیریت را وارد کنید" required>
            </div>
            
            <div class="form-group">
                <label>📇 سه رقم اول کد ملی</label>
                <input type="text" name="code_prefix" id="code_prefix" 
                       maxlength="3" pattern="[0-9]{3}" 
                       placeholder="مثال: 063" required>
            </div>
            
            <div class="form-group">
                <label>📍 استان</label>
                <input type="text" name="province" id="province" 
                       placeholder="نام استان">
            </div>
            
            <div class="form-group">
                <label>🏙️ شهر</label>
                <input type="text" name="city" id="city" 
                       placeholder="نام شهر">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="save_national_code" class="btn btn-success">
                    <i class="fas fa-save"></i> ذخیره/بروزرسانی
                </button>
                <button type="submit" name="delete_override" class="btn btn-danger" onclick="return confirm('آیا از حذف این کد اطمینان دارید؟')">
                    <i class="fas fa-trash"></i> حذف override
                </button>
            </div>
        </form>
        
        <?php if (isset($adminMessage)): ?>
        <div class="alert alert-<?php echo $adminMessage['type']; ?>" style="margin-top: 15px; padding: 10px; border-radius: 8px; background: <?php echo $adminMessage['type'] == 'success' ? '#d5f5e3' : '#fadbd8'; ?>">
            <?php echo $adminMessage['message']; ?>
        </div>
        <?php endif; ?>
        
        <hr style="margin: 20px 0;">
        
        <h4><i class="fas fa-list"></i> لیست کدهای اصلاح شده (override)</h4>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>سه رقم اول</th><th>استان</th><th>شهر</th><th>تاریخ بروزرسانی</th></tr>
                </thead>
                <tbody>
                    <?php if (!empty($overrides)): ?>
                        <?php foreach ($overrides as $prefix => $info): ?>
                        <tr>
                            <td><?php echo $prefix; ?></td>
                            <td><?php echo htmlspecialchars($info['province']); ?></td>
                            <td><?php echo htmlspecialchars($info['city']); ?></td>
                            <td><?php echo $info['updated_at'] ?? '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center;">هیچ کدی اصلاح نشده است</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    // مخفی کردن همه تب‌ها
    document.querySelectorAll('.tab-content').forEach(function(tab) {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.classList.remove('active');
    });
    
    // نمایش تب انتخاب شده
    document.getElementById(tabId).classList.add('active');
    event.target.classList.add('active');
}

function showRowDetails(element) {
    const details = element.querySelector('.table-container');
    if (details.style.display === 'none') {
        details.style.display = 'block';
    } else {
        details.style.display = 'none';
    }
}

function showEditDialog() {
    alert('لطفاً از بخش "پنل مدیریت" با وارد کردن رمز عبور، اطلاعات را اصلاح کنید.');
}
</script>