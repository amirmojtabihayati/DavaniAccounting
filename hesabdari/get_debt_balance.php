<?php
// get_student_balance.php
header('Content-Type: application/json; charset=utf-8'); // تنظیم نوع خروجی به JSON

// مسیردهی مطمئن (مانند قبل)
$basePathAjax = dirname(__DIR__);
$dbPath = $basePathAjax . "/db_connection.php";

if (file_exists($dbPath)) {
    include_once $dbPath;
} else {
    error_log("File not found in AJAX (Balance): " . $dbPath);
    echo json_encode(['error' => 'Server configuration error: DB connection not found.']);
    exit;
}

$response = ['balance' => null, 'error' => null]; // پاسخ پیش فرض

if (isset($_GET['student_id'])) {
    $student_id = filter_var($_GET['student_id'], FILTER_VALIDATE_INT);

    if ($student_id) {
        try {
            $db = new class_db();
            $cnn = $db->connection_database;
            if ($cnn) {
                $cnn->set_charset("utf8mb4");

                // محاسبه مجموع بدهی ها
                $total_debt = 0;
                $sql_debt = "SELECT SUM(amount) as total FROM debts WHERE student_id = ?";
                $stmt_debt = $cnn->prepare($sql_debt);
                if ($stmt_debt) {
                    $stmt_debt->bind_param("i", $student_id);
                    $stmt_debt->execute();
                    $result_debt = $stmt_debt->get_result();
                    if ($row_debt = $result_debt->fetch_assoc()) {
                        // IFNULL یا COALESCE در SQL بهتر است، اما اینجا هم چک می کنیم
                        $total_debt = (float)($row_debt['total'] ?? 0);
                    }
                    $stmt_debt->close();
                } else {
                    $response['error'] = "خطا در محاسبه مجموع بدهی‌ها.";
                    error_log("Prepare failed (get total debt - balance): " . $cnn->error);
                }

                 // محاسبه مجموع پرداخت ها (فقط اگر خطای قبلی نبود)
                 $total_paid = 0;
                 if ($response['error'] === null) {
                     $sql_paid = "SELECT SUM(amount_paid) as total FROM payments WHERE student_id = ?";
                     $stmt_paid = $cnn->prepare($sql_paid);
                     if ($stmt_paid) {
                         $stmt_paid->bind_param("i", $student_id);
                         $stmt_paid->execute();
                         $result_paid = $stmt_paid->get_result();
                         if ($row_paid = $result_paid->fetch_assoc()) {
                             $total_paid = (float)($row_paid['total'] ?? 0);
                         }
                         $stmt_paid->close();
                     } else {
                         $response['error'] = "خطا در محاسبه مجموع پرداخت‌ها.";
                         error_log("Prepare failed (get total paid - balance): " . $cnn->error);
                    }
                 }

                 // محاسبه موجودی نهایی (فقط اگر خطایی نبود)
                if ($response['error'] === null) {
                    $response['balance'] = $total_debt - $total_paid;
                }

                $cnn->close();
            } else {
                 $response['error'] = "خطا در اتصال به پایگاه داده.";
                 error_log("Get student balance AJAX - Connection Failed");
            }
        } catch (Exception $e) {
            $response['error'] = "خطای داخلی سرور در محاسبه موجودی.";
            error_log("Get student balance AJAX - DB Exception: " . $e->getMessage());
        }
    } else {
         $response['error'] = "شناسه دانش آموز نامعتبر است.";
    }
} else {
     $response['error'] = "شناسه دانش آموز ارسال نشده است.";
}

echo json_encode($response);
exit;
?>