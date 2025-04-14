<?php
include "../db_connection.php"; // اتصال به پایگاه داده

$cnn = (new class_db())->connection_database;

if (isset($_POST['title']) && isset($_POST['national_code'])) {
    $debt_title = $_POST['title'];
    $national_code = $_POST['national_code'];

    // جستجوی مقدار بدهی بر اساس عنوان و کد ملی
    $stmt = $cnn->prepare("SELECT id FROM students WHERE national_code = ?");
    $stmt->bind_param("s", $national_code);
    $stmt->execute();
    $stmt->bind_result($student_id);
    $stmt->fetch();
    $stmt->close();
    $stmt = $cnn->prepare("SELECT amount FROM debts WHERE title = ? AND student_id = ?");
    $stmt->bind_param("ss", $debt_title, $student_id);
    $stmt->execute();
    $stmt->bind_result($debt_amount);
    $stmt->fetch();
    
    // اگر بدهی وجود داشته باشد، مقدار آن را ارسال کنید
    echo $debt_amount ? $debt_amount : 'مقدار بدهی موجود نیست';
    
    $stmt->close();
}

$cnn->close();
?>