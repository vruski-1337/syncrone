<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('owner');

$pageTitle  = 'Sales Profit Report';
$activePage = 'reports';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

$stmt = $conn->prepare("
    SELECT
        DATE(s.created_at) AS sale_date,
        COUNT(s.id) AS sales_count,
        SUM(s.final_amount) AS total_revenue,
        SUM(si.purchase_price * si.quantity) AS total_cost,
        SUM(s.final_amount) - SUM(si.purchase_price * si.quantity) AS profit
    FROM sales s
    LEFT JOIN sale_items si ON si.sale_id = s.id
    WHERE s.company_id = ?
      AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY DATE(s.created_at)
    ORDER BY sale_date DESC
");
$stmt->bind_param('iss', $cid, $dateFrom, $dateTo);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalRevenue = array_sum(array_column($rows, 'total_revenue'));
$totalCost    = array_sum(array_column($rows, 'total_cost'));
$totalProfit  = array_sum(array_column($rows, 'profit'));
$totalSales   = array_sum(array_column($rows, 'sales_count'));
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

<!-- Date Filter -->
<div class="card table-card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= sanitize($dateFrom) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= sanitize($dateTo) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Filter</button>
                <a href="reports.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card blue"><div class="card-body">
            <div class="stat-info"><div class="stat-label">Total Sales</div><div class="stat-value"><?= $totalSales ?></div></div>
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card teal"><div class="card-body">
            <div class="stat-info"><div class="stat-label">Total Revenue</div><div class="stat-value"><?= formatCurrency($totalRevenue) ?></div></div>
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card orange"><div class="card-body">
            <div class="stat-info"><div class="stat-label">Total Cost</div><div class="stat-value"><?= formatCurrency($totalCost) ?></div></div>
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card green"><div class="card-body">
            <div class="stat-info"><div class="stat-label">Net Profit</div><div class="stat-value"><?= formatCurrency($totalProfit) ?></div></div>
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div></div>
    </div>
</div>

<!-- Report Table -->
<div class="card table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-bar me-2"></i>Daily Breakdown</span>
        <small class="text-muted"><?= sanitize($dateFrom) ?> to <?= sanitize($dateTo) ?></small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr><th>Date</th><th>Sales Count</th><th>Total Revenue</th><th>Total Cost</th><th>Profit</th><th>Margin %</th></tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No data for selected period.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <?php $margin = $r['total_revenue'] > 0 ? round(($r['profit'] / $r['total_revenue']) * 100, 1) : 0; ?>
                <tr>
                    <td><?= formatDate($r['sale_date']) ?></td>
                    <td><?= $r['sales_count'] ?></td>
                    <td><?= formatCurrency($r['total_revenue']) ?></td>
                    <td><?= formatCurrency($r['total_cost']) ?></td>
                    <td class="<?= $r['profit'] >= 0 ? 'text-success' : 'text-danger' ?> fw-semibold"><?= formatCurrency($r['profit']) ?></td>
                    <td><?= $margin ?>%</td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
            <tfoot class="table-dark">
                <tr>
                    <td><strong>TOTAL</strong></td>
                    <td><strong><?= $totalSales ?></strong></td>
                    <td><strong><?= formatCurrency($totalRevenue) ?></strong></td>
                    <td><strong><?= formatCurrency($totalCost) ?></strong></td>
                    <td><strong><?= formatCurrency($totalProfit) ?></strong></td>
                    <td><strong><?= $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0 ?>%</strong></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
