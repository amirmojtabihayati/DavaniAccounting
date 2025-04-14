<?php
include "../header.php";
include_once "../convertToPersianNumbers.php";
include_once "../Jalali-Date/jdf.php"; // اطمینان از بارگذاری درست کتابخانه jdf

include "../db_connection.php";
$cnn = (new class_db())->connection_database;

$errors = [];
$success_msg = [];

$first_name = "";
$last_name = "";

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // استفاده از JOIN برای اتصال دو جدول
    $sql = "SELECT s.first_name, s.last_name, s.national_code, d.title, d.amount, d.approval_number, d.date 
            FROM debts d 
            JOIN students s ON s.id = d.student_id 
            WHERE d.student_id = ?";
    $stmt = $cnn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id); // اطمینان از استفاده از نوع داده صحیح
        $stmt->execute();
        $result = $stmt->get_result();
        
        // دریافت نام و نام خانوادگی دانش‌آموز
        if ($row = $result->fetch_assoc()) {
            $first_name = htmlspecialchars($row['first_name']);
            $last_name = htmlspecialchars($row['last_name']);
            $nationalCode = htmlspecialchars($row['national_code']);
            $result->data_seek(0); // بازگشت به ابتدای نتیجه برای حلقه بعدی
        }
    } else {
        $errors[] = "خطا در آماده سازی پرس و جو";
    }
}
?>
<div class="debt-container">
    <div style="box-shadow: 1px 1px 27px -1px;
                background-color: whitesmoke;
                margin: auto;
                max-width: 75%;
                padding: 20px;
                border-radius: 5px;">

        <div style="display: flex; justify-content: space-between;">
            <h1 style="margin: auto;">وضعیت بدهی دانش‌آموز <?= $first_name . ' ' . $last_name?></h1>
            <a href="debtsReport.php" title="بازگشت" style="display: inline-flex; float: left;">
                <svg xmlns="http://www.w3.org/2000/svg" 
                    width="41" height="41" viewBox="0 0 24 24" fill="none" stroke="#265c7b" 
                    stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left-circle"> 
                    <circle 
                    cx="12" cy="12" r="10"/><polyline points="12 8 8 12 12 16"/><line x1="16" y1="12" x2="8" y2="12"/>
                </svg>
            </a>
        </div><hr>
        <h1 style="text-align: center;">کد ملی: <?= convertToPersianNumbers($nationalCode) ?></h1><hr>
        <table id="payments_table" style="width: 100%; border-collapse: collapse; margin: auto; text-align: center;">
            <tr>
                <th>عنوان</th>
                <th>مقدار</th>
                <th>شماره مصوبه</th>
                <th>تاریخ</th>
            </tr>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $national_code = htmlspecialchars($row['national_code']);
                    $title = htmlspecialchars($row['title']);
                    $amount = htmlspecialchars($row['amount']);
                    $approval_number = htmlspecialchars($row['approval_number']);
                    $date = htmlspecialchars($row['date']);

                    // تبدیل تاریخ میلادی به timestamp
                    $timestamp = strtotime($date); 
                    // تبدیل به تاریخ شمسی
                    $jalaliDate = jdate('Y/m/d', $timestamp); 
            ?>
            <tr class="debt_tr">
                <td style="border-bottom: 1px solid black;"><?= $title; ?></td>
                <td style="border-bottom: 1px solid black;"><?= convertToPersianNumbers(number_format($amount, 0, ',', ',')); ?></td>
                <td style="border-bottom: 1px solid black;"><?= convertToPersianNumbers($approval_number); ?></td>
                <td style="border-bottom: 1px solid black;"><?= convertToPersianNumbers($jalaliDate); ?></td> <!-- تاریخ تبدیل شده به شمسی -->
            </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='6' style='border-bottom: 1px solid black;'>هیچ داده‌ای موجود نیست.</td></tr>";
            }
            ?>
        </table>
    </div>
</div>
<?php
include "../footer.php";
?>