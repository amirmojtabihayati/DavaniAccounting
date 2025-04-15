<?php
include "../header.php";
include "../db_connection.php";

// اتصال به دیتابیس
$cnn = (new class_db())->connection_database;

// کتابخانه های لازم برای کار با اکسل
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// آرایه هایی برای ذخیره پیام های موفقیت و خطا
$success_msg = [];
$errors = [];

// تعریف اولیه متغیرهای مورد نیاز
$stmt = null;
$checkStmt = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // بررسی آپلود فایل اکسل
    if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['excelFile']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheetData = $spreadsheet->getActiveSheet()->toArray();

            foreach ($sheetData as $row) {
                // دریافت داده ها از فایل اکسل
                $firstNameExcel = trim($row[0] ?? '');
                $lastNameExcel = trim($row[1] ?? '');
                $nationalCodeExcel = trim($row[2] ?? '');
                $fieldExcel = trim($row[3] ?? '');
                $gradeExcel = trim($row[4] ?? '');

                // اعتبارسنجی داده ها
                if (empty($firstNameExcel) || empty($lastNameExcel) || empty($nationalCodeExcel) || empty($fieldExcel) || empty($gradeExcel)) {
                    $errors[] = "در یکی از رکوردهای فایل اکسل فیلدهای خالی وجود دارد.";
                    continue; // ادامه به رکورد بعدی
                }

                // بررسی وجود کد ملی در پایگاه داده
                $checkStmt = $cnn->prepare("SELECT id FROM students WHERE national_code = ?");
                $checkStmt->bind_param("s", $nationalCodeExcel);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    $errors[] = "کد ملی '{$nationalCodeExcel}' قبلاً ثبت شده است.";
                } else {
                    // ذخیره اطلاعات در پایگاه داده
                    $stmt = $cnn->prepare("INSERT INTO students (first_name, last_name, national_code, field, grade) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("sssss", $firstNameExcel, $lastNameExcel, $nationalCodeExcel, $fieldExcel, $gradeExcel);                        if (!$stmt->execute()) {
                            $errors[] = "خطا در افزودن عضو از فایل اکسل: " . $stmt->error;
                        } else {
                            $success_msg[] = "دانش آموز از فایل اکسل با موفقیت اضافه شد: " . $firstNameExcel . " " . $lastNameExcel;
                        }
                    } else {
                        $errors[] = "خطا در آماده‌سازی پرس و جو (اکسل).";
                    }
                }
                $checkStmt->close(); // بستن checkStmt در هر حلقه
            }            if (empty($errors)) {
                $success_msg[] = "تمامی اعضا از فایل اکسل با موفقیت اضافه شدند!";
            }

        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            $errors[] = "خطا در خواندن فایل اکسل: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "خطا در پردازش فایل اکسل: " . $e->getMessage();
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

            // اعتبارسنجی کد ملی
            if (!preg_match('/^\d{10}$/', $nationalCode)) {
                $errors[] = "کد ملی وارد شده نامعتبر است.";
            } else {
                // بررسی وجود کد ملی در پایگاه داده
                $checkStmt = $cnn->prepare("SELECT id FROM students WHERE national_code = ?");
                $checkStmt->bind_param("s", $nationalCode);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    $errors[] = "کد ملی '{$nationalCode}' قبلاً ثبت شده است.";
                } else {
                    // ذخیره اطلاعات اعضا از فرم
                    $stmt = $cnn->prepare("INSERT INTO students (first_name, last_name, national_code, field, grade) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("sssss", $firstName, $lastName, $nationalCode, $field, $grade);

                        if ($stmt->execute()) {
                            $success_msg[] = "دانش آموز با موفقیت افزوده شد: " . $firstName . " " . $lastName;
                        } else {
                            $errors[] = "خطا در افزودن عضو: " . $stmt->error;
                        }
                    } else {
                        $errors[] = "خطا در آماده‌سازی پرس و جو (فرم).";
                    }
                }
                $checkStmt->close(); // بستن checkStmt در هر بار استفاده
            }        } else {
            $errors[] = "لطفا همه فیلدها را پر کنید.";
        }
    }

    // بستن اتصال
    if ($stmt) {
        $stmt->close(); // بستن $stmt اگر تعریف شده باشد
    }
}
// بستن اتصال
$cnn->close();
?>

<!DOCTYPE html>
<html lang="en" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>افزودن دانش آموز جدید</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Vazir', Tahoma, sans-serif;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #007bff;
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-label {
            font-weight: 600;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
            border-color: #565e64;
        }

        .error-message {
            color: #dc3545;
            margin-bottom: 0.5rem;
        }

        .success-message {
            color: #28a745;
            margin-bottom: 0.5rem;
        }    </style>
</head>
<body>

<div class="container">
    <div class="card shadow">
        <div class="card-header">
            <h1 class="mb-0">
                <i class="bi bi-person-plus-fill me-2"></i>
                افزودن دانش آموز جدید
            </h1>
        </div>

        <div class="card-body">
            <!-- نمایش پیام‌های خطا و موفقیت -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif (!empty($success_msg)): ?>
                <div class="alert alert-success">
                    <ul>
                        <?php foreach ($success_msg as $msg): ?>
                            <li><?= htmlspecialchars($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="Form" action="" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="firstName" class="form-label">نام:</label>
                    <input type="text" class="form-control" name="firstName" id="firstName" >
                </div>

                <div class="mb-3">
                    <label for="lastName" class="form-label">نام خانوادگی:</label>
                    <input type="text" class="form-control" name="lastName" id="lastName" >
                </div>

                <div class="mb-3">
                    <label for="nationalCode" class="form-label">کد ملی:</label>
                    <input type="text" class="form-control" name="nationalCode" id="nationalCode" pattern="\d{10}" >
                </div>

                <div class="mb-3">
                    <label for="field" class="form-label">رشته:</label>
                    <select class="form-select" name="field" id="field" >
                        <option value="">انتخاب کنید</option>
                        <option value="شبکه و نرم افزار">شبکه و نرم افزار</option>
                        <option value="الکترونیک">الکترونیک</option>
                        <option value="الکتروتکنیک">الکتروتکنیک</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="grade" class="form-label">پایه:</label>
                    <select class="form-select" name="grade" id="grade" >
                        <option value="">انتخاب کنید</option>
                        <option value="دهم">دهم</option>
                        <option value="یازدهم">یازدهم</option>
                        <option value="دوازدهم">دوازدهم</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="excelFile" class="form-label">بارگذاری فایل اکسل:</label>                    <input type="file" class="form-control" name="excelFile" id="excelFile" accept=".xls, .xlsx">
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary me-2">                        <i class="bi bi-plus-circle-fill me-1"></i>
                        افزودن
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='../Home.php'">
                        <i class="bi bi-x-circle-fill me-1"></i>
                        انصراف
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
include "../footer.php";
?>