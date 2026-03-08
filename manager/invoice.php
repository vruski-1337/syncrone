<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$id  = (int)($_GET['id'] ?? 0);
$cid = (int)$_SESSION['company_id'];
if (!$id) { header('Location: sales.php'); exit; }

$stmt = $conn->prepare("
    SELECT s.*, u.full_name AS manager_name
              , d.name AS doctor_name
    FROM sales s LEFT JOIN users u ON u.id = s.manager_id
     LEFT JOIN doctors d ON d.id = s.doctor_id
    WHERE s.id = ? AND s.company_id = ?
");
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) { header('Location: sales.php'); exit; }

$iStmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
$iStmt->bind_param('i', $id);
$iStmt->execute();
$items = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$iStmt->close();

$company = getCompanyData($conn, $cid);
$footer  = getFooterContent($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= sanitize($sale['invoice_number']) ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .invoice-wrap { width: 210mm; margin: 20px auto; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,.15); }
        .invoice-half { height: 148.5mm; padding: 8mm 12mm 6mm; box-sizing: border-box; overflow: hidden; }
        .invoice-company { font-size: .85rem; }
        .invoice-company h5 { font-size: 1rem; margin-bottom: 2px; }
        .inv-table { width: 100%; border-collapse: collapse; font-size: .75rem; margin-top: 4px; }
        .inv-table th, .inv-table td { border: 1px solid #ddd; padding: 3px 5px; }
        .inv-table th { background: #f4f6f9; font-weight: 600; }
        .inv-totals { margin-top: 6px; font-size: .8rem; }
        .inv-totals table { margin-left: auto; }
        .inv-totals td { padding: 1px 4px; }
        .copy-badge { display: inline-block; padding: 2px 12px; border-radius: 20px; font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; margin-bottom: 4px; }
        .copy-badge.customer { background: #2c7be5; color: #fff; }
        .copy-badge.store     { background: #27ae60; color: #fff; }
        .dashed-sep { border-top: 2px dashed #aaa; position: relative; text-align: center; margin: 0; }
        .dashed-sep::before { content: '✂ CUT HERE ✂'; position: absolute; top: -9px; left: 50%; transform: translateX(-50%); background: #fff; padding: 0 8px; font-size: .65rem; color: #999; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; margin: 0; }
            .invoice-wrap { box-shadow: none; margin: 0; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body style="background:#f4f6f9">

<!-- Print Controls -->
<div class="no-print container py-3">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold"><i class="fas fa-receipt me-2"></i>Invoice Preview</h6>
            <small class="text-muted"><?= sanitize($sale['invoice_number']) ?></small>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fas fa-print me-2"></i>Print Invoice
            </button>
            <a href="sale-view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>
    <hr>
</div>

<!-- A4 Invoice -->
<div class="invoice-wrap">

    <?php
    // Render a copy half: $copyType = 'customer' or 'store'
    function renderInvoiceCopy($copyType, $sale, $items, $company) {
        $label = $copyType === 'customer' ? 'CUSTOMER COPY' : 'STORE COPY';
        ?>
    <div class="invoice-half">
        <div class="d-flex justify-content-between align-items-start">
            <div class="invoice-company">
                <h5><?= htmlspecialchars($company['name'] ?? 'Pharma Care', ENT_QUOTES, 'UTF-8') ?></h5>
                <?php if ($company['address']): ?>
                    <div><?= htmlspecialchars($company['address'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($company['phone']): ?>
                    <div>Tel: <?= htmlspecialchars($company['phone'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($company['email']): ?>
                    <div><?= htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <span class="copy-badge <?= $copyType ?>"><?= $label ?></span>
                <div style="font-size:.75rem; margin-top:4px">
                    <strong>Invoice:</strong> <?= htmlspecialchars($sale['invoice_number'], ENT_QUOTES, 'UTF-8') ?><br>
                    <strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?>
                </div>
            </div>
        </div>

        <div style="font-size:.75rem; margin-top:5px; padding:3px 6px; background:#f8f9fb; border-radius:4px">
            <strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in', ENT_QUOTES, 'UTF-8') ?>
            <?php if ($sale['customer_phone']): ?>
                &nbsp;|&nbsp; <strong>Phone:</strong> <?= htmlspecialchars($sale['customer_phone'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
                <?php if (!empty($sale['doctor_name'])): ?>
                    &nbsp;|&nbsp; <strong>Doctor:</strong> <?= htmlspecialchars($sale['doctor_name'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            &nbsp;|&nbsp; <strong>Payment:</strong> <?= ucfirst($sale['payment_method']) ?>
        </div>

        <table class="inv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th style="width:50px">Qty</th>
                    <th style="width:75px">Unit Price</th>
                    <th style="width:75px">Subtotal</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($item['product_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="text-align:right"><?= $item['quantity'] ?></td>
                        <td style="text-align:right"><?= formatCurrency($item['unit_price']) ?></td>
                        <td style="text-align:right"><?= formatCurrency($item['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="inv-totals">
            <table>
                    <tr><td class="text-muted">Subtotal:</td><td class="text-end"><?= formatCurrency($sale['total_amount']) ?></td></tr>
                <?php if ($sale['discount'] > 0): ?>
                    <tr><td class="text-muted">Discount:</td><td class="text-end text-danger">- <?= formatCurrency($sale['discount']) ?></td></tr>
                <?php endif; ?>
                <tr style="border-top:2px solid #333">
                    <td><strong>TOTAL:</strong></td>
                        <td class="text-end"><strong><?= formatCurrency($sale['final_amount']) ?></strong></td>
                </tr>
            </table>
        </div>

        <?php if ($sale['notes']): ?>
            <div style="font-size:.7rem; margin-top:6px; color:#555">
                <strong>Notes:</strong> <?= htmlspecialchars($sale['notes'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div style="font-size:.7rem; margin-top:8px; text-align:center; color:#888; border-top:1px solid #eee; padding-top:4px">
            Thank you for your purchase! – <?= htmlspecialchars($company['name'] ?? 'Pharma Care', ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
    <?php } ?>

    <?php renderInvoiceCopy('customer', $sale, $items, $company); ?>

    <!-- Cut Line -->
    <div class="dashed-sep"></div>

    <?php renderInvoiceCopy('store', $sale, $items, $company); ?>

</div><!-- /.invoice-wrap -->

<div class="no-print text-center py-3 text-muted small">
    <?= $footer ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
