<?php
/**
 * Admin: Funds Audit
 * Audit collected funds of an org — view solicitation_returns & funds_audit
 */

$pageTitle = 'Funds Audit';
$pageHeader = 'Organization Funds Audit';
$pageSubtitle = 'Audit collected funds per organization — review returned solicitation papers, amounts collected, and formal audit records.';

require_once __DIR__ . '/../config/auth.php';
require_login('admin');

// Handle creating / updating a formal audit entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_audit') {
    csrf_verify();
    $requestId = intval($_POST['request_id'] ?? 0);
    $amount    = max(0, floatval($_POST['audited_amount'] ?? 0));
    $status    = in_array($_POST['audit_status'] ?? '', ['audited', 'pending', 'discrepancy'], true) ? $_POST['audit_status'] : 'pending';
    $notes     = trim($_POST['audit_notes'] ?? '');
    $auditedBy = trim($_POST['audited_by'] ?? '');

    // The organization always comes from the approved request itself, never from the form.
    $reqStmt = $pdo->prepare("SELECT id, org_id FROM solicitation_requests WHERE id = ? AND status = 'approved'");
    $reqStmt->execute([$requestId]);
    $reqRow = $reqStmt->fetch();
    $backOrg = $reqRow ? (int)$reqRow['org_id'] : 0;

    if ($reqRow && $auditedBy !== '') {
        $check = $pdo->prepare("SELECT id FROM funds_audit WHERE request_id = ?");
        $check->execute([$requestId]);
        if ($check->fetch()) {
            $upd = $pdo->prepare("UPDATE funds_audit SET audited_amount=?, audit_status=?, audit_notes=?, audited_by=?, audited_at=CURRENT_TIMESTAMP WHERE request_id=?");
            $upd->execute([$amount, $status, $notes, $auditedBy, $requestId]);
            set_flash('success', 'Audit record updated.');
        } else {
            $ins = $pdo->prepare("INSERT INTO funds_audit (org_id, request_id, audited_amount, audit_status, audit_notes, audited_by) VALUES (?,?,?,?,?,?)");
            $ins->execute([$reqRow['org_id'], $requestId, $amount, $status, $notes, $auditedBy]);
            set_flash('success', 'Audit record created successfully.');
        }
    } else {
        set_flash('danger', 'Could not save the audit. An approved request and the auditor name are required.');
    }
    header('Location: funds.php' . ($backOrg ? '?org_id=' . $backOrg : ''));
    exit;
}

// Fetch all orgs with their totals
$orgsStmt = $pdo->query("SELECT o.*, 
    (SELECT COUNT(*) FROM solicitation_requests WHERE org_id=o.id AND status='approved') as approved_count,
    (SELECT COALESCE(SUM(amount_collected),0) FROM solicitation_returns ret 
        JOIN solicitation_requests r ON ret.request_id=r.id WHERE r.org_id=o.id AND ret.papers_returned='yes') as total_collected,
    (SELECT COALESCE(SUM(audited_amount),0) FROM funds_audit WHERE org_id=o.id) as total_audited
    FROM organizations o WHERE o.status='active' ORDER BY o.org_name ASC");
$orgs = $orgsStmt->fetchAll();

// Filter by org if selected
$selectedOrgId = intval($_GET['org_id'] ?? 0);
$selectedOrg   = null;
$orgRequests   = [];
$orgReturns    = [];
$orgAudits     = [];

if ($selectedOrgId > 0) {
    foreach ($orgs as $o) {
        if ($o['id'] == $selectedOrgId) { $selectedOrg = $o; break; }
    }
    // Fetch approved requests for org
    $rStmt = $pdo->prepare("SELECT * FROM solicitation_requests WHERE org_id=? AND status='approved' ORDER BY id DESC");
    $rStmt->execute([$selectedOrgId]);
    $orgRequests = $rStmt->fetchAll();

    // Fetch returns for org
    $retStmt = $pdo->prepare("SELECT ret.*, r.control_no, r.event_name 
        FROM solicitation_returns ret 
        JOIN solicitation_requests r ON ret.request_id=r.id 
        WHERE r.org_id=? ORDER BY ret.id DESC");
    $retStmt->execute([$selectedOrgId]);
    $orgReturns = $retStmt->fetchAll();

    // Fetch audit records for org
    $auditStmt = $pdo->prepare("SELECT fa.*, r.control_no, r.event_name 
        FROM funds_audit fa 
        JOIN solicitation_requests r ON fa.request_id=r.id 
        WHERE fa.org_id=? ORDER BY fa.audited_at DESC");
    $auditStmt->execute([$selectedOrgId]);
    $orgAudits = $auditStmt->fetchAll();
}

// Build a map of request_id => audit for quick lookup
$auditByRequest = [];
foreach ($orgAudits as $a) {
    $auditByRequest[$a['request_id']] = $a;
}

// Header outputs HTML, so it is included only after all redirects/POST handling.
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Org Selector -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-body" style="padding:18px 24px;">
        <form method="GET" action="funds.php" style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
            <label class="form-label" style="margin:0; white-space:nowrap;"><i class="fa-solid fa-filter" style="color:var(--lemon-lime);"></i> Select Organization to Audit:</label>
            <select name="org_id" class="form-select" style="max-width:340px;">
                <option value="">— Choose Organization —</option>
                <?php foreach ($orgs as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $selectedOrgId == $o['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($o['acronym']) ?> — <?= htmlspecialchars($o['org_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> View Audit</button>
            <?php if ($selectedOrgId): ?>
                <a href="funds.php" class="btn btn-outline-primary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Organization Overview Cards -->
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); margin-bottom:24px;">
    <?php foreach ($orgs as $o): ?>
    <a href="funds.php?org_id=<?= $o['id'] ?>" style="text-decoration:none;">
        <div class="kpi-card" style="<?= $selectedOrgId == $o['id'] ? 'border:2px solid var(--lemon-lime); background:#f4fbf2;' : '' ?> cursor:pointer;">
            <div class="kpi-info">
                <h3 style="font-size:1.3rem;"><?= htmlspecialchars($o['acronym']) ?></h3>
                <p>PHP <?= number_format($o['total_collected'],0) ?> collected</p>
                <small style="color:var(--text-muted);"><?= $o['approved_count'] ?> approved drives</small>
            </div>
            <div class="kpi-icon icon-lime">
                <i class="fa-solid fa-building-columns"></i>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($selectedOrg): ?>
<!-- Org Detail Audit View -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="background: linear-gradient(135deg,#054204,#1D6517); color:#fff; border:none;">
        <div>
            <h3 style="color:#b6f0a7; font-size:1.1rem; font-weight:700;">
                <i class="fa-solid fa-money-bill-transfer"></i> Funds Audit: <?= htmlspecialchars($selectedOrg['acronym']) ?>
            </h3>
            <p style="font-size:0.85rem; color:#e8ffe4; margin:0;"><?= htmlspecialchars($selectedOrg['org_name']) ?></p>
        </div>
        <div style="text-align:right; color:#b6f0a7; font-size:0.9rem;">
            <div>Total Collected: <strong>PHP <?= number_format($selectedOrg['total_collected'],2) ?></strong></div>
            <div>Total Audited: <strong>PHP <?= number_format($selectedOrg['total_audited'],2) ?></strong></div>
        </div>
    </div>
</div>

<!-- Returns Records Table -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-receipt" style="color:var(--lemon-lime);"></i> Solicitation Returns (Student Records)</div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Control No</th>
                        <th>Event</th>
                        <th>Student Solicitor</th>
                        <th>Papers Returned?</th>
                        <th>Date Returned</th>
                        <th>Amount Collected</th>
                        <th>Receipt No.</th>
                        <th>Audit Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orgReturns)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-muted);">No return records yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orgReturns as $ret): ?>
                        <tr>
                            <td><span style="font-family:monospace; color:var(--forest-green); font-weight:700;"><?= htmlspecialchars($ret['control_no']) ?></span></td>
                            <td><?= htmlspecialchars($ret['event_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($ret['student_name']) ?></strong><br>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($ret['student_id'] ?? '') ?></small>
                            </td>
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
                                <?php $as=$ret['audit_status']; $cls=$as==='audited'?'badge-approved':($as==='discrepancy'?'badge-denied':'badge-pending'); ?>
                                <span class="badge <?= $cls ?>"><?= htmlspecialchars(str_replace('_',' ',$as)) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Formal Audit Records per Approved Request -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-stamp" style="color:var(--royal-green);"></i> Formal Audit Records per Solicitation Drive</div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($orgRequests)): ?>
            <p style="padding:20px; color:var(--text-muted);">No approved requests for this org.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Control No</th>
                        <th>Event</th>
                        <th>Target Amount</th>
                        <th>Audited Amount</th>
                        <th>Audit Status</th>
                        <th>Audited By</th>
                        <th>Audit Notes</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orgRequests as $req): ?>
                    <?php $audit = $auditByRequest[$req['id']] ?? null; ?>
                    <tr>
                        <td><span style="font-family:monospace; color:var(--forest-green); font-weight:700;"><?= htmlspecialchars($req['control_no']) ?></span></td>
                        <td><?= htmlspecialchars($req['event_name']) ?></td>
                        <td>PHP <?= number_format($req['target_amount'],2) ?></td>
                        <td>
                            <?php if ($audit): ?>
                                <strong style="color:var(--forest-green);">PHP <?= number_format($audit['audited_amount'],2) ?></strong>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">Not yet audited</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($audit): ?>
                                <?php $as=$audit['audit_status']; $cls=$as==='audited'?'badge-approved':($as==='discrepancy'?'badge-denied':'badge-pending'); ?>
                                <span class="badge <?= $cls ?>"><?= htmlspecialchars($as) ?></span>
                            <?php else: ?>
                                <span class="badge badge-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($audit['audited_by'] ?? '—') ?></td>
                        <td style="font-size:0.83rem; max-width:200px;"><?= htmlspecialchars($audit['audit_notes'] ?? '—') ?></td>
                        <td style="text-align:right;">
                            <button class="btn btn-sm btn-primary" onclick="openFormalAuditModal(<?= htmlspecialchars(json_encode(['req'=>$req,'audit'=>$audit]), ENT_QUOTES) ?>)">
                                <i class="fa-solid fa-pen-to-square"></i> <?= $audit ? 'Update' : 'Create' ?> Audit
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
<?php endif; ?>

<!-- Modal: Create/Update Formal Audit -->
<div id="formalAuditModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-stamp" style="color:var(--royal-green);"></i> Formal Audit Record</h3>
            <button class="modal-close" onclick="closeModal('formalAuditModal')">&times;</button>
        </div>
        <form method="POST" action="funds.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_audit">
            <input type="hidden" name="org_id" id="fAuditOrgId" value="<?= $selectedOrgId ?>">
            <input type="hidden" name="request_id" id="fAuditRequestId">
            <div class="modal-body">
                <div style="background:var(--bg-light); border:1px solid var(--border-color); border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:0.88rem;">
                    <strong id="fAuditControlNo"></strong> — <span id="fAuditEventName"></span>
                </div>
                <div class="form-group">
                    <label class="form-label required">Audited Amount (PHP)</label>
                    <input type="number" name="audited_amount" id="fAuditAmount" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">Audit Status</label>
                    <select name="audit_status" id="fAuditStatus" class="form-select">
                        <option value="audited">Audited</option>
                        <option value="pending">Pending</option>
                        <option value="discrepancy">Discrepancy</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Audit Notes</label>
                    <textarea name="audit_notes" id="fAuditNotes" class="form-control" rows="3" placeholder="Auditor's notes and observations..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label required">Audited By</label>
                    <input type="text" name="audited_by" id="fAuditedBy" class="form-control" placeholder="Name and title of auditor" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="closeModal('formalAuditModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-stamp"></i> Save Audit Record</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFormalAuditModal(data) {
    var req = data.req, audit = data.audit;
    document.getElementById('fAuditRequestId').value = req.id;
    document.getElementById('fAuditOrgId').value = req.org_id;
    document.getElementById('fAuditControlNo').textContent = req.control_no;
    document.getElementById('fAuditEventName').textContent = req.event_name;
    document.getElementById('fAuditAmount').value = audit ? audit.audited_amount : '';
    document.getElementById('fAuditStatus').value = audit ? audit.audit_status : 'audited';
    document.getElementById('fAuditNotes').value = audit ? (audit.audit_notes || '') : '';
    document.getElementById('fAuditedBy').value = audit ? (audit.audited_by || '') : '';
    openModal('formalAuditModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>