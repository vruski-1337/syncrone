<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle = 'Indent Management';
$activePage = 'indents';
$cid = (int)($_SESSION['company_id'] ?? 0);
$uid = (int)($_SESSION['user_id'] ?? 0);
$footer = getFooterContent($conn);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $details = trim((string)($_POST['details'] ?? ''));
        $status = $_POST['status'] ?? 'open';

        if ($title === '') {
            $errors[] = 'Indent title is required.';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare('INSERT INTO indents (company_id, title, details, status, requested_by) VALUES (?,?,?,?,?)');
            $stmt->bind_param('isssi', $cid, $title, $details, $status, $uid);
            $stmt->execute();
            $stmt->close();
            setFlash('success', 'Indent created.');
            header('Location: indents.php');
            exit;
        }
    }
}

$list = $conn->prepare('SELECT i.*, u.full_name AS requested_by_name FROM indents i LEFT JOIN users u ON u.id = i.requested_by WHERE i.company_id = ? ORDER BY i.created_at DESC');
$list->bind_param('i', $cid);
$list->execute();
$indents = $list->get_result()->fetch_all(MYSQLI_ASSOC);
$list->close();
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

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card table-card">
            <div class="card-header">Create Indent</div>
            <div class="card-body">
                <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="mb-2"><input name="title" class="form-control" placeholder="Indent title" required></div>
                    <div class="mb-2"><textarea name="details" class="form-control" rows="3" placeholder="Items/details required"></textarea></div>
                    <div class="mb-2"><select name="status" class="form-select"><option value="open">Open</option><option value="in-progress">In Progress</option><option value="closed">Closed</option></select></div>
                    <button class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Create</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header">Indent Register</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Title</th><th>Status</th><th>Requested By</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if (empty($indents)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No indents yet.</td></tr>
                    <?php else: foreach ($indents as $i): ?>
                        <tr>
                            <td><strong><?= sanitize($i['title']) ?></strong><div class="small text-muted"><?= sanitize($i['details'] ?: '') ?></div></td>
                            <td><span class="badge bg-secondary"><?= sanitize(ucfirst($i['status'])) ?></span></td>
                            <td><?= sanitize($i['requested_by_name'] ?: '—') ?></td>
                            <td><?= formatDateTime($i['created_at']) ?></td>
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
