<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('manager');

$pageTitle  = 'New Sale';
$activePage = 'sale-add';
$cid        = (int)$_SESSION['company_id'];
$uid        = (int)$_SESSION['user_id'];
$footer     = getFooterContent($conn);
$errors     = [];

// Load products with prices for JS
$pStmt = $conn->prepare("SELECT id, name, selling_price, purchase_price, stock_quantity FROM products WHERE company_id=? AND stock_quantity > 0 ORDER BY name");
$pStmt->bind_param('i', $cid); $pStmt->execute();
$products = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $customerName  = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $notes         = trim($_POST['notes'] ?? '');
        $discount      = (float)($_POST['discount'] ?? 0);
        $totalAmount   = (float)($_POST['total_amount'] ?? 0);
        $finalAmount   = (float)($_POST['final_amount'] ?? 0);

        $allowedPayments = ['cash', 'card', 'mobile', 'credit'];
        if (!in_array($paymentMethod, $allowedPayments)) $paymentMethod = 'cash';
        if ($discount < 0) $discount = 0;
        if ($finalAmount < 0) $finalAmount = 0;

        // Validate items
        $items = [];
        $productIds  = $_POST['product_id'] ?? [];
        $quantities  = $_POST['quantity'] ?? [];
        $unitPrices  = $_POST['unit_price'] ?? [];

        if (empty($productIds)) {
            $errors[] = 'Please add at least one product.';
        } else {
            foreach ($productIds as $idx => $pid) {
                $pid = (int)$pid;
                $qty = (float)($quantities[$idx] ?? 0);
                $price = (float)($unitPrices[$idx] ?? 0);

                if (!$pid || $qty <= 0) continue;

                // Verify product belongs to company and get purchase price
                $pCheck = $conn->prepare("SELECT id, name, purchase_price, stock_quantity FROM products WHERE id=? AND company_id=?");
                $pCheck->bind_param('ii', $pid, $cid);
                $pCheck->execute();
                $pData = $pCheck->get_result()->fetch_assoc();
                $pCheck->close();

                if (!$pData) {
                    $errors[] = "Invalid product selected.";
                    continue;
                }
                if ($pData['stock_quantity'] < $qty) {
                    $errors[] = "Insufficient stock for '{$pData['name']}'. Available: {$pData['stock_quantity']}";
                    continue;
                }

                $items[] = [
                    'product_id'     => $pid,
                    'product_name'   => $pData['name'],
                    'quantity'       => $qty,
                    'unit_price'     => $price,
                    'purchase_price' => $pData['purchase_price'],
                    'subtotal'       => $qty * $price,
                ];
            }

            if (empty($items)) $errors[] = 'No valid items in sale.';
        }

        if (empty($errors)) {
            $invoiceNumber = generateInvoiceNumber();
            $conn->begin_transaction();
            try {
                // Insert sale
                $stmt = $conn->prepare("INSERT INTO sales (company_id, manager_id, invoice_number, customer_name, customer_phone, total_amount, discount, final_amount, payment_method, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('iisssdddss', $cid, $uid, $invoiceNumber, $customerName, $customerPhone, $totalAmount, $discount, $finalAmount, $paymentMethod, $notes);
                $stmt->execute();
                $saleId = $conn->insert_id;
                $stmt->close();

                // Insert items and update stock
                foreach ($items as $item) {
                    $iStmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, unit_price, purchase_price, subtotal) VALUES (?,?,?,?,?,?,?)");
                    $iStmt->bind_param('iisdddd', $saleId, $item['product_id'], $item['product_name'], $item['quantity'], $item['unit_price'], $item['purchase_price'], $item['subtotal']);
                    $iStmt->execute();
                    $iStmt->close();

                    // Deduct stock
                    $sStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND company_id = ?");
                    $sStmt->bind_param('dii', $item['quantity'], $item['product_id'], $cid);
                    $sStmt->execute();
                    $sStmt->close();
                }

                $conn->commit();
                setFlash('success', 'Sale created successfully! Invoice: ' . $invoiceNumber);
                header('Location: invoice.php?id=' . $saleId);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
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

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-plus-circle me-2"></i>New Sale</h5>
    <a href="sales.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<form method="POST" id="saleForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="total_amount" id="hiddenTotal" value="0">
    <input type="hidden" name="final_amount" id="hiddenFinal" value="0">

    <div class="row g-4">
        <!-- Customer Info -->
        <div class="col-lg-4">
            <div class="card table-card h-100">
                <div class="card-header"><i class="fas fa-user me-2"></i>Customer & Payment</div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Customer Name</label>
                        <input type="text" name="customer_name" class="form-control" placeholder="Walk-in customer" value="<?= sanitize($_POST['customer_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Customer Phone</label>
                        <input type="text" name="customer_phone" class="form-control" value="<?= sanitize($_POST['customer_phone'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash"   <?= ($_POST['payment_method'] ?? 'cash') === 'cash'   ? 'selected' : '' ?>>Cash</option>
                            <option value="card"   <?= ($_POST['payment_method'] ?? '') === 'card'   ? 'selected' : '' ?>>Card</option>
                            <option value="mobile" <?= ($_POST['payment_method'] ?? '') === 'mobile' ? 'selected' : '' ?>>Mobile Payment</option>
                            <option value="credit" <?= ($_POST['payment_method'] ?? '') === 'credit' ? 'selected' : '' ?>>Credit</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sale Items -->
        <div class="col-lg-8">
            <div class="card table-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i>Sale Items</span>
                    <button type="button" class="btn btn-success btn-sm" id="addRowBtn">
                        <i class="fas fa-plus me-1"></i>Add Row
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0" id="saleItemsTable">
                        <thead>
                            <tr>
                                <th style="min-width:200px">Product</th>
                                <th style="width:90px">Qty</th>
                                <th style="width:110px">Unit Price</th>
                                <th style="width:110px">Subtotal</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="saleItemsBody">
                            <tr data-index="0">
                                <td>
                                    <select name="product_id[0]" class="form-select form-select-sm product-select" data-name="product_id">
                                        <option value="">— Select Product —</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>">
                                                <?= sanitize($p['name']) ?> (Stock: <?= $p['stock_quantity'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="quantity[0]" class="form-control form-control-sm qty-input" data-name="quantity" min="0.01" step="0.01" value="1"></td>
                                <td><input type="number" name="unit_price[0]" class="form-control form-control-sm unit-price" data-name="unit_price" min="0" step="0.01" value="0.00"></td>
                                <td><span class="row-subtotal fw-semibold text-primary">$0.00</span>
                                    <input type="hidden" name="subtotal[0]" class="subtotal-input" data-name="subtotal" value="0"></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Totals -->
                <div class="card-footer">
                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Subtotal:</span>
                                <strong id="totalAmount">$0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="text-muted mb-0">Discount ($):</label>
                                <input type="number" name="discount" id="discount" class="form-control form-control-sm text-end" style="width:120px" min="0" step="0.01" value="<?= sanitize($_POST['discount'] ?? '0.00') ?>">
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold fs-5">Final Total:</span>
                                <span class="fw-bold fs-5 text-success" id="finalAmount">$0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary px-5">
                    <i class="fas fa-save me-2"></i>Complete Sale
                </button>
                <a href="sales.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<!-- Row Template (hidden) -->
<template id="rowTemplate">
    <tr>
        <td>
            <select class="form-select form-select-sm product-select" data-name="product_id">
                <option value="">— Select Product —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>">
                        <?= sanitize($p['name']) ?> (Stock: <?= $p['stock_quantity'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" class="form-control form-control-sm qty-input" data-name="quantity" min="0.01" step="0.01" value="1"></td>
        <td><input type="number" class="form-control form-control-sm unit-price" data-name="unit_price" min="0" step="0.01" value="0.00"></td>
        <td><span class="row-subtotal fw-semibold text-primary">$0.00</span>
            <input type="hidden" class="subtotal-input" data-name="subtotal" value="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
    </tr>
</template>

<?php include 'layout-bottom.php'; ?>
