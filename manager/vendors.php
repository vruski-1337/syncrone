<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle = 'Vendors';
$activePage = 'vendors';
$cid = (int)($_SESSION['company_id'] ?? 0);
$footer = getFooterContent($conn);
$errors = [];
$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $contact = trim((string)($_POST['contact_person'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $isManufacturer = isset($_POST['is_manufacturer']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $id = (int)($_POST['id'] ?? 0);

        if ($name === '') {
            $errors[] = 'Vendor name is required.';
        }

        if (empty($errors)) {
            if ($id > 0) {
                $stmt = $conn->prepare('UPDATE vendors SET name=?, contact_person=?, phone=?, email=?, address=?, is_manufacturer=?, is_active=? WHERE id=? AND company_id=?');
                $stmt->bind_param('sssssiiii', $name, $contact, $phone, $email, $address, $isManufacturer, $isActive, $id, $cid);
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Vendor updated.');
            } else {
                $stmt = $conn->prepare('INSERT INTO vendors (company_id, name, contact_person, phone, email, address, is_manufacturer, is_active) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->bind_param('isssssii', $cid, $name, $contact, $phone, $email, $address, $isManufacturer, $isActive);
                $stmt->execute();
                $stmt->close();
                setFlash('success', 'Vendor added.');
            }
            header('Location: vendors.php');
            exit;
        }
    }
}

$editVendor = null;
if ($editId > 0) {
    $eStmt = $conn->prepare('SELECT * FROM vendors WHERE id = ? AND company_id = ? LIMIT 1');
    $eStmt->bind_param('ii', $editId, $cid);
    $eStmt->execute();
    $editVendor = $eStmt->get_result()->fetch_assoc();
    $eStmt->close();
}

$listStmt = $conn->prepare('SELECT * FROM vendors WHERE company_id = ? ORDER BY created_at DESC');
$listStmt->bind_param('i', $cid);
$listStmt->execute();
$vendors = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

$d = $_POST ?: ($editVendor ?? []);
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-truck-loading me-2"></i>Vendors</h5>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card table-card">
            <div class="card-header"><?= $editVendor ? 'Edit Vendor' : 'Add Vendor' ?></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="id" value="<?= (int)($d['id'] ?? 0) ?>">
                    <div class="mb-2"><input name="name" class="form-control" placeholder="Vendor name *" value="<?= sanitize($d['name'] ?? '') ?>" required></div>
                    <div class="mb-2"><input name="contact_person" class="form-control" placeholder="Contact person" value="<?= sanitize($d['contact_person'] ?? '') ?>"></div>
                    <div class="mb-2"><input name="phone" class="form-control" placeholder="Phone" value="<?= sanitize($d['phone'] ?? '') ?>"></div>
                    <div class="mb-2"><input name="email" class="form-control" placeholder="Email" value="<?= sanitize($d['email'] ?? '') ?>"></div>
                    <div class="mb-2"><textarea name="address" class="form-control" rows="2" placeholder="Address"><?= sanitize($d['address'] ?? '') ?></textarea></div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="is_manufacturer" id="is_manufacturer" <?= !empty($d['is_manufacturer']) ? 'checked' : '' ?>><label class="form-check-label" for="is_manufacturer">Manufacturer entry</label></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= !isset($d['is_active']) || !empty($d['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Active</label></div>
                    <button class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i><?= $editVendor ? 'Update' : 'Save' ?></button>
                    <?php if ($editVendor): ?><a href="vendors.php" class="btn btn-outline-secondary btn-sm">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header">Vendor Directory</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($vendors)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No vendors yet.</td></tr>
                    <?php else: foreach ($vendors as $v): ?>
                        <tr>
                            <td><strong><?= sanitize($v['name']) ?></strong><div class="small text-muted"><?= sanitize($v['email'] ?? '') ?></div></td>
                            <td><?= sanitize($v['contact_person'] ?: '—') ?></td>
                            <td><?= sanitize($v['phone'] ?: '—') ?></td>
                            <td><?= (int)$v['is_manufacturer'] === 1 ? '<span class="badge bg-info text-dark">Manufacturer</span>' : '<span class="badge bg-secondary">Supplier</span>' ?></td>
                            <td><?= (int)$v['is_active'] === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                            <td>
                                <a href="vendors.php?edit=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                <a href="vendor-delete.php?id=<?= (int)$v['id'] ?>&token=<?= urlencode(generateCsrfToken()) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this vendor?')"><i class="fas fa-trash"></i></a>
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
</body>
</html>
