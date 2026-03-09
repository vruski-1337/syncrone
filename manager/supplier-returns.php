<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle = 'Supplier Returns';
$activePage = 'supplier-returns';
$cid = (int)($_SESSION['company_id'] ?? 0);
$uid = (int)($_SESSION['user_id'] ?? 0);
$footer = getFooterContent($conn);
$errors = [];

$vStmt = $conn->prepare('SELECT id, name FROM vendors WHERE company_id = ? AND is_active = 1 ORDER BY name');
$vStmt->bind_param('i', $cid);
$vStmt->execute();
$vendors = $vStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$vStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $vendorId = (int)($_POST['vendor_id'] ?? 0);
        $returnDate = $_POST['return_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'pending';
        $notes = trim((string)($_POST['notes'] ?? ''));
        $productNames = $_POST['product_name'] ?? [];
        $qtys = $_POST['quantity'] ?? [];
        $costs = $_POST['unit_cost'] ?? [];

        if ($vendorId <= 0) {
            $errors[] = 'Vendor is required.';
        }

        $items = [];
        $total = 0.0;
        foreach ($productNames as $i => $pn) {
            $name = trim((string)$pn);
            $qty = (float)($qtys[$i] ?? 0);
            $cost = (float)($costs[$i] ?? 0);
            if ($name === '' || $qty <= 0) {
                continue;
            }
            $sub = $qty * $cost;
            $items[] = ['name' => $name, 'qty' => $qty, 'cost' => $cost, 'sub' => $sub];
            $total += $sub;
        }

        if (empty($items) && empty($errors)) {
            $errors[] = 'Add at least one return line.';
        }

        if (empty($errors)) {
            $returnNo = 'SRET-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $conn->begin_transaction();
            try {
                $srStmt = $conn->prepare('INSERT INTO supplier_returns (company_id, vendor_id, return_number, return_date, status, total_amount, notes, created_by) VALUES (?,?,?,?,?,?,?,?)');
                $srStmt->bind_param('iisssdsi', $cid, $vendorId, $returnNo, $returnDate, $status, $total, $notes, $uid);
                $srStmt->execute();
                $returnId = (int)$conn->insert_id;
                $srStmt->close();

                foreach ($items as $it) {
                    $itemStmt = $conn->prepare('INSERT INTO supplier_return_items (supplier_return_id, product_name, quantity, unit_cost, subtotal) VALUES (?,?,?,?,?)');
                    $itemStmt->bind_param('isddd', $returnId, $it['name'], $it['qty'], $it['cost'], $it['sub']);
                    $itemStmt->execute();
                    $itemStmt->close();
                }

                $conn->commit();
                setFlash('success', 'Supplier return logged: ' . $returnNo);
                header('Location: supplier-returns.php');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $errors[] = 'Failed to save supplier return: ' . $e->getMessage();
            }
        }
    }
}

$list = $conn->prepare('SELECT sr.*, v.name AS vendor_name FROM supplier_returns sr JOIN vendors v ON v.id = sr.vendor_id WHERE sr.company_id = ? ORDER BY sr.created_at DESC');
$list->bind_param('i', $cid);
$list->execute();
$returns = $list->get_result()->fetch_all(MYSQLI_ASSOC);
$list->close();
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-undo-alt me-2"></i>Supplier Returns</h5>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card table-card">
            <div class="card-header">Create Supplier Return</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="mb-2"><label class="form-label small mb-1">Vendor</label><select name="vendor_id" class="form-select" required><option value="">Select vendor</option><?php foreach ($vendors as $v): ?><option value="<?= (int)$v['id'] ?>"><?= sanitize($v['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small mb-1">Date</label><input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                        <div class="col-6"><label class="form-label small mb-1">Status</label><select name="status" class="form-select"><option value="pending">Pending</option><option value="sent">Sent</option><option value="completed">Completed</option></select></div>
                    </div>
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="border rounded p-2 mb-2">
                        <input name="product_name[]" class="form-control form-control-sm mb-1" placeholder="Product name">
                        <div class="row g-1"><div class="col"><input name="quantity[]" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="Qty"></div><div class="col"><input name="unit_cost[]" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="Unit Cost"></div></div>
                    </div>
                    <?php endfor; ?>
                    <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Notes"></textarea>
                    <button class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Log Return</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header">Return History</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Return #</th><th>Date</th><th>Vendor</th><th>Status</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No supplier returns yet.</td></tr>
                    <?php else: foreach ($returns as $r): ?>
                        <tr>
                            <td><code><?= sanitize($r['return_number']) ?></code></td>
                            <td><?= formatDate($r['return_date']) ?></td>
                            <td><?= sanitize($r['vendor_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= sanitize(ucfirst($r['status'])) ?></span></td>
                            <td><?= formatCurrency($r['total_amount']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
</body>
</html>
