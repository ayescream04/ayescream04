<?php
/** ORG Portal: Return records of all students (with photo proof) */
$pageTitle = 'Return Records';
$pageHeader = 'Return Records';
$pageSubtitle = 'Returned solicitation letters and money of your listed students, with photo proof.';

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/students.php';
require_login('org');
$orgId = (int) current_user()['org_id'];
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

ensure_student_columns($pdo);

$q = $pdo->prepare("SELECT ret.*, r.control_no AS request_control_no, r.event_name
                    FROM solicitation_returns ret JOIN solicitation_requests r ON ret.request_id = r.id
                    WHERE r.org_id = ? ORDER BY ret.id DESC");
$q->execute([$orgId]);
$rows = $q->fetchAll();

$listed = count($rows); $returned = 0; $tentative = 0.0; $collected = 0.0;
foreach ($rows as $x) {
    $tentative += (float) $x['target_amount'];
    if ($x['papers_returned'] === 'yes') { $returned++; $collected += (float) $x['amount_collected']; }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card"><div class="kpi-info"><h3><?= $listed ?></h3><p>Students Listed</p></div><div class="kpi-icon icon-blue"><i class="fa-solid fa-user-graduate"></i></div></div>
    <div class="kpi-card"><div class="kpi-info"><h3><?= $returned ?></h3><p>Returned</p></div><div class="kpi-icon icon-forest"><i class="fa-solid fa-clipboard-check"></i></div></div>
    <div class="kpi-card"><div class="kpi-info"><h3>PHP <?= number_format($tentative, 2) ?></h3><p>Tentative Amount</p></div><div class="kpi-icon icon-yellow"><i class="fa-solid fa-bullseye"></i></div></div>
    <div class="kpi-card"><div class="kpi-info"><h3>PHP <?= number_format($collected, 2) ?></h3><p>Amount Returned</p></div><div class="kpi-icon icon-lime"><i class="fa-solid fa-coins"></i></div></div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-receipt" style="color:var(--lemon-lime);"></i> Student Return Records (<?= $listed ?>)</div>
        <a href="students.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-user-plus"></i> Student Records</a>
    </div>
    <div class="card-body" style="padding:0;"><div class="table-responsive">
        <table class="custom-table">
            <thead><tr><th>Drive</th><th>Control No</th><th>Student</th><th>Tentative</th><th>Returned?</th><th>Date</th><th>Amount</th><th>Receipt</th><th>Proof</th><th>Audit</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="11" class="empty-cell">No students listed yet.</td></tr>
            <?php else: foreach ($rows as $x): $ok = $x['papers_returned'] === 'yes';
                $photo = !empty($x['proof_photo']) ? '../assets/uploads/return_proofs/' . rawurlencode(basename($x['proof_photo'])) : ''; ?>
                <tr>
                    <td><span class="control-pill"><?= htmlspecialchars($x['request_control_no']) ?></span><br><small style="color:var(--text-muted);"><?= htmlspecialchars($x['event_name']) ?></small></td>
                    <td><span class="control-pill"><?= htmlspecialchars($x['control_no'] ?? '') ?></span></td>
                    <td><strong><?= htmlspecialchars($x['student_name']) ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($x['student_id'] ?? '') ?></small></td>
                    <td>PHP <?= number_format((float)$x['target_amount'], 2) ?></td>
                    <td><span class="badge <?= $ok ? 'badge-approved' : 'badge-pending' ?>"><?= $ok ? 'Yes' : 'No' ?></span></td>
                    <td><?= htmlspecialchars($x['date_returned'] ?? '—') ?></td>
                    <td><strong>PHP <?= number_format((float)$x['amount_collected'], 2) ?></strong></td>
                    <td><?= htmlspecialchars($x['receipt_no'] ?: '—') ?></td>
                    <td><?php if ($photo): ?><a href="<?= htmlspecialchars($photo) ?>" target="_blank" title="Open photo"><img src="<?= htmlspecialchars($photo) ?>" alt="Proof" style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid var(--border-color);"></a><?php else: ?>—<?php endif; ?></td>
                    <td><?php $as = $x['audit_status']; ?><span class="badge <?= $as === 'audited' ? 'badge-approved' : ($as === 'discrepancy' ? 'badge-denied' : 'badge-pending') ?>"><?= htmlspecialchars(str_replace('_', ' ', $as)) ?></span></td>
                    <td style="text-align:right;"><a class="btn btn-sm btn-blue" href="student.php?id=<?= (int)$x['id'] ?>"><?= $ok ? 'View' : 'Record return' ?></a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>