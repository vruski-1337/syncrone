<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle  = 'Sales';
$activePage = 'sales';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

$stmt = $conn->prepare("
    SELECT s.*, u.full_name AS manager_name
           , d.name AS doctor_name
           , p.name AS patient_name
    FROM sales s
    LEFT JOIN users u ON u.id = s.manager_id
    LEFT JOIN doctors d ON d.id = s.doctor_id
        LEFT JOIN patients p ON p.id = s.patient_id
    WHERE s.company_id = ? AND DATE(s.created_at) BETWEEN ? AND ?
    ORDER BY s.created_at DESC
");
$stmt->bind_param('iss', $cid, $dateFrom, $dateTo);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-receipt me-2"></i>Sales</h5>
    <a href="sale-add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Sale</a>
</div>
<!-- Date filter -->
<div class="card table-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto"><label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= sanitize($dateFrom) ?>"></div>
            <div class="col-auto"><label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= sanitize($dateTo) ?>"></div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Filter</button>
                <a href="sales.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="card table-card">
    <div class="card-header d-flex justify-content-between">
        <span>Sales from <?= sanitize($dateFrom) ?> to <?= sanitize($dateTo) ?></span>
        <span class="badge bg-primary"><?= count($sales) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr><th>Invoice #</th><th>Date</th><th>Customer</th><th>Patient</th><th>Doctor</th><th>Manager</th><th>Payment</th><th>Total</th><th>Discount</th><th>Final</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($sales)): ?>
                <tr><td colspan="11" class="text-center py-4 text-muted">No sales found for this period.</td></tr>
            <?php else: foreach ($sales as $s): ?>
                <tr>
                    <td><code class="small"><?= sanitize($s['invoice_number']) ?></code></td>
                    <td class="small"><?= formatDateTime($s['created_at']) ?></td>
                    <td><?= sanitize($s['customer_name'] ?? 'Walk-in') ?></td>
                          <td><?= sanitize($s['patient_name'] ?? '—') ?></td>
                    <td><?= sanitize($s['doctor_name'] ?? '—') ?></td>
                    <td><?= sanitize($s['manager_name'] ?? '—') ?></td>
                    <td><span class="badge bg-secondary small"><?= ucfirst($s['payment_method']) ?></span></td>
                    <td><?= formatCurrency($s['total_amount']) ?></td>
                    <td><?= formatCurrency($s['discount']) ?></td>
                    <td><strong><?= formatCurrency($s['final_amount']) ?></strong></td>
                    <td class="action-btns">
                        <a href="sale-view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                        <a href="invoice.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Print" target="_blank"><i class="fas fa-print"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php if (!empty($sales)): ?>
            <tfoot class="table-light">
                <tr>
                    <td colspan="7"><strong>Total</strong></td>
                    <td><strong><?= formatCurrency(array_sum(array_column($sales, 'total_amount'))) ?></strong></td>
                    <td><strong><?= formatCurrency(array_sum(array_column($sales, 'discount'))) ?></strong></td>
                    <td><strong><?= formatCurrency(array_sum(array_column($sales, 'final_amount'))) ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php include 'layout-bottom.php'; ?>
