<?php
// include های ضروری
include "../header.php";
include "../db_connection.php"; // فرض می‌کنیم این فایل کلاس class_db و متد connection_database را دارد
include_once "../convertToPersianNumbers.php"; // تابع تبدیل به اعداد فارسی برای نمایش
require_once "../Jalali-Date/jdf.php"; // کتابخانه تاریخ جلالی

// تابع کمکی برای تبدیل اعداد فارسی/عربی به انگلیسی
function convertToEnglishNumbers($string) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '،'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٬']; // شامل کامای عربی/فارسی
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '']; // کاما حذف می‌شود

    // حذف جداکننده هزارگان انگلیسی
    $string = str_replace(',', '', $string);
    // تبدیل فارسی و عربی به انگلیسی و حذف کامای فارسی/عربی
    return str_replace($persian, $english, str_replace($arabic, $english, $string));
}


$cnn = (new class_db())->connection_database;

$errors = [];
$success_msg = [];
$search_national_code = '';
$debt_amount = 0.0; // مقدار اولیه بدهی به صورت عددی
$debt_amount_display = ''; // برای نمایش با فرمت فارسی
$student_name = '';
$student_id = null; // اضافه شد برای ذخیره ID دانش آموز
$debt_titles = [];
$has_debt = false; // متغیر برای بررسی وجود بدهی

// بررسی درخواست POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // بخش جستجوی دانش آموز
    if (isset($_POST['search'])) {
        $search_national_code = trim($_POST['national_code']);
        if (!empty($search_national_code)) {
            // بهینه‌سازی: گرفتن نام و ID در یک کوئری
            $stmt = $cnn->prepare("SELECT id, first_name, last_name FROM students WHERE national_code = ?");
            $stmt->bind_param("s", $search_national_code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($student_data = $result->fetch_assoc()) {
                $student_id = $student_data['id']; // ذخیره ID
                $student_name = $student_data['first_name'] . ' ' . $student_data['last_name'];

                // جستجوی عناوین بدهی مربوط به این دانش‌آموز
                $stmt_debt = $cnn->prepare("SELECT title FROM debts WHERE student_id = ? AND amount > 0"); // فقط بدهی های پرداخت نشده یا با مبلغ مثبت
                $stmt_debt->bind_param("i", $student_id); // بایند با ID
                $stmt_debt->execute();
                $debt_result = $stmt_debt->get_result();
                while ($row = $debt_result->fetch_assoc()) {
                    $debt_titles[] = $row['title'];
                }
                $stmt_debt->close();

                if (count($debt_titles) > 0) {
                    $has_debt = true;
                } else {
                    $errors[] = "هیچ بدهی فعالی برای این دانش‌آموز یافت نشد.";
                    $has_debt = false;
                }
            } else {
                $errors[] = "دانش‌آموزی با این کد ملی یافت نشد.";
                $student_name = '';
                $has_debt = false;
            }
            $stmt->close();
        } else {
             $errors[] = "لطفا کد ملی را وارد کنید.";
        }
    }
    // بخش افزودن قسط بندی - بدون تودرتویی شرط POST
    // نکته: برای اجرای این بخش، کد ملی باید از قبل جستجو شده و در سشن یا فیلد مخفی نگهداری شود
    // یا اینکه فرم افزودن قسط شامل کد ملی هم باشد. فرض می‌کنیم کد ملی در $search_national_code وجود دارد (از جستجوی قبلی).
     else if (isset($_POST['add_taghsit'])) {
        // دریافت کد ملی از فیلد مخفی یا از متغیر اگر در همان صفحه است
        $search_national_code = $_POST['hidden_national_code'] ?? $search_national_code; // بازیابی کد ملی
        $student_id = $_POST['hidden_student_id'] ?? null; // بازیابی ID دانش آموز
        $debt_title = trim($_POST['debt_title']);
        $number_of_installments = filter_input(INPUT_POST, 'number_of_installments', FILTER_VALIDATE_INT);
        $installment_dates = $_POST['installment_dates'] ?? []; // آرایه تاریخ‌ها
        $manual_amounts = $_POST['manual_amounts'] ?? []; // آرایه مبالغ

        // اعتبارسنجی اولیه
        if (empty($search_national_code) || empty($student_id)) {
             $errors[] = "اطلاعات دانش‌آموز (کد ملی) یافت نشد. لطفاً ابتدا جستجو کنید.";
        } elseif (empty($debt_title)) {
            $errors[] = "لطفاً عنوان بدهی را انتخاب کنید.";
        } elseif ($number_of_installments === false || $number_of_installments <= 0) {
            $errors[] = "تعداد اقساط نامعتبر است.";
        } elseif (count($installment_dates) !== $number_of_installments || count($manual_amounts) !== $number_of_installments) {
             $errors[] = "تعداد تاریخ‌ها یا مبالغ وارد شده با تعداد اقساط انتخاب شده مغایرت دارد.";
        } else {
            // آماده‌سازی دستور INSERT
            // ستون debt_amount در جدول installments باید از نوع DECIMAL یا FLOAT باشد
            $stmt_insert = $cnn->prepare("INSERT INTO installments (student_id, national_code, debt_title, installment_amount, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($stmt_insert === false) {
                $errors[] = "خطا در آماده‌سازی دستور پایگاه داده: " . $cnn->error;
            } else {
                $insert_success = true;
                for ($i = 0; $i < $number_of_installments; $i++) {
                    $due_date_jalali = trim($installment_dates[$i]);
                    // تبدیل مبلغ فارسی/عربی به انگلیسی و سپس به float
                    $amount_str = convertToEnglishNumbers($manual_amounts[$i]);
                    $installment_amount_val = filter_var($amount_str, FILTER_VALIDATE_FLOAT);

                    // اعتبارسنجی تاریخ و مبلغ
                    if (empty($due_date_jalali)) {
                        $errors[] = "تاریخ قسط " . ($i + 1) . " نمی‌تواند خالی باشد.";
                        $insert_success = false;
                        break; // توقف حلقه در صورت خطا
                    }
                     if ($installment_amount_val === false || $installment_amount_val <= 0) {
                        $errors[] = "مبلغ قسط " . ($i + 1) . " نامعتبر است (" . htmlspecialchars($manual_amounts[$i]) . ").";
                        $insert_success = false;
                        break; // توقف حلقه در صورت خطا
                    }


                    // تبدیل تاریخ جلالی به میلادی
                    try {
                        // بررسی فرمت YYYY/MM/DD
                        if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $due_date_jalali, $matches)) {
                             throw new Exception("فرمت تاریخ قسط " . ($i + 1) . " نامعتبر است.");
                        }
                        list($jy, $jm, $jd) = [$matches[1], $matches[2], $matches[3]];
                        $gregorianDate = jdf("Y-m-d", jmktime(0, 0, 0, $jm, $jd, $jy)); // استفاده مستقیم از jdf برای تبدیل امن‌تر
                         if (!$gregorianDate) {
                             throw new Exception("تاریخ قسط " . ($i + 1) . " نامعتبر است.");
                         }
                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                        $insert_success = false;
                        break;
                    }

                    // بایند کردن مقادیر - i برای student_id, s برای national_code, s برای title, d برای amount, s برای date
                    $stmt_insert->bind_param("issds", $student_id, $search_national_code, $debt_title, $installment_amount_val, $gregorianDate);

                    if (!$stmt_insert->execute()) {
                        $errors[] = "خطا در ذخیره‌سازی قسط " . ($i + 1) . ": " . $stmt_insert->error;
                        $insert_success = false;
                        break; // توقف در صورت خطا
                    }
                } // End for loop

                $stmt_insert->close();

                if ($insert_success && empty($errors)) {
                    $success_msg[] = "تقسیط بدهی با موفقیت ثبت شد.";
                    // پاک کردن داده‌های فرم یا ریدایرکت
                    // $search_national_code = ''; // برای پاک کردن فرم جستجو
                    // $student_name = '';
                    // $debt_titles = [];
                    // $has_debt = false;
                    // $debt_amount_display = '';
                    // $student_id = null;

                    // یا ریدایرکت به همین صفحه یا صفحه دیگر
                    // header("Location: " . $_SERVER['PHP_SELF'] . "?status=success");
                    // exit;

                } elseif (empty($errors)) {
                     $errors[] = "خطا: عملیات ثبت اقساط به طور کامل انجام نشد.";
                }

                 // اگر خطایی رخ داده، باید اطلاعات دانش آموز و بدهی ها را مجدد لود کنیم تا فرم به درستی نمایش داده شود
                if(!empty($errors) && !empty($search_national_code) && empty($student_name)){
                     // کد بازیابی اطلاعات دانش آموز و بدهی ها مانند بخش search
                     $stmt_reload = $cnn->prepare("SELECT id, first_name, last_name FROM students WHERE national_code = ?");
                     $stmt_reload->bind_param("s", $search_national_code);
                     $stmt_reload->execute();
                     $result_reload = $stmt_reload->get_result();
                     if ($student_data_reload = $result_reload->fetch_assoc()) {
                        $student_id = $student_data_reload['id'];
                        $student_name = $student_data_reload['first_name'] . ' ' . $student_data_reload['last_name'];
                        $stmt_debt_reload = $cnn->prepare("SELECT title FROM debts WHERE student_id = ? AND amount > 0");
                        $stmt_debt_reload->bind_param("i", $student_id);
                        $stmt_debt_reload->execute();
                        $debt_result_reload = $stmt_debt_reload->get_result();
                        while ($row_reload = $debt_result_reload->fetch_assoc()) {
                            $debt_titles[] = $row_reload['title'];
                        }
                        $stmt_debt_reload->close();
                        $has_debt = count($debt_titles) > 0;
                     }
                     $stmt_reload->close();
                }


            } // End else ($stmt_insert !== false)
        } // End else (validation)
    } // End else if add_taghsit
} // End POST check

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <style>
    /* استایل های CSS که در ادامه می آید اینجا قرار می گیرد */
        body {
            font-family: 'Vazirmatn', sans-serif;
            direction: rtl; /* اطمینان از راست‌چین بودن کل صفحه */
            background-color: #f4f7f6; /* یک پس‌زمینه ملایم */
            color: #333;
            line-height: 1.6;
        }
    </style>
    <link rel="stylesheet" href="test.css">
</head>
<body>
<div class="container">
    <h1 id="h1">تقسیط بدهی دانش آموز</h1>

    <form id="searchForm" action="" method="post">
        <div class="jostojo">
            <label id="label" for="national_code">جستجو بر اساس کد ملی:</label>
            <input class="input" type="text" id="national_code_input" name="national_code" value="<?= htmlspecialchars(convertToPersianNumbers($search_national_code)) ?>" required>
            <button id="submit_btn" type="submit" name="search">جستجو</button>
        </div>
    </form>

    <?php if (!empty($errors)): ?>
        <div class="message-container error-container">
            <?php foreach ($errors as $error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
         <div class="message-container success-container">
             <div class="success-message"><?php echo htmlspecialchars($success_msg[0]); ?></div>
        </div>
    <?php endif; ?>


    <?php if ($student_name && $has_debt): ?>
        <div class="student-info">
            <label>نام دانش‌آموز:</label>
            <span>&nbsp; <?= htmlspecialchars($student_name) ?> </span>
             <label style="margin-right: 20px;">کد ملی:</label>
             <span>&nbsp; <?= htmlspecialchars(convertToPersianNumbers($search_national_code)) ?> </span>
        </div>

        <form id="installmentForm" action="" method="post">
            <input type="hidden" name="hidden_national_code" value="<?= htmlspecialchars($search_national_code) ?>">
             <input type="hidden" name="hidden_student_id" value="<?= htmlspecialchars($student_id) ?>">

            <div class="item">
                <label id="label" for="debt_title">عنوان بدهی</label>
                <select class="select" name="debt_title" id="debt_title" required onchange="fetchDebtAmount()">
                    <option value="">یک گزینه انتخاب کنید</option>
                    <?php foreach ($debt_titles as $title): ?>
                        <?php $selected = (isset($_POST['debt_title']) && $_POST['debt_title'] == $title) ? 'selected' : ''; ?>
                        <option value="<?= htmlspecialchars($title) ?>" <?= $selected ?>><?= htmlspecialchars($title) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>مبلغ کل بدهی:</label>
                <span id="debt_amount_display" data-raw-amount="0">&nbsp;لطفا عنوان بدهی را انتخاب کنید&nbsp;</span>

                <label id="label">تعداد اقساط:</label>
                <?php $num_installments_value = $_POST['number_of_installments'] ?? 1; ?>
                <input type="number" name="number_of_installments" id="number_of_installments" class="input" min="1" value="<?= htmlspecialchars($num_installments_value) ?>">

                <label id="label">نحوه محاسبه مبلغ:</label>
                 <?php $amount_type_value = $_POST['amount_type'] ?? 'automatic'; ?>
                <label><input type="radio" name="amount_type" value="automatic" <?= ($amount_type_value === 'automatic') ? 'checked' : '' ?> onclick="toggleAmountInputs(false)"> خودکار</label>
                <label><input type="radio" name="amount_type" value="manual" <?= ($amount_type_value === 'manual') ? 'checked' : '' ?> onclick="toggleAmountInputs(true)"> دستی</label>

                <button id="generate_installments_btn" type="button" onclick="generateInstallmentFields()">ایجاد/بازنشانی فیلدهای قسط</button>
            </div>

            <div id="installments_container">
                <?php
                 // اگر فرم قبلا سابمیت شده (و احتمالا خطا داده)، فیلدهای قبلی را بازسازی کن
                 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_taghsit']) && !empty($_POST['installment_dates'])) {
                     echo '<script>';
                     echo 'document.addEventListener("DOMContentLoaded", function() {';
                     echo '   generateInstallmentFields();'; // فراخوانی تابع جاوا اسکریپت برای بازسازی
                     echo '   populateExistingFields(' . json_encode($_POST['installment_dates']) . ', ' . json_encode($_POST['manual_amounts']) . ');';
                     echo '});';
                     echo '</script>';
                 }
                 ?>
            </div>
            <div id="installments_summary" style="margin-top: 15px; font-weight: bold;"></div>


            <button id="submit_btn" type="submit" name="add_taghsit" <?php echo ($student_id && $has_debt) ? '' : 'disabled'; ?>>ثبت تقسیط</button>
            <button id="cancel_btn" type="button" onclick="window.location.href='../index.php'">انصراف</button>
        </form>
    <?php elseif ($student_name && !$has_debt && !empty($errors)): ?>
         <div class="message-container info-container">
            <div class="info-message"><?= $errors[0] // نمایش پیام "هیچ بدهی فعالی یافت نشد" ?></div>
        </div>
     <?php elseif (!empty($errors) && empty($student_name)): ?>
          <div class="message-container error-container">
             <div class="error-message"><?= $errors[0] // نمایش پیام "دانش آموز یافت نشد" یا "کد ملی وارد کنید" ?></div>
         </div>
    <?php endif; ?>

</div>

<script src="../assets/js/jquery.js"></script>
<script src="../PersianDate/dist/persian-date.min.js"></script>
<script src="../DatePicker/dist/js/persian-datepicker.min.js"></script>
<script src="../assets/js/hideMessage.js"></script>
<script src="../assets/js/ConverterPersianNumbers.js"></script>

<script>
let totalDebtAmount = 0; // متغیر گلوبال برای مبلغ کل بدهی

// تابع برای تبدیل اعداد فارسی و عربی به انگلیسی
function convertToEnglishNumbers(str) {
    if (!str) return '';
    const persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '،'];
    const arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٬'];
    const english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '']; // کاما حذف می‌شود
    str = String(str); // Ensure it's a string
    // اول کامای انگلیسی را حذف کن
    str = str.replace(/,/g, '');
    // بعد کامای فارسی/عربی را حذف و اعداد را تبدیل کن
    persian.forEach((char, index) => {
        str = str.replace(new RegExp(char, 'g'), english[index]);
    });
     arabic.forEach((char, index) => {
        str = str.replace(new RegExp(char, 'g'), english[index]);
    });
    return str;
}


// تابع برای فرمت‌دهی اعداد با کاما (جداکننده هزارگان)
function numberWithCommas(x) {
    // تبدیل ورودی به رشته و حذف کاراکترهای غیرعددی (به جز نقطه اعشار اگر لازم باشد)
    let parts = String(x).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return parts.join('.');
}

// تابع برای دریافت مبلغ بدهی با AJAX
function fetchDebtAmount() {
    const debtTitle = document.getElementById('debt_title').value;
    // کد ملی را از فیلد مخفی یا متغیر اولیه PHP بگیرید
    const nationalCode = document.querySelector('input[name="hidden_national_code"]')?.value || '<?= htmlspecialchars($search_national_code ?? "") ?>';
    const displaySpan = document.getElementById('debt_amount_display');
    const generateBtn = document.getElementById('generate_installments_btn');

    // پاک کردن فیلدهای قسط و غیرفعال کردن دکمه اگر عنوانی انتخاب نشده
    if (!debtTitle) {
        displaySpan.innerHTML = '&nbsp;لطفا عنوان بدهی را انتخاب کنید&nbsp;';
        displaySpan.setAttribute('data-raw-amount', '0');
        totalDebtAmount = 0;
        generateBtn.disabled = true;
        clearInstallmentFields();
        return;
    }

    // فعال کردن دکمه و نمایش حالت لودینگ
    generateBtn.disabled = false;
    displaySpan.innerHTML = '...درحال بارگذاری';

    $.ajax({
        url: 'get_debt_for_taghsit.php', // فایل PHP برای دریافت مبلغ
        type: 'POST',
        data: {
            title: debtTitle,
            national_code: nationalCode
        },
        success: function(data) {
            // تبدیل پاسخ به عدد اعشاری
            const amount = parseFloat(data);
            if (!isNaN(amount) && amount > 0) {
                totalDebtAmount = amount; // ذخیره مبلغ خام
                // نمایش مبلغ فرمت شده (بدون اعشار یا با دو رقم اعشار اگر لازم است)
                displaySpan.innerText = convertToPersianNumbers(numberWithCommas(amount.toFixed(0))); // یا toFixed(2)
                displaySpan.setAttribute('data-raw-amount', amount);
                 // به صورت خودکار فیلدها را ایجاد نکنید، اجازه دهید کاربر دکمه را بزند
                 // generateInstallmentFields();
                 // فقط فیلدهای قبلی را پاک کنید اگر مبلغ جدیدی لود شد
                 clearInstallmentFields();
            } else {
                totalDebtAmount = 0;
                displaySpan.innerText = 'مبلغ یافت نشد';
                displaySpan.setAttribute('data-raw-amount', '0');
                generateBtn.disabled = true; // غیرفعال کردن دکمه چون مبلغ معتبر نیست
                clearInstallmentFields();
                alert('خطا: مبلغ بدهی برای عنوان انتخاب شده یافت نشد یا معتبر نیست.');
            }
        },
        error: function() {
            totalDebtAmount = 0;
            displaySpan.innerText = 'خطا در دریافت';
            displaySpan.setAttribute('data-raw-amount', '0');
            generateBtn.disabled = true;
            clearInstallmentFields();
            alert('خطا در ارتباط با سرور برای دریافت مبلغ بدهی.');
        }
    });
}

// تابع برای ایجاد یا بازنشانی فیلدهای قسط
function generateInstallmentFields() {
    const container = document.getElementById('installments_container');
    const numberOfInstallments = parseInt(document.getElementById('number_of_installments').value) || 0;
    const isManual = document.querySelector('input[name="amount_type"]:checked').value === 'manual';

    // بررسی مبلغ کل و تعداد اقساط
    if (totalDebtAmount <= 0 || numberOfInstallments <= 0) {
        clearInstallmentFields(); // پاک کردن فیلدها اگر نامعتبر است
        if (totalDebtAmount <= 0 && document.getElementById('debt_title').value) {
             // alert('ابتدا یک عنوان بدهی با مبلغ معتبر انتخاب کنید.'); // پیام می‌تواند آزاردهنده باشد
        } else if (numberOfInstallments <= 0 && totalDebtAmount > 0) {
             alert('تعداد اقساط باید حداقل ۱ باشد.');
        }
        return; // خروج از تابع
    }

    // پاک کردن فیلدهای قبلی
    container.innerHTML = '';

    // اضافه کردن دکمه "حذف همه اقساط"
    const deleteAllContainer = document.createElement('div');
    deleteAllContainer.title = 'حذف همه اقساط';
    deleteAllContainer.classList.add('delete-all-container');
    deleteAllContainer.innerHTML = '<img src="../images/del-all_icon.png" alt="حذف همه" class="delete-icon"> حذف همه اقساط';
    deleteAllContainer.onclick = clearInstallmentFields; // تابع پاکسازی را فراخوانی کند
    container.appendChild(deleteAllContainer);

    // محاسبه مبلغ هر قسط (گرد کردن به پایین و اضافه کردن باقیمانده به قسط آخر)
    let baseInstallmentAmount = Math.floor(totalDebtAmount / numberOfInstallments);
    let remainder = totalDebtAmount - (baseInstallmentAmount * numberOfInstallments);

    for (let i = 0; i < numberOfInstallments; i++) {
        const installmentDiv = document.createElement('div');
        installmentDiv.classList.add('installment-group');
        installmentDiv.setAttribute('data-index', i);

        const dateAmountDiv = document.createElement('div');
        dateAmountDiv.classList.add('date-amount');

        // شماره قسط
        const numberLabel = document.createElement('span');
        numberLabel.classList.add('installment-number');
        numberLabel.innerText = `قسط ${convertToPersianNumbers(i + 1)}:`;
        dateAmountDiv.appendChild(numberLabel);

        // --- بلوک تاریخ ---
        const dateWrapper = document.createElement('div');
        dateWrapper.classList.add('input-wrapper'); // **تغییر جدید**

        const labelDate = document.createElement('label');
        labelDate.innerText = `تاریخ:`;
        const inputDate = document.createElement('input');
        inputDate.type = 'text';
        inputDate.name = 'installment_dates[]';
        inputDate.className = 'ghest date-input'; // کلاس برای Datepicker
        inputDate.placeholder = 'YYYY/MM/DD';
        inputDate.autocomplete = 'off'; // جلوگیری از تکمیل خودکار مرورگر

        dateWrapper.appendChild(labelDate);
        dateWrapper.appendChild(inputDate);
        dateAmountDiv.appendChild(dateWrapper); // **تغییر جدید**

        // --- بلوک مبلغ ---
        const amountWrapper = document.createElement('div');
        amountWrapper.classList.add('input-wrapper'); // **تغییر جدید**

        const labelAmount = document.createElement('label');
        labelAmount.innerText = `مبلغ:`;
        const amountInput = document.createElement('input');
        amountInput.type = 'text'; // نوع text برای نمایش بهتر فارسی و کاما
        amountInput.name = 'manual_amounts[]';
        amountInput.className = 'ghest amount-input';
        amountInput.placeholder = 'مبلغ قسط';
        amountInput.inputMode = 'numeric'; // کمک به نمایش کیبورد عددی در موبایل (اگرچه هدف دسکتاپ است)
        amountInput.readOnly = !isManual; // فقط در حالت دستی قابل ویرایش

        let currentInstallmentAmount = baseInstallmentAmount;
        // اضافه کردن باقیمانده به قسط آخر
        if (i === numberOfInstallments - 1) {
            currentInstallmentAmount += remainder;
        }

        // مقداردهی اولیه مبلغ (نمایش فرمت شده فارسی)
        amountInput.value = convertToPersianNumbers(numberWithCommas(currentInstallmentAmount.toFixed(0))); // نمایش بدون اعشار

        // رویدادها برای فیلد مبلغ
        amountInput.addEventListener('input', function() {
            const englishValue = convertToEnglishNumbers(this.value);
            const numericValue = parseFloat(englishValue) || 0;
            // نمایش فرمت شده فارسی در اینپوت همزمان با تایپ
            this.value = convertToPersianNumbers(numberWithCommas(numericValue.toFixed(0)));
            if (isManual) {
                updateInstallmentSummary(); // به‌روزرسانی خلاصه فقط اگر دستی است
            }
        });
        amountInput.addEventListener('focus', function() {
            if (!this.readOnly) {
                // هنگام فوکوس، عدد انگلیسی و بدون کاما نمایش داده شود (برای ویرایش راحت)
                this.value = convertToEnglishNumbers(this.value);
                 this.select(); // انتخاب کل متن برای جایگزینی آسان
            }
        });
        amountInput.addEventListener('blur', function() {
            if (!this.readOnly) {
                // هنگام خروج از فوکوس، دوباره فرمت فارسی با کاما اعمال شود
                const englishValue = convertToEnglishNumbers(this.value);
                const numericValue = parseFloat(englishValue) || 0;
                this.value = convertToPersianNumbers(numberWithCommas(numericValue.toFixed(0)));
                if (isManual) {
                    updateInstallmentSummary(); // بروزرسانی مجدد خلاصه
                }
            }
        });

        amountWrapper.appendChild(labelAmount);
        amountWrapper.appendChild(amountInput);
        dateAmountDiv.appendChild(amountWrapper); // **تغییر جدید**

        installmentDiv.appendChild(dateAmountDiv);
        container.appendChild(installmentDiv);

        // فعال‌سازی Datepicker برای فیلد تاریخ با تنظیمات مناسب
        $(inputDate).pDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true,
            initialValue: false,
            position: "auto",
            calendarType: "persian",
            observer: true, // مهم برای المان‌های داینامیک
            inputDelay: 400, // کمی تاخیر برای جلوگیری از باز شدن‌های ناخواسته
            calendar: { persian: { locale: 'fa' } },
            toolbox: {
                enabled: true,
                calendarSwitch: { enabled: false },
                todayButton: { enabled: true, text: { fa: 'امروز' } },
                submitButton: { enabled: false },
                // clearButton: { enabled: true, text: { fa: 'پاک' } } // فعال کردن دکمه پاک کردن (اختیاری)
            }
        });
    }
    // به‌روزرسانی خلاصه مجموع پس از ایجاد تمام فیلدها
    updateInstallmentSummary();
}

// تابع برای پاک کردن فیلدهای قسط و خلاصه
function clearInstallmentFields() {
    document.getElementById('installments_container').innerHTML = '';
    document.getElementById('installments_summary').innerHTML = '';
    // همچنین کلاس‌های وضعیت را از خلاصه پاک کنید
    document.getElementById('installments_summary').classList.remove('summary-match', 'summary-mismatch', 'summary-invalid');
}

// تابع برای تغییر وضعیت ورودی‌های مبلغ (خواندنی/قابل ویرایش)
// با توجه به CSS جدید، این تابع فقط فیلدها را بازنشانی می‌کند
function toggleAmountInputs(isManual) {
    // بهترین کار بازنشانی کامل فیلدهاست تا مقادیر پیش‌فرض درست محاسبه شوند
    generateInstallmentFields();
}

// تابع برای محاسبه و نمایش مجموع مبالغ وارد شده و مقایسه با کل بدهی
function updateInstallmentSummary() {
    const summaryDiv = document.getElementById('installments_summary');
    const amountInputs = document.querySelectorAll('#installments_container .amount-input');
    const isManual = document.querySelector('input[name="amount_type"]:checked').value === 'manual';

    summaryDiv.innerHTML = ''; // پاک کردن محتوای قبلی
    // پاک کردن کلاس‌های وضعیت قبلی **تغییر جدید**
    summaryDiv.classList.remove('summary-match', 'summary-mismatch', 'summary-invalid');

    if (amountInputs.length === 0) {
        return; // اگر قسطی وجود ندارد، خارج شو
    }

    let currentTotal = 0;
    let hasInvalidAmount = false;

    amountInputs.forEach(input => {
        const englishValue = convertToEnglishNumbers(input.value);
        const numericValue = parseFloat(englishValue);
        if (isNaN(numericValue) || numericValue < 0) {
            hasInvalidAmount = true; // علامت‌گذاری در صورت وجود مقدار نامعتبر
        }
        currentTotal += numericValue || 0; // اگر NaN بود، 0 جمع کن
    });

    const totalAmountPersian = convertToPersianNumbers(numberWithCommas(currentTotal.toFixed(0)));
    const totalDebtPersian = convertToPersianNumbers(numberWithCommas(totalDebtAmount.toFixed(0)));
    const difference = currentTotal - totalDebtAmount;
    const differencePersian = convertToPersianNumbers(numberWithCommas(Math.abs(difference).toFixed(0)));

    // متن اصلی خلاصه
    let summaryText = `مجموع مبالغ اقساط: ${totalAmountPersian}`;
    if (isManual) { // فقط در حالت دستی، کل بدهی را هم نشان بده
        summaryText += ` (کل بدهی: ${totalDebtPersian})`;
    }
    summaryDiv.innerHTML = `<span>${summaryText}</span>`; // نمایش متن اصلی

    let differenceText = '';
    // اضافه کردن پیام وضعیت فقط در حالت دستی
    if (isManual) {
        if (hasInvalidAmount) {
            differenceText = `<span style="color: #d35400;">حداقل یک مبلغ نامعتبر است.</span>`; // استفاده از استایل داخلی برای این پیام خاص
            summaryDiv.classList.add('summary-invalid'); // **تغییر جدید**
        } else if (Math.abs(difference) > 0.01) { // مقایسه با تلورانس کم
            const diffType = difference > 0 ? 'بیشتر' : 'کمتر';
            differenceText = `<span>مبلغ ${differencePersian} ${diffType} از کل بدهی است!</span>`;
            summaryDiv.classList.add('summary-mismatch'); // **تغییر جدید**
        } else {
            differenceText = `<span>مجموع مبالغ با کل بدهی مطابقت دارد.</span>`;
            summaryDiv.classList.add('summary-match'); // **تغییر جدید**
        }
        summaryDiv.innerHTML += differenceText; // اضافه کردن پیام وضعیت
    } else {
         // در حالت خودکار، فقط مجموع را نشان می‌دهیم و فرض می‌کنیم درست است
         summaryDiv.classList.add('summary-match'); // می‌توان کلاس مطابقت را اضافه کرد
    }
}


// تابع کمکی برای تبدیل رشته تاریخ جلالی به فرمتی که setDate ممکن است بفهمد (نیاز به تست)
// یا صرفاً برای استفاده در موارد دیگر
function parseJalaliDate(jalaliString) {
    // فرض فرمت YYYY/MM/DD
    const parts = String(jalaliString).split('/');
    if (parts.length === 3) {
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10);
        const day = parseInt(parts[2], 10);
        if (!isNaN(year) && !isNaN(month) && !isNaN(day)) {
             // pDatepicker ممکن است آرایه [y, m, d] را بفهمد
             // return [year, month, day];
             // یا ممکن است نیاز به timestamp میلی‌ثانیه داشته باشد
             // یا فقط رشته اصلی را برگردانیم
            return jalaliString; // در حال حاضر رشته را برمی‌گردانیم
        }
    }
    return null; // فرمت نامعتبر
}

// تابع برای بازگرداندن مقادیر قبلی در صورت خطا در سمت سرور
// (این تابع باید بعد از generateInstallmentFields فراخوانی شود)
function populateExistingFields(dates, amounts) {
    const dateInputs = document.querySelectorAll('#installments_container .date-input');
    const amountInputs = document.querySelectorAll('#installments_container .amount-input');
    const isManual = document.querySelector('input[name="amount_type"]:checked').value === 'manual';

    dates.forEach((date, index) => {
        if (dateInputs[index] && date) {
            // بهترین روش تنظیم مقدار ورودی و اجازه دادن به Datepicker برای خواندن آن است
            dateInputs[index].value = date;
             // فعال‌سازی مجدد یا آپدیت Datepicker ممکن است لازم باشد اگر observer کار نکند
             // $(dateInputs[index]).pDatepicker('update');
        }
    });

    amounts.forEach((amount, index) => {
        if (amountInputs[index] && amount !== null && amount !== undefined) {
            const englishValue = convertToEnglishNumbers(String(amount));
            const numericValue = parseFloat(englishValue) || 0;
            amountInputs[index].value = convertToPersianNumbers(numberWithCommas(numericValue.toFixed(0)));
            // وضعیت readOnly قبلاً در generateInstallmentFields تنظیم شده است
            // amountInputs[index].readOnly = !isManual;
        }
    });
    // به‌روزرسانی خلاصه پس از پر کردن مقادیر
    updateInstallmentSummary();
}


// اجرای اولیه هنگام بارگذاری صفحه
$(document).ready(function() {
    // کد ملی اولیه از PHP (اگر وجود داشته باشد)
    const initialNationalCode = '<?= htmlspecialchars($search_national_code ?? "") ?>';

    // اگر کد ملی از قبل وجود دارد (مثلا بعد از جستجو یا خطا در ثبت)
    if (initialNationalCode) {
        // تبدیل کد ملی در فیلد جستجو به فارسی
        const nationalCodeInput = document.getElementById('national_code_input');
        if(nationalCodeInput) {
            nationalCodeInput.value = convertToPersianNumbers(initialNationalCode);
        }

        // اگر عنوان بدهی از قبل انتخاب شده (مثلا بعد از خطا)
        const selectedDebtTitle = document.getElementById('debt_title').value;
        if (selectedDebtTitle) {
            fetchDebtAmount(); // دریافت مبلغ برای عنوان انتخاب شده
            // توجه: فیلدهای قسط توسط کد PHP که populateExistingFields را صدا می‌زند بازسازی می‌شوند
        } else {
            // اگر عنوانی انتخاب نشده، دکمه ایجاد قسط غیرفعال باشد
            $('#generate_installments_btn').prop('disabled', true);
        }
    } else {
        // اگر کد ملی وجود ندارد (بار اول)، دکمه ایجاد قسط غیرفعال باشد
        $('#generate_installments_btn').prop('disabled', true);
    }

    // مخفی کردن پیام‌های سرور بعد از چند ثانیه (اگر تابعش موجود باشد)
    if (typeof hideMessages === 'function') {
        hideMessages(7000); // مثلا بعد از ۷ ثانیه
    }

    // اطمینان از آپدیت خلاصه هنگام تغییر تعداد اقساط یا نوع مبلغ
    $('#number_of_installments').on('change', generateInstallmentFields);
    $('input[name="amount_type"]').on('change', function() {
        toggleAmountInputs(this.value === 'manual');
    });
     // هنگام تغییر عنوان بدهی هم خلاصه آپدیت شود (بعد از fetch)
     // این کار داخل fetchDebtAmount انجام می‌شود با clearInstallmentFields

});
</script>
<?php
include "../footer.php";
?>