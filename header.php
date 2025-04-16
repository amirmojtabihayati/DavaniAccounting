
<?php
session_start();

// --- پیکربندی سایت ---
$site_on_local = "localhost";
$site_on_domain = ""; // نام دامنه

// تشخیص URL فعلی و تنظیم آدرس پایه
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$script_path = dirname($_SERVER['SCRIPT_NAME']); // مسیر پوشه اسکریپت

// اگر در ریشه هاست هستید، path ممکن است '/' باشد، آن را خالی کنید
$base_path = ($script_path == '/' || $script_path == '\\') ? '' : $script_path;

if ($host == $site_on_local) {
    // برای لوکال هاست، ممکن است نیاز به تعریف زیرپوشه باشد
    // مثال: http://localhost/DavaniAccounting/DavaniAccounting/
    $base_url = $protocol . $host . "/DavaniAccounting/DavaniAccounting/"; // **مسیر پروژه را اینجا تنظیم کنید**
} elseif ($host == $site_on_domain) {
    // برای دامنه اصلی
    $base_url = $protocol . $host . "/"; // آدرس ریشه دامنه
} else {
     // حالت پیش فرض یا خطا - می‌توانید یک آدرس پیش‌فرض تنظیم کنید
     $base_url = $protocol . $host . $base_path . "/";
}

define("SITE_URL", $base_url);
define("SITE_ASSETS", SITE_URL . "assets"); // پوشه فایل‌های CSS/JS/Fonts
define("SITE_IMAGES", SITE_URL . "images"); // پوشه تصاویر عمومی مانند لوگو، آیکون‌ها
// define("SITE_AVATARS", SITE_URL . "uploads/avatars/"); // مسیر آواتارها را جدا کنید بهتر است

// --- بررسی وضعیت ورود کاربر (از کامنت خارج شود در صورت نیاز) ---
// if (!(isset($_SESSION['user_logged']))) {
//     header("Location: " . SITE_URL . "login.php");
//     exit; // همیشه بعد از هدر ریدایرکت، exit() قرار دهید
// } else {
//     $user_logged = $_SESSION['user_logged'];
//     // مثال: $username = $user_logged['username'];
//     // مثال: $user_role = $user_logged['role'];
// }

// --- تعیین نام فایل و مسیر فعلی ---
$request_uri = $_SERVER['REQUEST_URI'];
$current_path_info = parse_url($request_uri, PHP_URL_PATH);
$current_filename = basename($current_path_info); // نام فایل فعلی مثل 'taghsit.php'
$current_dir = basename(dirname($current_path_info)); // نام پوشه فعلی مثل 'hesabdari'

// --- تعیین فایل‌های CSS و JS خاص صفحه ---
$page_css = "";
$datepicker_css = "";
$load_datepicker_js = false; // پرچم برای بارگذاری JS مربوط به Datepicker

// می‌توانید از یک آرایه برای نگاشت استفاده کنید که خواناتر است
$css_mapping = [
    'login.php' => 'login.css',
    'userAdd.php' => 'form.css',
    'StudentsAdd.php' => 'StudentsAdd.css',
    'StudentsDelete.php' => 'StudentsActions.css',
    'StudentsUpdate.php' => 'StudentsActions.css',
    'logout.php' => 'StudentsActions.css',
    'StudentsList.php' => 'StudentsList-style.css',
    'debtsReport.php' => 'debtReport-style.css',
    'debtsDetail.php' => 'debts.css',
    'add_debt.php' => 'debts.css',
    'add_payment.php' => 'debts.css',
    'taghsit.php' => 'form.css',
    'takhfif.php' => 'form.css', // اضافه شد
    // صفحات دیگر را اضافه کنید
    'Home.php' => 'home-style.css', // مثال
];

if (isset($css_mapping[$current_filename])) {
    $page_css = $css_mapping[$current_filename];
}

// بررسی نیاز به Datepicker بر اساس نام فایل
if (in_array($current_filename, ['add_debt.php', 'taghsit.php', 'add_payment.php'])) { // یا صفحات دیگر
    $datepicker_css = "persian-datepicker.min.css"; // نام دقیق فایل CSS
    $load_datepicker_js = true;
}

// --- تعیین منوی فعال ---
$active_menu = $current_filename; // پیش‌فرض: نام فایل
$active_submenu = '';

// تعیین منوی اصلی فعال بر اساس پوشه یا فایل
if (in_array($current_dir, ['menu-aza'])) {
    $active_menu = 'menu-aza'; // شناسه منوی اعضا
    $active_submenu = $current_filename; // زیرمنوی فعال
} elseif (in_array($current_dir, ['hesabdari'])) {
    $active_menu = 'hesabdari'; // شناسه منوی حسابداری
    $active_submenu = $current_filename;
} elseif (in_array($current_filename, ['listhesabAza.php', 'SorateHesab.php', 'SorateHesabVizhe.php'])) {
     $active_menu = 'reports'; // شناسه منوی گزارش‌ها
     $active_submenu = $current_filename;
} elseif ($current_filename == 'userAdd.php') {
     $active_menu = 'userAdd.php';
} elseif ($current_filename == 'Home.php'){
     $active_menu = 'Home.php';
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سامانه مدیریت دانش آموزان</title>

    <meta name="description" content="سامانه مدیریت یکپارچه آموزشگاه"/>

    <script src="<?php echo SITE_ASSETS; ?>/vendor/PersianDate/dist/persian-date.min.js"></script>
    <script src="<?php echo SITE_ASSETS; ?>/vendor/persian-datepicker/dist/js/persian-datepicker.min.js"></script>
    <script src="<?php echo SITE_ASSETS; ?>/vendor/persian-datepicker/assets/persian-datepicker.min.js"></script>
    <script src="<?php echo SITE_ASSETS; ?>/js/ConverterPersianNumbers.js"></script> <script src="<?php echo SITE_ASSETS; ?>/js/PersianNumbersInput.js"></script><script src="<?php echo SITE_ASSETS; ?>/js/hideMessage.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_IMAGES; ?>/favicon.ico"/> <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="<?php echo SITE_ASSETS; ?>/css/footer.css"> 
    <link rel="stylesheet" href="<?php echo SITE_ASSETS; ?>/css/ErrorSuccess-style.css"> <?php if ($page_css): ?>
        
        <link rel="stylesheet" href="<?php echo SITE_ASSETS; ?>/css/<?php echo $page_css; ?>">
        <?php endif; ?>
        
        <?php if ($datepicker_css): ?>
            <link rel="stylesheet" href="<?php echo SITE_ASSETS; ?>/vendor/persian-datepicker/dist/css/persian-datepicker.min.css"> <?php endif; ?>
            <link rel="stylesheet" href="<?php echo SITE_ASSETS; ?>/css/header-nav.css">

    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
</head>
<body>

<header class="site-header">
    <div class="header-content">
        <div class="header-right">
            <img class="profile-pic" src="<?php echo SITE_IMAGES; ?>/profile-placeholder.png" alt="تصویر پروفایل"> <div class="user-info">
                <span class="username"><?php echo isset($_SESSION['user_logged']['name']) ? htmlspecialchars($_SESSION['user_logged']['name']) : 'کاربر مهمان'; ?></span>
                <span class="user-role"><?php echo isset($_SESSION['user_logged']['role']) ? htmlspecialchars($_SESSION['user_logged']['role']) : ' '; ?></span>
            </div>
            <span class="current-date" id="header-current-date"></span> </div>
        <div class="header-left">
             <a href="<?php echo SITE_URL; ?>settings.php" class="settings-link" title="تنظیمات">
                 <img src="<?php echo SITE_IMAGES; ?>/icons/setting-3.svg" alt="تنظیمات"> </a>
             <a href="<?php echo SITE_URL; ?>logout.php" class="logout-link" title="خروج">
                 <img src="<?php echo SITE_IMAGES; ?>/icons/logout.svg" alt="خروج"> </a>
        </div>
    </div>
</header>

<nav class="main-navbar">
    <ul class="navbar-menu">
        <li class="<?php echo ($active_menu == 'Home.php') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>Home.php">
                 <img src="<?php echo SITE_IMAGES; ?>/icons/home.svg" alt=""> خانه
            </a>
        </li>
        <li class="has-submenu <?php echo ($active_menu == 'menu-aza') ? 'active-parent' : ''; ?>">
            <a href="#">
                 <img src="<?php echo SITE_IMAGES; ?>/icons/users.svg" alt=""> منوی دانش آموزان <span class="submenu-indicator"></span>
            </a>
            <ul class="submenu">
                <li class="<?php echo ($active_submenu == 'StudentsAdd.php') ? 'active' : ''; ?>">
                    <a href="<?php echo SITE_URL; ?>menu-aza/StudentsAdd.php">افزودن دانش آموزان</a>
                </li>
                <li class="<?php echo ($active_submenu == 'StudentsList.php') ? 'active' : ''; ?>">
                    <a href="<?php echo SITE_URL; ?>menu-aza/StudentsList.php">نمایش دانش آموزان</a>
                </li>
            </ul>
        </li>
        <li class="has-submenu <?php echo ($active_menu == 'hesabdari') ? 'active-parent' : ''; ?>">
            <a href="#">
                 <img src="<?php echo SITE_IMAGES; ?>/icons/accounting.svg" alt=""> منوی حسابداری <span class="submenu-indicator"></span>
            </a>
            <ul class="submenu">
                 <li class="<?php echo ($active_submenu == 'debtsReport.php') ? 'active' : ''; ?>"><a href="<?php echo SITE_URL; ?>hesabdari/debtsReport.php">لیست بدهی ها</a></li>
                 <li class="<?php echo ($active_submenu == 'add_debt.php') ? 'active' : ''; ?>"><a href="<?php echo SITE_URL; ?>hesabdari/add_debt.php">ثبت بدهی</a></li>
                 <li class="<?php echo ($active_submenu == 'add_payment.php') ? 'active' : ''; ?>"><a href="<?php echo SITE_URL; ?>hesabdari/add_payment.php">ثبت پرداخت</a></li>
                 <li class="<?php echo ($active_submenu == 'taghsit.php') ? 'active' : ''; ?>"><a href="<?php echo SITE_URL; ?>hesabdari/taghsit.php">تقسیط بدهی</a></li>
                 <li class="<?php echo ($active_submenu == 'takhfif.php') ? 'active' : ''; ?>"><a href="<?php echo SITE_URL; ?>hesabdari/takhfif.php">ثبت تخفیف</a></li>
            </ul>
        </li>
         <li class="has-submenu <?php echo ($active_menu == 'reports') ? 'active-parent' : ''; ?>">
             <a href="#">
                  <img src="<?php echo SITE_IMAGES; ?>/icons/report-2.svg" alt=""> منوی گزارش ها <span class="submenu-indicator"></span>
             </a>
             <ul class="submenu">
                  <li class="<?php echo ($active_submenu == 'listhesabAza.php') ? 'active' : ''; ?>"><a href="<?php echo SITE_URL; ?>listhesabAza.php">تراز صندوق</a></li>
                  <li class="<?php echo ($active_submenu == 'SorateHesab.php') ? 'active' : ''; ?>"><a href="<?php echo SITE_URL; ?>SorateHesab.php">گزارش مالی</a></li>
                  <li class="<?php echo ($active_submenu == 'SorateHesabVizhe.php') ? 'active' : ''; ?>"><a href="<?php echo SITE_URL; ?>SorateHesabVizhe.php">گزارش تقسیط</a></li>
             </ul>
         </li>
         <li class="<?php echo ($active_menu == 'userAdd.php') ? 'active' : ''; ?>">
             <a href="<?php echo SITE_URL; ?>userAdd.php">
                 <img src="<?php echo SITE_IMAGES; ?>/icons/users.svg" alt=""> افزودن کاربر
             </a>
         </li>
    </ul>
</nav>