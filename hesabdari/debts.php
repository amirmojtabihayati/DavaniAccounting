<?php
include "../header.php";
include_once "../convertToPersianNumbers.php";
include "../db_connection.php"; // اتصال به پایگاه داده
include_once "../Jalali-Date/jdf.php"; // اطمینان از بارگذاری درست کتابخانه jdf

include_once "../jalali-master/src/Converter.php";
include_once "../jalali-master/src/Jalalian.php";

use Jalali\Jalalian;

$cnn = (new class_db())->connection_database;

// متغیرهای جستجو
$search_national_code = '';

$errors = [];
$success_msg = [];

// بررسی اینکه آیا فرم جستجو ارسال شده است
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['search'])) {
        $search_national_code = isset($_POST['national_code']) ? trim($_POST['national_code']) : '';
    }

    // بررسی اینکه آیا فرم ثبت بدهی ارسال شده است
    if (isset($_POST['add_debt'])) {
        // دریافت تاریخ شمسی از فرم
        $jalali_date = $_POST['date']; // فرض بر این است که تاریخ شمسی در فرمت YYYY/MM/DD است
        $dateParts = explode('/', $jalali_date);
        $year = (int)$dateParts[0];
        $month = (int)$dateParts[1];
        $day = (int)$dateParts[2];

        // ایجاد شیء Jalalian
        $jalalianDate = new Jalalian($year, $month, $day);

        // تبدیل به تاریخ میلادی
        $gregorianDate = $jalalianDate->toCarbon()->format('Y-m-d');

        // بررسی وجود شماره مصوبه در پایگاه داده
        $check_approval_number_stmt = $cnn->prepare("SELECT COUNT(*) FROM debts WHERE approval_number = ?");
        $check_approval_number_stmt->bind_param("s", $approval_number);
        $check_approval_number_stmt->execute();
        $check_approval_number_stmt->bind_result($approval_number_count);
        $check_approval_number_stmt->fetch();
        $check_approval_number_stmt->close();
 
        if ($approval_number_count > 0) {
            $errors[] = "شماره مصوبه قبلاً ثبت شده است. لطفاً شماره ای دیگر در نظر بگیرید.";
        } else {
            // ذخیره اطلاعات بدهی در پایگاه داده
            $stmt = $cnn->prepare("INSERT INTO debts (student_id, amount, title, approval_number, date) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("idsss", $student_id, $amount, $title, $approval_number, $gregorianDate); // استفاده از تاریخ میلادی
                if ($stmt->execute()) {
                    $success_msg[] = "بدهی با موفقیت ثبت شد";
                } else {
                    $errors[] = "خطا در ثبت بدهی: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "خطا در آماده‌سازی پرس و جو";
            }
        }
    }
}

// ساخت کوئری برای دریافت دانش‌آموزان
$query = "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, national_code FROM students WHERE 1=1";

if (!empty($search_national_code)) {
    $query .= " AND national_code LIKE '%" . $cnn->real_escape_string($search_national_code) . "%'";
}

$students = $cnn->query($query);
?>
<div class="debt-container" style="margin: 20px; display: none;">
    <div style="box-shadow: 1px 1px 27px -1px;
                background-color: whitesmoke;
                margin: auto;
                max-width: 31%;
                padding: 20px;
                border-radius: 5px;">

        <h1 id="h1">وضعیت بدهی دانش‌آموز</h1>
        <div id="current_debt" style="margin: 10px; font-size: 15pt; color: blue; text-align: center;"></div><hr>
        <h1 style="color: #333; text-align: center;">پرداخت ها</h1>
        <table id="payments_table" style="width: 60%; border-collapse: collapse; margin: auto; text-align: center;">
            <tr class="debt_tr">
                <th style="border-bottom: 1px solid black;" id="debt_th">عنوان پرداخت</th>
                <th style="border-bottom: 1px solid black;" id="debt_th">مقدار پرداخت (تومان)</th>
            </tr>
            <!-- پرداخت‌ها در اینجا اضافه می‌شوند -->
        </table>
    </div>
</div>
<div class="container">
<h1 id="h1">ایجاد بدهی</h1>

<!-- فرم جستجو -->
    <form id="searchForm" action="" method="post">
        <div class="jostojo">
            <label id="label" for="national_code">جستجو بر اساس کد ملی:</label>
            <input class="input" type="text" name="national_code" value="<?= htmlspecialchars(convertToPersianNumbers($search_national_code)) ?>">
            <button id="submit_btn" type="submit" name="search">جستجو</button>
        </div>
    </form>
        <!-- نمایش پیام‌های خطا -->
        <?php if (!empty($errors)) { ?>
            <?php foreach ($errors as $error) { ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php }
        } elseif (!empty($success_msg)) { ?>
            <div class="success-message"><?php echo htmlspecialchars($success_msg[0]); ?></div>
        <?php } ?>

    <div id="current_debt" style="margin: 10px; font-size: 15pt; color: blue;"></div>

    <!-- فرم ایجاد بدهی -->
    <form id="debtForm" action="" method="post">
        <label id="label" for="student_id">نام و نام خانوادگی:</label>
        <select class="select" name="student_id" id="student_id" required onchange="getStudentDebts(this.value)">
            <option value="">یه گزینه انتخاب کنید</option>
            <?php while ($row = $students->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($row['id']) ?>"><?= $row['full_name'] ?> (کد ملی: <?= htmlspecialchars($row['national_code']) ?>)</option>
            <?php endwhile; ?>
        </select>

        <label id="label" for="debt_amount">مبلغ بدهی</label>
        <input class="input" id="debt_amount" type="text" name="debt_amount" required>

        <label id="label" for="debt_title">عنوان بدهی</label>
        <select class="select" name="debt_title" id="debt_title" required>
            <option value="">یه گزینه انتخاب کنید</option>
            <option value="شهریه هیئت امنایی">شهریه هیئت امنایی</option>
            <option value="کتاب">کتاب</option>
            <option value="بیمه">بیمه</option>
            <option value="تقویتی">تقویتی</option>
            <option value="مشارکت مردمی(مصوبه انجمن اولیا و مربیان)">مشارکت مردمی(مصوبه انجمن اولیا و مربیان)</option>
            <option value="مهارتهای فنی-کارگاهی">مهارتهای فنی-کارگاهی</option>
            <option value="سایر...">سایر...</option>
        </select>

        <label id="label" for="approval_number">شماره مصوبه</label>
        <input class="input" type="text" name="approval_number">

        <label id="label" for="date">تاریخ</label>
        <input class="input" id="date" type="text" name="date" required readonly placeholder="تاریخ را مشخص کنید">
        
        <button id="submit_btn" type="submit" name="add_debt">افزودن</button>
        <button id="submit_btn" type="button" onclick="window.location.href='../index.php'">انصراف</button>
    </form>
</div>
<script src="../assets/js/ConverterPersianNumbers.js"></script>
<!-- jquery Helper -->
<script src="../assets/js/jquery.js"></script>
<!-- بارگذاری persian-date.js -->
<script src="../PersianDate/dist/persian-date.min.js"></script>
<!-- بارگذاری persian-datepicker.js -->
<script src="../DatePicker/dist/js/persian-datepicker.min.js"></script>
<script src="../assets/js/PersianNumbersInput.js"></script>

<script>
$(document).ready(function() {
    // فعال‌سازی تاریخ‌ساز
    $("#date").pDatepicker({
        format: 'YYYY/MM/DD', // فرمت تاریخ
        autoClose: true,      // بستن خودکار پس از انتخاب تاریخ
        initialValue: false,  // مقدار اولیه
        position: "auto",     // موقعیت نمایش تقویم
        calendarType: "persian" // نوع تقویم
    });
});
document.addEventListener("DOMContentLoaded", function() {
    // تابع htmlspecialchars برای جلوگیری از XSS
    function htmlspecialchars(string) {
        return String(string)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // تابع برای نمایش مجموع بدهی
    function showDebt(debt) {
        const currentDebtElement = document.getElementById("current_debt");
        if (currentDebtElement) {
            if (debt) {
                currentDebtElement.innerText = "مجموع بدهی: " + convertToPersianNumbers(numberWithCommas(debt)) + " تومان ";
            } else {
                currentDebtElement.innerText = 'بدهی‌ای وجود ندارد.';
            }
        } else {
            console.error("عنصر با ID 'current_debt' پیدا نشد.");
        }
    }

    // تابع برای نمایش پرداخت‌ها
    function showPayments(payments) {
        const paymentsTable = document.getElementById("payments_table");
        if (paymentsTable) {
            // پاک کردن ردیف‌های قبلی
            const rows = paymentsTable.querySelectorAll("tr:not(:first-child)");
            rows.forEach(row => row.remove());

            // بررسی اینکه payments یک آرایه معتبر است
            if (Array.isArray(payments)) {
                payments.forEach(payment => {
                    const row = document.createElement("tr");
                    row.innerHTML = `<td style="font-size:12pt;">${htmlspecialchars(payment.payment_title)}</td>
                                    <td style="font-size:16pt;">${convertToPersianNumbers(numberWithCommas(payment.total_payment))}</td>`;
                    paymentsTable.appendChild(row);
                });
            } else {
                console.error("payments is not an array or is undefined:", payments);
            }
        } else {
            console.error("عنصر با ID 'payments_table' پیدا نشد.");
        }
    }

    // تابع برای دریافت بدهی‌های دانش‌آموز
    window.getStudentDebts = function(studentId) {
        console.log("ID دانش‌آموز وارد شده:", studentId);
        if (studentId) {
            const xhr = new XMLHttpRequest();
            const url = "get_student_debts.php?student_id=" + encodeURIComponent(studentId);
            console.log("در حال ارسال درخواست به:", url);
            xhr.open("GET", url, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        console.log("پاسخ از سرور:", response); // اضافه کردن این خط
                        showDebt(response.total_debt);
                        showPayments(response.payments);
                        showDebtContainer();
                    } else {
                        console.error("خطا در دریافت اطلاعات:", xhr.statusText);
                    }
                }
            };
            xhr.send();
        } else {
            document.getElementById("current_debt").innerText = '';
        }
    };

    function showDebtContainer() {
        document.querySelector('.debt-container').style.display = 'block';
    }
    
});
</script>
<script src="../assets/js/hideMessage.js"></script>
<?php
include "../footer.php";
$cnn->close();  
?>