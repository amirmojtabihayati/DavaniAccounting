<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // در محیط عملیاتی غیرفعال شود

// --- شامل کردن فایل‌های ضروری ---
$basePath = __DIR__ . "/../"; // مسیر پوشه والد
include $basePath . "header.php";

// بررسی و شامل کردن فایل‌های کمکی و کتابخانه‌ها
$required_files = [
    "convertToPersianNumbers.php",
    "db_connection.php",
    "assets/vendor/Jalali-Date/jdf.php", // برای نمایش تاریخ شمسی (اگر لازم باشد)
    "assets/vendor/jalali-master/src/Converter.php", // برای تبدیل تاریخ
    "assets/vendor/jalali-master/src/Jalalian.php"    // برای تبدیل تاریخ
];

foreach ($required_files as $file) {
    $filepath = $basePath . $file;
    if (file_exists($filepath)) {
        include_once $filepath;
    } else {
        // ثبت خطا و نمایش پیام مناسب یا خروج
        error_log("Required file not found: " . $filepath);
        die("<div class='container'><div class='error-message'>خطای سیستمی: فایل ضروری یافت نشد ($file). لطفاً با پشتیبانی تماس بگیرید.</div></div>");
    }
}

// بررسی وجود توابع و کلاس‌های لازم
if (!function_exists('convertToPersianNumbers')) { /* ... تعریف تابع جایگزین یا خطا ... */ }
if (!function_exists('jdate')) { /* ... تعریف تابع جایگزین یا خطا ... */ }
if (!class_exists('Jalali\Jalalian')) { die("<div class='container'><div class='error-message'>خطای سیستمی: کتابخانه تاریخ شمسی بارگذاری نشده است.</div></div>"); }

use Jalali\Jalalian;

// --- اتصال به پایگاه داده ---
try {
    $db = new class_db();
    $cnn = $db->connection_database;
    if ($cnn) {
        $cnn->set_charset("utf8mb4"); // تنظیم انکودینگ
    } else {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    error_log("Add Debt DB Error: " . $e->getMessage());
    die("<div class='container'><div class='error-message'>خطا در اتصال به پایگاه داده.</div></div>");
}


// --- متغیرهای عمومی ---
$search_national_code = '';
$students_result = null; // برای نگهداری نتیجه کوئری دانش آموزان
$errors = [];
$success_msg = [];


// --- پردازش فرم‌ها ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- پردازش فرم جستجو ---
    if (isset($_POST['search'])) {
        $search_national_code = isset($_POST['national_code']) ? trim($_POST['national_code']) : '';
        // کوئری جستجو در ادامه اجرا می‌شود
    }

    // --- پردازش فرم ثبت بدهی ---
    if (isset($_POST['add_debt'])) {
        // 1. دریافت و اعتبارسنجی داده‌های فرم
        $student_id = isset($_POST['student_id']) ? filter_var($_POST['student_id'], FILTER_VALIDATE_INT) : null;
        // حذف جداکننده هزارگان و تبدیل به عدد انگلیسی
        $amount_str = isset($_POST['debt_amount']) ? preg_replace('/[^\d.]/', '', $_POST['debt_amount']) : '0';
        $amount = filter_var($amount_str, FILTER_VALIDATE_FLOAT); // یا FILTER_VALIDATE_INT اگر اعشار مجاز نیست

        $title = isset($_POST['debt_title']) ? trim($_POST['debt_title']) : '';
        $approval_number = isset($_POST['approval_number']) ? trim($_POST['approval_number']) : null; // شماره مصوبه می‌تواند خالی باشد
        $jalali_date_str = isset($_POST['date']) ? trim($_POST['date']) : '';

        // اعتبارسنجی‌های اولیه
        if (empty($student_id)) {
            $errors[] = "لطفاً دانش‌آموز را انتخاب کنید.";
        }
        if ($amount === false || $amount <= 0) { // مبلغ باید عدد مثبت باشد
            $errors[] = "مبلغ بدهی نامعتبر است یا وارد نشده است.";
        }
        if (empty($title)) {
            $errors[] = "لطفاً عنوان بدهی را انتخاب کنید.";
        }
        if (empty($jalali_date_str) || !preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $jalali_date_str, $dateParts)) {
             $errors[] = "فرمت تاریخ شمسی نامعتبر است (مثال: 1403/01/25).";
        }

        // 2. تبدیل تاریخ شمسی به میلادی (فقط اگر خطای تاریخ وجود نداشته باشد)
        $gregorianDate = null;
        if (empty($errors)) { // فقط اگر خطاهای قبلی وجود ندارد
             try {
                // $dateParts در preg_match بالا پر شده است
                $year = (int)$dateParts[1];
                $month = (int)$dateParts[2];
                $day = (int)$dateParts[3];
                 // اعتبارسنجی بیشتر تاریخ (مثلا ماه بین ۱ تا ۱۲) - کتابخانه Jalalian ممکن است خودش این کار را بکند
                 if ($month < 1 || $month > 12 || $day < 1 || $day > 31) { // اعتبارسنجی ساده
                      $errors[] = "تاریخ شمسی وارد شده نامعتبر است.";
                 } else {
                    $jalalianDate = new Jalalian($year, $month, $day);
                    // استفاده از toCarbon برای تبدیل به شی Carbon و سپس فرمت کردن
                    // اطمینان حاصل کنید که Carbon نصب است یا از روش دیگر تبدیل استفاده کنید
                     if (method_exists($jalalianDate, 'toCarbon')) {
                        $gregorianDate = $jalalianDate->toCarbon()->format('Y-m-d');
                    } elseif (method_exists($jalalianDate, 'toGregorian')) {
                         // اگر متد toGregorian وجود دارد و سال، ماه، روز را برمی‌گرداند
                         list($gy, $gm, $gd) = $jalalianDate->toGregorian($year, $month, $day);
                         $gregorianDate = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
                    }
                     else {
                         $errors[] = "خطا در تبدیل تاریخ شمسی به میلادی.";
                         error_log("Jalalian library cannot convert date.");
                    }
                 }
            } catch (Exception $e) {
                $errors[] = "خطا در پردازش تاریخ: " . $e->getMessage();
                error_log("Date Conversion Error: " . $e->getMessage());
            }
        }


        // 3. بررسی یکتایی شماره مصوبه (در صورت نیاز و اگر خطاهای قبلی نبود)
        if (empty($errors) && !empty($approval_number)) {
             // فرض بر اینکه شماره مصوبه باید در کل سیستم یکتا باشد
            $check_stmt = $cnn->prepare("SELECT COUNT(*) FROM debts WHERE approval_number = ?");
            if ($check_stmt) {
                $check_stmt->bind_param("s", $approval_number);
                $check_stmt->execute();
                $check_stmt->bind_result($approval_number_count);
                $check_stmt->fetch();
                $check_stmt->close();

                if ($approval_number_count > 0) {
                    $errors[] = "این شماره مصوبه ($approval_number) قبلاً ثبت شده است. لطفاً شماره دیگری وارد کنید یا آن را خالی بگذارید.";
                }
            } else {
                $errors[] = "خطا در بررسی شماره مصوبه.";
                error_log("Prepare failed (check approval number): " . $cnn->error);
            }
        }

        // 4. درج در پایگاه داده (فقط اگر هیچ خطایی وجود ندارد)
        if (empty($errors) && $gregorianDate !== null) {
            $insert_stmt = $cnn->prepare("INSERT INTO debts (student_id, amount, title, approval_number, date) VALUES (?, ?, ?, ?, ?)");
            if ($insert_stmt) {
                 // Note: amount might be float, use 'd' for double/float
                $insert_stmt->bind_param("idsss", $student_id, $amount, $title, $approval_number, $gregorianDate);
                if ($insert_stmt->execute()) {
                    $success_msg[] = "بدهی با مبلغ " . number_format($amount) . " تومان برای عنوان '" . htmlspecialchars($title) . "' با موفقیت ثبت شد.";
                     // پاک کردن فیلدهای فرم پس از ثبت موفق (اختیاری، با JS بهتر است)
                     // $_POST = []; // یا unset کنید
                } else {
                    $errors[] = "خطا در ثبت بدهی در پایگاه داده: " . $insert_stmt->error;
                    error_log("Execute failed (insert debt): " . $insert_stmt->error);
                }
                $insert_stmt->close();
            } else {
                $errors[] = "خطا در آماده‌سازی کوئری ثبت بدهی.";
                 error_log("Prepare failed (insert debt): " . $cnn->error);
            }
        }
    } // end if add_debt
} // end if POST


// --- کوئری برای دریافت لیست دانش‌آموزان (برای dropdown) ---
// همیشه اجرا می‌شود تا dropdown پر شود، با فیلتر جستجو در صورت وجود
$sql_students = "SELECT id, first_name, last_name, national_code FROM students WHERE 1=1";
$params_students = [];
$types_students = '';

if (!empty($search_national_code)) {
    $sql_students .= " AND national_code LIKE ?";
    $search_param = "%" . $search_national_code . "%";
    $params_students[] = $search_param;
    $types_students .= 's';
}
$sql_students .= " ORDER BY last_name, first_name"; // مرتب سازی برای نمایش بهتر

$stmt_students = $cnn->prepare($sql_students);

if (!$stmt_students) {
     error_log("Prepare failed (fetch students): " . $cnn->error);
     $errors[] = "خطا در بارگذاری لیست دانش آموزان.";
} else {
    if (!empty($params_students)) {
        $stmt_students->bind_param($types_students, ...$params_students);
    }
    if (!$stmt_students->execute()) {
        error_log("Execute failed (fetch students): " . $stmt_students->error);
        $errors[] = "خطا در اجرای کوئری لیست دانش آموزان.";
    } else {
        $students_result = $stmt_students->get_result(); // نتیجه برای استفاده در فرم
         if (!$students_result) {
              error_log("Getting result failed (fetch students): " . $stmt_students->error);
             $errors[] = "خطا در دریافت لیست دانش آموزان.";
        }
    }
    // استیتمنت دانش آموزان را اینجا نمی بندیم، چون در حلقه از آن استفاده می شود
}

?>

<div class="container">
    <h1 class="page-title">ایجاد و ثبت بدهی جدید</h1>

    <div class="debt-status-container" id="debtStatusContainer" style="display: none;">
        <h2>وضعیت بدهی دانش‌آموز انتخاب شده</h2>
        <div id="current_debt_info">
            <span class="loading-message">در حال بارگذاری اطلاعات بدهی...</span>
        </div>
        <hr>
        <h3>ریز پرداخت‌ها/بدهی‌های قبلی</h3>
        <div class="table-responsive"> <table id="payments_table" class="styled-table small-font">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>مبلغ (تومان)</th>
                        <th>شماره مصوبه</th>
                        <th>تاریخ ثبت</th>
                    </tr>
                 </thead>
                 <tbody>
                    <tr><td colspan="4" class="no-results">داده‌ای برای نمایش وجود ندارد.</td></tr>
                 </tbody>
            </table>
        </div>
         <hr>
    </div>


    <form id="searchForm" action="" method="post" class="search-form-section">
         <h2>جستجوی دانش‌آموز</h2>
        <div class="jostojo">
            <label for="national_code">کد ملی:</label>
            <input class="searchBy" type="text" id="national_code" name="national_code" placeholder="کد ملی دانش آموز را وارد کنید" value="<?= htmlspecialchars($search_national_code) ?>">
            <button type="submit" name="search" class="Button search-button">جستجو</button>
            <button type="button" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'" class="Button reset-button">پاک کردن جستجو</button> </div>
    </form>
    <hr>

     <div class="message-container">
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="error-message"><?= htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
             <?php foreach ($success_msg as $msg): ?>
                <div class="success-message"><?= htmlspecialchars($msg); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <h2>ثبت بدهی جدید</h2>
    <form id="debtForm" action="" method="post" class="debt-add-form">
        <div class="form-grid"> <div class="form-group">
                <label for="student_id">انتخاب دانش‌آموز:</label>
                <select class="select" name="student_id" id="student_id" required onchange="getStudentDebts(this.value)">
                    <option value="">-- دانش آموز را انتخاب کنید --</option>
                    <?php
                    if ($students_result && $students_result->num_rows > 0) {
                        while ($row = $students_result->fetch_assoc()):
                            $display_text = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . ' (کد ملی: ' . htmlspecialchars(convertToPersianNumbers($row['national_code'])) . ')';
                    ?>
                            <option value="<?= htmlspecialchars($row['id']) ?>"
                                <?php // اگر فرم ثبت بدهی ناموفق بود، دانش آموز قبلی را انتخاب شده نگه دار
                                if (isset($_POST['add_debt']) && isset($_POST['student_id']) && $_POST['student_id'] == $row['id']) echo ' selected'; ?>
                            >
                                <?= $display_text ?>
                            </option>
                    <?php
                        endwhile;
                        // بستن استیتمنت دانش آموزان بعد از حلقه
                        if (isset($stmt_students)) $stmt_students->close();
                    } else {
                        echo '<option value="" disabled>دانش آموزی یافت نشد (با توجه به جستجو)</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="debt_amount">مبلغ بدهی (تومان):</label>
                <input class="input" id="debt_amount" type="text" name="debt_amount" inputmode="numeric" required placeholder="مبلغ به تومان وارد شود" value="<?= isset($_POST['add_debt']) ? htmlspecialchars($_POST['debt_amount'] ?? '') : '' ?>" onkeyup="formatNumber(this)">
                 <small>فقط عدد وارد کنید.</small>
            </div>

            <div class="form-group">
                <label for="debt_title">عنوان بدهی:</label>
                <select class="select" name="debt_title" id="debt_title" required>
                    <option value="">-- عنوان را انتخاب کنید --</option>
                    <?php
                     $debt_titles = ["شهریه هیئت امنایی", "کتاب", "بیمه", "تقویتی", "مشارکت مردمی(مصوبه انجمن اولیا و مربیان)", "مهارتهای فنی-کارگاهی", "سایر..."];
                     foreach ($debt_titles as $title_option) {
                         $selected = (isset($_POST['add_debt']) && isset($_POST['debt_title']) && $_POST['debt_title'] == $title_option) ? 'selected' : '';
                         echo "<option value=\"$title_option\" $selected>" . htmlspecialchars($title_option) . "</option>";
                     }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="approval_number">شماره مصوبه (اختیاری):</label>
                <input class="input" type="text" name="approval_number" id="approval_number" placeholder="شماره مصوبه را وارد کنید" value="<?= isset($_POST['add_debt']) ? htmlspecialchars($_POST['approval_number'] ?? '') : '' ?>">
            </div>

            <div class="form-group">
                <label for="date">تاریخ ثبت بدهی:</label>
                <input class="input" id="date" type="text" name="date" required readonly placeholder="تاریخ را انتخاب کنید" value="<?= isset($_POST['add_debt']) ? htmlspecialchars($_POST['date'] ?? '') : '' ?>" style="background-color: #fff; cursor: pointer;">
            </div>
        </div> <div class="form-actions">
            <button type="submit" name="add_debt" class="Button save-button">ثبت بدهی</button>
            <button type="button" class="Button cancel-button" onclick="window.location.href='../Home.php'">بازگشت به صفحه اصلی</button> </div>
    </form>
</div>

<script>
    // تابع برای فرمت کردن عدد با جداکننده هزارگان هنگام تایپ
    function formatNumber(input) {
        // Remove existing commas and non-digit characters except dot
        let numStr = input.value.replace(/[^\d.]/g, '');
         // Split into integer and decimal parts
        let parts = numStr.split('.');
        let integerPart = parts[0];
        let decimalPart = parts.length > 1 ? '.' + parts[1] : '';

         // Add commas to the integer part
        integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        // Set the formatted value back to the input
        input.value = integerPart + decimalPart;
    }
     // تابع برای تبدیل اعداد فارسی/عربی به انگلیسی قبل از ارسال فرم (اختیاری)
    function convertNumbersToEnglish(value) {
        const persianNumbers = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g];
        const arabicNumbers = [/٠/g, /١/g, /٢/g, /٣/g, /٤/g, /٥/g, /٦/g, /٧/g, /٨/g, /٩/g];
        let str = String(value);
        for (let i = 0; i < 10; i++) {
            str = str.replace(persianNumbers[i], i).replace(arabicNumbers[i], i);
        }
        return str;
    }


    // تابع برای افزودن جداکننده هزارگان به عدد
     function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // تابع htmlspecialchars جاوا اسکریپت (برای جلوگیری از XSS در سمت کلاینت)
    function escapeHTML(string) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(string).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

$(document).ready(function() {
    // فعال‌سازی انتخابگر تاریخ فارسی
    $("#date").pDatepicker({
        format: 'YYYY/MM/DD', // فرمت تاریخ
        autoClose: true,      // بستن خودکار
        initialValue: false,  // مقدار اولیه نداشته باشد
        position: "auto",
        calendarType: "persian",
        observer: true,       // مهم برای کارکرد با فرمت اولیه
        formatPersian: true   // نمایش اعداد فارسی در تقویم
    });

     // پاک کردن مقدار اولیه مبلغ اگر '0' است هنگام فوکوس
     $('#debt_amount').on('focus', function() {
        if (this.value === '0') {
            this.value = '';
        }
        // اعمال فرمت اولیه در صورت نیاز
        formatNumber(this);
     });
      // اعمال فرمت هنگام ترک فیلد
     $('#debt_amount').on('blur', function() {
         formatNumber(this);
         if (this.value === '') {
             // this.value = '0'; // یا خالی بگذارید
         }
     });
     // اعمال فرمت اولیه در بارگذاری صفحه اگر مقداری از قبل وجود دارد
     formatNumber(document.getElementById('debt_amount'));


     // تابع برای نمایش مجموع بدهی در بخش وضعیت
    function showDebtStatus(totalDebt) {
        const currentDebtElement = document.getElementById("current_debt_info");
        if (currentDebtElement) {
            if (totalDebt !== null && totalDebt !== undefined) {
                 let formattedDebt = numberWithCommas(parseFloat(totalDebt).toFixed(0)); // بدون اعشار
                currentDebtElement.innerHTML = `<strong>مجموع بدهی فعلی: ${convertToPersianNumbers(formattedDebt)} تومان</strong>`;
            } else {
                currentDebtElement.innerHTML = '<strong>بدهی‌ای برای این دانش آموز ثبت نشده است.</strong>';
            }
        } else {
            console.error("Element with ID 'current_debt_info' not found.");
        }
    }

    // تابع برای نمایش پرداخت‌ها/بدهی‌های قبلی در جدول وضعیت
    function showPreviousDebts(debts) {
        const paymentsTableBody = document.querySelector("#payments_table tbody");
        if (paymentsTableBody) {
            // پاک کردن ردیف‌های قبلی
            paymentsTableBody.innerHTML = ''; // ساده‌ترین راه برای پاک کردن

            if (Array.isArray(debts) && debts.length > 0) {
                debts.forEach(debt => {
                    const row = paymentsTableBody.insertRow();
                    row.innerHTML = `
                        <td>${escapeHTML(debt.title)}</td>
                        <td>${convertToPersianNumbers(numberWithCommas(parseFloat(debt.amount).toFixed(0)))}</td>
                        <td>${convertToPersianNumbers(escapeHTML(debt.approval_number || '-'))}</td>
                        <td>${convertToPersianNumbers(escapeHTML(debt.jalali_date || '-'))}</td>
                    `;
                });
            } else {
                // نمایش پیام "یافت نشد" اگر آرایه خالی است یا معتبر نیست
                 paymentsTableBody.innerHTML = '<tr><td colspan="4" class="no-results">هیچ بدهی قبلی برای این دانش آموز یافت نشد.</td></tr>';
            }
        } else {
            console.error("Element 'tbody' inside '#payments_table' not found.");
        }
    }


     // تابع برای دریافت و نمایش اطلاعات بدهی دانش آموز با AJAX
    window.getStudentDebts = function(studentId) {
        console.log("Fetching debts for student ID:", studentId);
        const debtStatusContainer = document.getElementById('debtStatusContainer');
        const currentDebtInfo = document.getElementById('current_debt_info');
        const paymentsTableBody = document.querySelector("#payments_table tbody");

        if (studentId && debtStatusContainer && currentDebtInfo && paymentsTableBody) {
            // نمایش کانتینر و پیام بارگذاری
            debtStatusContainer.style.display = 'block';
            currentDebtInfo.innerHTML = '<span class="loading-message">در حال بارگذاری اطلاعات بدهی...</span>';
            paymentsTableBody.innerHTML = '<tr><td colspan="4" class="loading-message">در حال بارگذاری...</td></tr>';


            const xhr = new XMLHttpRequest();
            // **مهم:** مطمئن شوید فایل get_student_debts.php وجود دارد و کار می‌کند
            const url = "get_student_debts.php?student_id=" + encodeURIComponent(studentId);
            console.log("Requesting URL:", url);

            xhr.open("GET", url, true);
            xhr.setRequestHeader("Accept", "application/json"); // درخواست پاسخ JSON

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            console.log("Server response:", response);
                            // نمایش مجموع بدهی
                            showDebtStatus(response.total_debt);
                             // نمایش ریز بدهی ها
                            showPreviousDebts(response.debts || []); // ارسال آرایه خالی در صورت نبود بدهی
                        } catch (e) {
                             console.error("Error parsing JSON response:", e);
                             console.error("Response text:", xhr.responseText);
                            currentDebtInfo.innerHTML = '<span class="error-message">خطا در پردازش پاسخ سرور.</span>';
                            paymentsTableBody.innerHTML = '<tr><td colspan="4" class="error-message">خطا در پردازش داده‌ها.</td></tr>';
                        }
                    } else {
                        console.error("Error fetching student debts:", xhr.status, xhr.statusText);
                         currentDebtInfo.innerHTML = `<span class="error-message">خطا ${xhr.status} در دریافت اطلاعات بدهی.</span>`;
                         paymentsTableBody.innerHTML = `<tr><td colspan="4" class="error-message">خطا ${xhr.status}.</td></tr>`;
                    }
                }
            };
            xhr.onerror = function () {
                 console.error("Network error occurred.");
                 currentDebtInfo.innerHTML = '<span class="error-message">خطای شبکه در ارتباط با سرور.</span>';
                 paymentsTableBody.innerHTML = '<tr><td colspan="4" class="error-message">خطای شبکه.</td></tr>';
            };
            xhr.send();
        } else {
            // اگر studentId خالی است، کانتینر وضعیت را مخفی کن
            if (debtStatusContainer) debtStatusContainer.style.display = 'none';
             if (!studentId) console.log("Student ID is empty, hiding status container.");
             else console.error("One or more status elements not found.");

        }
    };

    // فراخوانی تابع getStudentDebts اگر دانش آموزی از قبل انتخاب شده باشد (بعد از POST ناموفق)
    const initialStudentId = $('#student_id').val();
    if (initialStudentId) {
        getStudentDebts(initialStudentId);
    }


     // (اختیاری) تبدیل اعداد ورودی به انگلیسی قبل از ارسال فرم اصلی
     /*
     $('#debtForm').on('submit', function() {
         $('#debt_amount').val(convertNumbersToEnglish($('#debt_amount').val().replace(/,/g, '')));
         // تبدیل سایر فیلدهای عددی در صورت نیاز
     });
     */

});
</script>

<?php
// --- بستن اتصال و شامل کردن فوتر ---
if ($cnn) {
    $cnn->close();
}
include $basePath . "footer.php";
?>