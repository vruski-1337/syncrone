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
        <a href="../manager/products.php" class="<?= $activePage === 'products' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> Products
        </a>
        <a href="../manager/product-bulk-add.php" class="<?= $activePage === 'product-bulk-add' ? 'active' : '' ?>">
            <i class="fas fa-table"></i> Bulk Add Products
        </a>
        <a href="../manager/categories.php" class="<?= $activePage === 'categories' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> Categories
        </a>
        <a href="../manager/units.php" class="<?= $activePage === 'units' ? 'active' : '' ?>">
            <i class="fas fa-ruler"></i> Units
        </a>
        <a href="../manager/sales.php" class="<?= $activePage === 'sales' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> Sales
        </a>
        <a href="../manager/patients.php" class="<?= $activePage === 'patients' ? 'active' : '' ?>">
            <i class="fas fa-user-injured"></i> Patients
        </a>
        <a href="../manager/sale-add.php" class="<?= $activePage === 'sale-add' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i> New Sale
        </a>
        <a href="../manager/doctors.php" class="<?= $activePage === 'doctors' ? 'active' : '' ?>">
            <i class="fas fa-user-md"></i> Doctors
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
