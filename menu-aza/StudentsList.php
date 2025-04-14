<?php
declare(strict_types=1);
// include "../header.php";
include_once "../convertToPersianNumbers.php";
include_once "../db_connection.php";

error_reporting(E_ALL);
ini_set('display_errors', '1');

$cnn = (new class_db())->connection_database;

// تنظیمات پیمایش
$results_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $results_per_page;

// مدیریت پارامترهای جستجو
$searchCondition = "";
$params = [];
$types = "";

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
                $searchCondition .= " AND `$column` LIKE ?";
                $params[] = '%' . htmlspecialchars(trim($student[$key])) . '%';
            }
        }
        
        $types = str_repeat('s', count($params));
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
$limit_types = "ii";

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        /* تنظیمات کلی */
body {
    font-family: 'Vazir', Tahoma, sans-serif;
    background-color: #f8f9fa;
}

/* استایل کارت اصلی */
.card {
    border-radius: 15px;
    overflow: hidden;
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

/* هدر کارت */
.card-header {
    padding: 1.5rem;
    border-bottom: 3px solid rgba(255,255,255,0.1);
}

/* فرم جستجو */
#searchForm input,
#searchForm select {
    border-radius: 8px;
    transition: all 0.3s ease;
}

#searchForm input:focus,
#searchForm select:focus {
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
    border-color: #0d6efd;
}

/* جدول */
.table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0 8px;
}

.table thead th {
    background-color: #2c3e50;
    color: white;
    border-bottom: 2px solid #dee2e6;
    vertical-align: middle;
}

.table tbody tr {
    background-color: white;
    transition: all 0.2s ease;
    position: relative;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    transform: scale(1.005);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.table tbody td {
    vertical-align: middle;
    padding: 1rem;
    border-top: 1px solid #e9ecef;
}

/* بدج‌ها */
.badge {
    padding: 0.6em 1em;
    font-weight: 500;
    letter-spacing: 0.5px;
}

.bg-info { background-color: #17a2b8!important; }
.bg-success { background-color: #28a745!important; }

/* دکمه‌ها */
.btn-group .btn {
    border-radius: 8px;
    margin: 0 3px;
    padding: 0.5rem 0.8rem;
}

.btn-outline-primary:hover { background-color: #0d6efd; }
.btn-outline-warning:hover { background-color: #ffc107; }
.btn-outline-danger:hover { background-color: #dc3545; }

/* صفحه‌بندی */
.pagination {
    gap: 5px;
}

.page-link {
    border-radius: 8px!important;
    min-width: 40px;
    text-align: center;
    border: 1px solid #dee2e6;
    color: #2c3e50;
}

.page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.page-link:hover {
    color: #0d6efd;
    background-color: #e9ecef;
}

/* اعلان‌ها */
.alert {
    border-radius: 10px;
    border: none;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
}

/* افکت‌های سفارشی */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.table tbody tr {
    animation: fadeIn 0.4s ease forwards;
}

/* جهت‌دهی راست به چپ */
[dir="rtl"] .table td,
[dir="rtl"] .table th {
    text-align: right;
}

/* رسپانسیو */
@media (max-width: 768px) {
    .card-header h3 {
        font-size: 1.4rem;
    }
    
    .btn-group .btn {
        padding: 0.4rem 0.6rem;
    }
    
    .table thead {
        display: none;
    }
    
    .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .table tbody td {
        display: block;
        text-align: left;
        position: relative;
        padding-left: 50%;
    }
    
    .table tbody td::before {
        content: attr(data-label);
        position: absolute;
        left: 1rem;
        width: 45%;
        padding-right: 1rem;
        font-weight: bold;
        color: #2c3e50;
    }
}
    </style>
</head>
<body>

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
                        <option>ریاضی</option>
                        <option>تجربی</option>
                        <option>انسانی</option>
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
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="align-middle">
                            <td><?= htmlspecialchars($row['first_name']) ?></td>
                            <td><?= htmlspecialchars($row['last_name']) ?></td>
                            <td dir="ltr"><?= convertToPersianNumbers($row['national_code']) ?></td>
                            <td><span class="badge bg-info"><?= $row['field'] ?></span></td>
                            <td><span class="badge bg-success"><?= $row['grade'] ?></span></td>
                            <td class="text-center">
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
                                        <i class="bi bi-trash3"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <?php if($result->num_rows === 0): ?>
                <div class="alert alert-warning text-center">موردی یافت نشد</div>
                <?php endif; ?>
            </div>

            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if($page > 1): ?>
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

                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= convertToPersianNumbers($i) ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                            <span aria-hidden="true">&rsaquo;</span>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $total_pages ?>" aria-label="Last">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

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
            const doc = parser.parseFromString(html, 'text/html');
            document.querySelector('tbody').innerHTML = doc.querySelector('tbody').innerHTML;
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
</body>
</html>
<?php 
// include "../footer.php";
 ?>