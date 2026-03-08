<?php
// Manager sidebar partial
$currentUser = getCurrentUser($conn);
$footer      = getFooterContent($conn);
$companyData = getCompanyData($conn, $_SESSION['company_id'] ?? 0);
$currentRole = $_SESSION['role'] ?? 'manager';
$roleLabel   = $currentRole === 'owner' ? 'Owner' : 'Manager';
$roleBadge   = $currentRole === 'owner' ? 'bg-success' : 'bg-info text-dark';
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

    <div class="sidebar-section">Inventory</div>
    <nav>
        <a href="products.php" class="<?= $activePage === 'products' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> Products
        </a>
        <a href="product-bulk-add.php" class="<?= $activePage === 'product-bulk-add' ? 'active' : '' ?>">
            <i class="fas fa-table"></i> Bulk Add Products
        </a>
        <a href="categories.php" class="<?= $activePage === 'categories' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> Categories
        </a>
        <a href="units.php" class="<?= $activePage === 'units' ? 'active' : '' ?>">
            <i class="fas fa-ruler"></i> Units
        </a>
        <a href="doctors.php" class="<?= $activePage === 'doctors' ? 'active' : '' ?>">
            <i class="fas fa-user-md"></i> Doctors
        </a>
    </nav>

    <div class="sidebar-section">Sales</div>
    <nav>
        <a href="sales.php" class="<?= $activePage === 'sales' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> Sales
        </a>
        <a href="sale-add.php" class="<?= $activePage === 'sale-add' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i> New Sale
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
            <span class="badge <?= $roleBadge ?>"><?= $roleLabel ?></span>
            <div class="avatar-circle"><?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)) ?></div>
            <span class="fw-semibold small d-none d-sm-inline"><?= sanitize($currentUser['full_name'] ?? $currentUser['username']) ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-danger ms-2"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
    <div class="main-content">
