<?php
// این فایل باید فقط مبلغ بدهی را به عنوان خروجی چاپ کند

include "../db_connection.php"; // اتصال به دیتابیس
include_once "../convertToPersianNumbers.php"; // برای استفاده احتمالی در آینده یا توابع کمکی

// تابع کمکی تبدیل اعداد فارسی/عربی به انگلیسی (اگر در db_connection نیست)
if (!function_exists('convertToEnglishNumbers')) {
    function convertToEnglishNumbers($string) {
         $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '،'];
         $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٬'];
         $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ''];
         $string = str_replace(',', '', $string);
         return str_replace($persian, $english, str_replace($arabic, $english, $string));
    }
}


header('Content-Type: text/plain'); // تنظیم هدر برای خروجی متنی ساده

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['national_code']) && isset($_POST['title'])) {
    $cnn = (new class_db())->connection_database;

    $national_code = trim($_POST['national_code']);
    $debt_title = trim($_POST['title']);

    // ابتدا ID دانش آموز را بگیرید
    $stmt_student = $cnn->prepare("SELECT id FROM students WHERE national_code = ?");
    $stmt_student->bind_param("s", $national_code);
    $stmt_student->execute();
    $result_student = $stmt_student->get_result();

    if ($student = $result_student->fetch_assoc()) {
        $student_id = $student['id'];

        // حالا مبلغ بدهی را بگیرید
        // فرض کنیم ستون مبلغ در جدول debts نامش 'amount' است
        $stmt_debt = $cnn->prepare("SELECT amount FROM debts WHERE student_id = ? AND title = ?");
        $stmt_debt->bind_param("is", $student_id, $debt_title);
        $stmt_debt->execute();
        $result_debt = $stmt_debt->get_result();

        if ($debt = $result_debt->fetch_assoc()) {
            // مبلغ را به صورت عددی خام چاپ کنید
            echo $debt['amount'];
        } else {
            echo '0'; // یا مقدار خطای دیگر
        }
        $stmt_debt->close();
    } else {
        echo '0'; // دانش آموز یافت نشد
    }
    $stmt_student->close();
    $cnn->close();

} else {
    echo '0'; // پارامترهای لازم ارسال نشده
}
?>