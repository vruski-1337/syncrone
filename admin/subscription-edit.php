<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('danger', 'Invalid plan.'); header('Location: subscriptions.php'); exit; }

$pageTitle  = 'Edit Subscription Plan';
$activePage = 'subscriptions';
$footer     = getFooterContent($conn);
$errors     = [];

$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$plan) { setFlash('danger', 'Plan not found.'); header('Location: subscriptions.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $price    = (float)($_POST['price'] ?? 0);
        $duration = (int)($_POST['duration_days'] ?? 30);
        $features = trim($_POST['features'] ?? '');
        $active   = isset($_POST['is_active']) ? 1 : 0;

        if (!$name) $errors[] = 'Plan name is required.';

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE subscriptions SET name=?, price=?, duration_days=?, features=?, is_active=? WHERE id=?");
            $stmt->bind_param('sdisii', $name, $price, $duration, $features, $active, $id);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Plan updated.');
            header('Location: subscriptions.php');
            exit;
        }
    }
}

$d = $_POST ?: $plan;
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>Edit Plan: <?= sanitize($plan['name']) ?></h5>
    <a href="subscriptions.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>
<div class="card table-card" style="max-width:600px">
    <div class="card-body p-4">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Plan Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= sanitize($d['name'] ?? '') ?>" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Price ($)</label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?= sanitize($d['price'] ?? '0.00') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Duration (days)</label>
                    <input type="number" name="duration_days" class="form-control" min="1" value="<?= sanitize($d['duration_days'] ?? '30') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Features</label>
                <textarea name="features" class="form-control" rows="5"><?= sanitize($d['features'] ?? '') ?></textarea>
            </div>
            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ($d['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Changes</button>
                <a href="subscriptions.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'layout-bottom.php'; ?>
