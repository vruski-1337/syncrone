<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle  = 'Categories';
$activePage = 'categories';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);
$errors     = [];

// Handle inline add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!$name) { $errors[] = 'Category name is required.'; }
        else {
            $stmt = $conn->prepare("INSERT INTO categories (company_id, name, description) VALUES (?,?,?)");
            $stmt->bind_param('iss', $cid, $name, $desc);
            $stmt->execute(); $stmt->close();
            setFlash('success', 'Category added.');
            header('Location: categories.php'); exit;
        }
    }
}

$cats = $conn->prepare("SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id WHERE c.company_id=? GROUP BY c.id ORDER BY c.name");
$cats->bind_param('i', $cid); $cats->execute();
$categories = $cats->get_result()->fetch_all(MYSQLI_ASSOC);
$cats->close();
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head><body>
<?php include 'layout-top.php'; ?>
<?= renderFlash() ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card table-card">
            <div class="card-header"><i class="fas fa-plus me-2"></i>Add Category</div>
            <div class="card-body p-3">
                <?php if ($errors): ?><div class="alert alert-danger small py-2"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div><?php endif; ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-2"><label class="form-label fw-semibold small">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-3"><label class="form-label fw-semibold small">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2"></textarea></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-plus me-1"></i>Add Category</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header"><i class="fas fa-tags me-2"></i>All Categories (<?= count($categories) ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Name</th><th>Description</th><th>Products</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No categories yet.</td></tr>
                    <?php else: foreach ($categories as $i => $c): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= sanitize($c['name']) ?></strong></td>
                            <td class="small text-muted"><?= sanitize($c['description'] ?? '—') ?></td>
                            <td><span class="badge bg-info text-dark"><?= $c['product_count'] ?></span></td>
                            <td class="action-btns">
                                <a href="category-edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                <a href="category-delete.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Delete this category? Products will be uncategorized.')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'layout-bottom.php'; ?>
