<?php
// فایل footer.php
?>

</main> <footer class="site-footer">
    <div class="footer-content">
        <p>
            &copy; <?php
            // نمایش سال جاری به صورت شمسی با استفاده از persian-date.js (نیاز به بارگذاری کتابخانه دارد)
            // یا استفاده از تاریخ میلادی اگر کتابخانه در دسترس نیست یا برای سادگی
            if (function_exists('jdate')) { // اگر از کتابخانه jdf در PHP استفاده می‌کنید
                // echo jdate('Y'); // نمایش سال شمسی با jdf
                 echo (new persianDate())->format('YYYY'); // نمایش سال شمسی با persian-date.js (اگر در JS باشد)
                 // برای سادگی فعلا از میلادی استفاده می کنیم:
                 // echo date('Y');
            } else {
                 // نمایش سال میلادی به عنوان جایگزین
                 echo date('Y');
            }
            ?>
            تمامی حقوق این سامانه متعلق به [ گروه برنامه نویسی مدرسه هیأت امنایی علامه دوانی ] می‌باشد.
        </p>
        <?php /* بخش اختیاری: نمایش نسخه نرم افزار */ ?>
        <?php
            // $app_version = "1.0.2"; // می‌توانید نسخه را اینجا یا در یک فایل کانفیگ تعریف کنید
            // if (isset($app_version)) {
            //     echo '<span class="app-version">نسخه ' . convertToPersianNumbers($app_version) . '</span>';
            // }
        ?>
        <?php /* لینک های اختیاری دیگر
        <div class="footer-links">
            <a href="<?php echo SITE_URL; ?>privacy-policy.php">سیاست حفظ حریم خصوصی</a>
            <span class="link-separator">|</span>
            <a href="<?php echo SITE_URL; ?>terms-of-service.php">شرایط استفاده</a>
        </div>
        */ ?>
    </div>
</footer>

<?php
// --- بارگذاری فایل‌های JavaScript عمومی یا خاص صفحه در انتهای Body ---

// مثال: اسکریپت اصلی برنامه شما (اگر دارید)
// <script src="<?= SITE_ASSETS; </script>

// Datepicker JS (Conditional Loading - این بخش قبلا در header.php مدیریت شده است)
// اگر نیاز به اجرای کدهای JS دارید که به المان‌های DOM وابسته‌اند، اینجا جای مناسبی است
// یا اینکه کدهای JS خود را داخل $(document).ready() یا DOMContentLoaded قرار دهید.

// اسکریپت نمایش تاریخ در هدر (از header.php به اینجا منتقل شد تا مطمئن شویم persian-date بارگذاری شده) -->
?>
<script src="<?php echo SITE_ASSETS; ?>/vendor/persian-date/persian-date.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // نمایش تاریخ شمسی در هدر
    const dateElement = document.getElementById('header-current-date');
    // بررسی دوباره برای اطمینان از وجود کتابخانه persianDate
    if (dateElement && typeof persianDate !== 'undefined') {
        try {
             dateElement.textContent = new persianDate().format('dddd D MMMM YYYY'); // فرمت کامل‌تر
        } catch (e) {
             console.error("Error formatting persian date:", e);
             // Fallback to Gregorian date if needed
             dateElement.textContent = new Date().toLocaleDateString('fa-IR');
        }
    } else if(dateElement) {
         // Fallback if persianDate library is not loaded
         dateElement.textContent = new Date().toLocaleDateString('fa-IR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }

    // کد اسکرول (اختیاری)
    const header = document.querySelector('.site-header');
    const navbar = document.querySelector('.main-navbar');
    if (header && navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 5) {
                document.body.classList.add('scrolled');
            } else {
                document.body.classList.remove('scrolled');
            }
        }, { passive: true }); // بهبود پرفورمنس اسکرول
    }
});
</script>

<?php
// بارگذاری شرطی JS مربوط به Datepicker (اگر از header.php به اینجا منتقل شود)
/*
if ($load_datepicker_js) {
    echo '<script src="' . SITE_ASSETS . '/vendor/persian-datepicker/persian-datepicker.min.js"></script>';
    // echo '<script src="' . SITE_ASSETS . '/js/datepicker-init.js"></script>'; // فایل تنظیمات Datepicker
}
*/
?>

</body>
</html>