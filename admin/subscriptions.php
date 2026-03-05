<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$pageTitle  = 'Subscription Plans';
$activePage = 'subscriptions';
$footer     = getFooterContent($conn);

$plans = $conn->query("
    SELECT s.*, COUNT(cs.id) AS active_companies
    FROM subscriptions s
    LEFT JOIN company_subscriptions cs ON cs.subscription_id = s.id AND cs.is_active = 1 AND cs.end_date >= CURDATE()
    GROUP BY s.id
    ORDER BY s.price ASC
")->fetch_all(MYSQLI_ASSOC);
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-credit-card me-2"></i>Subscription Plans</h5>
    <a href="subscription-add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Plan</a>
</div>
<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr><th>#</th><th>Plan Name</th><th>Price</th><th>Duration</th><th>Active Companies</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($plans)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No plans found.</td></tr>
            <?php else: foreach ($plans as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= sanitize($p['name']) ?></strong>
                        <br><small class="text-muted"><?= nl2br(sanitize(substr($p['features'] ?? '', 0, 80))) ?></small>
                    </td>
                    <td><?= formatCurrency($p['price']) ?></td>
                    <td><?= (int)$p['duration_days'] ?> days</td>
                    <td><span class="badge bg-info text-dark"><?= $p['active_companies'] ?></span></td>
                    <td><?= $p['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td class="action-btns">
                        <a href="subscription-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <a href="subscription-delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Delete this plan?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'layout-bottom.php'; ?>
