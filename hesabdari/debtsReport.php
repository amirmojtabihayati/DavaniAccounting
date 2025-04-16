<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// اطمینان از وجود و بارگذاری فایل‌ها
$basePath = __DIR__ . "/../"; // مسیر پوشه والد این فایل
include $basePath . "header.php";
include_once $basePath . "assets/vendor/Jalali-Date/jdf.php";
include_once $basePath . "convertToPersianNumbers.php";
include_once $basePath . "db_connection.php"; // فرض بر اینکه این فایل class_db را تعریف می‌کند

// بررسی وجود کلاس و تابع تبدیل اعداد
if (!function_exists('convertToPersianNumbers')) {
    // یک تابع پیش‌فرض یا جایگزین تعریف کنید یا خطا دهید
    function convertToPersianNumbers($input) {
        // پیاده‌سازی ساده یا فقط بازگرداندن ورودی
        $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($english_digits, $persian_digits, $input);
    }
    // یا: trigger_error("Function convertToPersianNumbers not found.", E_USER_ERROR);
}

// بررسی وجود کتابخانه jdf
if (!function_exists('jdate')) {
    // یک تابع پیش‌فرض یا جایگزین تعریف کنید یا خطا دهید
    function jdate($format, $timestamp = null) {
        return date($format, $timestamp ?? time()); // بازگشت به تاریخ میلادی پیش‌فرض
    }
    // یا: trigger_error("Function jdate from jdf library not found.", E_USER_ERROR);
}


try {
    $db = new class_db();
    $cnn = $db->connection_database;
    // تنظیم انکودینگ ارتباط دیتابیس (بسیار مهم برای فارسی)
    if ($cnn) {
        $cnn->set_charset("utf8mb4");
    } else {
        throw new Exception("Database connection failed.");
    }

} catch (Exception $e) {
    // مدیریت خطای اتصال دیتابیس به شکل مناسب
    // مثلاً لاگ کردن خطا و نمایش پیام عمومی به کاربر
    error_log("Database Connection Error: " . $e->getMessage()); // لاگ کردن خطا
    die("<div class='container-kol'><div class='error-message'>خطا در برقراری ارتباط با پایگاه داده. لطفا بعدا تلاش کنید.</div></div>");
    // include $basePath . "footer.php"; // فوتر را نمایش ندهیم اگر دیتابیس قطع است
    // exit();
}


// تنظیمات پیمایش
$results_per_page = 10; // تعداد نتایج در هر صفحه
$page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1; // صفحه فعلی با اعتبارسنجی
$offset = ($page - 1) * $results_per_page; // محاسبه آفسِت

// --- پردازش جستجو ---
$searchCondition = "";
$params = []; // پارامترها برای کوئری اصلی و تعداد کل
$paramTypes = ''; // نوع پارامترها (e.g., 'sssisi')

if (isset($_POST['data'])) {
    $q_json = $_POST['data'];
    $student_search = json_decode($q_json);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // مدیریت خطای JSON
        echo "<div class='error-message'>خطا در پردازش داده‌های جستجو: " . json_last_error_msg() . "</div>";
        // می‌توانید از ادامه اجرا جلوگیری کنید یا مقادیر پیش‌فرض را در نظر بگیرید
        $student_search = new stdClass(); // شیء خالی برای جلوگیری از خطاهای بعدی
    }

    // تابع پاکسازی ورودی (می‌تواند خارج از if باشد)
    function cleanInput($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    // دریافت و پاکسازی داده‌های جستجو
    $name = isset($student_search->name) ? cleanInput($student_search->name) : '';
    $family = isset($student_search->family) ? cleanInput($student_search->family) : '';
    $natCode = isset($student_search->natCode) ? cleanInput($student_search->natCode) : '';
    $field = isset($student_search->field) ? cleanInput($student_search->field) : '';
    $grade = isset($student_search->grade) ? cleanInput($student_search->grade) : ''; // شاید نیاز به اعتبارسنجی عددی باشد
    $approval_number = isset($student_search->approval_number) ? cleanInput($student_search->approval_number) : '';

    // ساخت بخش WHERE کوئری به صورت داینامیک و امن
    $conditions = [];
    if (!empty($name)) {
        $conditions[] = "s.`first_name` LIKE ?";
        $params[] = "%$name%";
        $paramTypes .= 's';
    }
    if (!empty($family)) {
        $conditions[] = "s.`last_name` LIKE ?";
        $params[] = "%$family%";
        $paramTypes .= 's';
    }
    if (!empty($natCode)) {
        $conditions[] = "s.`national_code` LIKE ?"; // اطمینان حاصل کنید که کد ملی همیشه رشته است یا تبدیل نوع انجام دهید
        $params[] = "%$natCode%";
        $paramTypes .= 's';
    }
    if (!empty($field)) {
        $conditions[] = "s.`field` LIKE ?";
        $params[] = "%$field%";
        $paramTypes .= 's';
    }
    if (!empty($grade)) {
        // اگر پایه باید عدد صحیح باشد:
        // if (filter_var($grade, FILTER_VALIDATE_INT)) {
        //     $conditions[] = "s.`grade` = ?";
        //     $params[] = (int)$grade;
        //     $paramTypes .= 'i';
        // }
        // اگر پایه می‌تواند رشته هم باشد (مانند 'دهم'):
         $conditions[] = "s.`grade` LIKE ?";
         $params[] = "$grade"; // یا "%$grade%" اگر جستجوی بخشی لازم است
         $paramTypes .= 's';
    }
     // جستجو در شماره مصوبه در جدول debts
    if (!empty($approval_number)) {
        // نکته: این شرط باعث می‌شود فقط دانش‌آموزانی که *حداقل یک* بدهی با این شماره مصوبه دارند نمایش داده شوند.
        // اگر می‌خواهید دانش‌آموزی که این شماره مصوبه را *ندارد* ولی شرایط دیگر را دارد هم بیاید، منطق پیچیده‌تر می‌شود.
        $conditions[] = "d.`approval_number` LIKE ?";
        $params[] = "%$approval_number%";
        $paramTypes .= 's';
    }

    if (!empty($conditions)) {
        // استفاده از AND برای اتصال شرایط
        // نکته: شرط `d.approval_number` داخل JOIN یا WHERE اصلی قرار می‌گیرد.
        // اگر داخل WHERE باشد، دانش‌آموزانی که هیچ بدهی‌ای ندارند (در صورت استفاده از LEFT JOIN) یا بدهی با آن شماره ندارند، حذف می‌شوند.
        // اگر می‌خواهید بر اساس شماره مصوبه جستجو کنید، بهتر است JOIN بماند و شرط در WHERE باشد.
        $searchCondition = " AND " . implode(" AND ", $conditions);
    }
}

// --- کوئری برای شمارش کل دانش‌آموزان متمایز مطابق با جستجو ---
// نکته: پارامترها و نوع آن‌ها ($params, $paramTypes) باید قبل از افزودن پارامترهای LIMIT کپی شوند
$countParams = $params;
$countParamTypes = $paramTypes;

// کوئری شمارش نیاز به GROUP BY ندارد و از DISTINCT استفاده می‌کند
$total_sql = "SELECT COUNT(DISTINCT s.id) as total
              FROM `students` s
              JOIN `debts` d ON s.id = d.student_id
              WHERE 1=1 $searchCondition";

$total_stmt = $cnn->prepare($total_sql);
if (!$total_stmt) {
    error_log("Prepare failed (total_sql): (" . $cnn->errno . ") " . $cnn->error);
    die("<div class='container-kol'><div class='error-message'>خطا در آماده‌سازی کوئری شمارش.</div></div>");
}

if (!empty($countParams)) {
    if (!$total_stmt->bind_param($countParamTypes, ...$countParams)) {
        error_log("Binding parameters failed (total_sql): (" . $total_stmt->errno . ") " . $total_stmt->error);
        die("<div class='container-kol'><div class='error-message'>خطا در اتصال پارامترهای کوئری شمارش.</div></div>");
    }
}

if (!$total_stmt->execute()) {
    error_log("Execute failed (total_sql): (" . $total_stmt->errno . ") " . $total_stmt->error);
    die("<div class='container-kol'><div class='error-message'>خطا در اجرای کوئری شمارش.</div></div>");
}

$total_result = $total_stmt->get_result();
if (!$total_result) {
     error_log("Getting result failed (total_sql): (" . $total_stmt->errno . ") " . $total_stmt->error);
     die("<div class='container-kol'><div class='error-message'>خطا در دریافت نتایج شمارش.</div></div>");
}

$total_row = $total_result->fetch_assoc();
$total_students = $total_row['total'] ?? 0;
$total_pages = $total_students > 0 ? ceil($total_students / $results_per_page) : 1; // محاسبه تعداد کل صفحات
$total_stmt->close(); // بستن statement

// اطمینان از اینکه صفحه در محدوده معتبر است
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $results_per_page; // محاسبه مجدد offset
}
if ($page < 1) {
    $page = 1;
    $offset = 0; // محاسبه مجدد offset
}


// --- کوئری برای محاسبه مجموع کل بدهی‌ها (برای همه دانش‌آموزان یافت شده) ---
$grand_total_debt = 0; // مقدار پیش‌فرض
if ($total_students > 0) { // فقط اگر دانش‌آموزی یافت شد، مجموع را محاسبه کن
    $total_debt_sql = "SELECT SUM(d.amount) as total_debt
                       FROM `debts` d
                       JOIN `students` s ON s.id = d.student_id
                       WHERE 1=1 $searchCondition";

    $total_debt_stmt = $cnn->prepare($total_debt_sql);
     if (!$total_debt_stmt) {
        error_log("Prepare failed (total_debt_sql): (" . $cnn->errno . ") " . $cnn->error);
        // می‌توانید ادامه دهید یا خطا دهید
        // die("<div class='container-kol'><div class='error-message'>خطا در آماده‌سازی کوئری مجموع بدهی.</div></div>");
        $grand_total_debt = 'خطا';
    } else {
        if (!empty($countParams)) { // استفاده از همان پارامترهای جستجو
             if (!$total_debt_stmt->bind_param($countParamTypes, ...$countParams)) {
                error_log("Binding parameters failed (total_debt_sql): (" . $total_debt_stmt->errno . ") " . $total_debt_stmt->error);
                $grand_total_debt = 'خطا';
             }
        }

        if ($grand_total_debt !== 'خطا' && !$total_debt_stmt->execute()) {
             error_log("Execute failed (total_debt_sql): (" . $total_debt_stmt->errno . ") " . $total_debt_stmt->error);
             $grand_total_debt = 'خطا';
        } else if ($grand_total_debt !== 'خطا') {
            $total_debt_result = $total_debt_stmt->get_result();
            if($total_debt_result) {
                $total_debt_row = $total_debt_result->fetch_assoc();
                $grand_total_debt = $total_debt_row['total_debt'] ?? 0; // مقدار کل بدهی
            } else {
                 error_log("Getting result failed (total_debt_sql): (" . $total_debt_stmt->errno . ") " . $total_debt_stmt->error);
                 $grand_total_debt = 'خطا';
            }
        }
        $total_debt_stmt->close();
    }
}


// --- کوئری اصلی برای بارگذاری دانش‌آموزان و مجموع بدهی هر کدام ---
// **تغییرات:** SUM(d.amount), GROUP BY s.id, ... , MAX(d.date)
// **توجه:** ستون‌های d.approval_number و d.date تکی حذف شدند چون با GROUP BY معنی ندارند.
//          به جای آن، تاریخ آخرین بدهی (MAX(d.date)) اضافه شده است.
$sql = "SELECT s.id, s.first_name, s.last_name, s.national_code,
               SUM(d.amount) as student_total_debt, -- مجموع بدهی این دانش آموز
               MAX(d.date) as last_debt_date        -- تاریخ آخرین بدهی
        FROM `students` s
        JOIN `debts` d ON s.id = d.student_id
        WHERE 1=1 $searchCondition
        GROUP BY s.id, s.first_name, s.last_name, s.national_code -- Group by تمام ستون‌های غیر تجمعی (non-aggregated) از s
        ORDER BY s.last_name, s.first_name -- مرتب سازی (اختیاری)
        LIMIT ?, ?"; // محدودیت برای صفحه‌بندی

$stmt = $cnn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (main sql): (" . $cnn->errno . ") " . $cnn->error);
    die("<div class='container-kol'><div class='error-message'>خطا در آماده‌سازی کوئری اصلی.</div></div>");
}

// افزودن پارامترهای offset و limit به پارامترها و types
$params[] = $offset;
$paramTypes .= 'i'; // integer
$params[] = $results_per_page;
$paramTypes .= 'i'; // integer

// Bind کردن همه پارامترها (شامل جستجو + offset + limit)
if (!empty($paramTypes)) { // بررسی کنیم type خالی نباشد
    if (!$stmt->bind_param($paramTypes, ...$params)) {
         error_log("Binding parameters failed (main sql): (" . $stmt->errno . ") " . $stmt->error);
         die("<div class='container-kol'><div class='error-message'>خطا در اتصال پارامترهای کوئری اصلی.</div></div>");
    }
} else {
    // اگر هیچ پارامتر جستجویی نبود، فقط offset و limit را bind می‌کنیم
    if (!$stmt->bind_param("ii", $offset, $results_per_page)) {
        error_log("Binding parameters failed (main sql - no search): (" . $stmt->errno . ") " . $stmt->error);
        die("<div class='container-kol'><div class='error-message'>خطا در اتصال پارامترهای صفحه‌بندی.</div></div>");
    }
}


if (!$stmt->execute()) {
    error_log("Execute failed (main sql): (" . $stmt->errno . ") " . $stmt->error);
    die("<div class='container-kol'><div class='error-message'>خطا در اجرای کوئری اصلی.</div></div>");
}

$result = $stmt->get_result();
if (!$result) {
     error_log("Getting result failed (main sql): (" . $stmt->errno . ") " . $stmt->error);
     die("<div class='container-kol'><div class='error-message'>خطا در دریافت نتایج اصلی.</div></div>");
}

?>

<div class="container-kol">

    <div class="summary-section">
        <?php if (isset($grand_total_debt) && $grand_total_debt !== 'خطا'): ?>
            مجموع کل بدهی‌ها (برای نتایج یافت شده):
            <strong><?= convertToPersianNumbers(number_format($grand_total_debt, 0, ',', ',') . " تومان") ?></strong>
        <?php elseif (isset($grand_total_debt) && $grand_total_debt === 'خطا'): ?>
            <span class="error-message">خطا در محاسبه مجموع کل بدهی‌ها.</span>
        <?php endif; ?>
        <hr>
    </div>

    <table>
        <thead>
            <tr>
                <th>نام و نام خانوادگی</th>
                <th>کد ملی</th>
                <th>مجموع بدهی دانش‌آموز</th> <th>تاریخ آخرین بدهی</th> <th>جزئیات</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $studentId = $row['id']; // شناسه دانش‌آموز
                    $firstName = htmlspecialchars($row['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $lastName = htmlspecialchars($row['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $fullName = $firstName . ' ' . $lastName;
                    $nat_code = htmlspecialchars($row['national_code'] ?? '', ENT_QUOTES, 'UTF-8');
                    // **مهم:** استفاده از مجموع بدهی محاسبه شده برای این دانش آموز
                    $student_total_debt_amount = $row['student_total_debt'] ?? 0;
                    // **مهم:** استفاده از تاریخ آخرین بدهی
                    $last_date = $row['last_debt_date'] ?? null;
                    // $approval_number = htmlspecialchars($row['approval_number']); // حذف شد

                    // تبدیل تاریخ میلادی آخرین بدهی به شمسی
                    $jalaliDate = '-'; // مقدار پیش فرض
                    if ($last_date) {
                         try {
                            $timestamp = strtotime($last_date);
                            if ($timestamp) {
                                $jalaliDate = jdate('Y/m/d', $timestamp);
                            }
                         } catch (Exception $e) {
                            // مدیریت خطای احتمالی در تبدیل تاریخ
                            error_log("Jalali date conversion error: " . $e->getMessage());
                            $jalaliDate = 'خطا در تاریخ';
                         }
                    }

                    // استفاده از شناسه دانش آموز در data attribute (اگر لازم باشد)
                    // data-approval_number حذف شد چون دیگر در کوئری اصلی نیست
            ?>
            <tr class="student-row" data-id="<?= $studentId ?>" data-name="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>" data-natcode="<?= $nat_code ?>">
                <td><?= $fullName ?></td>
                <td><?= convertToPersianNumbers($nat_code) ?></td>
                <td><?= convertToPersianNumbers(number_format($student_total_debt_amount, 0, ',', ',') . " تومان") ?></td> <td><?= convertToPersianNumbers($jalaliDate); ?></td> <td>
                    <a href="debtsDetail.php?id=<?= $studentId ?>" class="details-link" title="مشاهده جزئیات بدهی‌های <?= $fullName ?>" data-bs-original-title="جزئیات">
                        <i>
                            <svg version="1.1" class="has-solid icon-details" viewBox="0 0 36 36" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" focusable="false" role="img" width="20" height="20">
                                <path d="M32,6H4A2,2,0,0,0,2,8V28a2,2,0,0,0,2,2H32a2,2,0,0,0,2-2V8A2,2,0,0,0,32,6Zm0,22H4V8H32Z" class="clr-i-outline clr-i-outline-path-1"/>
                                <path d="M9,14H27a1,1,0,0,0,0-2H9a1,1,0,0,0,0,2Z" class="clr-i-outline clr-i-outline-path-2"/>
                                <path d="M9,18H27a1,1,0,0,0,0-2H9a1,1,0,0,0,0,2Z" class="clr-i-outline clr-i-outline-path-3"/>
                                <path d="M9,22H19a1,1,0,0,0,0-2H9a1,1,0,0,0,0,2Z" class="clr-i-outline clr-i-outline-path-4"/>
                                </svg>
                        </i>
                    </a>
                </td>
            </tr>
            <?php
                }
            } else {
                // نمایش پیام مناسب در صورت نبود نتیجه
                $colspan = 5; // تعداد ستون‌های جدول
                echo "<tr><td colspan='{$colspan}' class='no-results'>دانش آموزی با مشخصات وارد شده یافت نشد یا هیچ بدهی ثبت شده‌ای برای آن‌ها وجود ندارد.</td></tr>";
            }
            // بستن statement اصلی
            $stmt->close();
            ?>
        </tbody>
    </table>

    <div class="controls-section">
        <div class="jostojo">
            <form id="searchForm" action="" method="post">
                <label>جستجو:</label>
                <input class="searchBy" id="name" name="name" type="text" placeholder="نام" value="<?= htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input class="searchBy" id="family" name="family" type="text" placeholder="نام خانوادگی" value="<?= htmlspecialchars($family ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input class="searchBy" id="natCode" name="natCode" type="text" placeholder="کد ملی" value="<?= htmlspecialchars($natCode ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input class="searchBy" id="field" name="field" type="text" placeholder="رشته" value="<?= htmlspecialchars($field ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input class="searchBy" id="grade" name="grade" type="text" placeholder="پایه" value="<?= htmlspecialchars($grade ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input class="searchBy" id="approval_number" name="approval_number" type="text" placeholder="شماره مصوبه بدهی" value="<?= htmlspecialchars($approval_number ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="Button search-button" id="searchButton">جستجو</button>
                <button type="button" class="Button reset-button" id="resetButton">پاک کردن جستجو</button>
                <button type="button" class="Button pdf-button" id="generatePdfButton">خروجی PDF</button>
                <button type="button" class="Button excel-button" id="generateExcelButton">خروجی EXCEL</button>
            </form>
        </div>

        <div class="pagination-nav">
             <span class="total-results">تعداد کل نتایج: <?= convertToPersianNumbers($total_students) ?></span>

            <?php if ($total_pages > 1): // فقط اگر بیش از یک صفحه وجود دارد، پیمایش را نشان بده ?>
                <?php if ($page > 1): ?>
                    <a class="Button page-button first-page" title="صفحه اول" href="?page=1<?= isset($searchCondition) ? '&search=active' : '' ?>">&lt;&lt;</a>
                    <a class="Button page-button prev-page" title="صفحه قبلی" href="?page=<?= $page - 1 ?><?= isset($searchCondition) ? '&search=active' : '' ?>">&lt;</a>
                <?php else: ?>
                    <span class="Button page-button disabled">&lt;&lt;</span>
                    <span class="Button page-button disabled">&lt;</span>
                <?php endif; ?>

                <span class="page-info">صفحه <?= convertToPersianNumbers($page) ?> از <?= convertToPersianNumbers($total_pages) ?></span>

                <?php if ($page < $total_pages): ?>
                    <a class="Button page-button next-page" title="صفحه بعدی" href="?page=<?= $page + 1 ?><?= isset($searchCondition) ? '&search=active' : '' ?>">></a>
                    <a class="Button page-button last-page" title="صفحه آخر" href="?page=<?= $total_pages ?><?= isset($searchCondition) ? '&search=active' : '' ?>">>></a>
                <?php else: ?>
                     <span class="Button page-button disabled">></span>
                     <span class="Button page-button disabled">>></span>
                <?php endif; ?>
             <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // تابعی برای تنظیم مجدد رویداد کلیک برای ردیف‌ها
    function setRowClickEvent() {
        $('.student-row').off('click').on('click', function(event) {
            // اگر روی لینک جزئیات کلیک شد، هایلایت نکن
            if ($(event.target).closest('a.details-link').length) {
                return;
            }

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

    // تابع برای جمع آوری داده های فرم جستجو
    function getSearchData() {
        return {
            name: $('#name').val().trim(),
            family: $('#family').val().trim(),
            natCode: $('#natCode').val().trim(),
            field: $('#field').val().trim(),
            grade: $('#grade').val().trim(),
            approval_number: $('#approval_number').val().trim()
        };
    }

    // تابع برای انجام جستجوی AJAX
    function performSearch(searchData) {
        // نمایش یک نشانگر بارگذاری (اختیاری)
        $('tbody').html('<tr><td colspan="5" class="loading-message">در حال بارگذاری نتایج...</td></tr>');

        $.ajax({
            type: "POST",
            url: window.location.pathname, // ارسال به همین صفحه
            data: { data: JSON.stringify(searchData) },
            success: function(response) {
                // جایگزینی کل محتوای container یا فقط tbody و pagination
                // در اینجا فقط tbody و بخش کنترل‌ها (شامل pagination) را به روز می کنیم
                // این کار باعث می‌شود header و footer دست نخورده باقی بمانند.
                var newTbody = $(response).find('tbody').html();
                var newControls = $(response).find('.controls-section').html(); // شامل فرم و پیمایش
                var newSummary = $(response).find('.summary-section').html(); // شامل مجموع کل

                $('tbody').html(newTbody);
                $('.controls-section').html(newControls); // به روز رسانی فرم و پیمایش
                 $('.summary-section').html(newSummary); // به روز رسانی مجموع کل

                // اتصال مجدد رویدادها به دکمه‌ها و فرم در بخش کنترل‌های جدید
                bindControlEvents();
                // تنظیم مجدد رویداد کلیک برای ردیف‌های جدید
                setRowClickEvent();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Search Error:", textStatus, errorThrown);
                $('tbody').html('<tr><td colspan="5" class="error-message">خطا در بارگذاری نتایج جستجو. لطفاً دوباره تلاش کنید.</td></tr>');
                // بازیابی کنترل‌ها یا نمایش پیام خطا
                 $('.controls-section').html('<p class="error-message">خطا در بارگذاری کنترل‌ها.</p>');
            }
        });
    }

    // تابع برای اتصال رویدادها به دکمه ها و فرم جستجو
    function bindControlEvents() {
        // جستجو با دکمه Enter یا کلیک روی دکمه جستجو
        $('#searchForm').off('submit').on('submit', function(e) {
            e.preventDefault(); // جلوگیری از ارسال پیش‌فرض فرم
            var searchData = getSearchData();
            // تغییر URL برای نشان دادن صفحه اول نتایج جستجو (اختیاری)
            // history.pushState(null, '', window.location.pathname + '?page=1&search=active');
             // یا ساده تر:
             performSearch(searchData);
             // بعد از جستجو به صفحه اول برگردید
             // window.history.pushState({}, '', '?page=1'); // تغییر URL بدون ریلود
             // یا: performSearch(searchData); // که خودش صفحه اول را لود می‌کند چون page در GET نیست
        });

        // افزودن قابلیت ریست کردن فرم
        $('#resetButton').off('click').on('click', function() {
            // پاک کردن فیلدهای جستجو
            $('#searchForm').find('input[type="text"]').val('');

            // ارسال درخواست برای بارگذاری تمام اعضا (صفحه اول)
            performSearch({}); // ارسال داده خالی
             // بازگشت به URL اصلی بدون پارامتر جستجو
             window.history.pushState({}, '', window.location.pathname);
        });

        // افزودن قابلیت تولید PDF
        $('#generatePdfButton').off('click').on('click', function() {
            var searchData = getSearchData();
            var queryString = $.param({ data: JSON.stringify(searchData) }); // تبدیل به query string
            // باز کردن URL تولید PDF در یک تب جدید یا همین تب
            // مرورگر باید دانلود را به صورت خودکار آغاز کند
            window.location.href = "generate_pdf.php?" + queryString;
             // یا اگر میخواهید در تب جدید باز شود:
             // window.open("generate_pdf.php?" + queryString, '_blank');
        });

        // افزودن قابلیت تولید Excel
        $('#generateExcelButton').off('click').on('click', function() {
            var searchData = getSearchData();
            var queryString = $.param({ data: JSON.stringify(searchData) }); // تبدیل به query string
            // باز کردن URL تولید Excel
            window.location.href = "generate_excel.php?" + queryString;
             // یا اگر میخواهید در تب جدید باز شود:
             // window.open("generate_excel.php?" + queryString, '_blank');
        });

        // (اختیاری) مدیریت کلیک روی لینک‌های پیمایش با AJAX
        $('.pagination-nav a.page-button').off('click').on('click', function(e) {
            e.preventDefault(); // جلوگیری از بارگذاری کامل صفحه
            var targetUrl = $(this).attr('href');
            var searchData = getSearchData(); // حفظ شرایط جستجوی فعلی

            // نمایش نشانگر بارگذاری
             $('tbody').html('<tr><td colspan="5" class="loading-message">در حال بارگذاری صفحه...</td></tr>');

             $.ajax({
                type: "POST", // یا GET اگر صفحه بندی فقط با GET کار میکند
                url: targetUrl, // URL شامل شماره صفحه جدید
                data: { data: JSON.stringify(searchData) }, // ارسال شرایط جستجو
                success: function(response) {
                    var newTbody = $(response).find('tbody').html();
                    var newControls = $(response).find('.controls-section').html();
                     var newSummary = $(response).find('.summary-section').html();

                    $('tbody').html(newTbody);
                    $('.controls-section').html(newControls);
                     $('.summary-section').html(newSummary);

                    // به‌روزرسانی URL در مرورگر (اختیاری)
                    window.history.pushState({}, '', targetUrl);

                    bindControlEvents(); // اتصال مجدد رویدادها
                    setRowClickEvent(); // اتصال مجدد رویداد کلیک ردیف
                },
                error: function() {
                    $('tbody').html('<tr><td colspan="5" class="error-message">خطا در بارگذاری صفحه.</td></tr>');
                    // بازیابی کنترل‌ها یا نمایش پیام خطا
                    $('.controls-section').html('<p class="error-message">خطا در بارگذاری کنترل‌ها.</p>');
                }
            });
        });
    }

    // اولین بار اتصال رویدادها
    bindControlEvents();

});
</script>

<?php
// بستن اتصال دیتابیس (اگر در footer بسته نمی شود)
if ($cnn) {
    $cnn->close();
}
include $basePath . "footer.php";
?>