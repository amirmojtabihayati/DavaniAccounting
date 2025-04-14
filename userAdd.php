<?php
if (isset($_SESSION['user_added']) && $_SESSION['user_added'] == true) {
    header("Location:index.php");
}
include "header.php";
include "db_connection.php";
$cnn = (new class_db())->connection_database;

// گذاشتن پیام خطا در errors
$errors = [];
// گذاشتن پیام موفقیت در success_msg
$success_msg = [];

// گرفتن فیلدها از فرم
if (isset($_POST["username"]) && !empty($_POST["username"]) &&
    isset($_POST["password"]) && !empty($_POST["password"]) &&
    isset($_POST["repassword"]) && !empty($_POST["repassword"]) &&
    isset($_POST["role"]) && !empty($_POST["role"])) {

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $repassword = trim($_POST["repassword"]);
    $role = trim($_POST["role"]);

    // بررسی وجود نام کاربری در پایگاه داده
    $check_user_stmt = $cnn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check_user_stmt->bind_param("s", $username);
    $check_user_stmt->execute();
    $check_user_stmt->bind_result($user_count);
    $check_user_stmt->fetch();
    $check_user_stmt->close();
 
    if ($user_count > 0) {
        $errors[] = "نام کاربری قبلاً استفاده شده است. لطفاً نام کاربری دیگری انتخاب کنید.";
    } else {

    // بررسی تطابق رمز عبور
    if ($password !== $repassword) {
        $errors[] = "رمز عبور و تکرار آن باید یکسان باشند.";
    }

    // بررسی اینکه آیا فایلی ارسال شده است
    if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file_name = $_FILES['profilePic']['name'];
        $file_size = $_FILES['profilePic']['size'];
        $file_tmp = $_FILES['profilePic']['tmp_name'];
        $file_type = $_FILES['profilePic']['type'];

        // استخراج پسوند فایل
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // لیست پسوندهای مجاز
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        // بررسی پسوند فایل
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "پسوند فایل نامعتبر است. فقط پسوندهای JPG، JPEG، PNG و GIF مجاز هستند.";
        }

        // بررسی اندازه فایل (مثلاً حداکثر 2 مگابایت)
        if ($file_size > 2097152) {
            $errors[] = "اندازه فایل باید کمتر از 2 مگابایت باشد.";
        }

        // اگر خطا وجود نداشته باشد، فایل را بارگذاری کنید
        if (empty($errors)) {
            // تعیین مسیر ذخیره‌سازی فایل
            $upload_directory = 'uploads/'; // مطمئن شوید این پوشه وجود دارد و قابل نوشتن است
            $file_path = $upload_directory . basename($file_name);

            // انتقال فایل به پوشه مورد نظر
            if (move_uploaded_file($file_tmp, $file_path)) {
                // هش کردن رمز عبور
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // درج اطلاعات در پایگاه داده
                $stmt = $cnn->prepare("INSERT INTO users (username, password, role, profile) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $hashed_password, $role, $file_path);

                if ($stmt->execute()) {
                    $_SESSION['user_added'] = true;
                    $success_msg[] = "کاربر(" . $username . ") با موفقیت افزوده شد";
                } else {
                    echo "خطا در ذخیره‌سازی مسیر در پایگاه داده: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "خطا در بارگذاری عکس";
            }
        }
    } elseif (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] === UPLOAD_ERR_NO_FILE) {
        // اگر کاربر فایلی ارسال نکرده است، تنها اطلاعات کاربر را ذخیره می‌کنیم
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $cnn->prepare("INSERT INTO users (username, password, role, profile) VALUES (?, ?, ?, ?)");
        $default_profile_path = NULL;
        $stmt->bind_param("ssss", $username, $hashed_password, $role, $default_profile_path);

        if ($stmt->execute()) {
            $_SESSION['user_added'] = true;
            $success_msg[] = "کاربر(" . $username . ") با موفقیت افزوده شد";
        } else {
            echo "خطا در ذخیره‌سازی اطلاعات در پایگاه داده: " . $stmt->error;
        }

        $stmt->close();
    }
}
}

// بستن اتصال
$cnn->close();
?>

<div class="container">
    <h1 id="h1">افزودن کاربر جدید</h1>
    <!-- نمایش پیام‌های خطا -->
    <?php if (!empty($errors)) { ?>
        <?php foreach ($errors as $error) { ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php }
    } elseif (!empty($success_msg)) { ?>
        <div class="success-message"><?php echo htmlspecialchars($success_msg[0]); ?></div>
    <?php } ?>

    <form id="Form" action="" method="post" enctype="multipart/form-data"> <!-- اضافه کردن enctype -->
        <label id="label" for="username">نام کاربری:</label>
        <input class="input" type="text" id="username" name="username" required>

        <label id="label" for="password">گذرواژه:</label>
        <input class="input" type="password" id="password" name="password" required>

        <label id="label" for="repassword">تکرار گذرواژه:</label>
        <input class="input" type="password" id="repassword" name="repassword" required>

        <label id="label" for="role">نقش کاربر:</label>
        <select class="select" name="role" id="role" required>
            <option value="" selected>انتخاب کنید</option>
            <option value="admin">مدیر</option>
            <option value="user">معاون</option>
        </select>

        <label id="user-label" for="profilePic">عکس پروفایل:</label>
        <input class="input" type="file" id="profilePic" name="profilePic" accept="image/*" onchange="previewImage(event)">

        <div class="image-preview" id="imagePreview">
            <img id="preview" src="" alt="پیش‌نمایش عکس" />
        </div>

        <button id="submit_btn" type="submit">افزودن کاربر</button>
    </form>
</div>

<script src="assets/js/imagePreview.js"></script>
<script src="assets/js/hideMessage.js"></script>
<script src="script.js"></script>
<?php
include "footer.php";
?>