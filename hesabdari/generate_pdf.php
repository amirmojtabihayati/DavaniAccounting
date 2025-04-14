<?php
require_once('../vendor/autoload.php');

include "../db_connection.php";
$cnn = (new class_db())->connection_database;

// تنظیمات برای دریافت داده‌ها
$searchCondition = "";
$params = [];

// اگر داده‌های جستجو وجود داشته باشد، آن‌ها را دریافت کنید
if (isset($_GET['data'])) {
    $q_json = $_GET['data'];
    $student = json_decode($q_json);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'Error decoding JSON: ' . json_last_error_msg();
        exit();
    }

    function cleanInput($data) {
        return htmlspecialchars(trim($data));
    }

    // Clean student data
    $name = isset($student->name) ? cleanInput($student->name) : '';
    $family = isset($student->family) ? cleanInput($student->family) : '';
    $natCode = isset($student->natCode) ? cleanInput($student->natCode) : '';
    $field = isset($student->field) ? cleanInput($student->field) : '';
    $grade = isset($student->grade) ? cleanInput($student->grade) : '';
    $approval_number = isset($student->approval_number) ? cleanInput($student->approval_number) : '';

    // Prepare search conditions
    if (!empty($name)) {
        $searchCondition .= " AND s.`first_name` LIKE ?";
        $params[] = "%$name%";
    }
    if (!empty($family)) {
        $searchCondition .= " AND s.`last_name` LIKE ?";
        $params[] = "%$family%";
    }
    if (!empty($natCode)) {
        $searchCondition .= " AND s.`national_code` LIKE ?";
        $params[] = "%$natCode%";
    }
    if (!empty($field)) {
        $searchCondition .= " AND d.`field` LIKE ?";
        $params[] = "%$field%";
    }
    if (!empty($grade)) {
        $searchCondition .= " AND d.`grade` LIKE ?";
        $params[] = "$grade";
    }
    if (!empty($approval_number)) {
        $searchCondition .= " AND d.`approval_number` LIKE ?";
        $params[] = "%$approval_number%";
    }
}

// بارگذاری دانش‌آموزان و بدهی‌ها
$sql = "SELECT s.first_name, s.last_name, s.national_code, d.amount, d.date FROM `students` s
        JOIN `debts` d ON s.id = d.student_id
        WHERE 1=1 $searchCondition";

$stmt = $cnn->prepare($sql);
if ($params) {
    $types = str_repeat('s', count($params)); // Assuming all parameters are strings
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ایجاد PDF
$pdf = new TCPDF();
$pdf->SetMargins(10, 10, 10); // تنظیم حاشیه‌ها
$pdf->AddPage();

// اضافه کردن فونت فارسی
$pdf->AddFont('Yekan', '', '../assets/css/font/Yekan.eot', true); // اطمینان حاصل کنید که فایل فونت در مسیر درست قرار دارد
$pdf->SetFont('Yekan', '', 12); // استفاده از فونت فارسی

// عنوان
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'گزارش بدهی دانش‌آموزان', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'تاریخ: ' . date('Y-m-d'), 0, 1, 'C');
$pdf->Ln(10); // فاصله بین عنوان و جدول

// جدول هدر
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(200, 220, 255); // رنگ پس‌زمینه هدر
$pdf->Cell(40, 10, 'نام', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'نام خانوادگی', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'کد ملی', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'مقدار بدهی', 1, 0, 'C', 1);
$pdf->Cell(40, 10, 'تاریخ بدهی', 1, 1, 'C', 1); // Line break after header

// محتویات جدول
$pdf->SetFont('helvetica', '', 12);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(40, 10, htmlspecialchars($row['first_name']), 1);
        $pdf->Cell(40, 10, htmlspecialchars($row['last_name']), 1);
        $pdf->Cell(40, 10, htmlspecialchars($row['national_code']), 1);
        $pdf->Cell(40, 10, htmlspecialchars($row['amount']), 1);
        $pdf->Cell(40, 10, htmlspecialchars($row['date']), 1);
        $pdf->Ln(); // خط جدید برای ردیف بعدی
    }
} else {
    $pdf->Cell(200, 10, 'هیچ داده‌ای موجود نیست.', 1, 1, 'C');
}

// خروجی PDF
$filePath = $_SERVER['DOCUMENT_ROOT'] . '/DavaniSchool/debtsr_report.pdf';
if (!$pdf->Output($filePath, 'F')) {
    error_log('Failed to create PDF file at: ' . $filePath);
} else {
    echo "PDF created successfully at: " . $filePath;
}
?>