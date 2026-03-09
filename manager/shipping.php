<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle = 'Shipping Management';
$activePage = 'shipping';
$cid = (int)($_SESSION['company_id'] ?? 0);
$footer = getFooterContent($conn);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $referenceType = $_POST['reference_type'] ?? 'purchase_order';
        $referenceId = (int)($_POST['reference_id'] ?? 0);
        $carrier = trim((string)($_POST['carrier_name'] ?? ''));
        $tracking = trim((string)($_POST['tracking_number'] ?? ''));
        $status = $_POST['shipping_status'] ?? 'pending';
        $shippedAt = trim((string)($_POST['shipped_at'] ?? '')) ?: null;
        $deliveredAt = trim((string)($_POST['delivered_at'] ?? '')) ?: null;
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!in_array($referenceType, ['purchase_order', 'supplier_return'], true)) {
            $errors[] = 'Invalid reference type.';
        }
        if ($referenceId <= 0) {
            $errors[] = 'Reference ID is required.';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare('INSERT INTO shipping_records (company_id, reference_type, reference_id, carrier_name, tracking_number, shipping_status, shipped_at, delivered_at, notes) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('isissssss', $cid, $referenceType, $referenceId, $carrier, $tracking, $status, $shippedAt, $deliveredAt, $notes);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Shipping record saved.');
            header('Location: shipping.php');
            exit;
        }
    }
}

$list = $conn->prepare('SELECT * FROM shipping_records WHERE company_id = ? ORDER BY created_at DESC');
$list->bind_param('i', $cid);
$list->execute();
$records = $list->get_result()->fetch_all(MYSQLI_ASSOC);
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

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card table-card">
            <div class="card-header">Add Shipping Record</div>
            <div class="card-body">
                <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="mb-2"><label class="form-label small mb-1">Reference Type</label><select name="reference_type" class="form-select"><option value="purchase_order">Purchase Order</option><option value="supplier_return">Supplier Return</option></select></div>
                    <div class="mb-2"><label class="form-label small mb-1">Reference ID</label><input type="number" name="reference_id" class="form-control" min="1" required></div>
                    <div class="mb-2"><input name="carrier_name" class="form-control" placeholder="Carrier name"></div>
                    <div class="mb-2"><input name="tracking_number" class="form-control" placeholder="Tracking number"></div>
                    <div class="mb-2"><select name="shipping_status" class="form-select"><option value="pending">Pending</option><option value="shipped">Shipped</option><option value="delivered">Delivered</option><option value="cancelled">Cancelled</option></select></div>
                    <div class="row g-2 mb-2"><div class="col"><label class="form-label small mb-1">Shipped At</label><input type="datetime-local" name="shipped_at" class="form-control"></div><div class="col"><label class="form-label small mb-1">Delivered At</label><input type="datetime-local" name="delivered_at" class="form-control"></div></div>
                    <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Notes"></textarea>
                    <button class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Save</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header">Shipping History</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Type</th><th>Ref ID</th><th>Carrier</th><th>Tracking</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No shipping records yet.</td></tr>
                    <?php else: foreach ($records as $r): ?>
                        <tr>
                            <td><?= sanitize(ucwords(str_replace('_', ' ', $r['reference_type']))) ?></td>
                            <td><?= (int)$r['reference_id'] ?></td>
                            <td><?= sanitize($r['carrier_name'] ?: '—') ?></td>
                            <td><?= sanitize($r['tracking_number'] ?: '—') ?></td>
                            <td><span class="badge bg-secondary"><?= sanitize(ucfirst($r['shipping_status'])) ?></span></td>
                            <td><?= formatDateTime($r['created_at']) ?></td>
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
