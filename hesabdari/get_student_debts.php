<?php
include "../db_connection.php"; // اتصال به پایگاه داده
$cnn = (new class_db())->connection_database;

// تنظیم هدر برای پاسخ JSON
header('Content-Type: application/json');

// بررسی وجود ID دانش‌آموز در GET
$studentId = isset($_GET['student_id']) ? intval(trim($_GET['student_id'])) : 0;

if ($studentId <= 0) {
    echo json_encode(['error' => "لطفاً ID دانش‌آموز معتبر را وارد کنید."]);
    exit();
}

// محاسبه مجموع بدهی دانش‌آموز
$debtQuery = $cnn->prepare("SELECT SUM(amount) AS total_debt FROM debts WHERE student_id = ?");
if (!$debtQuery) {
    echo json_encode(['error' => "خطا در آماده‌سازی پرس و جو: " . $cnn->error]);
    exit();
}

$debtQuery->bind_param("i", $studentId);
if (!$debtQuery->execute()) {
    echo json_encode(['error' => "خطا در اجرای پرس و جو: " . $debtQuery->error]);
    exit();
}

$debtResult = $debtQuery->get_result();
$totalDebt = $debtResult->fetch_assoc()['total_debt'] ?? 0; // استفاده از null به عنوان مقدار پیش‌فرض

// بررسی پرداختی‌ها
$paymentQuery = $cnn->prepare("SELECT payment_title, SUM(amount_paid) AS total_payment FROM payments WHERE student_id = ? GROUP BY payment_title");
if (!$paymentQuery) {
    echo json_encode(['error' => "خطا در آماده‌سازی پرس و جو: " . $cnn->error]);
    exit();
}

$paymentQuery->bind_param("i", $studentId);
if (!$paymentQuery->execute()) {
    echo json_encode(['error' => "خطا در اجرای پرس و جو: " . $paymentQuery->error]);
    exit();
}

$paymentResult = $paymentQuery->get_result();

$payments = [];
while ($row = $paymentResult->fetch_assoc()) {
    $payments[] = $row; // ذخیره اطلاعات پرداخت
}

// خروجی به فرمت JSON
$response = [
    'total_debt' => $totalDebt,
    'payments' => $payments
];

// اطمینان از اینکه payments یک آرایه خالی است اگر هیچ پرداختی وجود نداشته باشد
if (empty($payments)) {
    $response['payments'] = []; 
}

echo json_encode($response);
$cnn->close();
?>