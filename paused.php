<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'] ?? '';
if ($role === 'admin') {
    header('Location: admin/dashboard.php');
    exit;
}

$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare('SELECT name, email, phone, usage_paused, pause_message FROM companies WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $companyId);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$company || (int)($company['usage_paused'] ?? 0) !== 1) {
    if ($role === 'owner') {
        header('Location: owner/dashboard.php');
    } else {
        header('Location: manager/dashboard.php');
    }
    exit;
}

$message = trim((string)($company['pause_message'] ?? ''));
if ($message === '') {
    $message = 'Your company account is temporarily paused. Please contact the administrator to renew your subscription and reactivate access.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Paused - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 text-center">
                    <div class="mb-3 text-warning"><i class="fas fa-pause-circle fa-3x"></i></div>
                    <h4 class="fw-bold mb-2">Account Temporarily Paused</h4>
                    <p class="text-muted mb-3"><strong><?= sanitize($company['name'] ?? 'Company') ?></strong></p>
                    <div class="alert alert-warning text-start mb-3"><?= nl2br(sanitize($message)) ?></div>
                    <p class="small text-muted mb-1">Contact for renewal:</p>
                    <p class="mb-4">
                        <?php if (!empty($company['phone'])): ?>
                            <span class="me-3"><i class="fas fa-phone me-1"></i><?= sanitize($company['phone']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($company['email'])): ?>
                            <span><i class="fas fa-envelope me-1"></i><?= sanitize($company['email']) ?></span>
                        <?php endif; ?>
                    </p>
                    <a href="logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
