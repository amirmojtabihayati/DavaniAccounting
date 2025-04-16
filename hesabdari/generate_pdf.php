<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // در محیط عملیاتی غیرفعال شود

require_once('../vendor/autoload.php'); // اگر از Composer استفاده می‌کنید
// یا مسیر مستقیم به فایل tcpdf.php:
// require_once('../assets/vendor/tcpdf/tcpdf.php');

include_once "../convertToPersianNumbers.php"; // برای تبدیل اعداد
include_once "../assets/vendor/Jalali-Date/jdf.php"; // برای تاریخ شمسی
include_once "../db_connection.php"; // اتصال به دیتابیس

// --- دریافت و پردازش پارامترهای جستجو ---
$searchCondition = "";
$params = [];
$paramTypes = '';
$searchData = [];

if (isset($_GET['data'])) {
    $q_json = $_GET['data'];
    $student_search = json_decode($q_json);

    if (json_last_error() === JSON_ERROR_NONE) {
         // تابع پاکسازی ورودی
        function cleanInputPdf($data) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }

        $name = isset($student_search->name) ? cleanInputPdf($student_search->name) : '';
        $family = isset($student_search->family) ? cleanInputPdf($student_search->family) : '';
        $natCode = isset($student_search->natCode) ? cleanInputPdf($student_search->natCode) : '';
        $field = isset($student_search->field) ? cleanInputPdf($student_search->field) : '';
        $grade = isset($student_search->grade) ? cleanInputPdf($student_search->grade) : '';
        $approval_number = isset($student_search->approval_number) ? cleanInputPdf($student_search->approval_number) : '';

        // ذخیره برای نمایش در PDF
        $searchData = [
            'نام' => $name,
            'نام خانوادگی' => $family,
            'کد ملی' => $natCode,
            'رشته' => $field,
            'پایه' => $grade,
            'شماره مصوبه' => $approval_number
        ];

        // ساخت بخش WHERE کوئری (مشابه صفحه اصلی)
        $conditions = [];
         if (!empty($name)) { $conditions[] = "s.`first_name` LIKE ?"; $params[] = "%$name%"; $paramTypes .= 's'; }
         if (!empty($family)) { $conditions[] = "s.`last_name` LIKE ?"; $params[] = "%$family%"; $paramTypes .= 's'; }
         if (!empty($natCode)) { $conditions[] = "s.`national_code` LIKE ?"; $params[] = "%$natCode%"; $paramTypes .= 's'; }
         if (!empty($field)) { $conditions[] = "s.`field` LIKE ?"; $params[] = "%$field%"; $paramTypes .= 's'; }
         if (!empty($grade)) { $conditions[] = "s.`grade` LIKE ?"; $params[] = "$grade"; $paramTypes .= 's'; }
         if (!empty($approval_number)) { $conditions[] = "d.`approval_number` LIKE ?"; $params[] = "%$approval_number%"; $paramTypes .= 's'; }

         if (!empty($conditions)) {
            $searchCondition = " AND " . implode(" AND ", $conditions);
        }
    } else {
        die("Error decoding search parameters for PDF.");
    }
}

// --- اتصال به دیتابیس ---
try {
    $db = new class_db();
    $cnn = $db->connection_database;
    if ($cnn) {
        $cnn->set_charset("utf8mb4");
    } else {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    error_log("PDF Generation DB Error: " . $e->getMessage());
    die("Error connecting to database for PDF generation.");
}

// --- کوئری برای دریافت *تمام* نتایج مطابق با جستجو (بدون LIMIT) ---
// **مهم:** کوئری مشابه صفحه اصلی است اما بدون LIMIT
$sql = "SELECT s.id, s.first_name, s.last_name, s.national_code,
               SUM(d.amount) as student_total_debt,
               MAX(d.date) as last_debt_date
        FROM `students` s
        JOIN `debts` d ON s.id = d.student_id
        WHERE 1=1 $searchCondition
        GROUP BY s.id, s.first_name, s.last_name, s.national_code
        ORDER BY s.last_name, s.first_name";

$stmt = $cnn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (PDF sql): (" . $cnn->errno . ") " . $cnn->error);
    die("Error preparing query for PDF generation.");
}

if (!empty($params)) {
    if (!$stmt->bind_param($paramTypes, ...$params)) {
         error_log("Binding parameters failed (PDF sql): (" . $stmt->errno . ") " . $stmt->error);
         die("Error binding parameters for PDF generation.");
    }
}

if (!$stmt->execute()) {
     error_log("Execute failed (PDF sql): (" . $stmt->errno . ") " . $stmt->error);
     die("Error executing query for PDF generation.");
}

$result = $stmt->get_result();
if (!$result) {
      error_log("Getting result failed (PDF sql): (" . $stmt->errno . ") " . $stmt->error);
      die("Error fetching results for PDF generation.");
}

// --- ایجاد PDF ---
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// تنظیمات سند
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Your School/Organization Name');
$pdf->SetTitle('گزارش بدهی دانش آموزان');
$pdf->SetSubject('گزارش بدهی');
$pdf->SetKeywords('بدهی, دانش آموزان, گزارش');

// تنظیم فونت فارسی
$pdf->SetFont('dejavusans', '', 10); // فونت پیش فرض TCPDF که فارسی را پشتیبانی می‌کند

// افزودن صفحه
$pdf->AddPage();

// --- نوشتن محتوا ---
$pdf->SetRTL(true); // فعال کردن راست به چپ

// عنوان گزارش
$pdf->SetFont('dejavusans', 'B', 14); // فونت بولد برای عنوان
$pdf->Cell(0, 10, 'گزارش بدهی دانش آموزان', 0, 1, 'C'); // 0: width=auto, 1: newline, C: center
$pdf->Ln(5); // فاصله

// نمایش تاریخ گزارش
$pdf->SetFont('dejavusans', '', 9);
$reportDate = jdate('Y/m/d H:i:s'); // تاریخ و زمان فعلی شمسی
$pdf->Cell(0, 7, 'تاریخ گزارش: ' . convertToPersianNumbers($reportDate), 0, 1, 'L'); // L: Left (چون RTL است، در سمت راست نمایش داده می‌شود)

// نمایش پارامترهای جستجو (اگر وجود داشت)
$searchCriteriaString = '';
foreach ($searchData as $key => $value) {
    if (!empty($value)) {
        $searchCriteriaString .= $key . ': ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '  |  ';
    }
}
if (!empty($searchCriteriaString)) {
     $pdf->Cell(0, 7, 'فیلترهای جستجو: ' . rtrim($searchCriteriaString, ' | '), 0, 1, 'L');
}
$pdf->Ln(5);


// ایجاد هدر جدول HTML (ساده‌ترین راه با TCPDF)
$html = '<table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr style="background-color:#007bff; color:#ffffff;">
                    <th width="30%">نام و نام خانوادگی</th>
                    <th width="25%">کد ملی</th>
                    <th width="25%">مجموع بدهی (تومان)</th>
                    <th width="20%">تاریخ آخرین بدهی</th>
                </tr>
            </thead>
            <tbody>';

$total_grand_debt_pdf = 0; // محاسبه مجموع کل برای نمایش در PDF

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $fullName = htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $nat_code = htmlspecialchars($row['national_code'] ?? '', ENT_QUOTES, 'UTF-8');
        $student_debt = $row['student_total_debt'] ?? 0;
        $last_date = $row['last_debt_date'] ?? null;
        $total_grand_debt_pdf += $student_debt; // افزودن به مجموع کل

        $jalaliDate = '-';
        if ($last_date) {
            try {
                $timestamp = strtotime($last_date);
                if ($timestamp) {
                    $jalaliDate = jdate('Y/m/d', $timestamp);
                }
            } catch (Exception $e) { $jalaliDate = 'خطا'; }
        }

        $html .= '<tr>
                    <td>' . convertToPersianNumbers($fullName) . '</td>
                    <td>' . convertToPersianNumbers($nat_code) . '</td>
                    <td align="right">' . convertToPersianNumbers(number_format($student_debt)) . '</td>
                    <td>' . convertToPersianNumbers($jalaliDate) . '</td>
                  </tr>';
    }
} else {
    $html .= '<tr><td colspan="4" align="center">هیچ رکوردی یافت نشد.</td></tr>';
}

$html .= '</tbody></table>';

// افزودن مجموع کل به PDF (بعد از جدول)
$pdf->SetFont('dejavusans', 'B', 10);
$totalDebtString = 'مجموع کل بدهی‌ها: ' . convertToPersianNumbers(number_format($total_grand_debt_pdf)) . ' تومان';
$pdf->Cell(0, 10, $totalDebtString, 0, 1, 'L'); // L در RTL یعنی راست چین
$pdf->Ln(5);

// نوشتن HTML جدول در PDF
$pdf->SetFont('dejavusans', '', 10); // بازگشت به فونت عادی
$pdf->writeHTML($html, true, false, true, false, '');

// بستن استیتمنت و اتصال دیتابیس
$stmt->close();
$cnn->close();

// --- خروجی PDF ---
// نام فایل منحصر به فرد
$timestamp = date('Ymd_His');
$filename = "بدهی دانش آموزان_{$timestamp}.pdf";

// خروجی مستقیم به مرورگر برای دانلود
$pdf->Output($filename, 'D'); // D: Force Download

exit; // اطمینان از پایان اسکریپت

?>