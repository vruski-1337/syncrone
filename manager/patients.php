<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle  = 'Patients';
$activePage = 'patients';
$cid        = (int)($_SESSION['company_id'] ?? 0);
$footer     = getFooterContent($conn);

$search = trim($_GET['q'] ?? '');
$where = '';
$params = [$cid];
$types = 'i';

if ($search !== '') {
    $where = ' AND (p.name LIKE ? OR p.phone LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$stmt = $conn->prepare("\n    SELECT p.*,\n           COUNT(s.id) AS total_visits,\n           COALESCE(SUM(s.final_amount), 0) AS total_billed,\n           MAX(s.created_at) AS last_visit\n    FROM patients p\n    LEFT JOIN sales s ON s.patient_id = p.id\n    WHERE p.company_id = ? {$where}\n    GROUP BY p.id\n    ORDER BY p.created_at DESC\n");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-user-injured me-2"></i>Patients</h5>
    <a href="patient-add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Patient</a>
</div>

<div class="card table-card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search patients..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
                <?php if ($search): ?><a href="patients.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr><th>#</th><th>Name</th><th>Phone</th><th>Age/Gender</th><th>Visits</th><th>Total Billed</th><th>Last Visit</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($patients)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No patients found.</td></tr>
            <?php else: foreach ($patients as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= sanitize($p['name']) ?></strong></td>
                    <td><?= sanitize($p['phone'] ?: '—') ?></td>
                    <td><?= sanitize(($p['age'] ?: '—') . ' / ' . ($p['gender'] ? ucfirst($p['gender']) : '—')) ?></td>
                    <td><span class="badge bg-info text-dark"><?= (int)$p['total_visits'] ?></span></td>
                    <td><?= formatCurrency($p['total_billed']) ?></td>
                    <td><?= $p['last_visit'] ? formatDateTime($p['last_visit']) : '—' ?></td>
                    <td><?= (int)$p['is_active'] === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td class="action-btns">
                        <a href="patient-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <a href="patient-delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this patient and prescriptions?');"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
</body>
</html>
