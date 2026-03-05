<?php
// Admin sidebar partial – include after <body>
// Variables expected: $pageTitle (string), $activePage (string), $conn
$currentUser = getCurrentUser($conn);
$footer      = getFooterContent($conn);
$logoUrl     = isset($company) && !empty($company['logo']) ? getLogoUrl($company['logo']) : null;
?>
<div class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-pills"></i></div>
        <span><?= SITE_NAME ?></span>
    </a>

    <div class="sidebar-section">Main Menu</div>
    <nav>
        <a href="dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
    </nav>

    <div class="sidebar-section">Companies</div>
    <nav>
        <a href="companies.php" class="<?= $activePage === 'companies' ? 'active' : '' ?>">
            <i class="fas fa-building"></i> Companies
        </a>
        <a href="subscriptions.php" class="<?= $activePage === 'subscriptions' ? 'active' : '' ?>">
            <i class="fas fa-credit-card"></i> Subscriptions
        </a>
    </nav>

    <div class="sidebar-section">Settings</div>
    <nav>
        <a href="alerts.php" class="<?= $activePage === 'alerts' ? 'active' : '' ?>">
            <i class="fas fa-bell"></i> Alerts
        </a>
        <a href="footer-settings.php" class="<?= $activePage === 'footer' ? 'active' : '' ?>">
            <i class="fas fa-paragraph"></i> Footer Settings
        </a>
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>

<div class="main-wrapper">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light d-md-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h6 class="page-title"><?= sanitize($pageTitle) ?></h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary">Admin</span>
            <div class="avatar-circle"><?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)) ?></div>
            <span class="fw-semibold small d-none d-sm-inline"><?= sanitize($currentUser['full_name'] ?? $currentUser['username']) ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-danger ms-2">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
    <!-- Main Content -->
    <div class="main-content">
