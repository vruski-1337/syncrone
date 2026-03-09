<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle  = 'Manager Dashboard';
$activePage = 'dashboard';
$cid        = (int)$_SESSION['company_id'];
$role       = $_SESSION['role'] ?? 'manager';

// Stats
$totalProducts  = $conn->prepare("SELECT COUNT(*) FROM products WHERE company_id=?");
$totalProducts->bind_param('i', $cid); $totalProducts->execute();
$totalProducts  = $totalProducts->get_result()->fetch_row()[0];

$totalCategories = $conn->prepare("SELECT COUNT(*) FROM categories WHERE company_id=?");
$totalCategories->bind_param('i', $cid); $totalCategories->execute();
$totalCategories = $totalCategories->get_result()->fetch_row()[0];

$uid = (int)$_SESSION['user_id'];
if ($role === 'owner') {
    $todaySales = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(final_amount),0) FROM sales WHERE company_id=? AND DATE(created_at)=CURDATE()");
    $todaySales->bind_param('i', $cid);
} else {
    $todaySales = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(final_amount),0) FROM sales WHERE manager_id=? AND DATE(created_at)=CURDATE()");
    $todaySales->bind_param('i', $uid);
}
$todaySales->execute();
[$salesToday, $revenueToday] = $todaySales->get_result()->fetch_row();

// Recent sales by manager or entire company for owner view
if ($role === 'owner') {
    $stmt = $conn->prepare("SELECT * FROM sales WHERE company_id=? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param('i', $cid);
} else {
    $stmt = $conn->prepare("SELECT * FROM sales WHERE manager_id=? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$recentSales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$inventoryAlerts = getInventoryAlerts($conn, $cid, 30);
$lowStockItems   = $inventoryAlerts['low_stock'];
$expiringItems   = $inventoryAlerts['expiring'];

$footer = getFooterContent($conn);
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

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card blue"><div class="card-body">
            <div class="stat-info"><div class="stat-label">Total Products</div><div class="stat-value"><?= $totalProducts ?></div></div>
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card teal"><div class="card-body">
            <div class="stat-info"><div class="stat-label">Categories</div><div class="stat-value"><?= $totalCategories ?></div></div>
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card orange"><div class="card-body">
            <div class="stat-info"><div class="stat-label">My Sales Today</div><div class="stat-value"><?= $salesToday ?></div></div>
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card green"><div class="card-body">
            <div class="stat-info"><div class="stat-label">Revenue Today</div><div class="stat-value"><?= formatCurrency($revenueToday) ?></div></div>
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card table-card h-100">
            <div class="card-header"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Alerts</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Product</th><th>Stock</th><th>Threshold</th></tr></thead>
                    <tbody>
                    <?php if (empty($lowStockItems)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No low-stock products.</td></tr>
                    <?php else: foreach ($lowStockItems as $item): ?>
                        <tr>
                            <td><?= sanitize($item['name']) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= $item['stock_quantity'] ?></span></td>
                            <td><?= $item['low_stock_threshold'] ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card table-card h-100">
            <div class="card-header"><i class="fas fa-calendar-times me-2 text-danger"></i>Expiry Alerts (30 Days)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Product</th><th>Expiry Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (empty($expiringItems)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No products near expiry.</td></tr>
                    <?php else: foreach ($expiringItems as $item): ?>
                        <?php $daysLeft = (int)floor((strtotime($item['expiry_date']) - time()) / 86400); ?>
                        <tr>
                            <td><?= sanitize($item['name']) ?></td>
                            <td><?= formatDate($item['expiry_date']) ?></td>
                            <td>
                                <?php if ($daysLeft < 0): ?>
                                    <span class="badge bg-danger">Expired</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><?= $daysLeft ?> day(s)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-receipt me-2"></i>My Recent Sales</span>
        <a href="sale-add.php" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>New Sale</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Invoice #</th><th>Date</th><th>Customer</th><th>Total</th><th>Final</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($recentSales)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No sales yet. <a href="sale-add.php">Create your first sale</a>.</td></tr>
            <?php else: foreach ($recentSales as $s): ?>
                <tr>
                    <td><code><?= sanitize($s['invoice_number']) ?></code></td>
                    <td class="small"><?= formatDateTime($s['created_at']) ?></td>
                    <td><?= sanitize($s['customer_name'] ?? 'Walk-in') ?></td>
                    <td><?= formatCurrency($s['total_amount']) ?></td>
                    <td><strong><?= formatCurrency($s['final_amount']) ?></strong></td>
                    <td class="action-btns">
                        <a href="sale-view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                        <a href="invoice.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-print"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
