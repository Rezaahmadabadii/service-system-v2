        <!-- Footer -->
        <div class="footer" style="text-align: center; padding: 20px; margin-top: 30px; color: rgba(255,255,255,0.7); font-size: 0.8rem;">
            <p>
                <i class="fas fa-code"></i> توسعه‌دهنده: رضا احمدآبادی | 
                <i class="fas fa-phone"></i> 09353984864 |
                <i class="fas fa-envelope"></i> <a href="https://t.me/ahmadabadireza" style="color: white;">تلگرام</a>
            </p>
            <p>نسخه 1.0</p>
        </div>
    </div>
    
    <!-- jQuery (اختیاری برای AJAX) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/service-system-v2/public/js/main.js"></script>
    
    <script>
        // تابع کپی متن
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('✅ متن کپی شد: ' + text);
            }, function() {
                alert('❌ خطا در کپی متن');
            });
        }
    </script>
</body>
</html>