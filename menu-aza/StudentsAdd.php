<?php
include "../header.php";
include "../db_connection.php";
$cnn = (new class_db())->connection_database;

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$success_msg = []; //آرایه ای برای ذخیره پیام موفقیت
$errors = []; // آرایه‌ای برای ذخیره پیام‌های خطا
$stmt = null; // تعریف اولیه متغیر $stmt

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // بررسی ارسال فایل اکسل
    if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['excelFile']['tmp_name'];
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheetData = $spreadsheet->getActiveSheet()->toArray();

        // فرض کنید که فایل اکسل شامل داده‌های اعضاست
        foreach ($sheetData as $row) {
            // فرض کنید ستون‌های اکسل به ترتیب: نام، نام خانوادگی، کد ملی، رشته، پایه
            $firstNameExcel = $row[0]; // نام
            $lastNameExcel = $row[1]; // نام خانوادگی
            $nationalCodeExcel = $row[2]; // کد ملی
            $fieldExcel = $row[3]; // رشته
            $gradeExcel = $row[4]; // پایه

            if (empty($firstNameExcel) || empty($lastNameExcel) || empty($nationalCodeExcel) || empty($fieldExcel) || empty($gradeExcel)) {
                $errors[] = "در یکی از رکوردهای فایل اکسل فیلدهای خالی وجود دارد.";
                continue; // ادامه می‌دهیم به رکورد بعدی
            }

            // بررسی وجود کد ملی در پایگاه داده
            $checkStmt = $cnn->prepare("SELECT id FROM students WHERE national_code = ?");
            $checkStmt->bind_param("s", $nationalCodeExcel);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $errors[] = "کد ملی '{$nationalCodeExcel}' قبلاً ثبت شده است.";
            } else {
                // ذخیره اطلاعات اکسل در پایگاه داده
                $stmt = $cnn->prepare("INSERT INTO students (first_name, last_name, national_code, field, grade) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) { // بررسی اینکه آیا $stmt به درستی ایجاد شده است
                    $stmt->bind_param("sssss", $firstNameExcel, $lastNameExcel, $nationalCodeExcel, $fieldExcel, $gradeExcel);

                    if (!$stmt->execute()) {
                        $errorMessages[] = "خطا در افزودن عضو از فایل اکسل: " . $stmt->error;
                    }
                } else {
                    $errors[] = "خطا در آماده‌سازی پرس و جو.";
                }
            }

            $checkStmt->close();
        }

        if (empty($errorMessages)) {
            $success_msg[] = "تمامی اعضا از فایل اکسل با موفقیت اضافه شدند!";
        }
    } else {
        // بررسی فیلدهای فرم برای ثبت دانش آموز جدید
        if (isset($_POST['firstName']) && !empty($_POST['firstName'])
            && isset($_POST['lastName']) && !empty($_POST['lastName'])
            && isset($_POST['nationalCode']) && !empty($_POST['nationalCode'])
            && isset($_POST['field']) && !empty($_POST['field'])
            && isset($_POST['grade']) && !empty($_POST['grade'])) {
            
            // دریافت داده‌ها از فرم
            $firstName = trim($_POST["firstName"]);
            $lastName = trim($_POST["lastName"]);
            $nationalCode = trim($_POST["nationalCode"]);
            $field = trim($_POST["field"]);
            $grade = trim($_POST["grade"]);
            
            // بررسی وجود کد ملی در پایگاه داده
            $checkStmt = $cnn->prepare("SELECT id FROM students WHERE national_code = ?");
            $checkStmt->bind_param("s", $nationalCode);
            $checkStmt->execute();
            $checkStmt->store_result();
            
            if ($checkStmt->num_rows > 0) {
                $errorMessages[] = "کد ملی '{$nationalCode}' قبلاً ثبت شده است.";
            } else {
                // ذخیره اطلاعات اعضا از فرم
                $stmt = $cnn->prepare("INSERT INTO students (first_name, last_name, national_code, field, grade) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) { // بررسی اینکه آیا $stmt به درستی ایجاد شده است
                    $stmt->bind_param("sssss", $firstName, $lastName, $nationalCode, $field, $grade);
                    
                    if ($stmt->execute()) {
                        $success_msg[] = "دانش آموز افزوده شد";
                    } else {
                        $errors[] = "خطا در افزودن عضو: " . $stmt->error;
                    }
                } else {
                    $errors[] = "خطا در آماده‌سازی پرس و جو.";
                }
            }
            $checkStmt->close();
        } else {
            $errors[] = "لطفا همه فیلدها را پر کنید";
        }
    }

    if ($stmt) {
        $stmt->close(); // بستن $stmt فقط اگر تعریف شده باشد
    }
}

// بستن اتصال
$cnn->close();
?>
<div class="container">
    <h1 id="h1">افزودن دانش آموز جدید</h1>

    <!-- نمایش پیام‌های خطا -->
    <?php if (!empty($errors)) { ?>
        <?php foreach ($errors as $error) { ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php }
    } elseif (!empty($success_msg)) { ?>
        <div class="success-message"><?php echo htmlspecialchars($success_msg[0]); ?></div>
    <?php } ?>

    <form id="Form" action="" method="post" enctype="multipart/form-data">
        <label id="label" for="firstName">نام:</label>
        <input class="input" type="text" name="firstName" id="firstName" >

        <label id="label" for="lastName">نام خانوادگی:</label>
        <input class="input" type="text" name="lastName" id="lastName" >

        <label id="label" for="nationalCode">کد ملی:</label>
        <input class="input" type="text" name="nationalCode" id="nationalCode" >
        
        <label id="label" for="field">رشته:</label>
        <select class="select" name="field" id="field" >
            <option value="">انتخاب کنید</option>
            <option value="شبکه و نرم افزار">شبکه و نرم افزار</option>
            <option value="الکترونیک">الکترونیک</option>
            <option value="الکتروتکنیک">الکتروتکنیک</option>
        </select>

        <label id="label" for="grade">پایه:</label>
        <select class="select" name="grade" id="grade" >
            <option value="">انتخاب کنید</option>
            <option value="دهم">دهم</option>
            <option value="یازدهم">یازدهم</option>
            <option value="دوازدهم">دوازدهم</option>
        </select>

        <label id="label" for="excelFile">بارگذاری فایل اکسل:</label>
        <input class="input" type="file" name="excelFile" id="excelFile" accept=".xls, .xlsx" >

        <button id="submit_btn" type="submit">افزودن</button>
        <button id="submit_btn" type="button" onclick="window.location.href='../index.php'">انصراف</button>
    </form>
</div>
<script src="../assets/js/hideMessage.js"></script>
<?php
include "../footer.php";
?>