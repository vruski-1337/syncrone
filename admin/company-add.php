<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('admin');

$pageTitle  = 'Add Company';
$activePage = 'companies';
$footer     = getFooterContent($conn);
$errors     = [];
$success    = '';

// Load subscriptions for dropdown
$subscriptions = $conn->query("SELECT id, name, price, duration_days FROM subscriptions WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name        = trim($_POST['name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $marquee     = trim($_POST['marquee_message'] ?? '');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $sub_id      = (int)($_POST['subscription_id'] ?? 0);
        $ownUsr      = trim($_POST['owner_username'] ?? '');
        $ownPwd      = $_POST['owner_password'] ?? '';
        $ownEmail    = trim($_POST['owner_email'] ?? '');
        $ownFullName = trim($_POST['owner_fullname'] ?? '');

        if (!$name)    $errors[] = 'Company name is required.';
        if (!$ownUsr)  $errors[] = 'Owner username is required.';
        if (!$ownPwd)  $errors[] = 'Owner password is required.';
        if (strlen($ownPwd) < 6) $errors[] = 'Password must be at least 6 characters.';

        // Check username unique
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $chk->bind_param('s', $ownUsr);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) $errors[] = 'Owner username already exists.';
        $chk->close();

        // Handle logo upload
        $logoFilename = null;
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
                // Create owner user
                $hash = password_hash($ownPwd, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role, is_active) VALUES (?,?,?,?,'owner',1)");
                $stmt->bind_param('ssss', $ownUsr, $hash, $ownEmail, $ownFullName);
                $stmt->execute();
                $ownerId = $conn->insert_id;
                $stmt->close();

                // Create company
                $subIdParam = $sub_id ?: null;
                $stmt = $conn->prepare("INSERT INTO companies (name, email, phone, address, logo, owner_id, subscription_id, marquee_message, is_active) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('sssssiisi', $name, $email, $phone, $address, $logoFilename, $ownerId, $subIdParam, $marquee, $is_active);
                $stmt->execute();
                $companyId = $conn->insert_id;
                $stmt->close();

                // Link owner to company
                $stmt = $conn->prepare("UPDATE users SET company_id = ? WHERE id = ?");
                $stmt->bind_param('ii', $companyId, $ownerId);
                $stmt->execute();
                $stmt->close();

                // Create subscription record
                if ($sub_id) {
                    $subRow = null;
                    foreach ($subscriptions as $s) { if ($s['id'] == $sub_id) { $subRow = $s; break; } }
                    if ($subRow) {
                        $startDate = date('Y-m-d');
                        $endDate   = date('Y-m-d', strtotime("+{$subRow['duration_days']} days"));
                        $stmt = $conn->prepare("INSERT INTO company_subscriptions (company_id, subscription_id, start_date, end_date, is_active) VALUES (?,?,?,?,1)");
                        $stmt->bind_param('iiss', $companyId, $sub_id, $startDate, $endDate);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                $conn->commit();
                setFlash('success', 'Company created successfully.');
                header('Location: companies.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                if ($logoFilename) deleteLogo($logoFilename);
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
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
    <h5 class="mb-0 fw-bold"><i class="fas fa-plus-circle me-2"></i>Add Company</h5>
    <a href="companies.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul>
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
                    <input type="text" name="name" class="form-control" value="<?= sanitize($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Subscription Plan</label>
                    <select name="subscription_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($subscriptions as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($_POST['subscription_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                                <?= sanitize($s['name']) ?> (<?= formatCurrency($s['price']) ?>/<?= $s['duration_days'] ?>d)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= sanitize($_POST['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Marquee Message</label>
                    <input type="text" name="marquee_message" class="form-control" placeholder="Scrolling announcement (optional)" value="<?= sanitize($_POST['marquee_message'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Company Logo</label>
                    <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                    <img id="logoPreview" src="" class="mt-2 logo-thumb d-none" alt="Preview">
                </div>
                <div class="col-md-4 d-flex align-items-center">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                               <?= isset($_POST['is_active']) || !isset($_POST['name']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <div class="form-section-title">Owner Account</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Owner Full Name</label>
                    <input type="text" name="owner_fullname" class="form-control" value="<?= sanitize($_POST['owner_fullname'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Owner Email</label>
                    <input type="email" name="owner_email" class="form-control" value="<?= sanitize($_POST['owner_email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Owner Username <span class="text-danger">*</span></label>
                    <input type="text" name="owner_username" class="form-control" value="<?= sanitize($_POST['owner_username'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Owner Password <span class="text-danger">*</span></label>
                    <input type="password" name="owner_password" class="form-control" placeholder="Min. 6 characters" required>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-2"></i>Create Company
                </button>
                <a href="companies.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'layout-bottom.php'; ?>
