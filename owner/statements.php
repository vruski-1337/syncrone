<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('owner');

$pageTitle  = 'Financial Statements';
$activePage = 'statements';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

$stmt = $conn->prepare("
    SELECT s.id, s.invoice_number, s.created_at, s.customer_name,
           s.total_amount, s.discount, s.final_amount, s.payment_method,
           u.full_name AS manager_name,
           SUM(si.purchase_price * si.quantity) AS cost_of_goods
    FROM sales s
    LEFT JOIN users u ON u.id = s.manager_id
    LEFT JOIN sale_items si ON si.sale_id = s.id
    WHERE s.company_id = ?
      AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmt->bind_param('iss', $cid, $dateFrom, $dateTo);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalRevenue = array_sum(array_column($sales, 'final_amount'));
$totalCost    = array_sum(array_column($sales, 'cost_of_goods'));
$totalDisc    = array_sum(array_column($sales, 'discount'));
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
                <a href="statements.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="text-muted small">Total Gross Revenue</div>
            <div class="fs-4 fw-bold text-primary"><?= formatCurrency($totalRevenue) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="text-muted small">Total Cost of Goods</div>
            <div class="fs-4 fw-bold text-warning"><?= formatCurrency($totalCost) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="text-muted small">Net Profit</div>
            <div class="fs-4 fw-bold text-success"><?= formatCurrency($totalRevenue - $totalCost) ?></div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-invoice-dollar me-2"></i>Transaction Statements</span>
        <small class="text-muted"><?= count($sales) ?> transactions</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead>
                <tr><th>Invoice #</th><th>Date/Time</th><th>Customer</th><th>Manager</th><th>Payment</th><th>Subtotal</th><th>Discount</th><th>Final</th><th>Cost</th><th>Profit</th></tr>
            </thead>
            <tbody>
            <?php if (empty($sales)): ?>
                <tr><td colspan="10" class="text-center py-4 text-muted">No transactions found.</td></tr>
            <?php else: foreach ($sales as $s):
                $profit = $s['final_amount'] - $s['cost_of_goods'];
            ?>
                <tr>
                    <td><code class="small"><?= sanitize($s['invoice_number']) ?></code></td>
                    <td class="small"><?= formatDateTime($s['created_at']) ?></td>
                    <td><?= sanitize($s['customer_name'] ?? 'Walk-in') ?></td>
                    <td><?= sanitize($s['manager_name'] ?? '—') ?></td>
                    <td><span class="badge bg-secondary small"><?= ucfirst($s['payment_method']) ?></span></td>
                    <td><?= formatCurrency($s['total_amount']) ?></td>
                    <td><?= formatCurrency($s['discount']) ?></td>
                    <td class="fw-semibold"><?= formatCurrency($s['final_amount']) ?></td>
                    <td><?= formatCurrency($s['cost_of_goods']) ?></td>
                    <td class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?> fw-semibold"><?= formatCurrency($profit) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php if (!empty($sales)): ?>
            <tfoot class="table-dark">
                <tr>
                    <td colspan="5"><strong>TOTALS</strong></td>
                    <td></td>
                    <td><strong><?= formatCurrency($totalDisc) ?></strong></td>
                    <td><strong><?= formatCurrency($totalRevenue) ?></strong></td>
                    <td><strong><?= formatCurrency($totalCost) ?></strong></td>
                    <td><strong><?= formatCurrency($totalRevenue - $totalCost) ?></strong></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
