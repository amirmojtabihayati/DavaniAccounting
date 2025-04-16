<?php
// include های ضروری
include "../header.php"; // Assume header includes necessary CSS links or <style> tags

// --- Error Reporting (Disable in production) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Includes ---
$basePath = __DIR__ . "/../";
include_once $basePath . "db_connection.php";
include_once $basePath . "convertToPersianNumbers.php"; // Function for display
require_once $basePath . "assets/vendor/Jalali-Date/jdf.php"; // Jalali date library

// --- Helper Function ---
function convertToEnglishNumbers($string) {
    if ($string === null || $string === '') return '';
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '،'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٬'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '']; // Remove comma

    $string = str_replace(',', '', $string); // Remove English comma first
    return str_replace($persian, $english, str_replace($arabic, $english, $string));
}

// --- Database Connection ---
try {
    $db = new class_db();
    $cnn = $db->connection_database;
    if ($cnn) {
        $cnn->set_charset("utf8mb4");
    } else {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    error_log("Installment Page DB Error: " . $e->getMessage());
    // Display user-friendly error message within the layout in the HTML part
    $errors[] = "خطا در اتصال به پایگاه داده.";
    // Avoid dying here to allow the rest of the page structure to load
}

// --- Variable Initialization ---
$errors = [];
$success_msg = [];
$search_national_code = '';
$student_name = '';
$student_id = null;
$debt_titles = [];
$has_debt = false;
$form_data_to_repopulate = null; // To store POST data if submission fails

// --- Process POST Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($cnn)) { // Only process if DB connection is okay

    // --- Student Search ---
    if (isset($_POST['search'])) {
        $search_national_code = trim($_POST['national_code'] ?? '');
        if (!empty($search_national_code)) {
            $stmt = $cnn->prepare("SELECT id, first_name, last_name FROM students WHERE national_code = ?");
            if ($stmt) {
                $stmt->bind_param("s", $search_national_code);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($student_data = $result->fetch_assoc()) {
                    $student_id = $student_data['id'];
                    $student_name = $student_data['first_name'] . ' ' . $student_data['last_name'];

                    // Fetch active debt titles for this student
                    $stmt_debt = $cnn->prepare("SELECT DISTINCT title FROM debts WHERE student_id = ? AND amount > 0 ORDER BY title");
                    if ($stmt_debt) {
                        $stmt_debt->bind_param("i", $student_id);
                        $stmt_debt->execute();
                        $debt_result = $stmt_debt->get_result();
                        while ($row = $debt_result->fetch_assoc()) {
                            $debt_titles[] = $row['title'];
                        }
                        $stmt_debt->close();
                        $has_debt = count($debt_titles) > 0;
                        if (!$has_debt) {
                            $errors[] = "هیچ بدهی فعالی برای این دانش‌آموز یافت نشد.";
                        }
                    } else {
                         $errors[] = "خطا در دریافت عناوین بدهی: " . $cnn->error;
                    }
                } else {
                    $errors[] = "دانش‌آموزی با این کد ملی یافت نشد.";
                }
                $stmt->close();
            } else {
                 $errors[] = "خطا در آماده‌سازی جستجوی دانش‌آموز: " . $cnn->error;
            }
        } else {
            $errors[] = "لطفا کد ملی را برای جستجو وارد کنید.";
        }
    }
    // --- Add Installment Plan ---
    else if (isset($_POST['add_taghsit'])) {
        // Retrieve necessary data
        $search_national_code = $_POST['hidden_national_code'] ?? ''; // Get from hidden field
        $student_id = isset($_POST['hidden_student_id']) ? filter_var($_POST['hidden_student_id'], FILTER_VALIDATE_INT) : null;
        $debt_title = trim($_POST['debt_title'] ?? '');
        $number_of_installments = filter_input(INPUT_POST, 'number_of_installments', FILTER_VALIDATE_INT);
        $installment_dates = $_POST['installment_dates'] ?? [];
        $manual_amounts = $_POST['manual_amounts'] ?? [];
        $amount_type = $_POST['amount_type'] ?? 'automatic'; // Get amount type

        // Store POST data to repopulate form if error occurs
        $form_data_to_repopulate = $_POST;

        // --- Validation ---
        if (empty($student_id)) { $errors[] = "اطلاعات دانش‌آموز نامعتبر است. لطفاً دوباره جستجو کنید."; }
        if (empty($debt_title)) { $errors[] = "لطفاً عنوان بدهی را انتخاب کنید."; }
        if ($number_of_installments === false || $number_of_installments <= 0) { $errors[] = "تعداد اقساط نامعتبر است."; }
        if (count($installment_dates) !== $number_of_installments) { $errors[] = "تعداد تاریخ‌های وارد شده (" . count($installment_dates) . ") با تعداد اقساط (" . $number_of_installments . ") مغایرت دارد."; }
        if (count($manual_amounts) !== $number_of_installments) { $errors[] = "تعداد مبالغ وارد شده (" . count($manual_amounts) . ") با تعداد اقساط (" . $number_of_installments . ") مغایرت دارد."; }

        // --- Further Validation and Processing if no initial errors ---
        if (empty($errors)) {
            $total_manual_amount = 0;
            $validated_installments = []; // Store validated data

            for ($i = 0; $i < $number_of_installments; $i++) {
                $due_date_jalali = trim($installment_dates[$i]);
                $amount_str = convertToEnglishNumbers($manual_amounts[$i]);
                $installment_amount_val = filter_var($amount_str, FILTER_VALIDATE_FLOAT);

                // Validate each date and amount
                if (empty($due_date_jalali)) { $errors[] = "تاریخ قسط " . ($i + 1) . " نمی‌تواند خالی باشد."; break; }
                if ($installment_amount_val === false || $installment_amount_val <= 0) { $errors[] = "مبلغ قسط " . ($i + 1) . " نامعتبر است."; break; }

                // Validate Jalali date format and convert to Gregorian
                if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $due_date_jalali, $matches)) {
                    $errors[] = "فرمت تاریخ قسط " . ($i + 1) . " ( " . htmlspecialchars($due_date_jalali) . " ) نامعتبر است. لطفاً از فرمت YYYY/MM/DD استفاده کنید."; break;
                }
                try {
                    list($jy, $jm, $jd) = [$matches[1], $matches[2], $matches[3]];
                    // Use jmktime carefully, ensure month/day are valid before calling
                     if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) { // Basic validation
                         throw new Exception("اجزای تاریخ قسط " . ($i + 1) . " نامعتبر است.");
                     }
                     // Check for valid day in month (more complex, jdf might handle it)
                     // $daysInMonth = jdate('t', jmktime(0,0,0, $jm, 1, $jy));
                     // if ($jd > $daysInMonth) { throw new Exception(...) }

                    $timestamp = jmktime(0, 0, 0, $jm, $jd, $jy);
                     if (!$timestamp) { throw new Exception("تاریخ قسط " . ($i + 1) . " نامعتبر است."); }
                    $gregorianDate = jdate("Y-m-d", $timestamp, '', '', 'en'); // Get Gregorian date
                     if (!$gregorianDate) { throw new Exception("خطا در تبدیل تاریخ قسط " . ($i + 1) . "."); }

                } catch (Exception $e) {
                    $errors[] = $e->getMessage(); break;
                }

                $validated_installments[] = [
                    'amount' => $installment_amount_val,
                    'date' => $gregorianDate
                ];
                $total_manual_amount += $installment_amount_val;
            } // End for loop validation

            // Check if total manual amount matches the original debt amount (if applicable)
             if (empty($errors) && $amount_type === 'manual') {
                // Fetch the original debt amount again for comparison (more secure than relying on JS variable)
                 $stmt_orig_amount = $cnn->prepare("SELECT amount FROM debts WHERE student_id = ? AND title = ?");
                  if ($stmt_orig_amount) {
                      $stmt_orig_amount->bind_param("is", $student_id, $debt_title);
                      $stmt_orig_amount->execute();
                      $result_orig_amount = $stmt_orig_amount->get_result();
                      if ($row_orig = $result_orig_amount->fetch_assoc()) {
                          $original_debt_amount = (float)$row_orig['amount'];
                           // Compare with a small tolerance for floating point issues
                           if (abs($total_manual_amount - $original_debt_amount) > 0.01) {
                               $errors[] = "مجموع مبالغ اقساط وارد شده (" . number_format($total_manual_amount) . ") با مبلغ کل بدهی (" . number_format($original_debt_amount) . ") برای عنوان '" . htmlspecialchars($debt_title) . "' مطابقت ندارد.";
                           }
                      } else { $errors[] = "خطا: مبلغ اصلی بدهی یافت نشد."; }
                      $stmt_orig_amount->close();
                  } else { $errors[] = "خطا در بررسی مبلغ اصلی بدهی."; }
             }


            // --- Insert into Database if no errors ---
            if (empty($errors)) {
                $stmt_insert = $cnn->prepare("INSERT INTO installments (student_id, national_code, debt_title, installment_amount, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                if ($stmt_insert === false) {
                    $errors[] = "خطا در آماده‌سازی دستور پایگاه داده: " . $cnn->error;
                } else {
                    $insert_success_all = true;
                    foreach ($validated_installments as $index => $installment) {
                        $stmt_insert->bind_param("issds", $student_id, $search_national_code, $debt_title, $installment['amount'], $installment['date']);
                        if (!$stmt_insert->execute()) {
                            $errors[] = "خطا در ذخیره‌سازی قسط " . ($index + 1) . ": " . $stmt_insert->error;
                            $insert_success_all = false;
                            break; // Stop on first error
                        }
                    }
                    $stmt_insert->close();

                    if ($insert_success_all) {
                        $success_msg[] = "تقسیط بدهی برای عنوان '" . htmlspecialchars($debt_title) . "' با موفقیت ثبت شد.";
                        // Clear form data only on complete success
                        $form_data_to_repopulate = null;
                        $search_national_code = ''; // Reset search
                        $student_name = '';
                        $debt_titles = [];
                        $has_debt = false;
                        $student_id = null;
                        // Consider redirecting here to prevent resubmission on refresh
                        // header("Location: " . $_SERVER['PHP_SELF'] . "?status=success"); exit;
                    }
                }
            }
        } // End if empty($errors) initial validation

        // --- Reload student data if errors occurred during submission ---
         if (!empty($errors) && !empty($search_national_code)) {
            // Need to reload student name and debt titles to display the form correctly
             $stmt_reload = $cnn->prepare("SELECT id, first_name, last_name FROM students WHERE national_code = ?");
             if($stmt_reload){
                 $stmt_reload->bind_param("s", $search_national_code);
                 $stmt_reload->execute();
                 $result_reload = $stmt_reload->get_result();
                 if ($student_data_reload = $result_reload->fetch_assoc()) {
                     $student_id = $student_data_reload['id']; // Keep student_id
                     $student_name = $student_data_reload['first_name'] . ' ' . $student_data_reload['last_name']; // Keep student name
                     // Reload debt titles
                     $stmt_debt_reload = $cnn->prepare("SELECT DISTINCT title FROM debts WHERE student_id = ? AND amount > 0 ORDER BY title");
                     if($stmt_debt_reload){
                         $stmt_debt_reload->bind_param("i", $student_id);
                         $stmt_debt_reload->execute();
                         $debt_result_reload = $stmt_debt_reload->get_result();
                         $debt_titles = []; // Reset titles before reloading
                         while ($row_reload = $debt_result_reload->fetch_assoc()) {
                             $debt_titles[] = $row_reload['title'];
                         }
                         $stmt_debt_reload->close();
                         $has_debt = count($debt_titles) > 0;
                     } else { $errors[] = "خطا در بارگذاری مجدد عناوین بدهی."; }
                 } else { $student_name = ''; $has_debt = false; } // Student not found on reload? Unlikely but handle
                 $stmt_reload->close();
             } else { $errors[] = "خطا در بارگذاری مجدد اطلاعات دانش آموز."; }
         }

    } // End else if add_taghsit
} // End POST check

?>
<!DOCTYPE html>
<html lang="fa"> <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقسیط بدهی</title>
    <link rel="stylesheet" href="../assets/vendor/persian-datepicker/dist/css/persian-datepicker.min.css"/>
    <link rel="stylesheet" href="path/to/your/main/styles.css">
    <style>
        body {
            direction: rtl;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            color: #333;
            font-size: 14px;
            line-height: 1.6;
        }

        .container {
            width: 95%;
            max-width: 900px;
            margin: 20px auto;
            padding: 25px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
        }
        .page-title { /* Renamed h1 to a class */
            color: #333;
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.6em;
        }
        hr { border: 0; height: 1px; background-color: #ddd; margin: 25px 0; }

        /* --- Forms --- */
        .search-form-section, .installment-form-section { margin-bottom: 20px; }
        .jostojo { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 15px; }
        .jostojo label { font-weight: bold; margin-left: 5px; flex-shrink: 0; }
        .searchBy, .input, .select { /* Combined input/select styles */
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1em;
            font-family: inherit;
            width: 100%;
            box-sizing: border-box;
            background-color: #fff;
        }
        .jostojo .input { flex-grow: 1; min-width: 200px; width: auto; } /* Specific for search input */
        .input:focus, .select:focus {
            border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .select {
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23007bff%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat; background-position: left 10px center; background-size: 10px auto; padding-left: 30px;
        }
        .input[type="number"] { width: 80px; flex-grow: 0; } /* Smaller width for number input */

        /* --- Buttons --- */
        .Button, #submit_btn, #generate_installments_btn, #cancel_btn { /* Apply Button style to existing IDs */
            padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; font-family: inherit;
            text-decoration: none; display: inline-flex; align-items: center; justify-content: center;
            transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.1s ease;
            background-color: #007bff; color: white; line-height: 1.5; margin-left: 5px; /* Add some margin */
        }
        .Button:hover, #submit_btn:hover, #generate_installments_btn:hover, #cancel_btn:hover { background-color: #0056b3; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15); }
        .Button:active, #submit_btn:active, #generate_installments_btn:active, #cancel_btn:active { background-color: #004085; transform: translateY(1px); }
        #submit_btn[name="search"] { background-color: #17a2b8; } /* Search button color */
        #submit_btn[name="search"]:hover { background-color: #138496; }
        #submit_btn[name="add_taghsit"] { background-color: #28a745; } /* Save button color */
        #submit_btn[name="add_taghsit"]:hover { background-color: #218838; }
        #cancel_btn { background-color: #dc3545; } /* Cancel button color */
        #cancel_btn:hover { background-color: #c82333; }
        #generate_installments_btn { background-color: #ffc107; color: #333; } /* Generate button color */
        #generate_installments_btn:hover { background-color: #e0a800; }
        #submit_btn:disabled, #generate_installments_btn:disabled { background-color: #adb5bd; cursor: not-allowed; box-shadow: none; transform: none; }

        /* --- Messages --- */
        .message-container { margin: 15px 0; }
        .error-message, .success-message, .info-message {
            padding: 12px 18px; border-radius: 4px; margin-bottom: 10px; font-size: 0.95em; border: 1px solid transparent;
        }
        .error-message { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .success-message { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .info-message { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .error-container, .success-container, .info-container { padding: 5px; border-radius: 5px; } /* Optional wrapper */
        .error-container { border: 1px dashed #dc3545; }
        .success-container { border: 1px dashed #28a745; }
        .info-container { border: 1px dashed #17a2b8; }


        /* --- Installment Form Specific Styles --- */
        .student-info {
            background-color: #e9ecef; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;
            font-size: 1.05em; border: 1px solid #ced4da;
        }
        .student-info label { font-weight: bold; color: #495057; }
        .student-info span { color: #0056b3; }

        .item { /* Wrapper for main controls */
            display: flex; flex-wrap: wrap; align-items: center; gap: 15px; margin-bottom: 20px;
            padding: 15px; background-color: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6;
        }
        .item label { margin-left: 5px; font-weight: normal; } /* Labels in item */
        .item .select, .item .input[type="number"] { width: auto; flex-grow: 1; min-width: 150px; } /* Adjust width */
         .item #debt_amount_display {
             font-weight: bold; color: #007bff; background-color: #fff; padding: 5px 10px; border-radius: 4px; border: 1px solid #ccc;
             min-width: 120px; text-align: center;
         }
        .item label input[type="radio"] { margin-left: 3px; vertical-align: middle; }

        /* --- Installment Fields Container --- */
        #installments_container {
            margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #fff;
            display: flex; flex-direction: column; gap: 15px; /* Space between installment groups */
        }
        .installment-group {
            background-color: #f9f9f9; padding: 15px; border-radius: 4px; border: 1px solid #eee;
            display: flex; flex-direction: column; gap: 10px; /* Space within a group */
            position: relative; /* For potential absolute positioning inside */
        }
         .date-amount { /* Container for number, date, amount */
             display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
         }
        .installment-number { font-weight: bold; color: #555; margin-left: 10px; min-width: 50px; }
         .input-wrapper { /* Wrapper for label + input */
             display: flex; flex-direction: column; flex-grow: 1; min-width: 180px; /* Allow growth */
         }
         .input-wrapper label { font-size: 0.9em; color: #666; margin-bottom: 3px; }
         .input-wrapper .ghest { /* Style for date and amount inputs */
             padding: 8px 10px; font-size: 0.95em;
         }
         .input-wrapper .amount-input { text-align: left; direction: ltr; } /* Align amount input left */
         .input-wrapper .amount-input:read-only { background-color: #e9ecef; cursor: not-allowed; }

         /* Delete All Button/Icon */
         .delete-all-container {
             display: inline-flex; align-items: center; gap: 5px; cursor: pointer; color: #dc3545;
             font-size: 0.9em; margin-bottom: 10px; border-bottom: 1px dashed #ffb8c1; padding-bottom: 5px;
             align-self: flex-start; /* Position top-left */
         }
         .delete-icon { width: 16px; height: 16px; vertical-align: middle; }
         .delete-all-container:hover { color: #a71d2a; border-bottom-color: #a71d2a; }


        /* --- Installment Summary --- */
        #installments_summary {
            margin-top: 15px; padding: 10px 15px; border-radius: 4px; font-weight: bold;
            border: 1px solid transparent; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;
        }
        #installments_summary.summary-match { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        #installments_summary.summary-mismatch { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        #installments_summary.summary-invalid { background-color: #fff3cd; border-color: #ffeeba; color: #856404; }


        /* --- Datepicker Style --- */
        .pdp-group { z-index: 100; } /* Ensure datepicker appears above other elements */

        /* --- Responsive Adjustments --- */
        @media (max-width: 768px) {
            .container { width: 98%; padding: 15px; }
            .item { flex-direction: column; align-items: stretch; }
            .item .select, .item .input[type="number"], .item #debt_amount_display { width: 100%; }
            .date-amount { flex-direction: column; align-items: stretch; }
            .input-wrapper { min-width: 100%; }
        }

    </style>
</head>
<body>
<div class="container">
    <h1 class="page-title">تقسیط بدهی دانش آموز</h1> <form id="searchForm" action="" method="post" class="search-form-section">
        <div class="jostojo">
            <label for="national_code_input">جستجو بر اساس کد ملی:</label> <input class="input" type="text" id="national_code_input" name="national_code" value="<?= htmlspecialchars($search_national_code ? convertToPersianNumbers($search_national_code) : '') ?>" required placeholder="کد ملی را وارد کنید">
            <button id="submit_btn" type="submit" name="search">جستجو</button>
        </div>
    </form>

    <?php if (!empty($errors) || !empty($success_msg)): ?>
    <div class="message-container">
        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <?php foreach ($errors as $error): ?>
                    <div class="error-message"><?= htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_msg)): ?>
            <div class="success-container">
                <?php foreach ($success_msg as $msg): ?>
                    <div class="success-message"><?= htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>


    <?php if ($student_name && $has_debt): ?>
        <div class="student-info">
            <label>نام دانش‌آموز:</label>
            <span>&nbsp; <?= htmlspecialchars($student_name) ?> </span>
            <label style="margin-right: 20px;">کد ملی:</label>
            <span>&nbsp; <?= htmlspecialchars(convertToPersianNumbers($search_national_code)) ?> </span>
        </div>
        <hr>
        <form id="installmentForm" action="" method="post" class="installment-form-section">
            <input type="hidden" name="hidden_national_code" value="<?= htmlspecialchars($search_national_code) ?>">
            <input type="hidden" name="hidden_student_id" value="<?= htmlspecialchars($student_id) ?>">

            <div class="item">
                <label for="debt_title">عنوان بدهی:</label>
                <select class="select" name="debt_title" id="debt_title" required onchange="fetchDebtAmount()">
                    <option value="">-- انتخاب کنید --</option>
                    <?php foreach ($debt_titles as $title): ?>
                         <?php $selected_title = ($form_data_to_repopulate['debt_title'] ?? '') === $title ? 'selected' : ''; ?>
                        <option value="<?= htmlspecialchars($title) ?>" <?= $selected_title ?>><?= htmlspecialchars($title) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>مبلغ کل بدهی:</label>
                <span id="debt_amount_display" data-raw-amount="0">&nbsp;-- عنوان را انتخاب کنید --&nbsp;</span>

                <label for="number_of_installments">تعداد اقساط:</label>
                <?php $num_installments_value = $form_data_to_repopulate['number_of_installments'] ?? 1; ?>
                <input type="number" name="number_of_installments" id="number_of_installments" class="input" min="1" value="<?= htmlspecialchars($num_installments_value) ?>">

                <label>محاسبه مبلغ:</label>
                <?php $amount_type_value = $form_data_to_repopulate['amount_type'] ?? 'automatic'; ?>
                <label><input type="radio" name="amount_type" value="automatic" <?= ($amount_type_value === 'automatic') ? 'checked' : '' ?> onclick="toggleAmountInputs(false)"> خودکار</label>
                <label><input type="radio" name="amount_type" value="manual" <?= ($amount_type_value === 'manual') ? 'checked' : '' ?> onclick="toggleAmountInputs(true)"> دستی</label>

                <button id="generate_installments_btn" type="button" onclick="generateInstallmentFields()" disabled>ایجاد فیلدهای قسط</button>
            </div>

            <div id="installments_container">
                <?php
                 // If form was submitted with errors, trigger JS to repopulate fields
                 if ($form_data_to_repopulate && isset($form_data_to_repopulate['add_taghsit']) && !empty($form_data_to_repopulate['installment_dates'])) {
                     echo '<script>';
                     echo 'document.addEventListener("DOMContentLoaded", function() {';
                     // Fetch amount first if title was selected
                     echo '  const initialTitle = document.getElementById("debt_title").value;';
                     echo '  if (initialTitle) { fetchDebtAmount(); }'; // Fetch amount associated with the title
                     // Use setTimeout to ensure fetchDebtAmount completes and totalDebtAmount is set
                     echo '  setTimeout(() => {';
                     echo '      generateInstallmentFields();'; // Generate fields structure
                     echo '      populateExistingFields(' . json_encode($form_data_to_repopulate['installment_dates']) . ', ' . json_encode($form_data_to_repopulate['manual_amounts']) . ');'; // Populate with old data
                     echo '      toggleAmountInputs(' . ($amount_type_value === 'manual' ? 'true' : 'false') . ');'; // Set readonly status correctly
                     echo '  }, 500);'; // Adjust delay if needed
                     echo '});';
                     echo '</script>';
                 }
                 ?>
            </div>
            <div id="installments_summary"></div> <div class="form-actions"> <button id="submit_btn" type="submit" name="add_taghsit">ثبت تقسیط</button>
                <button id="cancel_btn" type="button" onclick="window.location.href='../index.php'">انصراف</button>
            </div>
        </form>

    <?php elseif (!empty($search_national_code) && $student_name && !$has_debt): ?>
         <div class="message-container info-container">
             <div class="info-message">هیچ بدهی فعالی برای دانش‌آموز <?= htmlspecialchars($student_name) ?> یافت نشد.</div>
         </div>
    <?php elseif (!empty($search_national_code) && !$student_name): ?>
         <div class="message-container error-container">
             <div class="error-message">دانش‌آموزی با کد ملی <?= htmlspecialchars(convertToPersianNumbers($search_national_code)) ?> یافت نشد.</div>
         </div>
    <?php endif; ?>

</div><script src="../assets/js/jquery.js"></script> <script src="../PersianDate/dist/persian-date.min.js"></script>
<script src="../DatePicker/dist/js/persian-datepicker.min.js"></script>
<script src="../assets/js/ConverterPersianNumbers.js"></script> <script>
let totalDebtAmount = 0; // Global variable for total debt amount

// --- Helper JS Functions ---
function convertToEnglishNumbers(str) { /* ... (implementation from previous code) ... */
    if (!str) return '';
    const persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '،'];
    const arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٬'];
    const english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ''];
    str = String(str);
    str = str.replace(/,/g, '');
    persian.forEach((char, index) => { str = str.replace(new RegExp(char, 'g'), english[index]); });
    arabic.forEach((char, index) => { str = str.replace(new RegExp(char, 'g'), english[index]); });
    return str;
}
function numberWithCommas(x) { /* ... (implementation from previous code) ... */
    if (x === null || x === undefined) return '0';
    let parts = String(x).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return parts.join('.');
}
// Make sure convertToPersianNumbers is defined (either here or in included JS file)
 function convertToPersianNumbers(input) {
     if (input === null || input === undefined) return '';
     const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
     return String(input).replace(/[0-9]/g, function (w) {
         return persianDigits[parseInt(w)];
     });
 }


// --- Main Functions ---
function fetchDebtAmount() { /* ... (implementation from previous code, ensure it updates totalDebtAmount global variable) ... */
    const debtTitle = document.getElementById('debt_title').value;
    const nationalCode = document.querySelector('input[name="hidden_national_code"]')?.value || '<?= htmlspecialchars($search_national_code ?? "") ?>'; // Ensure nationalCode is available
    const displaySpan = document.getElementById('debt_amount_display');
    const generateBtn = document.getElementById('generate_installments_btn');

    if (!debtTitle || !nationalCode) {
        displaySpan.innerHTML = '&nbsp;-- انتخاب کنید --&nbsp;';
        displaySpan.setAttribute('data-raw-amount', '0');
        totalDebtAmount = 0;
        generateBtn.disabled = true;
        clearInstallmentFields();
        return;
    }

    generateBtn.disabled = false; // Enable button once title is selected
    displaySpan.innerHTML = '...درحال بارگذاری';
    displaySpan.setAttribute('data-raw-amount', '0'); // Reset raw amount

    $.ajax({
        url: 'get_debt_for_taghsit.php',
        type: 'POST',
        data: { title: debtTitle, national_code: nationalCode },
        success: function(data) {
            const amount = parseFloat(data);
            if (!isNaN(amount) && amount > 0) {
                totalDebtAmount = amount; // Update global variable
                displaySpan.innerText = convertToPersianNumbers(numberWithCommas(amount.toFixed(0)));
                displaySpan.setAttribute('data-raw-amount', amount);
                clearInstallmentFields(); // Clear old fields when new amount is loaded
            } else {
                totalDebtAmount = 0;
                displaySpan.innerText = 'مبلغ یافت نشد';
                displaySpan.setAttribute('data-raw-amount', '0');
                generateBtn.disabled = true;
                clearInstallmentFields();
                alert('خطا: مبلغ بدهی برای عنوان انتخاب شده یافت نشد یا صفر است.');
            }
        },
        error: function() {
            totalDebtAmount = 0;
            displaySpan.innerText = 'خطا در دریافت';
            displaySpan.setAttribute('data-raw-amount', '0');
            generateBtn.disabled = true;
            clearInstallmentFields();
            alert('خطا در ارتباط با سرور برای دریافت مبلغ بدهی.');
        }
    });
}

function generateInstallmentFields() { /* ... (exact implementation from previous code, ensure it uses the global totalDebtAmount) ... */
    const container = document.getElementById('installments_container');
    const numberOfInstallments = parseInt(document.getElementById('number_of_installments').value) || 0;
    const isManual = document.querySelector('input[name="amount_type"]:checked').value === 'manual';

    if (totalDebtAmount <= 0 || numberOfInstallments <= 0) {
        clearInstallmentFields();
        if (totalDebtAmount <= 0 && document.getElementById('debt_title').value) {
            // Maybe show a subtle message instead of alert
        } else if (numberOfInstallments <= 0 && totalDebtAmount > 0) {
             alert('تعداد اقساط باید حداقل ۱ باشد.');
        }
        return;
    }

    container.innerHTML = ''; // Clear previous fields

    // Add "Delete All" button
    const deleteAllContainer = document.createElement('div');
    deleteAllContainer.title = 'حذف همه اقساط';
    deleteAllContainer.classList.add('delete-all-container');
    deleteAllContainer.innerHTML = '<img src="../images/del-all_icon.png" alt="حذف همه" class="delete-icon"> حذف همه اقساط';
    deleteAllContainer.onclick = clearInstallmentFields;
    container.appendChild(deleteAllContainer);


    let baseInstallmentAmount = Math.floor(totalDebtAmount / numberOfInstallments);
    let remainder = totalDebtAmount - (baseInstallmentAmount * numberOfInstallments);

    for (let i = 0; i < numberOfInstallments; i++) {
        const installmentDiv = document.createElement('div');
        installmentDiv.classList.add('installment-group');
        installmentDiv.setAttribute('data-index', i);

        const dateAmountDiv = document.createElement('div');
        dateAmountDiv.classList.add('date-amount');

        // Installment Number
        const numberLabel = document.createElement('span');
        numberLabel.classList.add('installment-number');
        numberLabel.innerText = `قسط ${convertToPersianNumbers(i + 1)}:`;
        dateAmountDiv.appendChild(numberLabel);

        // Date Block
        const dateWrapper = document.createElement('div');
        dateWrapper.classList.add('input-wrapper');
        const labelDate = document.createElement('label');
        labelDate.innerText = `تاریخ:`;
        const inputDate = document.createElement('input');
        inputDate.type = 'text';
        inputDate.name = 'installment_dates[]';
        inputDate.className = 'ghest date-input';
        inputDate.placeholder = 'YYYY/MM/DD';
        inputDate.autocomplete = 'off';
        dateWrapper.appendChild(labelDate);
        dateWrapper.appendChild(inputDate);
        dateAmountDiv.appendChild(dateWrapper);

        // Amount Block
        const amountWrapper = document.createElement('div');
        amountWrapper.classList.add('input-wrapper');
        const labelAmount = document.createElement('label');
        labelAmount.innerText = `مبلغ:`;
        const amountInput = document.createElement('input');
        amountInput.type = 'text';
        amountInput.name = 'manual_amounts[]';
        amountInput.className = 'ghest amount-input';
        amountInput.placeholder = 'مبلغ قسط';
        amountInput.inputMode = 'numeric';
        amountInput.readOnly = !isManual;

        let currentInstallmentAmount = baseInstallmentAmount;
        if (i === numberOfInstallments - 1) {
            currentInstallmentAmount += remainder;
        }
        amountInput.value = convertToPersianNumbers(numberWithCommas(currentInstallmentAmount.toFixed(0)));

        // Event Listeners for Amount Input
        amountInput.addEventListener('input', function() {
            const englishValue = convertToEnglishNumbers(this.value);
            const numericValue = parseFloat(englishValue) || 0;
            this.value = convertToPersianNumbers(numberWithCommas(numericValue.toFixed(0)));
            if (isManual) updateInstallmentSummary();
        });
        amountInput.addEventListener('focus', function() {
            if (!this.readOnly) {
                this.value = convertToEnglishNumbers(this.value);
                 this.select();
            }
        });
        amountInput.addEventListener('blur', function() {
            if (!this.readOnly) {
                const englishValue = convertToEnglishNumbers(this.value);
                const numericValue = parseFloat(englishValue) || 0;
                this.value = convertToPersianNumbers(numberWithCommas(numericValue.toFixed(0)));
                if (isManual) updateInstallmentSummary();
            }
        });

        amountWrapper.appendChild(labelAmount);
        amountWrapper.appendChild(amountInput);
        dateAmountDiv.appendChild(amountWrapper);

        installmentDiv.appendChild(dateAmountDiv);
        container.appendChild(installmentDiv);

        // Initialize Datepicker
        $(inputDate).pDatepicker({
            format: 'YYYY/MM/DD', autoClose: true, initialValue: false, position: "auto",
            calendarType: "persian", observer: true, inputDelay: 400,
            calendar: { persian: { locale: 'fa' } },
            toolbox: { enabled: true, calendarSwitch: { enabled: false }, todayButton: { enabled: true, text: { fa: 'امروز' } }, submitButton: { enabled: false } }
        });
    }
    updateInstallmentSummary(); // Update summary after generation
}

function clearInstallmentFields() { /* ... (implementation from previous code) ... */
    document.getElementById('installments_container').innerHTML = '';
    document.getElementById('installments_summary').innerHTML = '';
    document.getElementById('installments_summary').className = ''; // Reset summary classes
}

function toggleAmountInputs(isManual) { /* ... (implementation from previous code - just calls generateInstallmentFields) ... */
     // Find all amount inputs and set readonly property
     const amountInputs = document.querySelectorAll('#installments_container .amount-input');
     amountInputs.forEach(input => {
         input.readOnly = !isManual;
         // Optional: Add/remove a class for visual styling if needed
         if (isManual) {
             input.classList.remove('readonly-amount');
         } else {
             input.classList.add('readonly-amount');
         }
     });
     // If switching back to automatic, regenerate fields to reset amounts
     if (!isManual) {
         generateInstallmentFields();
     } else {
         updateInstallmentSummary(); // Update summary when switching to manual
     }
}

function updateInstallmentSummary() { /* ... (implementation from previous code - uses global totalDebtAmount) ... */
    const summaryDiv = document.getElementById('installments_summary');
    const amountInputs = document.querySelectorAll('#installments_container .amount-input');
    const isManual = document.querySelector('input[name="amount_type"]:checked').value === 'manual';

    summaryDiv.innerHTML = '';
    summaryDiv.className = ''; // Reset classes

    if (amountInputs.length === 0) return;

    let currentTotal = 0;
    let hasInvalidAmount = false;

    amountInputs.forEach(input => {
        const englishValue = convertToEnglishNumbers(input.value);
        const numericValue = parseFloat(englishValue);
        if (isNaN(numericValue) || numericValue < 0) hasInvalidAmount = true;
        currentTotal += numericValue || 0;
    });

    const totalAmountPersian = convertToPersianNumbers(numberWithCommas(currentTotal.toFixed(0)));
    const totalDebtPersian = convertToPersianNumbers(numberWithCommas(totalDebtAmount.toFixed(0)));
    const difference = currentTotal - totalDebtAmount;
    const differencePersian = convertToPersianNumbers(numberWithCommas(Math.abs(difference).toFixed(0)));

    let summaryText = `مجموع اقساط: ${totalAmountPersian}`;
    if (isManual) summaryText += ` (کل بدهی: ${totalDebtPersian})`;
    summaryDiv.innerHTML = `<span>${summaryText}</span>`;

    let differenceText = '';
    if (isManual) {
        if (hasInvalidAmount) {
            differenceText = `<span style="color: #d35400;">مبلغ نامعتبر وجود دارد.</span>`;
            summaryDiv.classList.add('summary-invalid');
        } else if (Math.abs(difference) > 0.01) {
            const diffType = difference > 0 ? 'بیشتر' : 'کمتر';
            differenceText = `<span>( ${differencePersian} تومان ${diffType} )</span>`;
            summaryDiv.classList.add('summary-mismatch');
        } else {
            differenceText = `<span>( مطابقت دارد )</span>`;
            summaryDiv.classList.add('summary-match');
        }
        summaryDiv.innerHTML += differenceText;
    } else {
         summaryDiv.classList.add('summary-match'); // Assume match in auto mode
    }
}

// --- Function to repopulate fields after server-side error ---
function populateExistingFields(dates, amounts) {
    console.log("Populating fields with:", dates, amounts);
    const dateInputs = document.querySelectorAll('#installments_container .date-input');
    const amountInputs = document.querySelectorAll('#installments_container .amount-input');
    const isManual = document.querySelector('input[name="amount_type"]:checked').value === 'manual';

    if (dateInputs.length !== dates.length || amountInputs.length !== amounts.length) {
        console.error("Mismatch between expected fields and provided data for repopulation.");
        return;
    }

    dates.forEach((date, index) => {
        if (dateInputs[index]) {
            dateInputs[index].value = date; // Set the date value
             // Optional: Re-initialize datepicker if needed, though observer should handle it
             // $(dateInputs[index]).pDatepicker('setDate', parseJalaliDate(date)); // Might need parseJalaliDate function
        }
    });

    amounts.forEach((amount, index) => {
        if (amountInputs[index]) {
            const englishValue = convertToEnglishNumbers(amount);
            const numericValue = parseFloat(englishValue) || 0;
            // Set the formatted Persian value for display
            amountInputs[index].value = convertToPersianNumbers(numberWithCommas(numericValue.toFixed(0)));
            amountInputs[index].readOnly = !isManual; // Ensure readonly state is correct
        }
    });

    updateInstallmentSummary(); // Update summary after populating
}


// --- Initial Setup ---
$(document).ready(function() {
    // Trigger fetchDebtAmount if a title is already selected on page load (e.g., after POST error)
    const initialTitle = $('#debt_title').val();
    if (initialTitle) {
        fetchDebtAmount();
    } else {
         // Disable generate button if no title selected initially
         $('#generate_installments_btn').prop('disabled', true);
    }

     // Add event listener to number of installments input to potentially clear fields
     $('#number_of_installments').on('change', function() {
         // Optional: You might want to clear or regenerate fields if the number changes
         // For now, we rely on the user clicking the "Generate" button again
         // clearInstallmentFields();
     });

     // Add validation before submitting the main form
      $('#installmentForm').on('submit', function(e) {
          const summaryDiv = document.getElementById('installments_summary');
          const isManual = document.querySelector('input[name="amount_type"]:checked').value === 'manual';
          const hasInstallments = document.querySelectorAll('#installments_container .installment-group').length > 0;

           if (!hasInstallments) {
               alert('لطفاً ابتدا فیلدهای اقساط را با کلیک روی دکمه "ایجاد فیلدهای قسط" ایجاد کنید.');
               e.preventDefault();
               return false;
           }

           // Check for empty dates
           const dateInputs = document.querySelectorAll('#installments_container .date-input');
           let hasEmptyDate = false;
           dateInputs.forEach(input => {
               if (!input.value.trim()) {
                   hasEmptyDate = true;
               }
           });
           if (hasEmptyDate) {
               alert('لطفاً تاریخ تمام اقساط را مشخص کنید.');
               e.preventDefault();
               return false;
           }


          if (isManual && (summaryDiv.classList.contains('summary-mismatch') || summaryDiv.classList.contains('summary-invalid'))) {
              if (!confirm('مجموع مبالغ اقساط با کل بدهی مطابقت ندارد یا مبلغ نامعتبر وجود دارد. آیا مطمئن به ثبت هستید؟')) {
                  e.preventDefault(); // Prevent form submission
                  return false;
              }
          }
          // Convert amounts to English numbers before submitting
           const amountInputs = document.querySelectorAll('#installments_container .amount-input');
           amountInputs.forEach(input => {
               input.value = convertToEnglishNumbers(input.value);
           });


          return true; // Allow form submission
      });

});

</script>

</body>
</html>
<?php
// Close the database connection if it was opened
if (isset($cnn) && $cnn) {
    $cnn->close();
}
// Include footer if it exists
if (file_exists($basePath . "footer.php")) {
    include $basePath . "footer.php";
}
?>