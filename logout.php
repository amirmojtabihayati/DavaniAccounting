<?php
session_start();

// Unset all of the session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();
?>

<!DOCTYPE html>
<html lang="en" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خروج از سیستم</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        @font-face {
            font-family: Yekan;
            src: url(assets/font/Yekan.woff);
        }
        body {
            font-family: Yekan;
            background-color: #f8f9fa;
        }

        .card {
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card-header {
            padding: 1.5rem;            border-bottom: 3px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h3 class="mb-0">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        خروج از سیستم
                    </h3>
                </div>
                <div class="card-body">
                    <p class="lead">شما با موفقیت از سیستم خارج شدید.</p>
                    <div class="d-grid gap-2">
                        <a href="login.php" class="btn btn-success">
                            <i class="bi bi-box-arrow-in-right me-1"></i>
                            ورود به سیستم
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- Bootstrap JS (Optional, if you need any Bootstrap JS features) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>