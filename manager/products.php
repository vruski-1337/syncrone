<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('manager');

$pageTitle  = 'Products';
$activePage = 'products';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);

$search = trim($_GET['q'] ?? '');
$params = [$cid];
$types  = 'i';
$where  = '';

if ($search) {
    $where   = " AND p.name LIKE ?";
    $params[] = '%' . $search . '%';
    $types   .= 's';
}

$stmt = $conn->prepare("
    SELECT p.*, c.name AS category_name, u.name AS unit_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN units u ON u.id = p.unit_id
    WHERE p.company_id = ? $where
    ORDER BY p.name ASC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-pills me-2"></i>Products</h5>
    <a href="product-add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Product</a>
</div>
<div class="card table-card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search products..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
                <?php if ($search): ?><a href="products.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr><th>#</th><th>Product Name</th><th>Category</th><th>Unit</th><th>Purchase Price</th><th>Selling Price</th><th>Stock</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">
                    <i class="fas fa-pills fa-2x mb-2 d-block opacity-25"></i>No products found.
                </td></tr>
            <?php else: foreach ($products as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong><?= sanitize($p['name']) ?></strong>
                        <?php if ($p['description']): ?><br><small class="text-muted"><?= sanitize(substr($p['description'], 0, 60)) ?></small><?php endif; ?>
                    </td>
                    <td><?= sanitize($p['category_name'] ?? '—') ?></td>
                    <td><?= sanitize($p['unit_name'] ?? '—') ?></td>
                    <td><?= formatCurrency($p['purchase_price']) ?></td>
                    <td><?= formatCurrency($p['selling_price']) ?></td>
                    <td>
                        <?php if ($p['stock_quantity'] <= 0): ?>
                            <span class="badge bg-danger"><?= $p['stock_quantity'] ?></span>
                        <?php elseif ($p['stock_quantity'] <= 10): ?>
                            <span class="badge bg-warning text-dark"><?= $p['stock_quantity'] ?></span>
                        <?php else: ?>
                            <span class="badge bg-success"><?= $p['stock_quantity'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="action-btns">
                        <a href="product-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <a href="product-delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted small">Total: <?= count($products) ?> products</div>
</div>
<?php include 'layout-bottom.php'; ?>
