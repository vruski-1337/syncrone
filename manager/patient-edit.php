<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$id  = (int)($_GET['id'] ?? 0);
$cid = (int)($_SESSION['company_id'] ?? 0);
if (!$id) { setFlash('danger', 'Invalid patient.'); header('Location: patients.php'); exit; }

$pageTitle  = 'Edit Patient';
$activePage = 'patients';
$footer     = getFooterContent($conn);
$errors     = [];

$stmt = $conn->prepare('SELECT * FROM patients WHERE id = ? AND company_id = ? LIMIT 1');
$stmt->bind_param('ii', $id, $cid);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) { setFlash('danger', 'Patient not found.'); header('Location: patients.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $gender  = trim($_POST['gender'] ?? '');
        $ageRaw  = trim($_POST['age'] ?? '');
        $age     = $ageRaw !== '' ? (int)$ageRaw : null;
        $address = trim($_POST['address'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $rxNotes = trim($_POST['prescription_notes'] ?? '');

        if ($name === '') $errors[] = 'Patient name is required.';
        if ($age !== null && ($age < 0 || $age > 150)) $errors[] = 'Age must be between 0 and 150.';
        if (!in_array($gender, ['', 'male', 'female', 'other'], true)) $errors[] = 'Invalid gender selected.';

        $uploaded = null;
        if (!empty($_FILES['prescription']['name'])) {
            $uploaded = uploadPrescription($_FILES['prescription']);
            if (!$uploaded['success']) {
                $errors[] = $uploaded['error'];
            }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare('UPDATE patients SET name = ?, phone = ?, gender = ?, age = ?, address = ?, notes = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
                $stmt->bind_param('sssissiii', $name, $phone, $gender, $age, $address, $notes, $isActive, $id, $cid);
                $stmt->execute();
                $stmt->close();

                if ($uploaded && !empty($uploaded['filename'])) {
                    $stmt = $conn->prepare('INSERT INTO patient_prescriptions (patient_id, file_name, original_name, notes) VALUES (?,?,?,?)');
                    $stmt->bind_param('isss', $id, $uploaded['filename'], $uploaded['original_name'], $rxNotes);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();
                setFlash('success', 'Patient updated successfully.');
                header('Location: patient-edit.php?id=' . $id);
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                if ($uploaded && !empty($uploaded['filename'])) {
                    deletePrescriptionFile($uploaded['filename']);
                }
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$pStmt = $conn->prepare('SELECT * FROM patient_prescriptions WHERE patient_id = ? ORDER BY uploaded_at DESC');
$pStmt->bind_param('i', $id);
$pStmt->execute();
$prescriptions = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pStmt->close();

$d = $_POST ?: $patient;
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2"></i>Edit Patient</h5>
    <a href="patients.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="card table-card mb-3" style="max-width: 900px;">
    <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Patient Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($d['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($d['phone'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Age</label>
                    <input type="number" name="age" class="form-control" min="0" max="150" value="<?= sanitize($d['age'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">- Select -</option>
                        <option value="male" <?= ($d['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($d['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= ($d['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= sanitize($d['address'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($d['notes'] ?? '') ?></textarea>
                </div>
                <div class="col-md-7">
                    <label class="form-label fw-semibold">Upload New Prescription</label>
                    <input type="file" name="prescription" class="form-control" accept="application/pdf,image/jpeg,image/png,image/webp">
                    <div class="form-text">Allowed: PDF, JPG, PNG, WEBP (max 5MB)</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Prescription Notes</label>
                    <input type="text" name="prescription_notes" class="form-control" value="<?= sanitize($_POST['prescription_notes'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ($d['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Changes</button>
                <a href="patients.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<div class="card table-card" style="max-width: 900px;">
    <div class="card-header"><i class="fas fa-file-medical me-2"></i>Uploaded Prescriptions</div>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead><tr><th>#</th><th>File</th><th>Notes</th><th>Uploaded</th></tr></thead>
            <tbody>
            <?php if (empty($prescriptions)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No prescriptions uploaded yet.</td></tr>
            <?php else: foreach ($prescriptions as $i => $rx): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><a href="prescription-view.php?id=<?= (int)$rx['id'] ?>" target="_blank" class="text-decoration-none"><i class="fas fa-paperclip me-1"></i><?= sanitize($rx['original_name'] ?: $rx['file_name']) ?></a></td>
                    <td><?= sanitize($rx['notes'] ?: '—') ?></td>
                    <td><?= formatDateTime($rx['uploaded_at']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
</body>
</html>
