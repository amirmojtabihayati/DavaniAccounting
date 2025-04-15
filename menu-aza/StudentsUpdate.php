<?php
ob_start();
// declare(strict_types=1);
include_once "../header.php";
include_once "../db_connection.php";

// بررسی وجود ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: StudentsList.php");
    exit();
}

$student_id = (int)$_GET['id'];
$cnn = (new class_db())->connection_database;

// دریافت اطلاعات دانش‌آموز
$stmt = $cnn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    header("Location: StudentsList.php");
    exit();
}

// پردازش فرم ویرایش
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'national_code' => $_POST['national_code'] ?? '',
        'field' => $_POST['field'] ?? '',
        'grade' => $_POST['grade'] ?? ''
    ];

    // اعتبارسنجی داده‌ها
    $errors = [];
    if (empty($fields['first_name'])) $errors[] = 'نام الزامی است';
    if (empty($fields['last_name'])) $errors[] = 'نام خانوادگی الزامی است';
    if (!preg_match('/^\d{10}$/', $fields['national_code'])) $errors[] = 'کد ملی نامعتبر';

    if (empty($errors)) {
        $update_stmt = $cnn->prepare("
            UPDATE students 
            SET first_name = ?, last_name = ?, national_code = ?, field = ?, grade = ?
            WHERE id = ?
        ");
        $update_stmt->bind_param(
            "sssssi",
            $fields['first_name'],
            $fields['last_name'],
            $fields['national_code'],
            $fields['field'],
            $fields['grade'],
            $student_id
        );

        if ($update_stmt->execute()) {
            $_SESSION['flash_message'] = 'اطلاعات با موفقیت بروزرسانی شد';
            header("Location: StudentsList.php");
            exit();
        }
    }
}
?>

<div class="container-lg py-4">
    <div class="card shadow">
        <div class="card-header bg-warning text-white">
            <h3 class="mb-0">
                <i class="bi bi-pencil-square me-2"></i>
                ویرایش دانش‌آموز
            </h3>
        </div>

        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label for="firstName" class="form-label">نام</label>
                    <input type="text"
                           class="form-control"
                           id="firstName"
                           name="first_name"
                           value="<?= htmlspecialchars($student['first_name']) ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label for="lastName" class="form-label">نام خانوادگی</label>
                    <input type="text"
                           class="form-control"
                           id="lastName"
                           name="last_name"
                           value="<?= htmlspecialchars($student['last_name']) ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label for="nationalCode" class="form-label">کد ملی</label>
                    <input type="text"
                           class="form-control"
                           id="nationalCode"
                           name="national_code"
                           pattern="\d{10}"
                           value="<?= htmlspecialchars($student['national_code']) ?>"
                           required>
                </div>

                <div class="col-md-6">
                    <label for="fieldSelect" class="form-label">رشته</label>
                    <select class="form-select" id="fieldSelect" name="field" required>
                        <option value="شبکه و نرم افزار" <?= $student['field'] === 'شبکه و نرم افزار' ? 'selected' : '' ?>>شبکه و نرم افزار</option>
                        <option value="الکترونیک" <?= $student['field'] === 'الکترونیک' ? 'selected' : '' ?>>الکترونیک</option>
                        <option value="الکتروتکنیک" <?= $student['field'] === 'الکتروتکنیک' ? 'selected' : '' ?>>الکتروتکنیک</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="gradeSelect" class="form-label">پایه</label>
                    <select class="form-select" id="gradeSelect" name="grade" required>
                        <option value="دهم" <?= $student['grade'] === 'دهم' ? 'selected' : '' ?>>دهم</option>
                        <option value="یازدهم" <?= $student['grade'] === 'یازدهم' ? 'selected' : '' ?>>یازدهم</option>
                        <option value="دوازدهم" <?= $student['grade'] === 'دوازدهم' ? 'selected' : '' ?>>دوازدهم</option>
                    </select>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-save me-1"></i>
                        ذخیره تغییرات
                    </button>
                    <a href="StudentsList.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-1"></i>
                        بازگشت
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include "../footer.php";
ob_end_flush();
?>