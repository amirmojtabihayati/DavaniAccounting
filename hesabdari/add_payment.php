<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // در محیط عملیاتی غیرفعال شود

// --- شامل کردن فایل‌های ضروری ---
$basePath = __DIR__ . "/../";
include $basePath . "header.php";

$required_files_payment = [
    "convertToPersianNumbers.php",
    "db_connection.php",
    "assets/vendor/Jalali-Date/jdf.php" // برای انتخابگر تاریخ شمسی
];

foreach ($required_files_payment as $file) {
    $filepath = $basePath . $file;
    if (file_exists($filepath)) {
        include_once $filepath;
    } else {
        error_log("Required file not found (Payment Page): " . $filepath);
        die("<div class='container'><div class='error-message'>خطای سیستمی: فایل ضروری یافت نشد ($file).</div></div>");
    }
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
    error_log("Add Payment DB Error: " . $e->getMessage());
    die("<div class='container'><div class='error-message'>خطا در اتصال به پایگاه داده.</div></div>");
}

// --- متغیرهای عمومی ---
$search_national_code = '';
$students_result = null;
$errors = [];
$success_msg = [];

// --- پردازش فرم‌ها ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- پردازش فرم جستجو ---
    if (isset($_POST['search'])) {
        $search_national_code = isset($_POST['national_code']) ? trim($_POST['national_code']) : '';
        // کوئری جستجو در ادامه اجرا می‌شود
    }

    // --- پردازش فرم ثبت پرداخت ---
    if (isset($_POST["add_payment"])) {
        // 1. دریافت و اعتبارسنجی داده‌های فرم
        $student_id = isset($_POST['student_id']) ? filter_var($_POST['student_id'], FILTER_VALIDATE_INT) : null;
        // حذف جداکننده و تبدیل به عدد انگلیسی
        $amount_paid_str = isset($_POST['amount_paid']) ? preg_replace('/[^\d.]/', '', $_POST['amount_paid']) : '0';
        $amount_paid = filter_var($amount_paid_str, FILTER_VALIDATE_FLOAT);

        $payment_date_str = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : ''; // تاریخ شمسی YYYY/MM/DD
        $payment_title = isset($_POST['payment_title']) ? trim($_POST['payment_title']) : '';
        $transaction_number = isset($_POST['transaction_number']) ? trim($_POST['transaction_number']) : '';
        $payment_type = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : '';

        // اعتبارسنجی‌های اولیه
        if (empty($student_id)) {
            $errors[] = "لطفاً دانش‌آموز را انتخاب کنید.";
        }
        if ($amount_paid === false || $amount_paid <= 0) {
            $errors[] = "مبلغ پرداخت نامعتبر است یا وارد نشده است.";
        }
        if (empty($payment_date_str) || !preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $payment_date_str)) {
             $errors[] = "فرمت تاریخ واریز نامعتبر است (مثال: 1403/01/25).";
        }
         // تبدیل تاریخ شمسی به میلادی برای ذخیره (با فرض وجود کتابخانه Jalali/Jalalian)
         // **توجه:** اگر انتخابگر تاریخ شما تاریخ میلادی برمی‌گرداند، این بخش را اصلاح کنید.
         // اگر انتخابگر تاریخ شمسی برمی‌گرداند، از کتابخانه تبدیل استفاده کنید
         $gregorianDateForDb = null;
          if (empty($errors)) {
              // فرض می‌کنیم از Jalali\Jalalian استفاده می‌کنیم
              $jalaliLibPathConverter = $basePath . "jalali-master/src/Converter.php";
              $jalaliLibPathJalalian = $basePath . "jalali-master/src/Jalalian.php";
               if (file_exists($jalaliLibPathConverter) && file_exists($jalaliLibPathJalalian)) {
                   include_once $jalaliLibPathConverter;
                   include_once $jalaliLibPathJalalian;
                   if(class_exists('Jalali\Jalalian')) {
                        try {
                           list($y, $m, $d) = explode('/', $payment_date_str);
                           $gregorianDateForDb = Jalali\Jalalian::fromFormat('Y/m/d', $payment_date_str)->toCarbon()->format('Y-m-d');
                        } catch (Exception $e) {
                            $errors[] = "خطا در تبدیل تاریخ واریز.";
                            error_log("Payment date conversion error: " . $e->getMessage());
                        }
                   } else { $errors[] = "کتابخانه تبدیل تاریخ یافت نشد."; }
               } else {
                    $errors[] = "فایل کتابخانه تبدیل تاریخ یافت نشد.";
               }
               // اگر از <input type="date"> استفاده می‌کنید که تاریخ میلادی (YYYY-MM-DD) برمی‌گرداند:
               // $gregorianDateForDb = $payment_date_str; // با اعتبارسنجی فرمت YYYY-MM-DD
          }


        if (empty($payment_title)) { $errors[] = "لطفاً عنوان پرداخت را انتخاب کنید."; }
        if (empty($transaction_number)) { $errors[] = "لطفاً شماره واریز/تراکنش را وارد کنید."; }
        if (empty($payment_type)) { $errors[] = "لطفاً نوع واریز را انتخاب کنید."; }

        // --- انجام عملیات فقط اگر خطایی وجود ندارد ---
        if (empty($errors) && $gregorianDateForDb !== null) {
            // **منطق کلیدی:** فقط اطلاعات پرداخت را در جدول `payments` ثبت کن.
            // دیگر جدول `debts` را آپدیت نمی‌کنیم.
            $payment_stmt = $cnn->prepare("INSERT INTO payments (student_id, amount_paid, payment_date, payment_title, transaction_number, payment_type) VALUES (?, ?, ?, ?, ?, ?)");

            if ($payment_stmt) {
                 // amount_paid ممکن است float باشد, از 'd' استفاده کنید
                $payment_stmt->bind_param("idssss", $student_id, $amount_paid, $gregorianDateForDb, $payment_title, $transaction_number, $payment_type);

                if ($payment_stmt->execute()) {
                    $success_msg[] = "پرداخت مبلغ " . number_format($amount_paid) . " تومان با موفقیت ثبت شد.";
                     // پاک کردن فیلدها (اختیاری)
                     // $_POST = [];
                } else {
                    $errors[] = "خطا در ثبت اطلاعات پرداخت در پایگاه داده: " . $payment_stmt->error;
                     error_log("Execute failed (insert payment): " . $payment_stmt->error);
                }
                $payment_stmt->close();
            } else {
                $errors[] = "خطا در آماده‌سازی کوئری ثبت پرداخت.";
                 error_log("Prepare failed (insert payment): " . $cnn->error);
            }
        }
    } // end if add_payment
} // end if POST

// --- کوئری برای دریافت لیست دانش‌آموزان (با جستجو) ---
$sql_students = "SELECT id, first_name, last_name, national_code FROM students WHERE 1=1";
$params_students = [];
$types_students = '';

if (!empty($search_national_code)) {
    $sql_students .= " AND national_code LIKE ?";
    $search_param = "%" . $search_national_code . "%";
    $params_students[] = $search_param;
    $types_students .= 's';
}
$sql_students .= " ORDER BY last_name, first_name";

$stmt_students = $cnn->prepare($sql_students);
if (!$stmt_students) { /* ... Error Handling ... */ }
else {
    if (!empty($params_students)) { $stmt_students->bind_param($types_students, ...$params_students); }
    if (!$stmt_students->execute()) { /* ... Error Handling ... */ }
    else { $students_result = $stmt_students->get_result(); if (!$students_result) { /* Error handling */ } }
}

?>
<div class="container">
    <h1 class="page-title">ثبت پرداخت جدید</h1>

    <form id="searchForm" action="" method="post" class="search-form-section">
         <h2>جستجوی دانش‌آموز</h2>
        <div class="jostojo">
            <label for="national_code_search">کد ملی:</label>
            <input class="searchBy" type="text" id="national_code_search" name="national_code" placeholder="کد ملی دانش آموز" value="<?= htmlspecialchars($search_national_code) ?>">
            <button type="submit" name="search" class="Button search-button">جستجو</button>
            <button type="button" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'" class="Button reset-button">پاک کردن جستجو</button>
        </div>
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

    <h2>ثبت مشخصات پرداخت</h2>
    <form id="paymentForm" action="" method="post" class="payment-add-form">
        <div class="form-grid">
            <div class="form-group">
                <label for="student_id">انتخاب دانش‌آموز:</label>
                <select class="select" name="student_id" id="student_id" required onchange="getStudentBalance(this.value)">
                    <option value="">-- دانش آموز را انتخاب کنید --</option>
                    <?php
                    if ($students_result && $students_result->num_rows > 0) {
                        while ($row = $students_result->fetch_assoc()):
                             $display_text = htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . ' (کد ملی: ' . htmlspecialchars(convertToPersianNumbers($row['national_code'])) . ')';
                    ?>
                        <option value="<?= htmlspecialchars($row['id']) ?>"
                            <?php if (isset($_POST['add_payment']) && isset($_POST['student_id']) && $_POST['student_id'] == $row['id']) echo ' selected'; ?>>
                            <?= $display_text ?>
                        </option>
                    <?php
                        endwhile;
                         if (isset($stmt_students)) $stmt_students->close();
                    } else {
                        echo '<option value="" disabled>دانش آموزی یافت نشد.</option>';
                    }
                    ?>
                </select>
                 <div class="current-balance-display" id="currentBalanceDisplay">
                     موجودی بدهی: <span id="current_balance_amount">-- انتخاب کنید --</span>
                </div>
            </div>

            <div class="form-group">
                <label for="amount_paid">مبلغ پرداخت (تومان):</label>
                <input class="input" id="amount_paid" type="text" name="amount_paid" inputmode="numeric" required placeholder="مبلغ پرداختی" value="<?= isset($_POST['add_payment']) ? htmlspecialchars($_POST['amount_paid'] ?? '') : '' ?>" onkeyup="formatNumber(this)">
                 <small>فقط عدد وارد کنید.</small>
            </div>

            <div class="form-group">
                <label for="payment_date">تاریخ واریز:</label>
                <input class="input" id="payment_date" type="text" name="payment_date" required readonly placeholder="تاریخ را انتخاب کنید" value="<?= isset($_POST['add_payment']) ? htmlspecialchars($_POST['payment_date'] ?? '') : '' ?>" style="background-color: #fff; cursor: pointer;">
            </div>

            <div class="form-group">
                <label for="payment_title">بابت (عنوان پرداخت):</label>
                <select class="select" name="payment_title" id="payment_title" required>
                     <option value="">-- عنوان را انتخاب کنید --</option>
                    <?php
                     $payment_titles = ["شهریه هیئت امنایی", "کتاب", "بیمه", "تقویتی", "مشارکت مردمی(مصوبه انجمن اولیا و مربیان)", "مهارتهای فنی-کارگاهی", "سایر..."];
                     foreach ($payment_titles as $title_option) {
                         $selected = (isset($_POST['add_payment']) && isset($_POST['payment_title']) && $_POST['payment_title'] == $title_option) ? 'selected' : '';
                         echo "<option value=\"$title_option\" $selected>" . htmlspecialchars($title_option) . "</option>";
                     }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="transaction_number">شماره واریز / تراکنش:</label>
                <input class="input" type="text" name="transaction_number" id="transaction_number" required placeholder="شماره پیگیری یا فیش" value="<?= isset($_POST['add_payment']) ? htmlspecialchars($_POST['transaction_number'] ?? '') : '' ?>">
            </div>

             <div class="form-group">
                <label for="payment_type">نوع واریز:</label>
                <select class="select" name="payment_type" id="payment_type" required>
                    <option value="">-- نوع واریز را انتخاب کنید --</option>
                     <?php
                     $payment_types = ["کارتخوان", "کارت به کارت", "فیش", "خودپرداز", "سایر..."];
                     foreach ($payment_types as $type_option) {
                         $selected = (isset($_POST['add_payment']) && isset($_POST['payment_type']) && $_POST['payment_type'] == $type_option) ? 'selected' : '';
                         echo "<option value=\"$type_option\" $selected>" . htmlspecialchars($type_option) . "</option>";
                     }
                    ?>
                </select>
            </div>
        </div><div class="form-actions">
            <button type="submit" name="add_payment" class="Button save-button">ثبت پرداخت</button>
            <button type="button" class="Button cancel-button" onclick="window.location.href='../index.php'">بازگشت</button>
        </div>
    </form>

</div>

<script>
    // تابع فرمت عدد هنگام تایپ
    function formatNumber(input) {
        let numStr = input.value.replace(/[^\d.]/g, '');
        let parts = numStr.split('.');
        let integerPart = parts[0];
        let decimalPart = parts.length > 1 ? '.' + parts[1] : '';
        integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        input.value = integerPart + decimalPart;
    }

    // تابع افزودن جداکننده هزارگان
     function numberWithCommas(x) {
        if (x === null || x === undefined) return '0'; // یا مقدار پیش فرض دیگر
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
     // تابع تبدیل اعداد فارسی به انگلیسی (برای نمایش در صورت نیاز)
    function convertToPersianDigits(str) {
        if (str === null || str === undefined) return '';
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return String(str).replace(/\d/g, d => persianDigits[parseInt(d)]);
    }


$(document).ready(function() {
    // فعال‌سازی انتخابگر تاریخ فارسی
    $("#payment_date").pDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        initialValue: false, // مهم: مقدار اولیه را ست نکنید تا placeholder نمایش داده شود
        observer: true,
        formatPersian: true,
        position: "auto",
        calendarType: "persian"
    });

    // --- مدیریت فیلد مبلغ ---
     $('#amount_paid').on('focus', function() { if (this.value === '0') this.value = ''; formatNumber(this); });
     $('#amount_paid').on('blur', function() { formatNumber(this); });
     formatNumber(document.getElementById('amount_paid')); // فرمت اولیه

    // --- تابع دریافت و نمایش موجودی بدهی ---
     window.getStudentBalance = function(studentId) {
        const balanceDisplaySpan = document.getElementById('current_balance_amount');
        if (!balanceDisplaySpan) {
            console.error("Element #current_balance_amount not found.");
            return;
        }

        balanceDisplaySpan.textContent = 'در حال بارگذاری...'; // نمایش پیام بارگذاری
        balanceDisplaySpan.style.color = '#888'; // رنگ خاکستری

        if (studentId) {
            $.ajax({
                url: 'get_student_balance.php', // **فایل AJAX جدید**
                type: 'GET',
                data: { student_id: studentId },
                dataType: 'json', // انتظار پاسخ JSON
                success: function(response) {
                    console.log("Balance response:", response);
                    if (response && response.balance !== undefined) {
                         let balance = parseFloat(response.balance);
                         let formattedBalance = numberWithCommas(balance.toFixed(0));
                         balanceDisplaySpan.textContent = convertToPersianDigits(formattedBalance) + ' تومان';
                         // تغییر رنگ بر اساس مثبت یا صفر بودن بدهی
                         balanceDisplaySpan.style.color = balance > 0 ? 'red' : 'green'; // قرمز برای بدهکار، سبز برای تسویه
                         if(balance <= 0) {
                             balanceDisplaySpan.textContent += " (تسویه شده)";
                         }
                    } else {
                        balanceDisplaySpan.textContent = 'خطا در دریافت موجودی';
                        balanceDisplaySpan.style.color = 'orange';
                        console.error("Invalid balance response structure:", response);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                     console.error("Error fetching student balance:", textStatus, errorThrown);
                     console.error("Response Text:", jqXHR.responseText); // نمایش پاسخ سرور در صورت خطا
                     balanceDisplaySpan.textContent = 'خطا در ارتباط';
                     balanceDisplaySpan.style.color = 'orange';
                }
            });
        } else {
            balanceDisplaySpan.textContent = '-- انتخاب کنید --'; // بازگشت به حالت اولیه
            balanceDisplaySpan.style.color = '#888';
        }
    };

     // فراخوانی تابع در بارگذاری اولیه اگر دانش آموزی انتخاب شده است
    const initialStudentIdPay = $('#student_id').val();
    if (initialStudentIdPay) {
        getStudentBalance(initialStudentIdPay);
    }

    // (اختیاری) اعتبارسنجی سمت کلاینت: جلوگیری از پرداخت بیشتر از بدهی
    $('#paymentForm').on('submit', function(e) {
        const balanceText = $('#current_balance_amount').text();
        const amountPaid = parseFloat($('#amount_paid').val().replace(/,/g, '')) || 0;

         // استخراج عدد از متن موجودی (با حذف "تومان" و تبدیل فارسی به انگلیسی)
         let currentBalance = 0;
         if (balanceText && balanceText !== '-- انتخاب کنید --' && balanceText !== 'در حال بارگذاری...' && !balanceText.includes('خطا')) {
            let balanceStr = balanceText.replace(/ تومان.*/, '').replace(/,/g, '');
            const persianNumbers = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g];
            for (let i = 0; i < 10; i++) { balanceStr = balanceStr.replace(persianNumbers[i], i); }
            currentBalance = parseFloat(balanceStr) || 0;
         }

         console.log("Current Balance Parsed:", currentBalance);
         console.log("Amount Paid:", amountPaid);


        if (currentBalance <= 0 && amountPaid > 0) {
             alert('این دانش آموز بدهی ندارد یا تسویه کرده است. امکان ثبت پرداخت وجود ندارد.');
             e.preventDefault(); // جلوگیری از ارسال فرم
             return false;
        }

        // این بخش را فقط اگر می‌خواهید کاربر نتواند بیشتر از بدهی‌اش پرداخت کند فعال کنید
        /*
        if (amountPaid > currentBalance && currentBalance > 0) {
             if (!confirm(`مبلغ پرداختی (${numberWithCommas(amountPaid)} تومان) بیشتر از موجودی بدهی (${numberWithCommas(currentBalance)} تومان) است. آیا مطمئن هستید؟`)) {
                 e.preventDefault();
                 return false;
            }
        }
        */

        // تبدیل مبلغ به عدد انگلیسی قبل از ارسال (اگر لازم است)
        // $('#amount_paid').val($('#amount_paid').val().replace(/,/g, ''));

        return true; // اجازه ارسال فرم
    });

});
</script>
<style>
    /* استایل بخش نمایش موجودی بدهی */
    .current-balance-display {
        margin-top: 10px;
        padding: 8px;
        font-size: 1.1em;
        font-weight: bold;
        text-align: right; /* یا center */
        border: 1px solid #eee;
        border-radius: 4px;
        background-color: #f8f9fa;
    }
     .current-balance-display span {
         /* استایل متن داخل span */
         margin-right: 5px;
     }

</style>

<?php
// --- بستن اتصال و شامل کردن فوتر ---
if ($cnn) {
    $cnn->close();
}
include $basePath . "footer.php";
?>