<?php
// get_debt_for_taghsit.php
header('Content-Type: text/plain; charset=utf-8'); // Output plain text (the amount)

// --- Error Reporting (Disable in production) ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in AJAX response
ini_set('log_errors', 1); // Log errors instead

// --- Includes ---
$basePath = __DIR__ . "/../"; // Adjust if this file is in a different location
include_once $basePath . "db_connection.php";

// --- Input Validation ---
$title = $_POST['title'] ?? null;
$national_code = $_POST['national_code'] ?? null;

if (empty($title) || empty($national_code)) {
    http_response_code(400); // Bad Request
    echo "0"; // Return 0 or an error indicator
    exit;
}

// --- Database Connection ---
try {
    $db = new class_db();
    $cnn = $db->connection_database;
    if ($cnn) {
        $cnn->set_charset("utf8mb4");
    } else {
        throw new Exception("DB Connection failed");
    }
} catch (Exception $e) {
    error_log("AJAX get_debt_amount DB Error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo "0";
    exit;
}

// --- Fetch Debt Amount ---
$debt_amount = 0;
// Query to get the specific debt amount for the title and student (identified by national code)
// IMPORTANT: Assumes only ONE active debt record exists for a given title and student.
// If multiple records can exist for the same title, you might need SUM() or specific logic.
$sql = "SELECT d.amount
        FROM debts d
        JOIN students s ON d.student_id = s.id
        WHERE s.national_code = ? AND d.title = ? AND d.amount > 0
        LIMIT 1"; // Limit 1 just in case, adjust logic if needed

$stmt = $cnn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ss", $national_code, $title);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $debt_amount = (float)$row['amount'];
    }
    $stmt->close();
} else {
    error_log("AJAX get_debt_amount Prepare Failed: " . $cnn->error);
    http_response_code(500);
    echo "0"; // Indicate error
    $cnn->close();
    exit;
}

$cnn->close();

// --- Output the raw amount ---
// Use number_format to prevent scientific notation for very large/small numbers, ensure no thousands separator
echo number_format($debt_amount, 2, '.', ''); // Output with 2 decimal places, no thousands separator

?>