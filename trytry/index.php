<?php
/**
 * ORG Portal: Dashboard / Home
 */

$pageTitle = 'Org Dashboard';
$pageHeader = 'Organization Dashboard';
$pageSubtitle = 'Overview of your solicitation drives, status, and fund summaries.';

require_once __DIR__ . '/../config/auth.php';
require_login('org');

$orgId = current_user()['org_id'];
if (!$orgId) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Fetch org info
$orgStmt = $pdo->prepare("SELECT * FROM organizations WHERE id=?");
$orgStmt->execute([$orgId]);
$org = $orgStmt->fetch();

// KPIs
$totalPending  = $pdo->prepare("SELECT COUNT(*) FROM solicitation_requests WHERE org_id=? AND status='pending'");
$totalPending->execute([$orgId]); $cntPending = $totalPending->fetchColumn();

$totalApproved = $pdo->prepare("SELECT COUNT(*) FROM solicitation_requests WHERE org_id=? AND status='approved'");
$totalApproved->execute([$orgId]); $cntApproved = $totalApproved->fetchColumn();

$totalDenied = $pdo->prepare("SELECT COUNT(*) FROM solicitation_requests WHERE org_id=? AND status='denied'");
$totalDenied->execute([$orgId]); $cntDenied = $totalDenied->fetchColumn();

$fundStmt = $pdo->prepare("SELECT COALESCE(SUM(ret.amount_collected),0) 
    FROM solicitation_returns ret 
    JOIN solicitation_requests r ON ret.request_id=r.id 
    WHERE r.org_id=? AND ret.papers_returned='yes'");
$fundStmt->execute([$orgId]); $totalFunds = $fundStmt->fetchColumn();

// Recent requests
$recentStmt = $pdo->prepare("SELECT * FROM solicitation_requests WHERE org_id=? ORDER BY id DESC LIMIT 5");
$recentStmt->execute([$orgId]);
$recentRequests = $recentStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card" style="background:linear-gradient(135deg,#054204,#1D6517); color:#fff; border:none; margin-bottom:24px;">
    <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
        <div>
            <h2 style="font-size:1.2rem; font-weight:700; color:#b6f0a7; margin-bottom:4px;">
                <i class="fa-solid fa-flag"></i> Welcome, <?= htmlspecialchars($org['acronym'] ?? '') ?>!
            </h2>
            <p style="font-size:0.88rem; color:#e8ffe4; margin:0;"><?= htmlspecialchars($org['org_name'] ?? '') ?></p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="create_request.php" class="btn btn-lime">
                <i class="fa-solid fa-circle-plus"></i> New Solicitation
            </a>
            <a href="approved.php" class="btn btn-blue">
                <i class="fa-solid fa-file-word"></i> Download DOCX
            </a>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="margin-bottom:28px;">
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $cntPending ?></h3><p>Pending Requests</p></div>
        <div class="kpi-icon icon-yellow"><i class="fa-solid fa-hourglass-half"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $cntApproved ?></h3><p>Approved Drives</p></div>
        <div class="kpi-icon icon-forest"><i class="fa-solid fa-file-circle-check"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $cntDenied ?></h3><p>Denied Requests</p></div>
        <div class="kpi-icon" style="background:#fde8e8; color:#e74c3c;"><i class="fa-solid fa-ban"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3>PHP <?= number_format($totalFunds,2) ?></h3><p>Funds Collected</p></div>
        <div class="kpi-icon icon-lime"><i class="fa-solid fa-wallet"></i></div>
    </div>
</div>

<!-- Recent Requests -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-list-check" style="color:var(--royal-green);"></i>
            <span>My Recent Solicitation Requests</span>
        </div>
        <a href="create_request.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> New Request</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Control No</th>
                        <th>Event</th>
                        <th>Target Amount</th>
                        <th>Date Requested</th>
                        <th>Date Needed</th>
                        <th>Status</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRequests)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:35px; color:var(--text-muted);">
                            <i class="fa-solid fa-file-circle-plus" style="font-size:2rem; margin-bottom:8px; display:block;"></i>
                            No solicitation requests yet. Start by creating your first request.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($recentRequests as $req): ?>
                        <tr>
                            <td><span style="font-family:monospace; font-weight:700; color:var(--forest-green);"><?= htmlspecialchars($req['control_no']) ?></span></td>
                            <td><?= htmlspecialchars($req['event_name']) ?></td>
                            <td>PHP <?= number_format($req['target_amount'],2) ?></td>
                            <td><?= htmlspecialchars($req['datereq']) ?></td>
                            <td><strong style="color:#c0392b;"><?= htmlspecialchars($req['date_needed']) ?></strong></td>
                            <td><span class="badge badge-<?= htmlspecialchars($req['status']) ?>"><?= htmlspecialchars(ucfirst($req['status'])) ?></span></td>
                            <td style="text-align:right;">
                                <?php if ($req['status'] === 'approved'): ?>
                                <a href="approved.php" class="btn btn-sm btn-blue">
                                    <i class="fa-solid fa-file-word"></i> View DOCX
                                </a>
                                <?php else: ?>
                                <span style="font-size:0.8rem; color:var(--text-muted);">
                                    <?= $req['status'] === 'pending' ? '⏳ Awaiting review' : '✗ Denied' ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>