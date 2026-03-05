<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('danger', 'Invalid company.'); header('Location: companies.php'); exit; }

$pageTitle  = 'Reset Company Credentials';
$activePage = 'companies';
$footer     = getFooterContent($conn);
$errors     = [];

$stmt = $conn->prepare("SELECT c.name, u.id AS owner_id, u.username FROM companies c LEFT JOIN users u ON u.id = c.owner_id WHERE c.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$company) { setFlash('danger', 'Company not found.'); header('Location: companies.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';

        if (!$newUsername && !$newPassword) {
            $errors[] = 'Provide a new username or password to reset.';
        }
        if ($newPassword && strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }

        if ($newUsername && $newUsername !== $company['username']) {
            $chk = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->bind_param('si', $newUsername, $company['owner_id']);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) $errors[] = 'Username already taken.';
            $chk->close();
        }

        if (empty($errors)) {
            $setParts = [];
            $params   = [];
            $types    = '';

            if ($newUsername) {
                $setParts[] = 'username = ?';
                $params[]   = $newUsername;
                $types     .= 's';
            }
            if ($newPassword) {
                $setParts[] = 'password = ?';
                $params[]   = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $types     .= 's';
            }

            $params[] = $company['owner_id'];
            $types   .= 'i';

            $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();

            setFlash('success', 'Credentials reset successfully.');
            header('Location: companies.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include 'layout-top.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-key me-2"></i>Reset Credentials: <?= sanitize($company['name']) ?></h5>
    <a href="companies.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="card table-card" style="max-width:520px">
    <div class="card-body p-4">
        <div class="alert alert-info small mb-3">
            <i class="fas fa-info-circle me-2"></i>
            Current owner username: <strong><?= sanitize($company['username'] ?? 'N/A') ?></strong>
        </div>
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">New Username</label>
                <input type="text" name="new_username" class="form-control" value="<?= sanitize($company['username'] ?? '') ?>" placeholder="Leave unchanged to keep current">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                <div class="form-text">Minimum 6 characters.</div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning px-4"><i class="fas fa-save me-2"></i>Reset</button>
                <a href="companies.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
