<?php
declare(strict_types=1);

include "../header.php";
include_once "../convertToPersianNumbers.php";
include_once "../db_connection.php";

error_reporting(E_ALL);
ini_set('display_errors', '1');

// اتصال به دیتابیس
$cnn = (new class_db())->connection_database;

// تنظیمات پیمایش
$results_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $results_per_page;

// مدیریت پارامترهای جستجو
$searchCondition = "";
$params = [];
$types = "";

// بررسی درخواست POST برای جستجو
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['data'])) {
    $student = json_decode($_POST['data'], true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $searchFields = [
            'name' => 'first_name',
            'family' => 'last_name',
            'natCode' => 'national_code',
            'field' => 'field',
            'grade' => 'grade'
        ];

        foreach ($searchFields as $key => $column) {
            if (!empty($student[$key])) {
                if ($key === 'grade') {
                    // جستجوی دقیق برای پایه
                    $searchCondition .= " AND `$column` = ?";
                    $params[] = htmlspecialchars(trim($student[$key]));
                    $types .= 's'; // s برای string
                } else {
                    // جستجوی LIKE برای سایر فیلدها
                    $searchCondition .= " AND `$column` LIKE ?";
                    $params[] = '%' . htmlspecialchars(trim($student[$key])) . '%';                    $types .= 's'; // s برای string
                }
            }
        }
    }
}

// کوئری شمارش کل نتایج
$total_sql = "SELECT COUNT(*) as total FROM students WHERE 1=1 $searchCondition";
$total_stmt = $cnn->prepare($total_sql);

if (!empty($params)) {
    $total_stmt->bind_param($types, ...$params);
}

$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_students = $total_row['total'];
$total_pages = ceil($total_students / $results_per_page);

// کوئری دریافت داده‌ها
$sql = "SELECT * FROM students WHERE 1=1 $searchCondition LIMIT ?, ?";
$stmt = $cnn->prepare($sql);

// مدیریت پارامترها
$limit_params = [$offset, $results_per_page];
$limit_types = "ii"; // ii برای integer

if (!empty($params)) {
    $full_types = $types . $limit_types;
    $full_params = array_merge($params, $limit_params);
} else {
    $full_types = $limit_types;
    $full_params = $limit_params;
}

$stmt->bind_param($full_types, ...$full_params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-lg py-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">لیست دانش آموزان</h3>
        </div>

        <div class="card-body">
            <form id="searchForm" class="row g-3 mb-4">
                <div class="col-md-2">
                    <input type="text" class="form-control" id="name" placeholder="نام">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control" id="family" placeholder="نام خانوادگی">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control" id="natCode" placeholder="کد ملی">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="field">
                        <option value="">همه رشته‌ها</option>
                        <option>شبکه و نرم افزار</option>
                        <option>الکترونیک</option>
                        <option>الکتروتکنیک</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="grade">
                        <option value="">همه پایه‌ها</option>
                        <option>دهم</option>
                        <option>یازدهم</option>
                        <option>دوازدهم</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="button" class="btn btn-danger" id="resetButton">
                        <i class="bi bi-arrow-clockwise"></i> بازنشانی
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-dark">
                    <tr>
                        <th>نام</th>
                        <th>نام خانوادگی</th>
                        <th>کد ملی</th>
                        <th>رشته</th>
                        <th>پایه</th>
                        <th class="text-center">عملیات</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="align-middle">
                                <td><?= htmlspecialchars($row['first_name']) ?></td>
                                <td><?= htmlspecialchars($row['last_name']) ?></td>
                                <td dir="ltr"><?= convertToPersianNumbers($row['national_code']) ?></td>
                                <td><span class="badge bg-info"><?= $row['field'] ?></span></td>
                                <td><span class="badge bg-success"><?= $row['grade'] ?></span></td>                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="StudentsDetails.php?id=<?= $row['id'] ?>"
                                           class="btn btn-sm btn-outline-primary"
                                           data-bs-toggle="tooltip"
                                           title="جزئیات">
                                            <i class="bi bi-file-text"></i>
                                        </a>
                                        <a href="StudentsUpdate.php?id=<?= $row['id'] ?>"
                                           class="btn btn-sm btn-outline-warning"
                                           data-bs-toggle="tooltip"
                                           title="ویرایش">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="StudentsDelete.php?id=<?= $row['id'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           data-bs-toggle="tooltip"
                                           title="حذف"
                                           onclick="return confirm('آیا مطمئن هستید؟')">
                                            <i class="bi bi-trash3"></i>                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">موردی یافت نشد</td>
                        </tr>
                    <?php endif; ?>                    </tbody>
                </table>
            </div>

            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1" aria-label="First">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                <span aria-hidden="true">&lsaquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= convertToPersianNumbers($i) ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                <span aria-hidden="true">&rsaquo;</span>
                            </a>
                        </li>                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $total_pages ?>" aria-label="Last">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div></div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const debounce = (func, timeout = 500) => {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, args), timeout);
            };
        };

        const handleSearch = () => {
            const data = {
                name: document.getElementById('name').value,
                family: document.getElementById('family').value,
                natCode: document.getElementById('natCode').value,
                field: document.getElementById('field').value,
                grade: document.getElementById('grade').value
            };

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `data=${encodeURIComponent(JSON.stringify(data))}`
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');                document.querySelector('tbody').innerHTML = doc.querySelector('tbody').innerHTML;
                document.querySelector('.pagination').innerHTML = doc.querySelector('.pagination').innerHTML;
            });
        };

        document.getElementById('resetButton').addEventListener('click', () => {
            document.querySelectorAll('#searchForm input, #searchForm select').forEach(element => {
                element.value = '';
            });
            handleSearch();
        });

        document.querySelectorAll('#searchForm input, #searchForm select').forEach(element => {
            element.addEventListener('input', debounce(handleSearch));
        });
    });
</script>
<?php
include "../footer.php";
?>