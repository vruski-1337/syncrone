<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$pageTitle  = 'My Credentials';
$activePage = 'profile';
$footer     = getFooterContent($conn);
$errors     = [];

$adminId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $conn->prepare("SELECT id, username, email, full_name, password FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    setFlash('danger', 'Admin account not found.');
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $username       = trim($_POST['username'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $currentPwd     = $_POST['current_password'] ?? '';
        $newPassword    = $_POST['new_password'] ?? '';
        $confirmNewPwd  = $_POST['confirm_new_password'] ?? '';

        if (!$username) $errors[] = 'Username is required.';
        if (!$email) $errors[] = 'Email is required.';
        if (!$currentPwd) $errors[] = 'Current password is required to confirm changes.';

        if ($newPassword && strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }
        if ($newPassword !== $confirmNewPwd) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (!password_verify($currentPwd, $admin['password'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if ($username !== $admin['username']) {
            $chk = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $chk->bind_param('si', $username, $adminId);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'Username is already taken.';
            }
            $chk->close();
        }

        if ($email !== $admin['email']) {
            $chk = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $chk->bind_param('si', $email, $adminId);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'Email is already in use.';
            }
            $chk->close();
        }

        if (empty($errors)) {
            if ($newPassword) {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare('UPDATE users SET username = ?, email = ?, password = ?, updated_at = NOW() WHERE id = ?');
                $stmt->bind_param('sssi', $username, $email, $hash, $adminId);
            } else {
                $stmt = $conn->prepare('UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE id = ?');
                $stmt->bind_param('ssi', $username, $email, $adminId);
            }
            $stmt->execute();
            $stmt->close();

            $_SESSION['username'] = $username;
            setFlash('success', 'Admin credentials updated successfully.');
            header('Location: profile.php');
            exit;
        }
    }
}

$data = $_POST ?: $admin;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include 'layout-top.php'; ?>

<?= renderFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-user-cog me-2"></i>My Credentials</h5>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="card table-card" style="max-width: 720px;">
    <div class="card-body p-4">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" name="username" class="form-control" value="<?= sanitize($data['username'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($data['email'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                    <div class="form-text">Required to confirm account changes.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Confirm New Password</label>
                    <input type="password" name="confirm_new_password" class="form-control" placeholder="Repeat new password">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Update Credentials</button>
                <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
