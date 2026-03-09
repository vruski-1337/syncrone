<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle  = 'Add Product';
$activePage = 'products';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);
$errors     = [];

$categories = $conn->prepare("SELECT id, name FROM categories WHERE company_id=? ORDER BY name");
$categories->bind_param('i', $cid); $categories->execute();
$categories = $categories->get_result()->fetch_all(MYSQLI_ASSOC);

$units = $conn->prepare("SELECT id, name, abbreviation FROM units WHERE company_id=? ORDER BY name");
$units->bind_param('i', $cid); $units->execute();
$units = $units->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $batchNumber  = trim($_POST['batch_number'] ?? '');
        $cat_id   = (int)($_POST['category_id'] ?? 0) ?: null;
        $unit_id  = (int)($_POST['unit_id'] ?? 0) ?: null;
        $pur_price = (float)($_POST['purchase_price'] ?? 0);
        $sel_price = (float)($_POST['selling_price'] ?? 0);
        $stock    = (float)($_POST['stock_quantity'] ?? 0);
        $lowStock = (float)($_POST['low_stock_threshold'] ?? 10);
        $expiry   = trim($_POST['expiry_date'] ?? '');
        $desc     = trim($_POST['description'] ?? '');

        if (!$name) $errors[] = 'Product name is required.';
        if ($sel_price < 0) $errors[] = 'Selling price cannot be negative.';
        if ($lowStock < 0) $errors[] = 'Low stock threshold cannot be negative.';

        if (empty($errors)) {
            $expiryDate = $expiry !== '' ? $expiry : null;
            $stmt = $conn->prepare("INSERT INTO products (company_id, name, manufacturer, batch_number, category_id, unit_id, purchase_price, selling_price, stock_quantity, low_stock_threshold, expiry_date, description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('isssiiddddss', $cid, $name, $manufacturer, $batchNumber, $cat_id, $unit_id, $pur_price, $sel_price, $stock, $lowStock, $expiryDate, $desc);
            $stmt->execute();
            $stmt->close();
            setFlash('success', "Product '{$name}' added successfully.");
            header('Location: products.php');
            exit;
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
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-plus-circle me-2"></i>Add Product</h5>
    <div class="d-flex gap-2">
        <a href="product-bulk-add.php" class="btn btn-success btn-sm"><i class="fas fa-table me-1"></i>Bulk Add</a>
        <a href="products.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>
<div class="card table-card" style="max-width:700px">
    <div class="card-body p-4">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Manufacturer</label>
                    <input type="text" name="manufacturer" class="form-control" value="<?= sanitize($_POST['manufacturer'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Batch Number</label>
                    <input type="text" name="batch_number" class="form-control" value="<?= sanitize($_POST['batch_number'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Select Category —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($_POST['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($categories)): ?><div class="form-text text-warning"><a href="category-add.php">Add a category first</a></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Unit</label>
                    <select name="unit_id" class="form-select">
                        <option value="">— Select Unit —</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($_POST['unit_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= sanitize($u['name']) ?> (<?= sanitize($u['abbreviation']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Purchase Price (INR)</label>
                    <input type="number" name="purchase_price" class="form-control" step="0.01" min="0" value="<?= sanitize($_POST['purchase_price'] ?? '0.00') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Selling Price (INR)</label>
                    <input type="number" name="selling_price" class="form-control" step="0.01" min="0" value="<?= sanitize($_POST['selling_price'] ?? '0.00') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Stock Quantity</label>
                    <input type="number" name="stock_quantity" class="form-control" step="0.01" min="0" value="<?= sanitize($_POST['stock_quantity'] ?? '0') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Low Stock Threshold</label>
                    <input type="number" name="low_stock_threshold" class="form-control" step="0.01" min="0" value="<?= sanitize($_POST['low_stock_threshold'] ?? '10') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control" value="<?= sanitize($_POST['expiry_date'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= sanitize($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Add Product</button>
                <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'layout-bottom.php'; ?>
