<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':   header('Location: admin/dashboard.php'); exit;
        case 'owner':   header('Location: owner/dashboard.php'); exit;
        case 'manager': header('Location: manager/dashboard.php'); exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $user = login($conn, $username, $password);
        if ($user) {
            switch ($user['role']) {
                case 'admin':   header('Location: admin/dashboard.php'); exit;
                case 'owner':   header('Location: owner/dashboard.php'); exit;
                case 'manager': header('Location: manager/dashboard.php'); exit;
            }
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card card">
        <div class="card-header">
            <div class="login-logo">
                <i class="fas fa-pills"></i>
            </div>
            <h4 class="mb-1 fw-bold"><?= SITE_NAME ?></h4>
            <p class="mb-0 opacity-75 small">Pharmacy Management System</p>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger py-2">
                    <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="index.php" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-user text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" id="username"
                               name="username" placeholder="Enter username"
                               value="<?= sanitize($_POST['username'] ?? '') ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-lock text-muted"></i>
                        </span>
                        <input type="password" class="form-control border-start-0 ps-0" id="password"
                               name="password" placeholder="Enter password" required>
                        <button type="button" class="btn btn-light border"
                                onclick="togglePwd(this)" title="Show/Hide password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </div>
            </form>
        </div>
        <div class="card-footer text-center text-muted small py-3">
            <?= getFooterContent($conn) ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd(btn) {
    const input = btn.closest('.input-group').querySelector('input[type="password"], input[type="text"]');
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
