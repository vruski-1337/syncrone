<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle  = 'Doctors';
$activePage = 'doctors';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);

$search = trim($_GET['q'] ?? '');
$params = [$cid];
$types  = 'i';
$where  = '';

if ($search) {
    $where   = ' AND (d.name LIKE ? OR d.phone LIKE ? OR d.specialization LIKE ?)';
    $like    = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$stmt = $conn->prepare("\n    SELECT d.*,\n           COUNT(s.id) AS total_referrals,\n           COALESCE(SUM(s.final_amount), 0) AS referral_sales\n    FROM doctors d\n    LEFT JOIN sales s ON s.doctor_id = d.id\n    WHERE d.company_id = ? {$where}\n    GROUP BY d.id\n    ORDER BY d.name ASC\n");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-user-md me-2"></i>Doctors</h5>
    <a href="doctor-add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Doctor</a>
</div>

<div class="card table-card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search doctors..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
                <?php if ($search): ?><a href="doctors.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr><th>#</th><th>Name</th><th>Phone</th><th>Specialization</th><th>Commission</th><th>Referrals</th><th>Referral Sales</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($doctors)): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">No doctors found.</td></tr>
            <?php else: foreach ($doctors as $i => $d): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= sanitize($d['name']) ?></strong></td>
                    <td><?= sanitize($d['phone'] ?: '—') ?></td>
                    <td><?= sanitize($d['specialization'] ?: '—') ?></td>
                    <td><?= (float)$d['commission_rate'] ?>%</td>
                    <td><span class="badge bg-info text-dark"><?= (int)$d['total_referrals'] ?></span></td>
                    <td><?= formatCurrency($d['referral_sales']) ?></td>
                    <td><?= $d['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td class="action-btns">
                        <a href="doctor-edit.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <a href="doctor-delete.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this doctor?');"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted small">Total: <?= count($doctors) ?> doctors</div>
</div>

<?php include 'layout-bottom.php'; ?>
</body>
</html>
