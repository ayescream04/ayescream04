<?php
/**
 * Admin: View Approved Requests
 * Students returned solicitation letter of an org — manage returns & funds
 */

$pageTitle = 'Approved Requests';
$pageHeader = 'Approved Solicitation Requests';
$pageSubtitle = 'Manage approved drives, track student returned solicitation letters, and log collected funds.';

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/students.php';
require_login('admin');
ensure_student_columns($pdo);

// Handle adding/editing a return record
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $auditOptions = ['pending_audit', 'audited', 'discrepancy'];

    if ($action === 'add_return') {
        $requestId   = intval($_POST['request_id'] ?? 0);
        $studentName = trim($_POST['student_name'] ?? '');
        $studentId   = trim($_POST['student_id'] ?? '');
        $papersRet   = ($_POST['papers_returned'] ?? 'no') === 'yes' ? 'yes' : 'no';
        $dateRet     = !empty($_POST['date_returned']) ? $_POST['date_returned'] : null;
        $amount      = max(0, floatval($_POST['amount_collected'] ?? 0));
        $receiptNo   = trim($_POST['receipt_no'] ?? '');
        $remarks     = trim($_POST['remarks'] ?? '');
        $auditStatus = in_array($_POST['audit_status'] ?? '', $auditOptions, true) ? $_POST['audit_status'] : 'pending_audit';

        $chk = $pdo->prepare("SELECT id FROM solicitation_requests WHERE id = ? AND status = 'approved'");
        $chk->execute([$requestId]);

        if ($requestId > 0 && $studentName !== '' && $chk->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO solicitation_returns
                (request_id, control_no, student_name, student_id, papers_returned, date_returned, amount_collected, receipt_no, remarks, audit_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requestId, next_student_control_no($pdo, $requestId), $studentName, $studentId, $papersRet, $dateRet, $amount, $receiptNo, $remarks, $auditStatus]);
            set_flash('success', 'Return record added successfully.');
        } else {
            set_flash('danger', 'Could not add the record. Check the student name and that the request is approved.');
        }
        header('Location: approved.php');
        exit;
    }

    if ($action === 'update_audit') {
        $returnId    = intval($_POST['return_id'] ?? 0);
        $auditStatus = in_array($_POST['audit_status'] ?? '', $auditOptions, true) ? $_POST['audit_status'] : 'pending_audit';
        $remarks     = trim($_POST['remarks'] ?? '');
        if ($returnId > 0) {
            $stmt = $pdo->prepare("UPDATE solicitation_returns SET audit_status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$auditStatus, $remarks, $returnId]);
            set_flash('success', 'Audit status updated.');
        }
        header('Location: approved.php');
        exit;
    }
}

// Fetch all approved requests
$stmt = $pdo->query("SELECT r.*, o.org_name, o.acronym, o.president, o.adviser, o.email
                     FROM solicitation_requests r 
                     JOIN organizations o ON r.org_id = o.id 
                     WHERE r.status = 'approved' 
                     ORDER BY r.approved_at DESC");
$approvedRequests = $stmt->fetchAll();

// Fetch returns grouped by request_id
$retStmt = $pdo->query("SELECT ret.*, r.control_no AS request_control_no, r.event_name, o.acronym 
                        FROM solicitation_returns ret 
                        JOIN solicitation_requests r ON ret.request_id = r.id 
                        JOIN organizations o ON r.org_id = o.id
                        ORDER BY ret.id DESC");
$allReturns = $retStmt->fetchAll();

// Group returns by request_id
$returnsByRequest = [];
foreach ($allReturns as $ret) {
    $returnsByRequest[$ret['request_id']][] = $ret;
}

// Header outputs HTML, so it is included only after all redirects/POST handling.
require_once __DIR__ . '/../includes/header.php';
?>

<!-- KPI Row -->
<?php
$totalApproved = count($approvedRequests);
$totalReturned = 0; $totalFunds = 0;
foreach ($allReturns as $r) {
    if ($r['papers_returned'] === 'yes') { $totalReturned++; $totalFunds += $r['amount_collected']; }
}
?>
<div class="kpi-grid" style="grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); margin-bottom:24px;">
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $totalApproved ?></h3><p>Approved Drives</p></div>
        <div class="kpi-icon icon-forest"><i class="fa-solid fa-file-circle-check"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= count($allReturns) ?></h3><p>Student Solicitors Tracked</p></div>
        <div class="kpi-icon icon-blue"><i class="fa-solid fa-users"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3><?= $totalReturned ?></h3><p>Papers Returned</p></div>
        <div class="kpi-icon icon-lime"><i class="fa-solid fa-clipboard-check"></i></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-info"><h3>PHP <?= number_format($totalFunds, 2) ?></h3><p>Total Funds Collected</p></div>
        <div class="kpi-icon icon-yellow"><i class="fa-solid fa-coins"></i></div>
    </div>
</div>

<!-- Approved Requests List -->
<?php if (empty($approvedRequests)): ?>
    <div class="card">
        <div class="card-body" style="text-align:center; padding:40px; color:var(--text-muted);">
            <i class="fa-solid fa-inbox" style="font-size:2.5rem; margin-bottom:10px; display:block;"></i>
            No approved solicitation requests found.
        </div>
    </div>
<?php else: ?>
    <?php foreach ($approvedRequests as $req): ?>
    <?php $returns = $returnsByRequest[$req['id']] ?? []; ?>
    <div class="card" style="margin-bottom:28px;">
        <div class="card-header" style="background: linear-gradient(135deg,#f4fbf3,#e8f5e6);">
            <div>
                <span class="control-pill" style="font-family:monospace; font-weight:700; background:#e4f5df; color:var(--forest-green); border:1px solid var(--lemon-lime); padding:4px 10px; border-radius:4px;"><?= htmlspecialchars($req['control_no']) ?></span>
                <strong style="margin-left:12px; font-size:1.02rem;"><?= htmlspecialchars($req['org_name']) ?></strong>
                <span class="badge badge-approved" style="margin-left:8px;"><i class="fa-solid fa-check"></i> Approved</span>
            </div>
            <button class="btn btn-sm btn-lime" onclick="openAddReturnModal(<?= htmlspecialchars(json_encode($req), ENT_QUOTES) ?>)">
                <i class="fa-solid fa-plus"></i> Add Return Record
            </button>
        </div>
        <div class="card-body" style="padding:18px 24px 10px;">
            <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:0.88rem; margin-bottom:14px;">
                <span><strong>Event:</strong> <?= htmlspecialchars($req['event_name']) ?></span>
                <span><strong>Tentative Amount:</strong> PHP <?= number_format($req['target_amount'],2) ?></span>
                <span><strong>Approved by:</strong> <?= htmlspecialchars($req['approved_by'] ?? 'N/A') ?></span>
                <span><strong>Approved at:</strong> <?= htmlspecialchars($req['approved_at'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($req['admin_remarks'])): ?>
            <div style="background:#f9fbf8; border-left:3px solid var(--lemon-lime); padding:8px 14px; border-radius:4px; font-size:0.85rem; margin-bottom:14px; color:#444;">
                <strong>Admin Remarks:</strong> <?= htmlspecialchars($req['admin_remarks']) ?>
            </div>
            <?php endif; ?>

            <!-- Return Records Table -->
            <?php if (empty($returns)): ?>
                <p style="color:var(--text-muted); font-size:0.88rem; padding:10px 0;"><i class="fa-solid fa-clock"></i> No student return records yet for this drive.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="custom-table" style="font-size:0.87rem;">
                    <thead>
                        <tr>
                            <th>Student Solicitor</th>
                            <th>Tentative Amount</th>
                            <th>Papers Returned?</th>
                            <th>Date Returned</th>
                            <th>Amount Collected</th>
                            <th>Receipt No.</th>
                            <th>Photo Proof</th>
                            <th>Audit Status</th>
                            <th>Remarks</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returns as $ret): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ret['student_name']) ?></strong><br>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($ret['student_id'] ?? 'N/A') ?></small>
                                <?php if (!empty($ret['control_no'])): ?><br><span class="control-pill" style="font-size:.72rem;"><?= htmlspecialchars($ret['control_no']) ?></span><?php endif; ?>
                            </td>
                            <td>PHP <?= number_format((float)($ret['target_amount'] ?? 0), 2) ?></td>
                            <td>
                                <?php if ($ret['papers_returned'] === 'yes'): ?>
                                    <span class="badge badge-approved"><i class="fa-solid fa-check"></i> Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-pending"><i class="fa-solid fa-clock"></i> No</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($ret['date_returned'] ?? '—') ?></td>
                            <td><strong style="color:var(--forest-green);">PHP <?= number_format($ret['amount_collected'],2) ?></strong></td>
                            <td><?= htmlspecialchars($ret['receipt_no'] ?? '—') ?></td>
                            <td>
                                <?php if (!empty($ret['proof_photo'])): $pu = '../assets/uploads/return_proofs/' . rawurlencode(basename($ret['proof_photo'])); ?>
                                    <a href="<?= htmlspecialchars($pu) ?>" target="_blank" title="Open photo"><img src="<?= htmlspecialchars($pu) ?>" alt="Proof" style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid var(--border-color);"></a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $as = $ret['audit_status'];
                                $cls = $as === 'audited' ? 'badge-approved' : ($as === 'discrepancy' ? 'badge-denied' : 'badge-pending');
                                ?>
                                <span class="badge <?= $cls ?>"><?= htmlspecialchars(str_replace('_',' ',$as)) ?></span>
                            </td>
                            <td style="font-size:0.82rem; max-width:180px;"><?= htmlspecialchars($ret['remarks'] ?? '—') ?></td>
                            <td style="text-align:right;">
                                <button class="btn btn-sm btn-outline-primary" onclick="openAuditModal(<?= htmlspecialchars(json_encode($ret), ENT_QUOTES) ?>)">
                                    <i class="fa-solid fa-pen-to-square"></i> Update
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Modal: Add Return Record -->
<div id="addReturnModal" class="modal-overlay">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus" style="color:var(--lemon-lime);"></i> Add Student Return Record</h3>
            <button class="modal-close" onclick="closeModal('addReturnModal')">&times;</button>
        </div>
        <form method="POST" action="approved.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_return">
            <input type="hidden" name="request_id" id="addReturnRequestId">
            <div class="modal-body">
                <div style="background:var(--bg-light); border:1px solid var(--border-color); border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:0.88rem;">
                    <strong id="addReturnControlNo"></strong> — <span id="addReturnOrgName"></span><br>
                    <small style="color:var(--text-muted);" id="addReturnEventName"></small>
                </div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label required">Student Name</label>
                        <input type="text" name="student_name" class="form-control" required placeholder="Full name">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Student ID</label>
                        <input type="text" name="student_id" class="form-control" placeholder="e.g. 20-0001">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label required">Papers Returned?</label>
                        <select name="papers_returned" class="form-select">
                            <option value="yes">Yes — Returned</option>
                            <option value="no">No — Pending</option>
                        </select>
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Date Returned</label>
                        <input type="date" name="date_returned" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">Amount Collected (PHP)</label>
                        <input type="number" name="amount_collected" class="form-control" step="0.01" min="0" value="0.00">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Receipt No.</label>
                        <input type="text" name="receipt_no" class="form-control" placeholder="OR-XXXXX">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">Audit Status</label>
                        <select name="audit_status" class="form-select">
                            <option value="pending_audit">Pending Audit</option>
                            <option value="audited">Audited</option>
                            <option value="discrepancy">Discrepancy</option>
                        </select>
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="remarks" class="form-control" placeholder="Optional notes">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="closeModal('addReturnModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Return Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Update Audit Status -->
<div id="auditModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-clipboard-check" style="color:var(--royal-green);"></i> Update Audit Status</h3>
            <button class="modal-close" onclick="closeModal('auditModal')">&times;</button>
        </div>
        <form method="POST" action="approved.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_audit">
            <input type="hidden" name="return_id" id="auditReturnId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student: <strong id="auditStudentName"></strong></label>
                </div>
                <div class="form-group">
                    <label class="form-label required">Audit Status</label>
                    <select name="audit_status" id="auditStatus" class="form-select">
                        <option value="pending_audit">Pending Audit</option>
                        <option value="audited">Audited</option>
                        <option value="discrepancy">Discrepancy</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Remarks / Notes</label>
                    <textarea name="remarks" id="auditRemarks" class="form-control" placeholder="Auditor notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="closeModal('auditModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddReturnModal(req) {
    document.getElementById('addReturnRequestId').value = req.id;
    document.getElementById('addReturnControlNo').textContent = req.control_no;
    document.getElementById('addReturnOrgName').textContent = req.org_name;
    document.getElementById('addReturnEventName').textContent = req.event_name;
    openModal('addReturnModal');
}
function openAuditModal(ret) {
    document.getElementById('auditReturnId').value = ret.id;
    document.getElementById('auditStudentName').textContent = ret.student_name;
    document.getElementById('auditStatus').value = ret.audit_status;
    document.getElementById('auditRemarks').value = ret.remarks || '';
    openModal('auditModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>