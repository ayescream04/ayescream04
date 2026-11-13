<?php
/**
 * Admin: Denied Requests
 * View denied solicitation requests
 */

$pageTitle = 'Denied Requests';
$pageHeader = 'Denied Solicitation Requests';
$pageSubtitle = 'Review all rejected solicitation requests and their denial reasons.';

require_once __DIR__ . '/../config/auth.php';
require_login('admin');

// Fetch all denied requests
$stmt = $pdo->query("SELECT r.*, o.org_name, o.acronym, o.president, o.adviser, o.email 
                     FROM solicitation_requests r 
                     JOIN organizations o ON r.org_id = o.id 
                     WHERE r.status = 'denied' 
                     ORDER BY r.approved_at DESC");
$deniedRequests = $stmt->fetchAll();

// Header outputs HTML, so it is included only after all redirects/POST handling.
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-file-circle-xmark" style="color:#e74c3c;"></i>
            <span>Denied Solicitation Requests (<?= count($deniedRequests) ?>)</span>
        </div>
        <input type="text" class="form-control" style="width:250px; padding:6px 12px; font-size:0.85rem;" 
               placeholder="Search organization or event..." data-table-search="deniedTable">
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="custom-table" id="deniedTable">
                <thead>
                    <tr>
                        <th>Control No</th>
                        <th>Organization</th>
                        <th>Event / Purpose</th>
                        <th>Tentative Amount</th>
                        <th>Dates</th>
                        <th>Denied By</th>
                        <th>Denial Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deniedRequests)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">
                                <i class="fa-solid fa-circle-check" style="font-size:2.2rem; color:var(--lemon-lime); margin-bottom:8px; display:block;"></i>
                                No denied requests on record.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deniedRequests as $req): ?>
                        <tr>
                            <td>
                                <span style="font-family:monospace; font-weight:700; color:#c0392b;"><?= htmlspecialchars($req['control_no']) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($req['org_name']) ?></strong>
                            </td>
                            <td style="max-width:280px;">
                                <strong><?= htmlspecialchars($req['event_name']) ?></strong>
                                <p style="font-size:0.82rem; color:#555; margin-top:3px; line-height:1.3;"><?= htmlspecialchars($req['purpose']) ?></p>
                            </td>
                            <td>PHP <?= number_format($req['target_amount'],2) ?></td>
                            <td>
                                <small>
                                    Req: <?= htmlspecialchars($req['datereq']) ?><br>
                                    Needed: <strong style="color:#c0392b;"><?= htmlspecialchars($req['date_needed']) ?></strong>
                                </small>
                            </td>
                            <td>
                                <?= htmlspecialchars($req['approved_by'] ?? 'N/A') ?><br>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($req['approved_at'] ?? '') ?></small>
                            </td>
                            <td style="max-width:250px; font-size:0.85rem;">
                                <div style="background:#fde8e8; border-left:3px solid #e74c3c; padding:6px 10px; border-radius:4px; color:#721c24;">
                                    <?= htmlspecialchars($req['admin_remarks'] ?? 'No reason provided.') ?>
                                </div>
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