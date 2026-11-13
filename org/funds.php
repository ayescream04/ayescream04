<?php
/** ORG Portal: View Funds (read-only) */
$pageTitle = 'View Funds';
$pageHeader = 'Funds Collected';
$pageSubtitle = 'Returned solicitation papers, amounts collected and audit results for your organization.';

require_once __DIR__ . '/../config/auth.php';
require_login('org');
$orgId = (int) current_user()['org_id'];
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

$ret = $pdo->prepare("SELECT ret.*, r.control_no, r.event_name
                      FROM solicitation_returns ret JOIN solicitation_requests r ON ret.request_id = r.id
                      WHERE r.org_id = ? ORDER BY ret.id DESC");
$ret->execute([$orgId]);
$returns = $ret->fetchAll();

$aud = $pdo->prepare("SELECT fa.*, r.control_no, r.event_name
                      FROM funds_audit fa JOIN solicitation_requests r ON fa.request_id = r.id
                      WHERE fa.org_id = ? ORDER BY fa.audited_at DESC");
$aud->execute([$orgId]);
$audits = $aud->fetchAll();

$total = 0.0;
foreach ($returns as $x) { if ($x['papers_returned'] === 'yes') { $total += (float)$x['amount_collected']; } }
$audited = array_sum(array_map(fn($a) => (float)$a['audited_amount'], $audits));

require_once __DIR__ . '/../includes/header.php';
?>
<div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card"><div class="kpi-info"><h3>PHP <?= number_format($total, 2) ?></h3><p>Total Collected</p></div><div class="kpi-icon icon-lime"><i class="fa-solid fa-wallet"></i></div></div>
    <div class="kpi-card"><div class="kpi-info"><h3>PHP <?= number_format($audited, 2) ?></h3><p>Total Audited</p></div><div class="kpi-icon icon-forest"><i class="fa-solid fa-stamp"></i></div></div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-receipt" style="color:var(--lemon-lime);"></i> Student Returns</div></div>
    <div class="card-body" style="padding:0;"><div class="table-responsive">
        <table class="custom-table">
            <thead><tr><th>Control No</th><th>Event</th><th>Student</th><th>Returned?</th><th>Date</th><th>Amount</th><th>Receipt</th><th>Audit</th></tr></thead>
            <tbody>
            <?php if (!$returns): ?><tr><td colspan="8" class="empty-cell">No return records yet.</td></tr>
            <?php else: foreach ($returns as $x): ?>
                <tr>
                    <td><span class="control-pill"><?= htmlspecialchars($x['control_no']) ?></span></td>
                    <td><?= htmlspecialchars($x['event_name']) ?></td>
                    <td><strong><?= htmlspecialchars($x['student_name']) ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($x['student_id'] ?? '') ?></small></td>
                    <td><span class="badge <?= $x['papers_returned'] === 'yes' ? 'badge-approved' : 'badge-pending' ?>"><?= $x['papers_returned'] === 'yes' ? 'Yes' : 'No' ?></span></td>
                    <td><?= htmlspecialchars($x['date_returned'] ?? '—') ?></td>
                    <td><strong>PHP <?= number_format($x['amount_collected'], 2) ?></strong></td>
                    <td><?= htmlspecialchars($x['receipt_no'] ?? '—') ?></td>
                    <td><?php $as = $x['audit_status']; ?><span class="badge <?= $as === 'audited' ? 'badge-approved' : ($as === 'discrepancy' ? 'badge-denied' : 'badge-pending') ?>"><?= htmlspecialchars(str_replace('_', ' ', $as)) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-stamp" style="color:var(--royal-green);"></i> Formal Audit Records</div></div>
    <div class="card-body" style="padding:0;"><div class="table-responsive">
        <table class="custom-table">
            <thead><tr><th>Control No</th><th>Event</th><th>Audited Amount</th><th>Status</th><th>Audited By</th><th>Notes</th></tr></thead>
            <tbody>
            <?php if (!$audits): ?><tr><td colspan="6" class="empty-cell">No audit records yet.</td></tr>
            <?php else: foreach ($audits as $a): ?>
                <tr>
                    <td><span class="control-pill"><?= htmlspecialchars($a['control_no']) ?></span></td>
                    <td><?= htmlspecialchars($a['event_name']) ?></td>
                    <td><strong>PHP <?= number_format($a['audited_amount'], 2) ?></strong></td>
                    <td><?php $s = $a['audit_status']; ?><span class="badge <?= $s === 'audited' ? 'badge-approved' : ($s === 'discrepancy' ? 'badge-denied' : 'badge-pending') ?>"><?= htmlspecialchars($s) ?></span></td>
                    <td><?= htmlspecialchars($a['audited_by']) ?></td>
                    <td style="font-size:.85rem;"><?= htmlspecialchars($a['audit_notes'] ?? '—') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
