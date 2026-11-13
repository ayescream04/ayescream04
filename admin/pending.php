<?php
/**
 * Admin: View Pending Requests
 * Review pending requests and approve or deny (no letter is created at approval)
 */

$pageTitle = 'Pending Solicitation Requests';
$pageHeader = 'Pending Solicitation Requests';
$pageSubtitle = 'Review incoming student organization solicitation drives and approve or deny.';

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_login('admin');

// Handle Approval or Denial
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action       = $_POST['action'] ?? '';
    $requestId    = intval($_POST['request_id'] ?? 0);
    $adminRemarks = trim($_POST['admin_remarks'] ?? '');
    $approverName = trim($_POST['approver_name'] ?? '');
    if ($approverName === '') {
        $approverName = 'Office of Student Affairs';
    }

    if ($requestId > 0 && in_array($action, ['approve', 'deny'], true)) {
        if ($action === 'deny' && $adminRemarks === '') {
            set_flash('danger', 'A reason is required when denying a request.');
        } else {
            $newStatus = ($action === 'approve') ? 'approved' : 'denied';
            $stmt = $pdo->prepare("UPDATE solicitation_requests
                                   SET status = ?,
                                       admin_remarks = ?,
                                       approved_by = ?,
                                       approved_at = CURRENT_TIMESTAMP
                                   WHERE id = ? AND status = 'pending'");
            $stmt->execute([$newStatus, $adminRemarks, $approverName, $requestId]);

            if ($stmt->rowCount() > 0) {
                // Tell the organization about the new status (shown when they open their account)
                try {
                    notify_request_decision($pdo, $requestId, $newStatus, $approverName, $adminRemarks);
                } catch (PDOException $e) { /* the decision is saved even if the notification fails */ }
            }

            if ($stmt->rowCount() === 0) {
                set_flash('danger', 'This request was already processed or no longer exists.');
            } elseif ($action === 'approve') {
                set_flash('success', 'Solicitation request APPROVED. The organization can now list its student solicitors and create their letters.');
            } else {
                set_flash('danger', 'Solicitation request has been DENIED.');
            }
        }
    }
    header('Location: pending.php');
    exit;
}

// Fetch all pending requests
$stmt = $pdo->query("SELECT r.*, o.org_name, o.acronym, o.president, o.adviser, o.email, o.logo AS org_logo 
                     FROM solicitation_requests r 
                     JOIN organizations o ON r.org_id = o.id 
                     WHERE r.status = 'pending' 
                     ORDER BY r.datereq ASC");
$pendingRequests = $stmt->fetchAll();

// Header outputs HTML, so it is included only after all redirects/POST handling.
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-clock-rotate-left" style="color: #f39c12;"></i>
            <span>Pending Solicitation Queue (<?= count($pendingRequests) ?>)</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <input type="text" class="form-control" style="width: 250px; padding: 6px 12px; font-size: 0.85rem;" placeholder="Search organization or event..." data-table-search="pendingTable">
        </div>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="custom-table" id="pendingTable">
                <thead>
                    <tr>
                        <th>Control No</th>
                        <th>Organization</th>
                        <th>Event & Purpose</th>
                        <th>Tentative Amount</th>
                        <th>Dates</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingRequests)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 35px; color: var(--text-muted);">
                                <i class="fa-solid fa-circle-check" style="font-size: 2.2rem; color: var(--lemon-lime); margin-bottom: 8px; display: block;"></i>
                                All clear! There are currently no pending solicitation requests awaiting review.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pendingRequests as $req): ?>
                            <tr>
                                <td>
                                    <span class="control-pill"><?= htmlspecialchars($req['control_no']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($req['org_name']) ?></strong><br>
                                    <small style="color: var(--text-muted);">Chairman: <?= htmlspecialchars($req['president']) ?></small>
                                </td>
                                <td style="max-width: 320px;">
                                    <strong><?= htmlspecialchars($req['event_name']) ?></strong>
                                    <p style="font-size: 0.82rem; color: #444; margin-top: 4px; line-height: 1.3;">
                                        <?= htmlspecialchars($req['purpose']) ?>
                                    </p>
                                </td>
                                <td>
                                    <strong style="color: var(--forest-green); font-size: 1rem;">
                                        PHP <?= number_format($req['target_amount'], 2) ?>
                                    </strong>
                                </td>
                                <td>
                                    <small style="color: var(--text-muted);">
                                        Req: <?= htmlspecialchars($req['datereq']) ?><br>
                                        Needed: <strong style="color: #c0392b;"><?= htmlspecialchars($req['date_needed']) ?></strong>
                                    </small>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openReviewModal(<?= htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fa-solid fa-file-pen"></i> Review & Decide
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Review request and Approve / Deny -->
<div id="reviewModal" class="modal-overlay">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-signature" style="color: var(--royal-green);"></i> Review Solicitation Request</h3>
            <button type="button" class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
        </div>

        <form method="POST" action="pending.php" id="reviewForm">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" id="modalRequestId">
            <input type="hidden" name="action" id="modalAction" value="approve">

            <div class="modal-body">
                <!-- Request Summary Card -->
                <div style="background: var(--bg-light); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px 18px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div>
                            <span class="control-pill" id="modalControlNo">SOL-2026-XXXX</span>
                            <span style="margin-left: 10px; font-weight: 700; color: var(--forest-green);" id="modalOrgName">Org Name</span>
                        </div>
                        <span class="badge badge-pending">Pending Review</span>
                    </div>
                    <div class="form-row" style="font-size: 0.88rem;">
                        <div class="form-col"><strong>Event:</strong> <span id="modalEventName"></span></div>
                        <div class="form-col"><strong>Tentative Amount:</strong> <span id="modalTargetAmount"></span></div>
                        <div class="form-col"><strong>Date Needed:</strong> <span id="modalDateNeeded"></span></div>
                    </div>
                </div>

                <h4 style="font-size: 0.95rem; color: var(--forest-green); margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                    <i class="fa-solid fa-pen-fancy"></i> Decision Details
                </h4>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label required">Approved / Decided by</label>
                        <input type="text" name="approver_name" id="modalApproverName" class="form-control" value="Dean of Student Affairs" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Review Remarks / Conditions</label>
                    <textarea name="admin_remarks" id="modalAdminRemarks" class="form-control" placeholder="Specify any institutional conditions, approved quota, or reasons for approval/denial..."></textarea>
                </div>
            </div>

            <div class="modal-footer" style="justify-content: space-between;">
                <button type="button" class="btn btn-outline-danger" onclick="submitDecision('deny')">
                    <i class="fa-solid fa-ban"></i> Deny Request
                </button>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-outline-primary" onclick="closeModal('reviewModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitDecision('approve')">
                        <i class="fa-solid fa-check-circle"></i> Approve Request
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentSelectedReq = null;

function openReviewModal(req) {
    currentSelectedReq = req;
    document.getElementById('modalRequestId').value = req.id;
    document.getElementById('modalControlNo').textContent = req.control_no;
    document.getElementById('modalOrgName').textContent = req.org_name;
    document.getElementById('modalEventName').textContent = req.event_name;
    document.getElementById('modalTargetAmount').textContent = 'PHP ' + Number(req.target_amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('modalDateNeeded').textContent = req.date_needed;
    document.getElementById('modalAdminRemarks').value = req.admin_remarks || 'Approved as per Office of Student Affairs guidelines. Ensure all returned letters and cash collections are audited within 5 business days after the event.';
    
    openModal('reviewModal');
}

function submitDecision(action) {
    document.getElementById('modalAction').value = action;
    const remarks = document.getElementById('modalAdminRemarks').value.trim();

    if (action === 'deny') {
        if (!remarks) {
            alert('Please enter remarks/reason explaining why this solicitation request is being denied.');
            document.getElementById('modalAdminRemarks').focus();
            return;
        }
        if (confirm('Are you sure you want to DENY this solicitation request?')) {
            document.getElementById('reviewForm').submit();
        }
    } else {
        if (confirm('Approve this solicitation request? The organization will be able to list its student solicitors and create their letters.')) {
            document.getElementById('reviewForm').submit();
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>