<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../header.php";
include_once "../Jalali-Date/jdf.php"; // اطمینان از بارگذاری درست کتابخانه jdf
include_once "../convertToPersianNumbers.php";

include "../db_connection.php";
$cnn = (new class_db())->connection_database;

// تنظیمات پیمایش
$results_per_page = 10; // تعداد نتایج در هر صفحه
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // صفحه فعلی
$offset = ($page - 1) * $results_per_page; // محاسبه آفسِت

// جستجو
$searchCondition = "";
$params = [];
if (isset($_POST['data'])) {
    $q_json = $_POST['data'];
    $student = json_decode($q_json);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'Error decoding JSON: ' . json_last_error_msg();
        exit();
    }

    function cleanInput($data) {
        return htmlspecialchars(trim($data));
    }

    // Clean student data
    $name = isset($student->name) ? cleanInput($student->name) : '';
    $family = isset($student->family) ? cleanInput($student->family) : '';
    $natCode = isset($student->natCode) ? cleanInput($student->natCode) : '';
    $field = isset($student->field) ? cleanInput($student->field) : '';
    $grade = isset($student->grade) ? cleanInput($student->grade) : '';
    $approval_number = isset($student->approval_number) ? cleanInput($student->approval_number) : '';

    // Prepare search conditions
    if (!empty($name)) {
        $searchCondition .= " AND s.`first_name` LIKE ?";
        $params[] = "%$name%";
    }
    if (!empty($family)) {
        $searchCondition .= " AND s.`last_name` LIKE ?";
        $params[] = "%$family%";
    }
    if (!empty($natCode)) {
        $searchCondition .= " AND s.`national_code` LIKE ?";
        $params[] = "%$natCode%";
    }
    if (!empty($field)) {
        $searchCondition .= " AND s.`field` LIKE ?";
        $params[] = "%$field%";
    }
    if (!empty($grade)) {
        $searchCondition .= " AND s.`grade` LIKE ?";
        $params[] = "$grade";
    }
    if (!empty($approval_number)) {
        $searchCondition .= " AND d.`approval_number` LIKE ?";
        $params[] = "%$approval_number%";
    }
}

// شروع کوئری برای شمارش کل دانش‌آموزان و بدهی‌ها
$total_sql = "SELECT COUNT(DISTINCT s.id) as total FROM `students` s
              JOIN `debts` d ON s.id = d.student_id
              WHERE 1=1 $searchCondition";
$total_stmt = $cnn->prepare($total_sql);
if ($params) {
    $types = str_repeat('s', count($params)); // Assuming all parameters are strings
    $total_stmt->bind_param($types, ...$params);
}
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_students = $total_row['total'];
$total_pages = ceil($total_students / $results_per_page); // محاسبه تعداد کل صفحات

// شروع کوئری برای بارگذاری دانش‌آموزان و بدهی‌ها
$sql = "SELECT s.id, s.first_name, s.last_name, s.national_code, d.amount, d.approval_number, d.date FROM `students` s
        JOIN `debts` d ON s.id = d.student_id
        WHERE 1=1 $searchCondition
        GROUP BY s.id
        LIMIT ?, ?";
$stmt = $cnn->prepare($sql);
if ($params) {
    $types = str_repeat('s', count($params)) . 'ii'; // Assuming all parameters are strings, and adding 'ii' for offset and limit
    $params[] = $offset; // آفسِت
    $params[] = $results_per_page; // تعداد نتایج در هر صفحه
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $offset, $results_per_page);
}

$stmt->execute();
$result = $stmt->get_result();

// محاسبه مجموع بدهی‌ها
$total_debt_sql = "SELECT SUM(d.amount) as total_debt FROM `debts` d 
                   JOIN `students` s ON s.id = d.student_id 
                   WHERE 1=1 $searchCondition";
$total_debt_stmt = $cnn->prepare($total_debt_sql);
if ($params) {
    $total_debt_stmt->bind_param($types, ...$params);
}
$total_debt_stmt->execute();
$total_debt_result = $total_debt_stmt->get_result();
$total_debt_row = $total_debt_result->fetch_assoc();
$total_debt = $total_debt_row['total_debt'] ?? 0; // مقدار کل بدهی

?>
<div class="container-kol">
    <table>
    <thead>
        <tr>
            <th>نام</th>
            <th>کد ملی</th>
            <th>مقدار بدهی</th> 
            <th>شماره مصوبه</th>
            <th>تاریخ بدهی</th> 
            <th>جزئیات</th> 
        </tr>
    </thead>
    <tbody>
        <?php
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $firstName = htmlspecialchars($row['first_name']);
                $lastName = htmlspecialchars($row['last_name']);
                $nat_code = htmlspecialchars($row['national_code']);
                $debt_amount = htmlspecialchars($row['amount']); 
                $date = htmlspecialchars($row['date']); 
                $approval_number = htmlspecialchars($row['approval_number']); 
                $id = $row['id']; // اضافه کردن شناسه دانش‌آموز

                // تبدیل تاریخ میلادی به timestamp
                $timestamp = strtotime($date); 
                // تبدیل به تاریخ شمسی
                $jalaliDate = jdate('Y/m/d', $timestamp); 
        ?>
        <tr class="student-row" data-name="<?= $firstName . ' ' . $lastName ?>"
            data-natcode="<?= $nat_code ?>"
            data-approval_number="<?= $approval_number ?>">
            <td><?= $firstName . ' ' . $lastName ?></td>
            <td><?= convertToPersianNumbers($nat_code) ?></td>
            <td><?= convertToPersianNumbers(number_format($total_debt, 0, ',', ',') . " (تومان)") ?></td>
            <td><?= convertToPersianNumbers($approval_number) ?></td>
            <td><?= convertToPersianNumbers($jalaliDate); ?></td>
            <td>
                <a href="debtsDetail.php?id=<?= $id ?>" title data-bs-original-title="جزئیات">
                    <i>
                    <svg version="1.1" class="has-solid " viewBox="0 0 36 36" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" focusable="false" role="img" width="20" height="20" fill="#00aaff">
                    <path d="M32,6H4A2,2,0,0,0,2,8V28a2,2,0,0,0,2,2H32a2,2,0,0,0,2-2V8A2,2,0,0,0,32,6Zm0,22H4V8H32Z" class="clr-i-outline clr-i-outline-path-1"/>
                    <path d="M9,14H27a1,1,0,0,0,0-2H9a1,1,0,0,0,0,2Z" class="clr-i-outline clr-i-outline-path-2"/>
                    <path d="M9,18H27a1,1,0,0,0,0-2H9a1,1,0,0,0,0,2Z" class="clr-i-outline clr-i-outline-path-3"/>
                    <path d="M9,22H19a1,1,0,0,0,0-2H9a1,1,0,0,0,0,2Z" class="clr-i-outline clr-i-outline-path-4"/>
                    <path d="M32,6H4A2,2,0,0,0,2,8V28a2,2,0,0,0,2,2H32a2,2,0,0,0,2-2V8A2,2,0,0,0,32,6ZM19,22H9a1,1,0,0,1,0-2H19a1,1,0,0,1,0,2Zm8-4H9a1,1,0,0,1,0-2H27a1,1,0,0,1,0,2Zm0-4H9a1,1,0,0,1,0-2H27a1,1,0,0,1,0,2Z" class="clr-i-solid clr-i-solid-path-1" style="display:none"/>
                    </svg>
                    </i>
                </a> 
            </td>
        </tr>
        <?php
            }
        } else {
            echo "<div class='error-message'>دانش آموزی موجود نیست.</div>";
        }
        ?>
    </tbody>
    </table>

    <div>
        <div class="jostojo"> 
            <form id="searchForm" action="" method="post">
                <label>جستجو بر اساس:</label>
                <input class="searchBy" id="name" type="text" placeholder="نام">
                <input class="searchBy" id="family" type="text" placeholder="نام خانوادگی">
                <input class="searchBy" id="natCode" type="text" placeholder="کد ملی">
                <input class="searchBy" id="field" type="text" placeholder="رشته">
                <input class="searchBy" id="grade" type="text" placeholder="پایه">
                <input class="searchBy" id="approval_number" type="text" placeholder="شماره مصوبه">
                <button type="button" class="Button" id="resetButton" style="width: 50px;">پاک</button> <!-- دکمه پاک -->
                <button type="button" class="Button" id="generatePdfButton">تولید PDF</button>
            </form>
        </div>
        <div class="left-nav">
            <?php if ($page > 1): ?>
                <button type="button" class="Button">
                    <a title="اولین" href="?page=1"><<</a>
                </button>
                <button type="button" class="Button">
                    <a title="قبلی" href="?page=<?= $page - 1 ?>"><</a>
                </button>
                <?php endif; ?>
                
                <span>صفحه <?= $page ?> از <?= $total_pages ?></span>
                
                <?php if ($page < $total_pages): ?>
                <button type="button" class="Button">
                    <a title="بعدی" href="?page=<?= $page + 1 ?>">></a>
                </button>
                <button type="button" class="Button">
                    <a title="آخرین" href="?page=<?= $total_pages ?>">>></a>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    // تابعی برای تنظیم مجدد رویداد کلیک برای ردیف‌ها
    function setRowClickEvent() {
        $('.student-row').off('click').on('click', function() {
            // بررسی اینکه آیا ردیف در حال حاضر انتخاب شده است یا خیر
            if ($(this).hasClass('highlight')) {
                // اگر انتخاب شده بود، رنگ را بردارید
                $(this).removeClass('highlight');
            } else {
                // اگر انتخاب نشده بود، رنگ را به آن اضافه کنید
                $('.student-row').removeClass('highlight'); // رنگ از تمام ردیف‌ها برداشته می‌شود
                $(this).addClass('highlight'); // رنگ به ردیف کلیک شده اضافه می‌شود
            }
        });
    }

    // تنظیم رویداد کلیک برای ردیف‌های اولیه
    setRowClickEvent();

    // افزودن قابلیت جستجو با دکمه Enter
    $('#searchForm').on('keypress', function(e) {
        if (e.which === 13) { // بررسی اینکه آیا دکمه Enter فشرده شده است
            e.preventDefault(); // جلوگیری از ارسال پیش‌فرض فرم
            // جمع‌آوری داده‌ها برای ارسال
            var name = $('#name').val();
            var family = $('#family').val();
            var natCode = $('#natCode').val();
            var field = $('#field').val();
            var grade = $('#grade').val();
            var approval_number = $('#approval_number').val();
            var data = {
                name: name,
                family: family,
                natCode: natCode,
                field: field,
                grade: grade,
                approval_number: approval_number
            };
            // ارسال داده‌ها به سمت سرور
            $.ajax({
                type: "POST",
                url: "", // آدرس همین صفحه
                data: { data: JSON.stringify(data) },
                success: function(response) {
                    // بارگذاری نتایج جدید
                    $('tbody').html($(response).find('tbody').html());
                    // تنظیم مجدد رویداد کلیک برای ردیف‌های جدید
                    setRowClickEvent();
                },
                error: function() {
                    alert("خطا در ارسال درخواست.");
                }
            });
        }
    });

    // افزودن قابلیت ریست کردن فرم
    $('#resetButton').on('click', function() {
        // پاک کردن فیلدهای جستجو
        $('#name').val('');
        $('#family').val('');
        $('#natCode').val('');
        $('#field').val('');
        $('#grade').val('');
        $('#approval_number').val('');

        // ارسال درخواست برای بارگذاری تمام اعضا
        $.ajax({
            type: "POST",
            url: "", // آدرس همین صفحه
            data: { data: JSON.stringify({}) }, // ارسال داده خالی
            success: function(response) {
                // بارگذاری نتایج جدید
                $('tbody').html($(response).find('tbody').html());
                // تنظیم مجدد رویداد کلیک برای ردیف‌های جدید
                setRowClickEvent();
            },
            error: function() {
                alert("خطا در ارسال درخواست.");
            }
        });
    });

    // افزودن قابلیت تولید PDF
    $('#generatePdfButton').on('click', function() {
        var name = $('#name').val();
        var family = $('#family').val();
        var natCode = $('#natCode').val();
        var field = $('#field').val();
        var grade = $('#grade').val();
        var approval_number = $('#approval_number').val();
        
        var data = {
            name: name,
            family: family,
            natCode: natCode,
            field: field,
            grade: grade,
            approval_number: approval_number
        };

        // ارسال درخواست برای تولید PDF
        $.ajax({
            type: "GET",
            url: "generate_pdf.php", // مسیر فایل تولید PDF
            data: { data: JSON.stringify(data) },
            success: function(response) {
                // PDF به صورت خودکار دانلود خواهد شد
                window.location.href = response; // URL فایل PDF
            },
            error: function() {
                alert("خطا در تولید PDF.");
            }
        });
    });
});
</script>

<?php include "../footer.php"; ?>