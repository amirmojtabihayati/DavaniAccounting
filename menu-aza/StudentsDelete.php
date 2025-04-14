<?php
declare(strict_types=1);
include_once "../header.php";
include_once "../db_connection.php";

// بررسی وجود ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: StudentsList.php");
    exit();
}

$student_id = (int)$_GET['id'];
$db = new class_db();
$cnn = $db->connection_database();

// دریافت اطلاعات دانش‌آموز
$stmt = $cnn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    header("Location: StudentsList.php");
    exit();
}

// پردازش درخواست حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delete_stmt = $cnn->prepare("DELETE FROM students WHERE id = ?");
    $delete_stmt->bind_param("i", $student_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['flash_message'] = 'دانش‌آموز با موفقیت حذف شد';
        header("Location: StudentsList.php");
        exit();
    }
}
?>

<div class="container-lg py-4">
    <div class="card shadow">
        <div class="card-header bg-danger">
            <h3 class="mb-0 text-white">
                <i class="bi bi-trash3 me-2"></i>
                حذف دانش‌آموز
            </h3>
        </div>
        
        <div class="card-body">
            <div class="alert alert-warning">
                <h4 class="alert-heading">هشدار!</h4>
                <p>آیا مطمئن هستید می‌خواهید دانش‌آموز زیر را حذف کنید؟</p>
                <hr>
                <dl class="row">
                    <dt class="col-sm-3">نام کامل:</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($student['first_name'] . ' ' . htmlspecialchars($student['last_name'])) ?></dd>
                    
                    <dt class="col-sm-3">کد ملی:</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($student['national_code']) ?></dd>
                    
                    <dt class="col-sm-3">رشته و پایه:</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($student['field'] . ' - ' . $student['grade']) ?></dd>
                </dl>
            </div>

            <form method="post">
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>
                        تایید حذف
                    </button>
                    <a href="StudentsDetails.php?id=<?= $student_id ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-1"></i>
                        لغو
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "../footer.php"; ?>
