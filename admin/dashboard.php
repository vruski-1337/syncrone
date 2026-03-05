<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$pageTitle  = 'Admin Dashboard';
$activePage = 'dashboard';

// Stats
$totalCompanies = $conn->query("SELECT COUNT(*) FROM companies")->fetch_row()[0];
$activeSubsCount = $conn->query("SELECT COUNT(*) FROM company_subscriptions WHERE is_active=1 AND end_date >= CURDATE()")->fetch_row()[0];
$totalUsers = $conn->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetch_row()[0];
$revenue = $conn->query("SELECT COALESCE(SUM(s.price), 0) FROM company_subscriptions cs JOIN subscriptions s ON s.id=cs.subscription_id WHERE cs.is_active=1")->fetch_row()[0];

// Recent companies
$recentCompanies = $conn->query("
    SELECT c.*, u.username AS owner_username, sub.name AS subscription_name,
           cs.end_date, DATEDIFF(cs.end_date, CURDATE()) AS days_left
    FROM companies c
    LEFT JOIN users u ON u.id = c.owner_id
    LEFT JOIN company_subscriptions cs ON cs.company_id = c.id AND cs.is_active = 1
    LEFT JOIN subscriptions sub ON sub.id = cs.subscription_id
    ORDER BY c.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$alerts = getActiveAlerts($conn);
$footer = getFooterContent($conn);
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

<!-- Alerts -->
<?php foreach ($alerts as $alert): ?>
<div class="alert alert-<?= sanitize($alert['type']) ?> alert-dismissible fade show auto-dismiss" role="alert">
    <strong><i class="fas fa-bell me-2"></i><?= sanitize($alert['title']) ?></strong> <?= sanitize($alert['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card blue">
            <div class="card-body">
                <div class="stat-info">
                    <div class="stat-label">Total Companies</div>
                    <div class="stat-value"><?= $totalCompanies ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-building"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card green">
            <div class="card-body">
                <div class="stat-info">
                    <div class="stat-label">Active Subscriptions</div>
                    <div class="stat-value"><?= $activeSubsCount ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card orange">
            <div class="card-body">
                <div class="stat-info">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= $totalUsers ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card card purple">
            <div class="card-body">
                <div class="stat-info">
                    <div class="stat-label">Subscription Revenue</div>
                    <div class="stat-value"><?= formatCurrency($revenue) ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Companies -->
<div class="card table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-building me-2"></i>Recent Companies</span>
        <a href="companies.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Logo</th>
                    <th>Company Name</th>
                    <th>Owner</th>
                    <th>Subscription</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentCompanies)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No companies found.</td></tr>
            <?php else: foreach ($recentCompanies as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <?php if ($c['logo']): ?>
                            <img src="<?= getLogoUrl($c['logo']) ?>" class="logo-thumb" alt="Logo">
                        <?php else: ?>
                            <div class="logo-placeholder"><?= strtoupper(substr($c['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= sanitize($c['name']) ?></strong></td>
                    <td><?= sanitize($c['owner_username'] ?? 'N/A') ?></td>
                    <td><?= sanitize($c['subscription_name'] ?? '—') ?></td>
                    <td><?= $c['end_date'] ? formatDate($c['end_date']) : '—' ?></td>
                    <td>
                        <?php
                        $days = (int)($c['days_left'] ?? -999);
                        if (!$c['end_date']) { echo '<span class="badge bg-secondary">No Sub</span>'; }
                        elseif ($days < 0)   { echo '<span class="badge bg-danger">Expired</span>'; }
                        elseif ($days <= 7)  { echo '<span class="badge bg-warning text-dark">Expiring ('.$days.'d)</span>'; }
                        else                 { echo '<span class="badge bg-success">Active</span>'; }
                        ?>
                    </td>
                    <td class="action-btns">
                        <a href="company-edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
