<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$pageTitle  = 'Dashboard Alerts';
$activePage = 'alerts';
$footer     = getFooterContent($conn);
$errors     = [];

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type    = $_POST['type'] ?? 'info';
        $active  = isset($_POST['is_active']) ? 1 : 0;
        $allowed = ['warning', 'info', 'success', 'danger'];
        if (!in_array($type, $allowed)) $type = 'info';

        if (!$title || !$message) {
            $errors[] = 'Title and message are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO alerts (title, message, type, is_active) VALUES (?,?,?,?)");
            $stmt->bind_param('sssi', $title, $message, $type, $active);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Alert created.');
            header('Location: alerts.php');
            exit;
        }
    }
}

// Handle toggle/delete via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $aid    = (int)($_GET['id'] ?? 0);
    if ($aid) {
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM alerts WHERE id = ?");
            $stmt->bind_param('i', $aid);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Alert deleted.');
            header('Location: alerts.php');
            exit;
        }
        if ($action === 'toggle') {
            $stmt = $conn->prepare("UPDATE alerts SET is_active = 1 - is_active WHERE id = ?");
            $stmt->bind_param('i', $aid);
            $stmt->execute();
            $stmt->close();
            header('Location: alerts.php');
            exit;
        }
    }
}

$alerts = $conn->query("SELECT * FROM alerts ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
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
<div class="row g-4">
    <!-- Add Alert Form -->
    <div class="col-lg-4">
        <div class="card table-card">
            <div class="card-header"><i class="fas fa-plus me-2"></i>New Alert</div>
            <div class="card-body p-3">
                <?php if ($errors): ?>
                    <div class="alert alert-danger small py-2"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
                <?php endif; ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Title</label>
                        <input type="text" name="title" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Message</label>
                        <textarea name="message" class="form-control form-control-sm" rows="3" required></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Yellow)</option>
                            <option value="danger">Danger (Red)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" checked>
                            <label class="form-check-label small">Active</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-plus me-1"></i>Add Alert</button>
                </form>
            </div>
        </div>
    </div>
    <!-- Alerts List -->
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header"><i class="fas fa-bell me-2"></i>All Alerts</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($alerts)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No alerts.</td></tr>
                    <?php else: foreach ($alerts as $a): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($a['title']) ?></strong>
                                <br><small class="text-muted"><?= sanitize(substr($a['message'], 0, 60)) ?>...</small>
                            </td>
                            <td><span class="badge bg-<?= sanitize($a['type']) ?>"><?= ucfirst(sanitize($a['type'])) ?></span></td>
                            <td>
                                <a href="?action=toggle&id=<?= $a['id'] ?>">
                                    <?= $a['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                                </a>
                            </td>
                            <td class="small text-muted"><?= formatDate($a['created_at']) ?></td>
                            <td class="action-btns">
                                <a href="?action=delete&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Delete this alert?')"><i class="fas fa-trash"></i></a>
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
