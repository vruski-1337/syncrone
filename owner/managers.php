<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('owner');

$pageTitle  = 'Store Managers';
$activePage = 'managers';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);

$stmt = $conn->prepare("SELECT id, username, email, full_name, is_active, created_at FROM users WHERE company_id=? AND role='manager' ORDER BY created_at DESC");
$stmt->bind_param('i', $cid);
$stmt->execute();
$managers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
<?= renderFlash() ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-user-tie me-2"></i>Store Managers</h5>
    <a href="manager-add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Manager</a>
</div>
<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr><th>#</th><th>Full Name</th><th>Username</th><th>Email</th><th>Status</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($managers)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No managers found. Add one to get started.</td></tr>
            <?php else: foreach ($managers as $i => $m): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle" style="width:32px;height:32px;font-size:.8rem"><?= strtoupper(substr($m['full_name'] ?? $m['username'], 0, 1)) ?></div>
                            <?= sanitize($m['full_name'] ?? '—') ?>
                        </div>
                    </td>
                    <td><?= sanitize($m['username']) ?></td>
                    <td><?= sanitize($m['email'] ?? '—') ?></td>
                    <td><?= $m['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td class="small text-muted"><?= formatDate($m['created_at']) ?></td>
                    <td class="action-btns">
                        <a href="manager-edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <a href="manager-delete.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Delete this manager?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'layout-bottom.php'; ?>
