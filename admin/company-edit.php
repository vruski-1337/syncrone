<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('danger', 'Invalid company.'); header('Location: companies.php'); exit; }

$pageTitle  = 'Edit Company';
$activePage = 'companies';
$footer     = getFooterContent($conn);
$errors     = [];

// Load company
$stmt = $conn->prepare("SELECT c.*, u.username AS owner_username, u.email AS owner_email, u.full_name AS owner_fullname, cs.subscription_id AS active_sub_id FROM companies c LEFT JOIN users u ON u.id = c.owner_id LEFT JOIN company_subscriptions cs ON cs.company_id = c.id AND cs.is_active = 1 WHERE c.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$company) { setFlash('danger', 'Company not found.'); header('Location: companies.php'); exit; }

$subscriptions = $conn->query("SELECT id, name, price, duration_days FROM subscriptions WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$subStatus = checkSubscription($conn, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $marquee   = trim($_POST['marquee_message'] ?? '');
        $gstNumber = trim($_POST['gst_number'] ?? '');
        $gstPercent = (float)($_POST['gst_percentage'] ?? 0);
        $tagline   = trim($_POST['tagline'] ?? '');
        $usagePaused = isset($_POST['usage_paused']) ? 1 : 0;
        $pauseMessage = trim($_POST['pause_message'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sub_id    = (int)($_POST['subscription_id'] ?? 0);
        $ownEmail  = trim($_POST['owner_email'] ?? '');
        $ownFull   = trim($_POST['owner_fullname'] ?? '');

        if (!$name) $errors[] = 'Company name is required.';
        if ($gstPercent < 0 || $gstPercent > 100) $errors[] = 'GST percentage must be between 0 and 100.';
        if ($usagePaused && $pauseMessage === '') $errors[] = 'Pause message is required when usage is paused.';

        // Logo upload
        $logoFilename = $company['logo'];
        if (!empty($_FILES['logo']['name'])) {
            $upload = uploadLogo($_FILES['logo']);
            if (!$upload['success']) {
                $errors[] = $upload['error'];
            } else {
                $logoFilename = $upload['filename'];
            }
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // Update company
                $stmt = $conn->prepare("UPDATE companies SET name=?, email=?, phone=?, address=?, logo=?, subscription_id=?, marquee_message=?, gst_number=?, gst_percentage=?, tagline=?, usage_paused=?, pause_message=?, is_active=?, updated_at=NOW() WHERE id=?");
                $sub = $sub_id ?: null;
                $stmt->bind_param('sssssissdsisii', $name, $email, $phone, $address, $logoFilename, $sub, $marquee, $gstNumber, $gstPercent, $tagline, $usagePaused, $pauseMessage, $is_active, $id);
                $stmt->execute();
                $stmt->close();

                // Update owner info
                if ($company['owner_id']) {
                    $stmt = $conn->prepare("UPDATE users SET email=?, full_name=? WHERE id=?");
                    $stmt->bind_param('ssi', $ownEmail, $ownFull, $company['owner_id']);
                    $stmt->execute();
                    $stmt->close();
                }

                // Update subscription
                if ($sub_id) {
                    // Deactivate old subscriptions
                    $stmt = $conn->prepare("UPDATE company_subscriptions SET is_active=0 WHERE company_id=?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();

                    // Add new if changed
                    $subRow = null;
                    foreach ($subscriptions as $s) { if ($s['id'] == $sub_id) { $subRow = $s; break; } }
                    if ($subRow) {
                        $startDate = date('Y-m-d');
                        $endDate   = date('Y-m-d', strtotime("+{$subRow['duration_days']} days"));
                        $stmt = $conn->prepare("INSERT INTO company_subscriptions (company_id, subscription_id, start_date, end_date, is_active) VALUES (?,?,?,?,1)");
                        $stmt->bind_param('iiss', $id, $sub_id, $startDate, $endDate);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Delete old logo file if replaced
                if ($logoFilename !== $company['logo'] && $company['logo']) {
                    deleteLogo($company['logo']);
                }

                $conn->commit();
                setFlash('success', 'Company updated successfully.');
                header('Location: companies.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$d = $_POST ?: $company;
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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>Edit Company: <?= sanitize($company['name']) ?></h5>
    <a href="companies.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<?php if ($subStatus['status'] !== 'expired'): ?>
    <div class="alert alert-info small">
        <i class="fas fa-info-circle me-2"></i>
        Company pause is typically used after subscription expiry. Current subscription status: <strong><?= sanitize($subStatus['status']) ?></strong>.
    </div>
<?php endif; ?>

<div class="card table-card">
    <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div class="form-section-title">Company Information</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= sanitize($d['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($d['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($d['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Subscription Plan</label>
                    <select name="subscription_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($subscriptions as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($d['active_sub_id'] ?? $d['subscription_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>>
                                <?= sanitize($s['name']) ?> (<?= formatCurrency($s['price']) ?>/<?= $s['duration_days'] ?>d)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= sanitize($d['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Marquee Message</label>
                    <input type="text" name="marquee_message" class="form-control" value="<?= sanitize($d['marquee_message'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Company Logo</label>
                    <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                    <?php if ($company['logo']): ?>
                        <img id="logoPreview" src="<?= getLogoUrl($company['logo']) ?>" class="mt-2 logo-thumb" alt="Logo">
                    <?php else: ?>
                        <img id="logoPreview" src="" class="mt-2 logo-thumb d-none" alt="Preview">
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">GST Number</label>
                    <input type="text" name="gst_number" class="form-control" value="<?= sanitize($d['gst_number'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">GST Percentage (%)</label>
                    <input type="number" name="gst_percentage" class="form-control" min="0" max="100" step="0.01" value="<?= sanitize($d['gst_percentage'] ?? '0') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Tagline</label>
                    <input type="text" name="tagline" class="form-control" value="<?= sanitize($d['tagline'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="usage_paused" id="usage_paused" <?= ($d['usage_paused'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="usage_paused">Pause Company Usage (Owner/Manager)</label>
                    </div>
                    <div class="form-text">When enabled, company users will see a renewal message page and cannot use the panel.</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Pause Message</label>
                    <textarea name="pause_message" class="form-control" rows="2" placeholder="Custom renewal/contact message"><?= sanitize($d['pause_message'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4 d-flex align-items-center">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                               <?= ($d['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <div class="form-section-title">Owner Account</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control bg-light" value="<?= sanitize($company['owner_username'] ?? '') ?>" disabled>
                    <div class="form-text">Use "Reset Credentials" to change username/password.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Owner Full Name</label>
                    <input type="text" name="owner_fullname" class="form-control" value="<?= sanitize($company['owner_fullname'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Owner Email</label>
                    <input type="email" name="owner_email" class="form-control" value="<?= sanitize($company['owner_email'] ?? '') ?>">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Changes</button>
                <a href="company-reset.php?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-key me-2"></i>Reset Credentials</a>
                <a href="companies.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
