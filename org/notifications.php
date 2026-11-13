<?php
/** ORG Portal: Notifications (status updates on my solicitation requests) */
$pageTitle = 'Notifications';
$pageHeader = 'Notifications';
$pageSubtitle = 'Updates from the Office of Student Affairs about your solicitation requests.';

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_login('org');
$orgId = (int) current_user()['org_id'];
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

ensure_notifications_table($pdo);

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE org_id = ? ORDER BY id DESC LIMIT 100");
$stmt->execute([$orgId]);
$rows = $stmt->fetchAll();

// Opening this page marks everything as read (rows above still remember which ones were new).
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE org_id = ? AND is_read = 0")->execute([$orgId]);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-bell" style="color:var(--carolina-blue);"></i> My Notifications (<?= count($rows) ?>)</div>
        <a href="approved.php" class="btn btn-sm btn-blue"><i class="fa-solid fa-file-word"></i> Approved &amp; DOCX</a>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (!$rows): ?>
            <div class="empty-cell" style="padding:40px; text-align:center; color:var(--text-muted);">
                <i class="fa-regular fa-bell-slash" style="font-size:2rem; display:block; margin-bottom:8px;"></i>
                No notifications yet. You will be notified here when a request is approved or denied.
            </div>
        <?php else: foreach ($rows as $n): $isNew = !(int)$n['is_read']; $ok = $n['type'] === 'approved'; ?>
            <div style="padding:14px 20px; border-bottom:1px solid var(--border-color); border-left:5px solid <?= $ok ? 'var(--lemon-lime)' : '#e74c3c' ?>; background:<?= $isNew ? '#f4fbf3' : '#fff' ?>;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:4px;">
                    <strong style="color:var(--forest-green);">
                        <i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>" style="color:<?= $ok ? 'var(--royal-green)' : '#e74c3c' ?>;"></i>
                        <?= htmlspecialchars($n['title']) ?>
                        <?php if ($isNew): ?><span class="badge badge-pending" style="margin-left:6px;">New</span><?php endif; ?>
                    </strong>
                    <small style="color:var(--text-muted);"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($n['created_at']))) ?></small>
                </div>
                <div style="font-size:.9rem; color:#333;"><?= htmlspecialchars($n['message']) ?></div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
