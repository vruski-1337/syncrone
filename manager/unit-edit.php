<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('manager');
$id = (int)($_GET['id'] ?? 0); $cid = (int)$_SESSION['company_id'];
if (!$id) { setFlash('danger','Invalid.'); header('Location: units.php'); exit; }
$pageTitle = 'Edit Unit'; $activePage = 'units'; $footer = getFooterContent($conn); $errors = [];
$stmt = $conn->prepare("SELECT * FROM units WHERE id=? AND company_id=?");
$stmt->bind_param('ii', $id, $cid); $stmt->execute();
$unit = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$unit) { setFlash('danger','Not found.'); header('Location: units.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $name = trim($_POST['name'] ?? ''); $abbr = trim($_POST['abbreviation'] ?? '');
        if (!$name) { $errors[] = 'Name required.'; }
        else {
            $stmt = $conn->prepare("UPDATE units SET name=?, abbreviation=? WHERE id=? AND company_id=?");
            $stmt->bind_param('ssii', $name, $abbr, $id, $cid); $stmt->execute(); $stmt->close();
            setFlash('success','Unit updated.'); header('Location: units.php'); exit;
        }
    }
}
$d = $_POST ?: $unit;
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>Edit Unit</h5>
    <a href="units.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.sanitize($e).'</li>'; ?></ul></div><?php endif; ?>
<div class="card table-card" style="max-width:400px"><div class="card-body p-4">
    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <div class="mb-3"><label class="form-label fw-semibold">Unit Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= sanitize($d['name'] ?? '') ?>" required></div>
        <div class="mb-4"><label class="form-label fw-semibold">Abbreviation</label>
            <input type="text" name="abbreviation" class="form-control" value="<?= sanitize($d['abbreviation'] ?? '') ?>"></div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save</button>
            <a href="units.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
<?php include 'layout-bottom.php'; ?>
