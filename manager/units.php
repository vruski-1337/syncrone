<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);
$pageTitle = 'Units'; $activePage = 'units'; $cid = (int)$_SESSION['company_id'];
$footer = getFooterContent($conn); $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $name = trim($_POST['name'] ?? ''); $abbr = trim($_POST['abbreviation'] ?? '');
        if (!$name) { $errors[] = 'Unit name is required.'; }
        else {
            $stmt = $conn->prepare("INSERT INTO units (company_id, name, abbreviation) VALUES (?,?,?)");
            $stmt->bind_param('iss', $cid, $name, $abbr); $stmt->execute(); $stmt->close();
            setFlash('success', 'Unit added.'); header('Location: units.php'); exit;
        }
    }
}

$uStmt = $conn->prepare("SELECT u.*, COUNT(p.id) AS product_count FROM units u LEFT JOIN products p ON p.unit_id=u.id WHERE u.company_id=? GROUP BY u.id ORDER BY u.name");
$uStmt->bind_param('i', $cid); $uStmt->execute();
$units = $uStmt->get_result()->fetch_all(MYSQLI_ASSOC); $uStmt->close();
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
            <div class="card-header"><i class="fas fa-plus me-2"></i>Add Unit</div>
            <div class="card-body p-3">
                <?php if ($errors): ?><div class="alert alert-danger small py-2"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.sanitize($e).'</li>'; ?></ul></div><?php endif; ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-2"><label class="form-label fw-semibold small">Unit Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Gram" required></div>
                    <div class="mb-3"><label class="form-label fw-semibold small">Abbreviation</label>
                        <input type="text" name="abbreviation" class="form-control form-control-sm" placeholder="e.g. g"></div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-plus me-1"></i>Add Unit</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header"><i class="fas fa-ruler me-2"></i>All Units (<?= count($units) ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>#</th><th>Name</th><th>Abbreviation</th><th>Products</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($units)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No units yet.</td></tr>
                    <?php else: foreach ($units as $i => $u): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= sanitize($u['name']) ?></strong></td>
                            <td><code><?= sanitize($u['abbreviation'] ?? '—') ?></code></td>
                            <td><span class="badge bg-info text-dark"><?= $u['product_count'] ?></span></td>
                            <td class="action-btns">
                                <a href="unit-edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                <a href="unit-delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Delete this unit?')"><i class="fas fa-trash"></i></a>
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
