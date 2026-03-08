<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$pageTitle  = 'Companies';
$activePage = 'companies';
$footer     = getFooterContent($conn);

$search = trim($_GET['q'] ?? '');
$whereClause = '';
$params = [];
$types = '';

if ($search) {
    $whereClause = " WHERE c.name LIKE ? OR c.email LIKE ?";
    $like = '%' . $search . '%';
    $params = [$like, $like];
    $types = 'ss';
}

$sql = "
    SELECT c.*, u.username AS owner_username,
           sub.name AS subscription_name,
           cs.start_date, cs.end_date,
           DATEDIFF(cs.end_date, CURDATE()) AS days_left
    FROM companies c
    LEFT JOIN users u ON u.id = c.owner_id
    LEFT JOIN company_subscriptions cs ON cs.company_id = c.id AND cs.is_active = 1
    LEFT JOIN subscriptions sub ON sub.id = cs.subscription_id
    $whereClause
    ORDER BY c.created_at DESC
";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$companies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include 'layout-top.php'; ?>

<?= renderFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-building me-2"></i>Companies</h5>
    <a href="company-add.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i>Add Company
    </a>
</div>

<div class="card table-card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search companies..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="companies.php" class="btn btn-sm btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Logo</th>
                    <th>Company Name</th>
                    <th>Email</th>
                    <th>Owner</th>
                    <th>Subscription</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($companies)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">
                    <i class="fas fa-building fa-2x mb-2 d-block opacity-25"></i>No companies found.
                </td></tr>
            <?php else: foreach ($companies as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <?php if ($c['logo']): ?>
                            <img src="<?= getLogoUrl($c['logo']) ?>" class="logo-thumb" alt="Logo">
                        <?php else: ?>
                            <div class="logo-placeholder"><?= strtoupper(substr($c['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= sanitize($c['name']) ?></strong><br><small class="text-muted"><?= sanitize($c['phone'] ?? '') ?></small></td>
                    <td><?= sanitize($c['email'] ?? '—') ?></td>
                    <td><?= sanitize($c['owner_username'] ?? '—') ?></td>
                    <td><?= sanitize($c['subscription_name'] ?? '—') ?></td>
                    <td><?= $c['end_date'] ? formatDate($c['end_date']) : '—' ?></td>
                    <td>
                        <?php
                        $days = (int)($c['days_left'] ?? -9999);
                        if (!$c['end_date'])   echo '<span class="badge bg-secondary">No Sub</span>';
                        elseif ($days < 0)     echo '<span class="badge bg-danger">Expired</span>';
                        elseif ($days <= 7)    echo '<span class="badge bg-warning text-dark">Expiring</span>';
                        else                   echo '<span class="badge bg-success">Active</span>';
                        if (!$c['is_active'])  echo ' <span class="badge bg-dark">Inactive</span>';
                        if (!empty($c['usage_paused'])) echo ' <span class="badge bg-danger">Usage Paused</span>';
                        ?>
                    </td>
                    <td class="action-btns text-center">
                        <a href="company-edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="company-reset.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning" title="Reset Credentials"><i class="fas fa-key"></i></a>
                        <a href="company-delete.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger"
                           title="Delete" onclick="return confirm('Delete this company and all its data?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted small">Total: <?= count($companies) ?> companies</div>
</div>

<?php include 'layout-bottom.php'; ?>
