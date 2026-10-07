<?php
/** ORG Portal: My Pending Solicitations */
$pageTitle = 'Pending Solicitation';
$pageHeader = 'Pending Solicitation Requests';
$pageSubtitle = 'Requests that are still awaiting review by the Office of Student Affairs.';

require_once __DIR__ . '/../config/auth.php';
require_login('org');
$orgId = (int) current_user()['org_id'];
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM solicitation_requests WHERE org_id = ? AND status = 'pending' ORDER BY datereq ASC");
$stmt->execute([$orgId]);
$rows = $stmt->fetchAll();

$den = $pdo->prepare("SELECT * FROM solicitation_requests WHERE org_id = ? AND status = 'denied' ORDER BY approved_at DESC");
$den->execute([$orgId]);
$denied = $den->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-hourglass-half" style="color:#f39c12;"></i> Awaiting Review (<?= count($rows) ?>)</div>
        <a href="create_request.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus"></i> New Request</a></div>
    <div class="card-body" style="padding:0;"><div class="table-responsive">
        <table class="custom-table">
            <thead><tr><th>Control No</th><th>Event</th><th>Target</th><th>Requested</th><th>Needed</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="5" class="empty-cell">No pending requests.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><span class="control-pill"><?= htmlspecialchars($r['control_no']) ?></span></td>
                    <td><strong><?= htmlspecialchars($r['event_name']) ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($r['purpose']) ?></small></td>
                    <td>PHP <?= number_format($r['target_amount'], 2) ?></td>
                    <td><?= htmlspecialchars($r['datereq']) ?></td>
                    <td><?= htmlspecialchars($r['date_needed']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<?php if ($denied): ?>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-file-circle-xmark" style="color:#e74c3c;"></i> Denied Requests (<?= count($denied) ?>)</div></div>
    <div class="card-body" style="padding:0;"><div class="table-responsive">
        <table class="custom-table">
            <thead><tr><th>Control No</th><th>Event</th><th>Reason</th></tr></thead>
            <tbody>
            <?php foreach ($denied as $r): ?>
                <tr>
                    <td><span class="control-pill"><?= htmlspecialchars($r['control_no']) ?></span></td>
                    <td><?= htmlspecialchars($r['event_name']) ?></td>
                    <td><?= htmlspecialchars($r['admin_remarks'] ?? 'No reason provided.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
