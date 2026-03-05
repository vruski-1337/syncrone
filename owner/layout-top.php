<?php
// Owner sidebar partial
$currentUser = getCurrentUser($conn);
$footer      = getFooterContent($conn);
$companyData = getCompanyData($conn, $_SESSION['company_id'] ?? 0);
?>
<div class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <?php if (!empty($companyData['logo'])): ?>
            <img src="<?= getLogoUrl($companyData['logo']) ?>" alt="Logo">
        <?php else: ?>
            <div class="brand-icon"><i class="fas fa-pills"></i></div>
        <?php endif; ?>
        <span><?= sanitize($companyData['name'] ?? SITE_NAME) ?></span>
    </a>

    <div class="sidebar-section">Main Menu</div>
    <nav>
        <a href="dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
    </nav>

    <div class="sidebar-section">Management</div>
    <nav>
        <a href="managers.php" class="<?= $activePage === 'managers' ? 'active' : '' ?>">
            <i class="fas fa-user-tie"></i> Store Managers
        </a>
    </nav>

    <div class="sidebar-section">Reports</div>
    <nav>
        <a href="reports.php" class="<?= $activePage === 'reports' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Sales Report
        </a>
        <a href="statements.php" class="<?= $activePage === 'statements' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> Statements
        </a>
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>

<div class="main-wrapper">
    <!-- Marquee -->
    <?php if (!empty($companyData['marquee_message'])): ?>
    <div class="marquee-bar">
        <span class="marquee-inner">
            <i class="fas fa-bullhorn me-2"></i><?= sanitize($companyData['marquee_message']) ?>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <i class="fas fa-bullhorn me-2"></i><?= sanitize($companyData['marquee_message']) ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h6 class="page-title"><?= sanitize($pageTitle) ?></h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success">Owner</span>
            <div class="avatar-circle"><?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)) ?></div>
            <span class="fw-semibold small d-none d-sm-inline"><?= sanitize($currentUser['full_name'] ?? $currentUser['username']) ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-danger ms-2"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
    <div class="main-content">
