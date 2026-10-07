<?php
/**
 * Admin: Dashboard
 */

$pageTitle = 'Dashboard';
$pageHeader = 'Student Affairs Governance Dashboard';
$pageSubtitle = 'Overview of solicitation requests, returned papers and collected funds.';

require_once __DIR__ . '/../config/auth.php';
require_login('admin');

$pending    = (int)$pdo->query("SELECT COUNT(*) FROM solicitation_requests WHERE status='pending'")->fetchColumn();
$approved   = (int)$pdo->query("SELECT COUNT(*) FROM solicitation_requests WHERE status='approved'")->fetchColumn();
$totalFunds = (float)$pdo->query("SELECT COALESCE(SUM(amount_collected),0) FROM solicitation_returns WHERE papers_returned='yes'")->fetchColumn();
$returned   = (int)$pdo->query("SELECT COUNT(*) FROM solicitation_returns WHERE papers_returned='yes'")->fetchColumn();
$tracked    = (int)$pdo->query("SELECT COUNT(*) FROM solicitation_returns")->fetchColumn();
$returnRate = $tracked > 0 ? round(($returned / $tracked) * 100, 1) : 0;

$recentRequests = $pdo->query("SELECT r.id, r.control_no, r.event_name, r.status, o.acronym, o.org_name
                               FROM solicitation_requests r
                               JOIN organizations o ON r.org_id = o.id
                               ORDER BY r.id DESC LIMIT 5")->fetchAll();

$recentReturns = $pdo->query("SELECT ret.*, o.acronym
                              FROM solicitation_returns ret
                              JOIN solicitation_requests r ON ret.request_id = r.id
                              JOIN organizations o ON r.org_id = o.id
                              ORDER BY ret.id DESC LIMIT 5")->fetchAll();

$statusPage = ['pending' => 'pending.php', 'approved' => 'approved.php', 'denied' => 'denied.php'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $pending ?></h3><p>Pending Requests</p></div>
        <div class="kpi-icon icon-yellow"><i class="fa-solid fa-hourglass-half"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $approved ?></h3><p>Approved Drives</p></div>
        <div class="kpi-icon icon-forest"><i class="fa-solid fa-file-circle-check"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3>PHP <?= number_format($totalFunds, 2) ?></h3><p>Total Funds Logged</p></div>
        <div class="kpi-icon icon-lime"><i class="fa-solid fa-coins"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $returnRate ?>%</h3><p>Paper Return Rate</p></div>
        <div class="kpi-icon icon-blue"><i class="fa-solid fa-clipboard-check"></i></div>
    </div>
</div>

<div class="card banner-card" style="margin-bottom:24px;">
    <div class="card-body banner-body">
        <div>
            <h2>Governance &amp; Solicitation Management</h2>
            <p>You have <?= $pending ?> pending solicitation request(s) awaiting letter review and a decision.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="pending.php" class="btn btn-lime"><i class="fa-solid fa-clock-rotate-left"></i> Review Pending (<?= $pending ?>)</a>
            <a href="funds.php" class="btn btn-blue"><i class="fa-solid fa-money-bill-transfer"></i> Audit Org Funds</a>
        </div>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-list-check" style="color:var(--royal-green);"></i> Recent Solicitation Requests</div>
            <a href="pending.php" class="btn btn-sm btn-outline-primary">View Pending</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr><th>Control No</th><th>Organization</th><th>Event</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentRequests)): ?>
                            <tr><td colspan="4" class="empty-cell">No requests found.</td></tr>
                        <?php else: foreach ($recentRequests as $r): ?>
                            <tr>
                                <td>
                                    <a class="control-pill" href="<?= $statusPage[$r['status']] ?? 'pending.php' ?>">
                                        <?= htmlspecialchars($r['control_no']) ?>
                                    </a>
                                </td>
                                <td><strong><?= htmlspecialchars($r['acronym']) ?></strong></td>
                                <td><?= htmlspecialchars($r['event_name']) ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-receipt" style="color:var(--lemon-lime);"></i> Returned Solicitations by Students</div>
            <a href="approved.php" class="btn btn-sm btn-outline-primary">Manage Returns</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr><th>Student</th><th>Org</th><th>Returned?</th><th>Amount</th><th>Audit</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentReturns)): ?>
                            <tr><td colspan="5" class="empty-cell">No returned records logged.</td></tr>
                        <?php else: foreach ($recentReturns as $ret): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($ret['student_name']) ?></strong><br>
                                    <small style="color:var(--text-muted);"><?= htmlspecialchars($ret['student_id'] ?? 'N/A') ?></small>
                                </td>
                                <td><?= htmlspecialchars($ret['acronym']) ?></td>
                                <td>
                                    <?php if ($ret['papers_returned'] === 'yes'): ?>
                                        <span class="badge badge-approved">Yes</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>PHP <?= number_format((float)$ret['amount_collected'], 2) ?></strong></td>
                                <td>
                                    <?php $as = $ret['audit_status']; ?>
                                    <span class="badge <?= $as === 'audited' ? 'badge-approved' : ($as === 'discrepancy' ? 'badge-denied' : 'badge-pending') ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $as)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
