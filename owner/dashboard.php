<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('owner');

$pageTitle  = 'Owner Dashboard';
$activePage = 'dashboard';
$cid        = (int)$_SESSION['company_id'];

$subStatus = checkSubscription($conn, $cid);

// Stats
$totalManagers  = $conn->prepare("SELECT COUNT(*) FROM users WHERE company_id=? AND role='manager' AND is_active=1");
$totalManagers->bind_param('i', $cid); $totalManagers->execute();
$totalManagers  = $totalManagers->get_result()->fetch_row()[0];

$totalProducts = $conn->prepare("SELECT COUNT(*) FROM products WHERE company_id=?");
$totalProducts->bind_param('i', $cid); $totalProducts->execute();
$totalProducts = $totalProducts->get_result()->fetch_row()[0];

$todaySales = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(final_amount),0) FROM sales WHERE company_id=? AND DATE(created_at)=CURDATE()");
$todaySales->bind_param('i', $cid); $todaySales->execute();
[$salesToday, $revenueToday] = $todaySales->get_result()->fetch_row();
$inventoryAlerts = getInventoryAlerts($conn, $cid, 30);
$lowStockItems   = $inventoryAlerts['low_stock'];
$expiringItems   = $inventoryAlerts['expiring'];

// Recent sales
$stmt = $conn->prepare("
    SELECT s.*, u.full_name AS manager_name
           , d.name AS doctor_name
    FROM sales s LEFT JOIN users u ON u.id = s.manager_id
    LEFT JOIN doctors d ON d.id = s.doctor_id
    WHERE s.company_id = ?
    ORDER BY s.created_at DESC LIMIT 10
");
$stmt->bind_param('i', $cid);
$stmt->execute();
$recentSales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
<?= renderSubscriptionWarning($subStatus) ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card blue">
            <div class="card-body">
                <div class="stat-info"><div class="stat-label">Total Managers</div><div class="stat-value"><?= $totalManagers ?></div></div>
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card green">
            <div class="card-body">
                <div class="stat-info"><div class="stat-label">Total Products</div><div class="stat-value"><?= $totalProducts ?></div></div>
                <div class="stat-icon"><i class="fas fa-pills"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card orange">
            <div class="card-body">
                <div class="stat-info"><div class="stat-label">Sales Today</div><div class="stat-value"><?= $salesToday ?></div></div>
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card purple">
            <div class="card-body">
                <div class="stat-info"><div class="stat-label">Revenue Today</div><div class="stat-value"><?= formatCurrency($revenueToday) ?></div></div>
                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card table-card h-100">
            <div class="card-header"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Alerts</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Product</th><th>Stock</th><th>Threshold</th></tr></thead>
                    <tbody>
                    <?php if (empty($lowStockItems)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No low-stock items.</td></tr>
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
    <div class="col-md-6">
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
                            <td><?= $daysLeft < 0 ? '<span class="badge bg-danger">Expired</span>' : '<span class="badge bg-warning text-dark">' . $daysLeft . ' day(s)</span>' ?></td>
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
        <span><i class="fas fa-receipt me-2"></i>Recent Sales</span>
        <a href="reports.php" class="btn btn-sm btn-outline-primary">View Report</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Invoice #</th><th>Date</th><th>Customer</th><th>Doctor</th><th>Manager</th><th>Total</th><th>Discount</th><th>Final</th><th>Payment</th></tr>
            </thead>
            <tbody>
            <?php if (empty($recentSales)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No sales recorded yet.</td></tr>
            <?php else: foreach ($recentSales as $s): ?>
                <tr>
                    <td><code><?= sanitize($s['invoice_number']) ?></code></td>
                    <td class="small"><?= formatDateTime($s['created_at']) ?></td>
                    <td><?= sanitize($s['customer_name'] ?? 'Walk-in') ?></td>
                    <td><?= sanitize($s['doctor_name'] ?? '—') ?></td>
                    <td><?= sanitize($s['manager_name'] ?? 'N/A') ?></td>
                    <td><?= formatCurrency($s['total_amount']) ?></td>
                    <td><?= formatCurrency($s['discount']) ?></td>
                    <td><strong><?= formatCurrency($s['final_amount']) ?></strong></td>
                    <td><span class="badge bg-secondary"><?= ucfirst($s['payment_method']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
