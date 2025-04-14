<?php
session_start();
include "db_connection.php";
$cnn = (new class_db())->connection_database;

$errors = [];
$_SESSION["login_status"] = false; // مقداردهی اولیه به وضعیت لاگین

// تابع برای اجرای کوئری با مدیریت خطا
function executeQuery($connection, $sql, $types = null, ...$params) {
    $stmt = $connection->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log("Error executing query: " . $stmt->error);
        return false;
    }
    return $stmt;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["Username"]) && !empty($_POST["Username"]) &&
        isset($_POST["Password"]) && !empty($_POST["Password"])) {

        $Username = $_POST["Username"];
        $Password = $_POST["Password"];

        $stmt = executeQuery($cnn, "SELECT password FROM users WHERE username=?", 's', $Username);

        if ($stmt) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (password_verify($Password, $row["password"])) {
                    $_SESSION["login_status"] = true;
                    // تولید مجدد شناسه نشست بعد از ورود موفقیت‌آمیز
                    session_regenerate_id(true);
                    header("Location: Home.php");
                    exit();
                } else {
                    $errors[] = "گذرواژه صحیح نمی باشد";
                }
            } else {
                $errors[] = "نام کاربری پیدا نشد";
            }
            $stmt->close();
        } else {
            $errors[] = "خطا در اجرای کوئری";
        }
    } else {
        $errors[] = "لطفا تمامی فیلدها را پر کنید";
    }
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سامانه</title>
    <link rel="stylesheet" href="assets/css/Error&Success-style.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        @font-face {
            font-family: Yekan;
            src: url(assets/font/Yekan.woff);
        }

*{
    font-family: Yekan;
}

/* Error&Success-style.css (بدون تغییر عمده، فقط برای خوانایی بهتر) */
.error-message {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 6px;
    text-align: center;
    font-size: 16px;
}

.success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 6px;
    text-align: center;
    font-size: 16px;
}


body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* فونت خواناتر */
    background-color: #f7f7f7; /* پس زمینه روشن‌تر */
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
    padding: 30px; /* افزودن padding کلی به body */
    direction: rtl; /* راست به چپ برای فارسی */
}

.content {
    background-color: #fff;
    padding: 40px; /* افزایش padding برای فضای بیشتر */
    border-radius: 12px; /* لبه های گردتر */
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1); /* سایه ملایم تر و بزرگتر */
    width: 500px; /* عرض ثابت برای دسکتاپ */
    max-width: 95%; /* اطمینان از واکنشگرا بودن در صورت نیاز */
}

h1 {
    text-align: center;
    color: #333;
    margin-bottom: 30px; /* فاصله بیشتر از عنوان */
    font-size: 28px; /* اندازه فونت بزرگتر */
    font-weight: 600; /* وزن فونت نیمه ضخیم */
}

form > div {
    margin-bottom: 25px; /* فاصله بیشتر بین فیلدها */
}

label {
    display: block;
    margin-bottom: 8px; /* فاصله بیشتر از ورودی */
    color: #555;
    font-weight: bold;
    font-size: 16px; /* اندازه فونت بزرگتر */
}

input[type="text"],
input[type="password"] {
    width: calc(100% - 24px); /* تنظیم دقیق تر عرض */
    padding: 12px; /* padding بیشتر برای راحتی ورود */
    border: 1px solid #ddd;
    border-radius: 6px; /* لبه های گردتر */
    box-sizing: border-box;
    font-size: 16px; /* اندازه فونت بزرگتر */
    transition: border-color 0.3s ease; /* انیمیشن برای تغییر رنگ border */
}

input[type="text"]:focus,
input[type="password"]:focus {
    border-color: #007bff; /* تغییر رنگ border هنگام فوکوس */
    outline: none; /* حذف outline پیشفرض مرورگر */
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.2); /* افزودن box-shadow هنگام فوکوس */
}

input[type="submit"] {
    background-color: #007bff;
    color: white;
    padding: 7px 24px; /* padding بیشتر برای دکمه */
    border: none;
    border-radius: 6px; /* لبه های گردتر */
    cursor: pointer;
    font-size: 18px; /* اندازه فونت بزرگتر */
    width: 100%;
    transition: background-color 0.3s ease; /* انیمیشن برای تغییر رنگ پس زمینه */
}

input[type="submit"]:hover {
    background-color: #0056b3;
}

p {
    text-align: center;
    margin-top: 20px; /* فاصله بیشتر از دکمه */
    color: #777;
    font-size: 16px; /* اندازه فونت بزرگتر */
}

a {
    color: #007bff;
    text-decoration: none;
    transition: color 0.3s ease; /* انیمیشن برای تغییر رنگ لینک */
}

a:hover {
    text-decoration: underline;
    color: #0056b3;
}
    </style>
</head>
<body>
<div class="content">
    <form action="" method="post">
        <h1>ورود به سامانه مدیریت دانش آموزان</h1>
        <?php if (!empty($errors)) { ?>
            <?php foreach ($errors as $error) { ?>
                <div class="error-message"><?= htmlspecialchars($error); ?></div>
            <?php } } ?>
        <div>
            <label for="Username">نام کاربری</label>
            <input type="text" name="Username" id="Username" placeholder="Username" required>
        </div>
        <div>
            <label for="Password">گذرواژه</label>
            <input type="password" name="Password" id="Password" placeholder="Password" required>
        </div>
        <div><input type="submit" value="ورود"></div>
        <div><p><a href="password_forgot.php"> رمز عبور خود را فراموش کرده اید؟ کلیک کنید</a></p></div>
    </form>
</div>
</body>
</html>