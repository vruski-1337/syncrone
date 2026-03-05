<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$pageTitle  = 'Footer Settings';
$activePage = 'footer';
$footer     = getFooterContent($conn);
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $content = $_POST['content'] ?? '';
        // Allow safe HTML in footer – strip dangerous tags
        $allowedTags = '<strong><em><a><br><span><small>';
        $content = strip_tags($content, $allowedTags);

        $stmt = $conn->prepare("INSERT INTO footer_settings (id, content) VALUES (1, ?) ON DUPLICATE KEY UPDATE content = ?, updated_at = NOW()");
        $stmt->bind_param('ss', $content, $content);
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Footer settings updated.');
        header('Location: footer-settings.php');
        exit;
    }
}

$row = $conn->query("SELECT content FROM footer_settings WHERE id = 1")->fetch_assoc();
$currentContent = $row['content'] ?? '';
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-paragraph me-2"></i>Footer Settings</h5>
</div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>
<div class="card table-card" style="max-width:700px">
    <div class="card-body p-4">
        <p class="text-muted small mb-3">This footer content is displayed at the bottom of all pages across the system. Basic HTML is allowed (<code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;a&gt;</code>).</p>
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Footer Content</label>
                <textarea name="content" class="form-control font-monospace" rows="5"><?= htmlspecialchars($currentContent, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold text-muted small">Preview:</label>
                <div class="border rounded p-2 bg-light text-muted small" id="footerPreview"><?= $currentContent ?></div>
            </div>
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Footer</button>
        </form>
    </div>
</div>
<script>
document.querySelector('[name="content"]').addEventListener('input', function () {
    document.getElementById('footerPreview').innerHTML = this.value;
});
</script>
<?php include 'layout-bottom.php'; ?>
