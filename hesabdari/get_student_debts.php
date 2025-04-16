<?php

// --- تنظیمات خطا برای AJAX ---
ini_set('display_errors', 0); // عدم نمایش خطاها در خروجی
error_reporting(E_ALL); // همچنان همه خطاها را برای لاگ شدن گزارش کن (در صورت تنظیم لاگ سرور)

header('Content-Type: application/json; charset=utf-8');
// --- مسیردهی مطمئن ---
// __DIR__ مسیر پوشه ای است که get_student_debts.php در آن قرار دارد (مثلا .../hesabdari)
// dirname(__DIR__) مسیر پوشه والد را می‌دهد (مثلا .../DavaniAccounting)
$basePathAjax = dirname(__DIR__); // مسیر پوشه DavaniAccounting

// شامل کردن فایل‌ها با استفاده از مسیر پایه جدید
// بررسی وجود فایل قبل از include (برای اطمینان بیشتر)
$jdfPath = $basePathAjax . "/assets/vendor/Jalali-Date/jdf.php";
if (file_exists($jdfPath)) {
    include_once $jdfPath;
} else {
    // خروج با خطای JSON در صورت عدم وجود فایل
    error_log("File not found in AJAX: " . $jdfPath);
    echo json_encode(['error' => 'Server configuration error: Date library not found.', 'details' => $jdfPath]);
    exit;
}

$dbPath = $basePathAjax . "/db_connection.php";
 if (file_exists($dbPath)) {
    include_once $dbPath;
} else {
    error_log("File not found in AJAX: " . $dbPath);
    echo json_encode(['error' => 'Server configuration error: DB connection not found.', 'details' => $dbPath]);
    exit;
}

// ادامه کد شما...
// include_once "../convertToPersianNumbers.php"; // این هم باید بررسی شود
$converterPath = $basePathAjax . "/convertToPersianNumbers.php";
 if (file_exists($converterPath)) {
    include_once $converterPath;
} // else: شاید این فایل ضروری نباشد؟ اگر هست، خطا دهید


// بقیه کد get_student_debts.php ...

$response = ['total_debt' => 0, 'debts' => []];

// ... (بقیه کد برای اتصال به دیتابیس و واکشی اطلاعات) ...
 // **مهم:** مطمئن شوید تابع jdate حالا در دسترس است
 if (isset($student_id) && $student_id) {
     try {
        // ... اتصال دیتابیس ...
         if ($cnn) {
             // ... کوئری ...
             if ($result) {
                while ($row = $result->fetch_assoc()) {
                     // ... محاسبه مجموع ...
                     $jalaliDate = '-';
                    if (!empty($row['date'])) {
                        // **اینجا باید تابع jdate کار کند**
                        if (function_exists('jdate')) { // بررسی وجود تابع
                            try {
                                $timestamp = strtotime($row['date']);
                                if ($timestamp) {
                                    $jalaliDate = jdate('Y/m/d', $timestamp);
                                } else { $jalaliDate = $row['date']; }
                            } catch(Exception $e) { /* ... */ }
                        } else {
                            // اگر jdate لود نشده باشد، تاریخ میلادی را نشان بده یا خطا بده
                            $jalaliDate = $row['date'] . ' (jdate fail)';
                            error_log("jdate function not available in get_student_debts.php");
                        }
                    }
                    // ... بقیه ساخت آرایه ...
                    $debts_list[] = [
                       // ... fields ...
                        'jalali_date' => $jalaliDate
                    ];
                }
                // ...
             }
             // ...
         }
     } catch (Exception $e) { /* ... */ }
 }


echo json_encode($response);
exit;
?>