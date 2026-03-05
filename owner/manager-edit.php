<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('owner');

$id  = (int)($_GET['id'] ?? 0);
$cid = (int)$_SESSION['company_id'];
if (!$id) { setFlash('danger', 'Invalid manager.'); header('Location: managers.php'); exit; }

$pageTitle  = 'Edit Manager';
$activePage = 'managers';
$footer     = getFooterContent($conn);
$errors     = [];

$stmt = $conn->prepare("SELECT * FROM users WHERE id=? AND company_id=? AND role='manager'");
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
$manager = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$manager) { setFlash('danger', 'Manager not found.'); header('Location: managers.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $email     = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password  = $_POST['password'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($password && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';

        if (empty($errors)) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare("UPDATE users SET email=?, full_name=?, password=?, is_active=? WHERE id=?");
                $stmt->bind_param('sssii', $email, $full_name, $hash, $is_active, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET email=?, full_name=?, is_active=? WHERE id=?");
                $stmt->bind_param('ssii', $email, $full_name, $is_active, $id);
            }
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Manager updated.');
            header('Location: managers.php');
            exit;
        }
    }
}

$d = $_POST ?: $manager;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include 'layout-top.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2"></i>Edit Manager</h5>
    <a href="managers.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>
<div class="card table-card" style="max-width:600px">
    <div class="card-body p-4">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= sanitize($d['full_name'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($d['email'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control bg-light" value="<?= sanitize($manager['username']) ?>" disabled>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ($d['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Changes</button>
                <a href="managers.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'layout-bottom.php'; ?>
