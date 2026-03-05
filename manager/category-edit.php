<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('manager');
$id = (int)($_GET['id'] ?? 0); $cid = (int)$_SESSION['company_id'];
if (!$id) { setFlash('danger','Invalid.'); header('Location: categories.php'); exit; }
$pageTitle = 'Edit Category'; $activePage = 'categories'; $footer = getFooterContent($conn); $errors = [];
$stmt = $conn->prepare("SELECT * FROM categories WHERE id=? AND company_id=?");
$stmt->bind_param('ii', $id, $cid); $stmt->execute();
$cat = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$cat) { setFlash('danger','Not found.'); header('Location: categories.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '');
        if (!$name) { $errors[] = 'Name required.'; }
        else {
            $stmt = $conn->prepare("UPDATE categories SET name=?, description=? WHERE id=? AND company_id=?");
            $stmt->bind_param('ssii', $name, $desc, $id, $cid); $stmt->execute(); $stmt->close();
            setFlash('success','Category updated.'); header('Location: categories.php'); exit;
        }
    }
}
$d = $_POST ?: $cat;
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head><body>
<?php include 'layout-top.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>Edit Category</h5>
    <a href="categories.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.sanitize($e).'</li>'; ?></ul></div><?php endif; ?>
<div class="card table-card" style="max-width:500px"><div class="card-body p-4">
    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <div class="mb-3"><label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= sanitize($d['name'] ?? '') ?>" required></div>
        <div class="mb-4"><label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="2"><?= sanitize($d['description'] ?? '') ?></textarea></div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save</button>
            <a href="categories.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
<?php include 'layout-bottom.php'; ?>
