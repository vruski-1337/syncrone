<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$id  = (int)($_GET['id'] ?? 0);
$cid = (int)$_SESSION['company_id'];
if (!$id) { setFlash('danger', 'Invalid sale.'); header('Location: sales.php'); exit; }

$pageTitle  = 'Sale Details';
$activePage = 'sales';
$footer     = getFooterContent($conn);

$stmt = $conn->prepare("
    SELECT s.*, u.full_name AS manager_name, u.username AS manager_username
              , d.name AS doctor_name
                 , p.name AS patient_name
    FROM sales s
    LEFT JOIN users u ON u.id = s.manager_id
     LEFT JOIN doctors d ON d.id = s.doctor_id
     LEFT JOIN patients p ON p.id = s.patient_id
    WHERE s.id = ? AND s.company_id = ?
");
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) { setFlash('danger', 'Sale not found.'); header('Location: sales.php'); exit; }

$iStmt = $conn->prepare("
    SELECT si.*, p.name AS product_name_current
    FROM sale_items si
    LEFT JOIN products p ON p.id = si.product_id
    WHERE si.sale_id = ?
");
$iStmt->bind_param('i', $id);
$iStmt->execute();
$items = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$iStmt->close();
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-receipt me-2"></i>Sale: <?= sanitize($sale['invoice_number']) ?></h5>
    <div class="d-flex gap-2">
        <a href="invoice.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="fas fa-print me-1"></i>Print Invoice</a>
        <a href="sales.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card table-card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Sale Info</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">Invoice #</td><td><code><?= sanitize($sale['invoice_number']) ?></code></td></tr>
                    <tr><td class="text-muted">Date</td><td><?= formatDateTime($sale['created_at']) ?></td></tr>
                    <tr><td class="text-muted">Customer</td><td><?= sanitize($sale['customer_name'] ?? 'Walk-in') ?></td></tr>
                       <tr><td class="text-muted">Patient</td><td><?= sanitize($sale['patient_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Phone</td><td><?= sanitize($sale['customer_phone'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Manager</td><td><?= sanitize($sale['manager_name'] ?? '—') ?></td></tr>
                        <tr><td class="text-muted">Doctor</td><td><?= sanitize($sale['doctor_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Payment</td><td><span class="badge bg-secondary"><?= ucfirst($sale['payment_method']) ?></span></td></tr>
                    <tr><td class="text-muted">Notes</td><td><?= sanitize($sale['notes'] ?? '—') ?></td></tr>
                    <tr class="table-light"><td>Subtotal</td><td><?= formatCurrency($sale['total_amount']) ?></td></tr>
                    <tr class="table-warning"><td>Discount</td><td>– <?= formatCurrency($sale['discount']) ?></td></tr>
                    <tr class="table-success"><td><strong>Final</strong></td><td><strong><?= formatCurrency($sale['final_amount']) ?></strong></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card table-card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Items (<?= count($items) ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= sanitize($item['product_name'] ?? $item['product_name_current'] ?? 'Deleted') ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= formatCurrency($item['unit_price']) ?></td>
                            <td><?= formatCurrency($item['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end"><strong>Final Total:</strong></td>
                            <td><strong class="text-success"><?= formatCurrency($sale['final_amount']) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'layout-bottom.php'; ?>
