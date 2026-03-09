<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$id = (int)($_GET['id'] ?? ($_POST['sale_id'] ?? 0));
$cid = (int)($_SESSION['company_id'] ?? 0);
$uid = (int)($_SESSION['user_id'] ?? 0);

if (!$id) {
    setFlash('danger', 'Invalid sale.');
    header('Location: sales.php');
    exit;
}

$pageTitle = 'Sale Return';
$activePage = 'sales';
$footer = getFooterContent($conn);
$errors = [];

$saleStmt = $conn->prepare('SELECT id, invoice_number, customer_name, created_at FROM sales WHERE id = ? AND company_id = ? LIMIT 1');
$saleStmt->bind_param('ii', $id, $cid);
$saleStmt->execute();
$sale = $saleStmt->get_result()->fetch_assoc();
$saleStmt->close();

if (!$sale) {
    setFlash('danger', 'Sale not found.');
    header('Location: sales.php');
    exit;
}

$itemStmt = $conn->prepare("\n    SELECT si.id, si.product_id, si.product_name, si.quantity, si.unit_price, si.subtotal,\n           COALESCE(SUM(sri.quantity), 0) AS returned_qty\n    FROM sale_items si\n    LEFT JOIN sale_return_items sri ON sri.sale_item_id = si.id\n    WHERE si.sale_id = ?\n    GROUP BY si.id\n    ORDER BY si.id ASC\n");
$itemStmt->bind_param('i', $id);
$itemStmt->execute();
$items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $reason = trim((string)($_POST['reason'] ?? ''));
        $quantities = $_POST['return_qty'] ?? [];
        $returnRows = [];
        $returnTotal = 0.0;

        foreach ($items as $it) {
            $itemId = (int)$it['id'];
            $soldQty = (float)$it['quantity'];
            $returnedQty = (float)$it['returned_qty'];
            $available = max(0.0, $soldQty - $returnedQty);
            $requested = (float)($quantities[$itemId] ?? 0);

            if ($requested <= 0) {
                continue;
            }
            if ($requested > $available) {
                $errors[] = "Return qty exceeds available qty for '{$it['product_name']}'.";
                continue;
            }

            $line = $requested * (float)$it['unit_price'];
            $returnTotal += $line;
            $returnRows[] = [
                'sale_item_id' => $itemId,
                'product_id' => (int)($it['product_id'] ?? 0),
                'product_name' => (string)$it['product_name'],
                'qty' => $requested,
                'unit_price' => (float)$it['unit_price'],
                'subtotal' => $line,
            ];
        }

        if (empty($returnRows) && empty($errors)) {
            $errors[] = 'Enter at least one item quantity to return.';
        }

        if (empty($errors)) {
            $returnNo = 'RET-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $conn->begin_transaction();
            try {
                $rStmt = $conn->prepare('INSERT INTO sale_returns (sale_id, company_id, return_number, reason, returned_by, return_amount) VALUES (?,?,?,?,?,?)');
                $rStmt->bind_param('iissid', $id, $cid, $returnNo, $reason, $uid, $returnTotal);
                $rStmt->execute();
                $saleReturnId = (int)$conn->insert_id;
                $rStmt->close();

                foreach ($returnRows as $row) {
                    $riStmt = $conn->prepare('INSERT INTO sale_return_items (sale_return_id, sale_item_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?,?,?,?,?,?,?)');
                    $riStmt->bind_param('iiisddd', $saleReturnId, $row['sale_item_id'], $row['product_id'], $row['product_name'], $row['qty'], $row['unit_price'], $row['subtotal']);
                    $riStmt->execute();
                    $riStmt->close();

                    if ($row['product_id'] > 0 && $row['qty'] > 0) {
                        $stockStmt = $conn->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND company_id = ?');
                        $stockStmt->bind_param('dii', $row['qty'], $row['product_id'], $cid);
                        $stockStmt->execute();
                        $stockStmt->close();
                    }
                }

                $conn->commit();
                setFlash('success', 'Sale return saved: ' . $returnNo);
                header('Location: sale-view.php?id=' . $id);
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $errors[] = 'Failed to process return: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include 'layout-top.php'; ?>
<?= renderFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-undo-alt me-2"></i>Sale Return: <?= sanitize($sale['invoice_number']) ?></h5>
    <a href="sale-view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<form method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="sale_id" value="<?= $id ?>">

    <div class="card table-card mb-3">
        <div class="card-header"><i class="fas fa-list me-2"></i>Return Items</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Product</th><th>Sold Qty</th><th>Already Returned</th><th>Available</th><th>Unit Price</th><th>Return Qty</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <?php $available = max(0, (float)$it['quantity'] - (float)$it['returned_qty']); ?>
                    <tr>
                        <td><?= sanitize($it['product_name']) ?></td>
                        <td><?= (float)$it['quantity'] ?></td>
                        <td><?= (float)$it['returned_qty'] ?></td>
                        <td><span class="badge bg-info text-dark"><?= $available ?></span></td>
                        <td><?= formatCurrency($it['unit_price']) ?></td>
                        <td>
                            <input type="number" name="return_qty[<?= (int)$it['id'] ?>]" class="form-control form-control-sm" min="0" max="<?= $available ?>" step="0.01" value="0">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">
            <label class="form-label fw-semibold">Reason</label>
            <textarea name="reason" class="form-control" rows="2" placeholder="Reason for return (optional)"><?= sanitize($_POST['reason'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-warning"><i class="fas fa-check me-2"></i>Submit Return</button>
        <a href="sale-view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<?php include 'layout-bottom.php'; ?>
</body>
</html>
