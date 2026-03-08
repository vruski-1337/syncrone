<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle  = 'Add Doctor';
$activePage = 'doctors';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name         = trim($_POST['name'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $special      = trim($_POST['specialization'] ?? '');
        $commission   = (float)($_POST['commission_rate'] ?? 0);
        $notes        = trim($_POST['notes'] ?? '');
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        if (!$name) $errors[] = 'Doctor name is required.';
        if ($commission < 0 || $commission > 100) $errors[] = 'Commission rate must be between 0 and 100.';

        if (empty($errors)) {
            $stmt = $conn->prepare('INSERT INTO doctors (company_id, name, phone, specialization, commission_rate, notes, is_active) VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param('isssdsi', $cid, $name, $phone, $special, $commission, $notes, $isActive);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Doctor added successfully.');
            header('Location: doctors.php');
            exit;
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
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-user-md me-2"></i>Add Doctor</h5>
    <a href="doctors.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="card table-card" style="max-width: 760px;">
    <div class="card-body p-4">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Doctor Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Specialization</label>
                    <input type="text" name="specialization" class="form-control" value="<?= sanitize($_POST['specialization'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Commission Rate (%)</label>
                    <input type="number" name="commission_rate" class="form-control" min="0" max="100" step="0.01" value="<?= sanitize($_POST['commission_rate'] ?? '0') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= isset($_POST['is_active']) || !isset($_POST['name']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Add Doctor</button>
                <a href="doctors.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'layout-bottom.php'; ?>
</body>
</html>
