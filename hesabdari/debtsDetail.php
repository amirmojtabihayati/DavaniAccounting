<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // در محیط عملیاتی غیرفعال شود

// --- شامل کردن فایل‌های ضروری ---
$basePath = __DIR__ . "/../";
include $basePath . "header.php";

$required_files_details = [
    "convertToPersianNumbers.php",
    "assets/vendor/Jalali-Date/jdf.php",
    "db_connection.php"
];

foreach ($required_files_details as $file) {
    $filepath = $basePath . $file;
    if (file_exists($filepath)) {
        include_once $filepath;
    } else {
        error_log("Required file not found (Details Page): " . $filepath);
        die("<div class='container'><div class='error-message'>خطای سیستمی: فایل ضروری یافت نشد ($file).</div></div>");
    }
}

if (!function_exists('convertToPersianNumbers')) { /* ... */ }
if (!function_exists('jdate')) { /* ... */ }

// --- دریافت و اعتبارسنجی ID دانش آموز ---
$student_id = null;
if (isset($_GET['id'])) {
    $student_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($student_id === false) {
        die("<div class='container'><div class='error-message'>شناسه دانش آموز نامعتبر است.</div></div>");
    }
} else {
     die("<div class='container'><div class='error-message'>شناسه دانش آموز مشخص نشده است.</div></div>");
}


// --- اتصال به پایگاه داده ---
try {
    $db = new class_db();
    $cnn = $db->connection_database;
    if ($cnn) {
        $cnn->set_charset("utf8mb4");
    } else {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    error_log("Debt Details DB Error: " . $e->getMessage());
    die("<div class='container'><div class='error-message'>خطا در اتصال به پایگاه داده.</div></div>");
}


// --- واکشی اطلاعات دانش آموز و بدهی ها ---
$first_name = "";
$last_name = "";
$nationalCode = "";
$debts_data = []; // آرایه برای نگهداری تمام رکوردهای بدهی
$errors = [];

// کوئری بهینه تر: ابتدا اطلاعات دانش آموز را جداگانه بگیرید
$sql_student_info = "SELECT first_name, last_name, national_code FROM students WHERE id = ?";
$stmt_student_info = $cnn->prepare($sql_student_info);
if ($stmt_student_info) {
    $stmt_student_info->bind_param("i", $student_id);
    $stmt_student_info->execute();
    $result_student_info = $stmt_student_info->get_result();
    if ($student_info = $result_student_info->fetch_assoc()) {
        $first_name = htmlspecialchars($student_info['first_name']);
        $last_name = htmlspecialchars($student_info['last_name']);
        $nationalCode = htmlspecialchars($student_info['national_code']);
    } else {
        $errors[] = "دانش آموزی با این شناسه یافت نشد.";
    }
    $stmt_student_info->close();
} else {
     $errors[] = "خطا در آماده سازی کوئری اطلاعات دانش آموز.";
     error_log("Prepare failed (student info): " . $cnn->error);
}


// اگر دانش آموز پیدا شد، بدهی هایش را واکشی کن
if (empty($errors)) {
    $sql_debts = "SELECT title, amount, approval_number, date
                  FROM debts
                  WHERE student_id = ?
                  ORDER BY title, date DESC"; // مرتب سازی بر اساس عنوان و سپس تاریخ

    $stmt_debts = $cnn->prepare($sql_debts);
    if ($stmt_debts) {
        $stmt_debts->bind_param("i", $student_id);
        $stmt_debts->execute();
        $result_debts = $stmt_debts->get_result();
        if ($result_debts) {
            $debts_data = $result_debts->fetch_all(MYSQLI_ASSOC); // دریافت همه نتایج در یک آرایه
        } else {
             $errors[] = "خطا در دریافت نتایج بدهی ها.";
             error_log("Get result failed (fetch debts): " . $stmt_debts->error);
        }
        $stmt_debts->close();
    } else {
        $errors[] = "خطا در آماده سازی کوئری بدهی ها.";
        error_log("Prepare failed (fetch debts): " . $cnn->error);
    }
}

// --- گروه بندی بدهی ها و محاسبه مجموع ---
$grouped_debts = [];
$grand_total_debt = 0;

if (!empty($debts_data)) {
    foreach ($debts_data as $debt) {
        $title = $debt['title'] ?? 'بدون عنوان'; // عنوان پیش فرض اگر خالی بود
        if (!isset($grouped_debts[$title])) {
            $grouped_debts[$title] = [
                'items' => [],
                'subtotal' => 0
            ];
        }
         // تبدیل تاریخ به شمسی
        $jalaliDate = '-';
        if (!empty($debt['date'])) {
             try {
                 $timestamp = strtotime($debt['date']);
                 if ($timestamp && function_exists('jdate')) {
                     $jalaliDate = jdate('Y/m/d', $timestamp);
                 } else { $jalaliDate = $debt['date']; }
             } catch(Exception $e) { $jalaliDate = 'خطا تاریخ'; }
        }
        // افزودن تاریخ شمسی به آیتم
         $debt['jalali_date'] = $jalaliDate;

        $grouped_debts[$title]['items'][] = $debt; // اضافه کردن آیتم به گروه
        $current_amount = (float)($debt['amount'] ?? 0);
        $grouped_debts[$title]['subtotal'] += $current_amount; // محاسبه مجموع هر گروه
        $grand_total_debt += $current_amount; // محاسبه مجموع کل
    }
}

// --- بستن اتصال ---
$cnn->close();
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">جزئیات وضعیت بدهی دانش‌آموز: <?= $first_name . ' ' . $last_name ?></h1>
        <a href="debtsReport.php" title="بازگشت به لیست بدهی‌ها" class="back-link Button cancel-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            بازگشت
        </a>
    </div>
    <hr>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
            <div class="error-message"><?= htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="student-info">
            <strong>کد ملی:</strong> <?= convertToPersianNumbers($nationalCode) ?>
        </div>
        <hr>

        <div class="table-responsive">
            <table class="styled-table debt-details-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">عنوان بدهی</th>
                        <th style="width: 25%;">مبلغ (تومان)</th>
                        <th style="width: 20%;">شماره مصوبه</th>
                        <th style="width: 20%;">تاریخ ثبت (شمسی)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($grouped_debts)): ?>
                    <?php foreach ($grouped_debts as $title => $group): ?>
                        <tr>
                            <th colspan="4" class="group-header"><?= htmlspecialchars($title) ?></th>
                        </tr>
                        <?php foreach ($group['items'] as $debt_item): ?>
                        <tr>
                            <td></td>
                            <td><?= convertToPersianNumbers(number_format((float)$debt_item['amount'])) ?></td>
                            <td><?= convertToPersianNumbers(htmlspecialchars($debt_item['approval_number'] ?? '-')) ?></td>
                            <td><?= convertToPersianNumbers(htmlspecialchars($debt_item['jalali_date'] ?? '-')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                         <tr class="subtotal-row">
                             <td class="subtotal-label">مجموع (<?= htmlspecialchars($title) ?>):</td>
                             <td class="subtotal-amount"><?= convertToPersianNumbers(number_format($group['subtotal'])) ?></td>
                             <td colspan="2"></td> </tr>
                    <?php endforeach; ?>
                 </tbody>
                 <tfoot>
                    <tr class="grand-total-row">
                        <th class="grand-total-label">مجموع کل بدهی:</th>
                        <th class="grand-total-amount"><?= convertToPersianNumbers(number_format($grand_total_debt)) ?></th>
                        <th colspan="2"></th> </tr>
                 </tfoot>

                <?php else: ?>
                    <tr>
                        <td colspan="4" class="no-results">هیچ سابقه بدهی برای این دانش آموز ثبت نشده است.</td>
                    </tr>
                 </tbody> </table> <?php endif; ?>
                <?php if (!empty($grouped_debts)): ?>
                     </table> <?php endif; ?>

        </div> <?php endif; ?> </div> <?php
include $basePath . "footer.php";
?>