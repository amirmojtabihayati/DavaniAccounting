<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // در محیط عملیاتی غیرفعال شود

require '../vendor/autoload.php'; // مسیر Autoloader برای PhpSpreadsheet (با Composer)

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

include_once "../convertToPersianNumbers.php"; // برای تبدیل اعداد (اختیاری در اکسل)
include_once "../assets/vendor/Jalali-Date/jdf.php"; // برای تاریخ شمسی
include_once "../db_connection.php"; // اتصال به دیتابیس

// --- دریافت و پردازش پارامترهای جستجو (مشابه PDF) ---
$searchCondition = "";
$params = [];
$paramTypes = '';
$searchData = []; // برای نمایش در صورت نیاز در اکسل

if (isset($_GET['data'])) {
     $q_json = $_GET['data'];
     $student_search = json_decode($q_json);

     if (json_last_error() === JSON_ERROR_NONE) {
         function cleanInputExcel($data) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
         $name = isset($student_search->name) ? cleanInputExcel($student_search->name) : '';
         $family = isset($student_search->family) ? cleanInputExcel($student_search->family) : '';
         $natCode = isset($student_search->natCode) ? cleanInputExcel($student_search->natCode) : '';
         $field = isset($student_search->field) ? cleanInputExcel($student_search->field) : '';
         $grade = isset($student_search->grade) ? cleanInputExcel($student_search->grade) : '';
         $approval_number = isset($student_search->approval_number) ? cleanInputExcel($student_search->approval_number) : '';

          $searchData = [ 'نام'=>$name, 'نام خانوادگی'=>$family, 'کد ملی'=>$natCode, 'رشته'=>$field, 'پایه'=>$grade, 'شماره مصوبه'=>$approval_number ];

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
        die("Error decoding search parameters for Excel.");
    }
}


// --- اتصال به دیتابیس (مشابه PDF) ---
try {
    $db = new class_db();
    $cnn = $db->connection_database;
     if ($cnn) {
        $cnn->set_charset("utf8mb4");
    } else {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    error_log("Excel Generation DB Error: " . $e->getMessage());
    die("Error connecting to database for Excel generation.");
}

// --- کوئری برای دریافت *تمام* نتایج (مشابه PDF) ---
$sql = "SELECT s.id, s.first_name, s.last_name, s.national_code,
               SUM(d.amount) as student_total_debt,
               MAX(d.date) as last_debt_date
        FROM `students` s
        JOIN `debts` d ON s.id = d.student_id
        WHERE 1=1 $searchCondition
        GROUP BY s.id, s.first_name, s.last_name, s.national_code
        ORDER BY s.last_name, s.first_name";

$stmt = $cnn->prepare($sql);
if (!$stmt) { /* ... Error handling ... */ die("Error preparing query for Excel."); }
if (!empty($params)) {
    if (!$stmt->bind_param($paramTypes, ...$params)) { /* ... Error handling ... */ die("Error binding params for Excel."); }
}
if (!$stmt->execute()) { /* ... Error handling ... */ die("Error executing query for Excel."); }
$result = $stmt->get_result();
if (!$result) { /* ... Error handling ... */ die("Error fetching results for Excel."); }


// --- ایجاد فایل اکسل ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// تنظیم جهت شیت به راست به چپ
$sheet->setRightToLeft(true);

// تنظیم عنوان شیت
$sheet->setTitle('گزارش بدهی دانش آموزان');

// --- نوشتن هدرها ---
$headerRow = 1;
$sheet->setCellValue('A'.$headerRow, 'ردیف');
$sheet->setCellValue('B'.$headerRow, 'نام و نام خانوادگی');
$sheet->setCellValue('C'.$headerRow, 'کد ملی');
$sheet->setCellValue('D'.$headerRow, 'مجموع بدهی (تومان)');
$sheet->setCellValue('E'.$headerRow, 'تاریخ آخرین بدهی (شمسی)');
$sheet->setCellValue('F'.$headerRow, 'تاریخ آخرین بدهی (میلادی)'); // تاریخ میلادی برای مرتب سازی بهتر

// --- استایل دهی به هدرها ---
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '007BFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$sheet->getStyle('A'.$headerRow.':F'.$headerRow)->applyFromArray($headerStyle);


// --- نوشتن داده ها در شیت ---
$rowNumber = $headerRow + 1; // شروع داده ها از ردیف بعد از هدر
$counter = 1; // شماره ردیف
$total_grand_debt_excel = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $fullName = ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '');
        $nat_code = $row['national_code'] ?? '';
        $student_debt = $row['student_total_debt'] ?? 0;
        $last_date_gregorian = $row['last_debt_date'] ?? null;
        $total_grand_debt_excel += $student_debt;

        $jalaliDate = '-';
        if ($last_date_gregorian) {
            try {
                $timestamp = strtotime($last_date_gregorian);
                if ($timestamp) {
                    $jalaliDate = jdate('Y/m/d', $timestamp);
                    // فرمت میلادی برای اکسل (ممکن است برای مرتب سازی عددی بهتر باشد)
                    $excel_date_gregorian = date('Y-m-d', $timestamp);
                } else {
                    $excel_date_gregorian = '-';
                }
            } catch (Exception $e) {
                 $jalaliDate = 'خطا';
                 $excel_date_gregorian = 'خطا';
            }
        } else {
             $excel_date_gregorian = '-';
        }


        $sheet->setCellValue('A'.$rowNumber, $counter++);
        $sheet->setCellValue('B'.$rowNumber, $fullName);
        // کد ملی را به صورت رشته وارد کنید تا صفرهای اول حفظ شوند
        $sheet->setCellValueExplicit('C'.$rowNumber, $nat_code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        // بدهی را به صورت عدد وارد کنید
        $sheet->setCellValue('D'.$rowNumber, $student_debt);
        $sheet->setCellValue('E'.$rowNumber, $jalaliDate); // تاریخ شمسی برای نمایش
        $sheet->setCellValue('F'.$rowNumber, $excel_date_gregorian); // تاریخ میلادی

        // تنظیم فرمت عددی برای ستون بدهی (تومان)
        $sheet->getStyle('D'.$rowNumber)->getNumberFormat()->setFormatCode('#,##0');

        $rowNumber++;
    }
} else {
    // ادغام سلول ها برای نمایش پیام "یافت نشد"
    $sheet->mergeCells('A'.$rowNumber.':F'.$rowNumber);
    $sheet->setCellValue('A'.$rowNumber, 'هیچ رکوردی یافت نشد.');
    $sheet->getStyle('A'.$rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNumber++;
}

// --- افزودن ردیف مجموع کل ---
$sheet->setCellValue('C'.$rowNumber, 'مجموع کل:'); // در ستون کد ملی یا نام خانوادگی
$sheet->setCellValue('D'.$rowNumber, $total_grand_debt_excel); // مقدار مجموع کل
// استایل دهی به ردیف مجموع
$totalStyle = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]],
];
$sheet->getStyle('C'.$rowNumber.':D'.$rowNumber)->applyFromArray($totalStyle);
$sheet->getStyle('D'.$rowNumber)->getNumberFormat()->setFormatCode('#,##0'); // فرمت عدد


// --- تنظیمات نهایی شیت ---
// تنظیم عرض خودکار ستون‌ها (ممکن است برای فارسی کاملا دقیق نباشد)
foreach (range('A', 'F') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}
// عرض ستون کد ملی را کمی بیشتر کنید
$sheet->getColumnDimension('C')->setWidth(18);
$sheet->getColumnDimension('B')->setWidth(25); // عرض ستون نام


// بستن استیتمنت و اتصال دیتابیس
$stmt->close();
$cnn->close();

// --- ایجاد و خروجی فایل ---
$writer = new Xlsx($spreadsheet);

// نام فایل منحصر به فرد
$timestamp = date('Ymd_His');
$filename = "بدهی دانش آموزان_{$timestamp}.xlsx";

// تنظیم هدرها برای دانلود فایل Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
// If you're serving to IE 9, then the following may be needed
header('Cache-Control: max-age=1');
// If you're serving to IE over SSL, then the following may be needed
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header('Pragma: public'); // HTTP/1.0

// ذخیره در خروجی PHP
$writer->save('php://output');

exit; // اطمینان از پایان اسکریپت
?>