// public/js/main.js

// تابع کپی متن
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('✅ متن کپی شد!', 'success');
    }, function() {
        showToast('❌ خطا در کپی متن', 'error');
    });
}

// نمایش پیام Toast
function showToast(message, type) {
    let toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 20px;
        background: ${type === 'success' ? '#27ae60' : '#e74c3c'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        z-index: 9999;
        animation: fadeInOut 2s ease;
    `;
    document.body.appendChild(toast);
    
    setTimeout(function() {
        toast.remove();
    }, 2000);
}

// اعتبارسنجی کد ملی در لحظه
document.addEventListener('DOMContentLoaded', function() {
    const nationalInput = document.getElementById('national_code');
    if (nationalInput) {
        nationalInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    }
    
    const prefixInput = document.getElementById('code_prefix');
    if (prefixInput) {
        prefixInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3);
        });
    }
});

// انیمیشن fadeInOut
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateY(20px); }
        10% { opacity: 1; transform: translateY(0); }
        90% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-20px); }
    }
`;
document.head.appendChild(style);

// نمایش ردیف‌ها در نتایج جستجو
function toggleRowDetails(element) {
    const details = element.querySelector('.table-container');
    if (details) {
        if (details.style.display === 'none' || !details.style.display) {
            details.style.display = 'block';
        } else {
            details.style.display = 'none';
        }
    }
}

// ذخیره تب فعال در localStorage
document.querySelectorAll('.tab').forEach(function(tab, index) {
    tab.addEventListener('click', function() {
        localStorage.setItem('activeTab', index);
    });
});

// بازیابی تب فعال
window.addEventListener('load', function() {
    const savedTab = localStorage.getItem('activeTab');
    if (savedTab !== null) {
        const tabs = document.querySelectorAll('.tab');
        if (tabs[parseInt(savedTab)]) {
            tabs[parseInt(savedTab)].click();
        }
    }
});