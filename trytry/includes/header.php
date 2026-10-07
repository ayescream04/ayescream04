<?php
/**
 * Common Header Layout
 * Unified Student Organization Governance & Solicitation Tracking System
 */

require_once __DIR__ . '/../config/auth.php';

$currentUser = current_user();
$currentRole = $currentUser['role'] ?? 'guest';
$currentPage = basename($_SERVER['PHP_SELF']);

// Count Pending Requests for badge
$pendingCount = 0;
try {
    if ($currentRole === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM solicitation_requests WHERE status = 'pending'");
        $pendingCount = $stmt->fetch()['cnt'] ?? 0;
    } elseif ($currentRole === 'org' && !empty($currentUser['org_id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM solicitation_requests WHERE org_id = ? AND status = 'pending'");
        $stmt->execute([$currentUser['org_id']]);
        $pendingCount = $stmt->fetch()['cnt'] ?? 0;
    }
} catch (Exception $e) {
    $pendingCount = 0;
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>Student Org Governance & Solicitation Tracking</title>
    
    <!-- Google Fonts & Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Application Stylesheet -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Chart.js for Statistics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <!-- docx.js for Client-Side DOCX Generation -->
    <script src="https://cdn.jsdelivr.net/npm/docx@8.5.0/build/index.min.js"></script>
</head>
<body>
<div class="app-container">
    
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo-icon" style="background:none;">
                <img src="../bcclogo.jfif" alt="BCC" style="width:40px;height:40px;object-fit:contain;">
            </div>
            <div class="sidebar-brand">
                <h2>SOL-TRACK</h2>
                <span>Org Governance</span>
            </div>
        </div>

        <div class="sidebar-user">
            <span class="role-badge"><?= strtoupper(htmlspecialchars($currentUser['role'] ?? 'USER')) ?></span>
            <div class="user-name" title="<?= htmlspecialchars($currentUser['name'] ?? 'User') ?>">
                <?= htmlspecialchars($currentUser['name'] ?? 'Guest User') ?>
            </div>
        </div>

        <ul class="sidebar-nav">
            <?php if ($currentRole === 'admin'): ?>
                <div class="nav-section-title">ADMIN GOVERNANCE</div>
                
                <li>
                    <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="pending.php" class="<?= $currentPage === 'pending.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Pending Requests</span>
                        <?php if ($pendingCount > 0): ?>
                            <span class="nav-badge"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="approved.php" class="<?= $currentPage === 'approved.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-circle-check"></i>
                        <span>Approved Requests</span>
                    </a>
                </li>
                <li>
                    <a href="denied.php" class="<?= $currentPage === 'denied.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-circle-xmark"></i>
                        <span>Denied Requests</span>
                    </a>
                </li>
                <li>
                    <a href="funds.php" class="<?= $currentPage === 'funds.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                        <span>Funds Audit</span>
                    </a>
                </li>
                <li>
                    <a href="organizations.php" class="<?= $currentPage === 'organizations.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-sitemap"></i>
                        <span>Organizations</span>
                    </a>
                </li>
                <li>
                    <a href="statistics.php" class="<?= $currentPage === 'statistics.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Statistics</span>
                    </a>
                </li>

            <?php elseif ($currentRole === 'org'): ?>
                <div class="nav-section-title">ORGANIZATION PORTAL</div>
                
                <li>
                    <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-house"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-id-card"></i>
                        <span>Org Profile</span>
                    </a>
                </li>
                <li>
                    <a href="create_request.php" class="<?= $currentPage === 'create_request.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-circle-plus"></i>
                        <span>Create Solicitation</span>
                    </a>
                </li>
                <li>
                    <a href="pending.php" class="<?= $currentPage === 'pending.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-hourglass-half"></i>
                        <span>Pending Solicitation</span>
                        <?php if ($pendingCount > 0): ?>
                            <span class="nav-badge"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="approved.php" class="<?= $currentPage === 'approved.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>Approved & DOCX</span>
                    </a>
                </li>
                <li>
                    <a href="funds.php" class="<?= $currentPage === 'funds.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-wallet"></i>
                        <span>View Funds</span>
                    </a>
                </li>
                <li>
                    <a href="statistics.php" class="<?= $currentPage === 'statistics.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Statistics</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-footer">
            <a href="../logout.php">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Top Navbar -->
        <header class="top-navbar no-print">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="mobileSidebarToggle" class="btn btn-icon btn-outline-primary" style="display: none;">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="page-title">
                    <h1><?= isset($pageHeader) ? htmlspecialchars($pageHeader) : 'Dashboard' ?></h1>
                    <p><?= isset($pageSubtitle) ? htmlspecialchars($pageSubtitle) : 'Unified Student Organization Governance & Solicitation Tracking' ?></p>
                </div>
            </div>

            <div class="top-navbar-actions">
                <div class="datetime-display">
                    <i class="fa-regular fa-calendar-check" style="color: var(--royal-green);"></i>
                    <span><?= date('l, F j, Y') ?></span>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <main class="content-area">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : ($flash['type'] === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-info') ?>"></i>
                    <div><?= htmlspecialchars($flash['message']) ?></div>
                </div>
            <?php endif; ?>
