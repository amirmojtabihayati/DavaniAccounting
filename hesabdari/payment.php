<?php
include "../header.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../db_connection.php";
$cnn = (new class_db())->connection_database;

// متغیرهای جستجو
$search_national_code = '';

// بررسی اینکه آیا فرم جستجو ارسال شده است
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search_national_code = $_POST['national_code'];
}

// بررسی ارسال فرم پرداخت
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_payment"])) {
    // دریافت اطلاعات فرم
    $student_id = $_POST['student_id'];
    $amount_paid = $_POST['amount_paid'];
    $payment_date = $_POST['payment_date'];
    $payment_title = $_POST['payment_title'];
    $transaction_number = $_POST['transaction_number'];
    $payment_type = $_POST['payment_type'];

    // به‌روزرسانی بدهی دانش‌آموز
    $stmt = $cnn->prepare("UPDATE debts SET amount = amount - ? WHERE student_id = ? AND amount > 0");
    $stmt->bind_param("di", $amount_paid, $student_id);
    
    if ($stmt->execute()) {
        // ثبت اطلاعات پرداخت در جدول پرداخت‌ها
        $payment_stmt = $cnn->prepare("INSERT INTO payments (student_id, amount_paid, payment_date, payment_title, transaction_number, payment_type) VALUES (?, ?, ?, ?, ?, ?)");
        $payment_stmt->bind_param("idssss", $student_id, $amount_paid, $payment_date, $payment_title, $transaction_number, $payment_type);
        $payment_stmt->execute();
        echo "<div style='color: green;'>پرداخت با موفقیت ثبت شد!</div>";
        $payment_stmt->close();
    } else {
        echo "<div style='color: red;'>خطا در ثبت پرداخت!</div>";
    }
    $stmt->close();
}

// ساخت کوئری برای دریافت دانش‌آموزان
$query = "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, national_code FROM students WHERE 1=1";

if (!empty($search_national_code)) {
    $query .= " AND national_code LIKE '%" . $cnn->real_escape_string($search_national_code) . "%'";
}

$students = $cnn->query($query);

?>
    <div class="container">
        <h1 id="h1">پرداخت</h1>

        <form id="Form" action="" method="post">
        <div class="jostojo">
                <label id="label" for="national_code">جستجو بر اساس کد ملی:</label>
                <input class="input" type="text" name="national_code" value="<?= htmlspecialchars($search_national_code) ?>">

                <button id="submit_btn" type="submit" name="search">جستجو</button>
            </div>
        </form>

        <form action="" method="post">
        <label id="label" for="student_id">نام و نام خانوادگی:</label>
        <select class="select" name="student_id" id="student_id" required onchange="getStudentDebts(this.value)">
            <option value="">یه گزینه انتخاب کنید</option>
                <?php while ($row = $students->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= $row['full_name'] ?> (کد ملی: <?= $row['national_code'] ?>)</option>
                <?php endwhile; ?>
            </select>

            <span style="color: black; font-size: 24px; margin-bottom: 30px;"> بدهی:</span>
            <div id="debt_amount" style="color: blue;"></div>

            <label id="label" for="amount_paid">مبلغ پرداخت</label>
            <input class="input" type="text" name="amount_paid" required>

            <label id="label" for="payment_date">تاریخ واریز</label>
            <input class="input" type="date" name="payment_date" required>

            <label id="label" for="payment_title">عنوان</label>
            <select class="select" name="payment_title" id="payment_title" required>
                <option value=""> یه گزینه را انتخاب کنید</option>
                <option value="شهریه هیئت امنایی">شهریه هیئت امنایی</option>
                <option value="کتاب">کتاب</option>
                <option value="بیمه">بیمه</option>
                <option value="تقویتی">تقویتی</option>
                <option value="مشارکت مردمی(مصوبه انجمن اولیا و مربیان)">مشارکت مردمی(مصوبه انجمن اولیا و مربیان)</option>
                <option value="مهارتهای فنی-کارگاهی">مهارتهای فنی-کارگاهی</option>
                <option value="سایر...">سایر...</option>
            </select>

            <label id="label" for="transaction_number">شماره واریز</label>
            <input class="input" type="text" name="transaction_number" required>

            <label id="label" for="payment_type">نوع واریز</label>
            <select class="select" name="payment_type" id="payment_type" required>
                <option value=""> یه گزینه را انتخاب کنید</option>
                <option value="کارتخوان">کارتخوان</option>
                <option value="کارت به کارت">کارت به کارت</option>
                <option value="فیش">فیش</option>
                <option value="خودپرداز">خودپرداز</option>
                <option value="سایر...">سایر...</option>
            </select>

            <button id="submit_btn" type="submit" name="add_payment">افزودن</button>
            <button id="submit_btn" type="button" onclick="window.location.href='../index.php'">انصراف</button>
        </form>
    </div>

    <script>
        function searchStudent() {
            const nationalCode = document.getElementById('national_code').value;

            // با استفاده از AJAX، اطلاعات دانش‌آموز را بارگذاری کنید
            const xhr = new XMLHttpRequest();
            xhr.open("GET", "get_student_debt.php?national_code=" + nationalCode, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    document.getElementById("debt_amount").innerText = response.debt;
                    document.getElementById("student_id").value = response.student_id; // انتخاب دانش‌آموز
                }
            };
            xhr.send();
        }
    </script>
<?php
$cnn->close();
include "../footer.php";
?>