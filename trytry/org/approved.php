<?php
/** ORG Portal: Approved requests + solicitation letter DOCX */
$pageTitle = 'Approved & DOCX';
$pageHeader = 'Approved Solicitations';
$pageSubtitle = 'Preview and download the official solicitation letter for each approved drive.';

require_once __DIR__ . '/../config/auth.php';
require_login('org');
$orgId = (int) current_user()['org_id'];
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

// Make sure the logo column exists (it is normally created from the Org Profile page)
try {
    if (!$pdo->query("SHOW COLUMNS FROM organizations LIKE 'logo'")->fetch()) {
        $pdo->exec("ALTER TABLE organizations ADD COLUMN logo VARCHAR(255) NULL DEFAULT NULL");
    }
} catch (PDOException $e) { /* ignore */ }

$stmt = $pdo->prepare("SELECT r.*, o.org_name, o.acronym, o.president, o.adviser, o.email, o.logo AS org_logo
                       FROM solicitation_requests r JOIN organizations o ON r.org_id = o.id
                       WHERE r.org_id = ? AND r.status = 'approved' ORDER BY r.approved_at DESC");
$stmt->execute([$orgId]);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-file-invoice-dollar" style="color:var(--royal-green);"></i> Approved Drives (<?= count($rows) ?>)</div></div>
    <div class="card-body" style="padding:0;"><div class="table-responsive">
        <table class="custom-table">
            <thead><tr><th>Control No</th><th>Event</th><th>Target</th><th>Approved By</th><th>Remarks</th><th style="text-align:right;">Letter</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="empty-cell">No approved requests yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><span class="control-pill"><?= htmlspecialchars($r['control_no']) ?></span></td>
                    <td><strong><?= htmlspecialchars($r['event_name']) ?></strong></td>
                    <td>PHP <?= number_format($r['target_amount'], 2) ?></td>
                    <td><?= htmlspecialchars($r['approved_by'] ?? 'N/A') ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars($r['approved_at'] ?? '') ?></small></td>
                    <td style="max-width:260px; font-size:.85rem;"><?= htmlspecialchars($r['admin_remarks'] ?? '—') ?></td>
                    <td style="text-align:right;">
                        <button type="button" class="btn btn-sm btn-blue" onclick="openSolicitationLetterModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="fa-solid fa-file-word"></i> View / Download DOCX
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>