<?php
/** ORG Portal: Student records (students who will be given the solicitation letter) */
$pageTitle = 'Student Records';
$pageHeader = 'Student Records';
$pageSubtitle = 'List the students who will be given a solicitation letter for each approved drive.';

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/students.php';
require_login('org');
$orgId = (int) current_user()['org_id'];
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

ensure_student_columns($pdo);
$requestId = (int) ($_GET['request'] ?? $_POST['request_id'] ?? 0);

/* ---------- Handle add / remove student ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $req = get_org_request($pdo, $orgId, $requestId);

    if (!$req) {
        set_flash('danger', 'That drive was not found or is not approved.');
        header('Location: students.php');
        exit;
    }

    if ($action === 'add_student') {
        $name    = trim($_POST['student_name'] ?? '');
        $sid     = trim($_POST['student_id'] ?? '');
        $section = trim($_POST['year_section'] ?? '');
        $target  = max(0, (float) ($_POST['target_amount'] ?? 0));

        if (!empty($req['date_needed']) && $req['date_needed'] < date('Y-m-d')) {
            set_flash('danger', 'The event date (' . $req['date_needed'] . ') has already passed, so no more students can be added to this drive.');
        } elseif ($name === '') {
            set_flash('danger', 'Student name is required.');
        } else {
            $dupe = false;
            if ($sid !== '') {
                $d = $pdo->prepare("SELECT id FROM solicitation_returns WHERE request_id = ? AND student_id = ?");
                $d->execute([$requestId, $sid]);
                $dupe = (bool) $d->fetch();
            }
            if ($dupe) {
                set_flash('danger', 'That student ID is already listed for this drive.');
            } else {
                $ctrl = next_student_control_no($pdo, $requestId);
                $pdo->prepare("INSERT INTO solicitation_returns
                    (request_id, control_no, student_name, student_id, year_section, target_amount, papers_returned, amount_collected, audit_status)
                    VALUES (?, ?, ?, ?, ?, ?, 'no', 0, 'pending_audit')")
                    ->execute([$requestId, $ctrl, $name, $sid, $section, $target]);
                set_flash('success', 'Student added with Control No. ' . $ctrl . '. You can now open the student page to create the letter.');
            }
        }
    } elseif ($action === 'delete_student') {
        $sidRow = (int) ($_POST['return_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM solicitation_returns WHERE id = ? AND request_id = ? AND papers_returned = 'no'");
        $del->execute([$sidRow, $requestId]);
        set_flash($del->rowCount() ? 'success' : 'danger',
                  $del->rowCount() ? 'Student removed from the list.' : 'Only students who have not returned yet can be removed.');
    }
    header('Location: students.php?request=' . $requestId);
    exit;
}

/* ---------- Page data ---------- */
$req = $requestId ? get_org_request($pdo, $orgId, $requestId) : false;

if ($req) {
    $st = $pdo->prepare("SELECT * FROM solicitation_returns WHERE request_id = ? ORDER BY id ASC");
    $st->execute([$requestId]);
    $students = $st->fetchAll();
    $allocated = 0.0;
    foreach ($students as $s) { $allocated += (float) $s['target_amount']; }
    $remaining = (float) $req['target_amount'] - $allocated;
    $eventPassed = !empty($req['date_needed']) && $req['date_needed'] < date('Y-m-d');
} else {
    $dr = $pdo->prepare("SELECT r.id, r.control_no, r.event_name, r.target_amount, r.date_needed,
            (SELECT COUNT(*) FROM solicitation_returns x WHERE x.request_id = r.id) AS students,
            (SELECT COALESCE(SUM(x.target_amount),0) FROM solicitation_returns x WHERE x.request_id = r.id) AS allocated
        FROM solicitation_requests r WHERE r.org_id = ? AND r.status = 'approved' ORDER BY r.approved_at DESC");
    $dr->execute([$orgId]);
    $drives = $dr->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$req): ?>
<!-- Choose a drive -->
<div class="card">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-user-graduate" style="color:var(--royal-green);"></i> Approved Drives (<?= count($drives) ?>)</div></div>
    <div class="card-body" style="padding:0;"><div class="table-responsive">
        <table class="custom-table">
            <thead><tr><th>Control No</th><th>Event</th><th>Tentative Amount</th><th>Students Listed</th><th>Date Needed</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
            <?php if (!$drives): ?><tr><td colspan="6" class="empty-cell">No approved drives yet. Once a request is approved, you can list its student solicitors here.</td></tr>
            <?php else: foreach ($drives as $d): ?>
                <tr>
                    <td><span class="control-pill"><?= htmlspecialchars($d['control_no']) ?></span></td>
                    <td><strong><?= htmlspecialchars($d['event_name']) ?></strong></td>
                    <td>PHP <?= number_format((float)$d['target_amount'], 2) ?></td>
                    <td><?= (int)$d['students'] ?> student(s)<br><small style="color:var(--text-muted);">Assigned: PHP <?= number_format((float)$d['allocated'], 2) ?></small></td>
                    <td><?= htmlspecialchars($d['date_needed']) ?><?php if ($d['date_needed'] < date('Y-m-d')): ?><br><span class="badge badge-denied">Event passed</span><?php endif; ?></td>
                    <td style="text-align:right;"><a class="btn btn-sm btn-primary" href="students.php?request=<?= (int)$d['id'] ?>"><i class="fa-solid fa-users"></i> Manage Students</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<?php else: ?>
<!-- Drive summary -->
<div class="kpi-grid" style="margin-bottom:24px;">
    <div class="kpi-card"><div class="kpi-info"><h3>PHP <?= number_format((float)$req['target_amount'], 2) ?></h3><p>Tentative Amount</p></div><div class="kpi-icon icon-forest"><i class="fa-solid fa-bullseye"></i></div></div>
    <div class="kpi-card"><div class="kpi-info"><h3>PHP <?= number_format($allocated, 2) ?></h3><p>Assigned to Students</p></div><div class="kpi-icon icon-lime"><i class="fa-solid fa-coins"></i></div></div>
    <div class="kpi-card"><div class="kpi-info"><h3 style="<?= $remaining < 0 ? 'color:#c0392b;' : '' ?>">PHP <?= number_format($remaining, 2) ?></h3><p><?= $remaining < 0 ? 'Over the tentative amount' : 'Not yet assigned' ?></p></div><div class="kpi-icon icon-yellow"><i class="fa-solid fa-scale-balanced"></i></div></div>
    <div class="kpi-card"><div class="kpi-info"><h3><?= count($students) ?></h3><p>Students Listed</p></div><div class="kpi-icon icon-blue"><i class="fa-solid fa-user-graduate"></i></div></div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span class="control-pill"><?= htmlspecialchars($req['control_no']) ?></span>
            <span><?= htmlspecialchars($req['event_name']) ?></span>
        </div>
        <a href="students.php" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-arrow-left"></i> All drives</a>
    </div>
    <div class="card-body">
        <?php if ($eventPassed): ?>
        <div class="alert alert-info" style="margin:0;"><i class="fa-solid fa-calendar-xmark"></i>
            <div><strong>This event has already passed (<?= htmlspecialchars($req['date_needed']) ?>).</strong> You can no longer add another solicitor to this drive. You can still print the existing letters and record returns.</div>
        </div>
        <?php else: ?>
        <h4 style="font-size:.95rem; color:var(--forest-green); margin-bottom:12px;"><i class="fa-solid fa-user-plus"></i> Add a student who will be given the letter</h4>
        <form method="POST" action="students.php?request=<?= (int)$requestId ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_student">
            <input type="hidden" name="request_id" value="<?= (int)$requestId ?>">
            <div class="form-row">
                <div class="form-col form-group" style="flex:2;">
                    <label class="form-label required" for="student_name">Student Name</label>
                    <input type="text" id="student_name" name="student_name" class="form-control" required maxlength="150" placeholder="Full name">
                </div>
                <div class="form-col form-group">
                    <label class="form-label" for="student_id">Student ID</label>
                    <input type="text" id="student_id" name="student_id" class="form-control" maxlength="30" placeholder="e.g. 2022-10042">
                </div>
                <div class="form-col form-group">
                    <label class="form-label" for="year_section">Year &amp; Section</label>
                    <input type="text" id="year_section" name="year_section" class="form-control" maxlength="50" placeholder="e.g. BSIS 3-A">
                </div>
                <div class="form-col form-group">
                    <label class="form-label" for="target_amount">Tentative Amount (PHP)</label>
                    <input type="number" id="target_amount" name="target_amount" class="form-control" step="0.01" min="0" value="0.00">
                </div>
            </div>
            <div style="text-align:right;"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Student</button></div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-list" style="color:var(--royal-green);"></i> Listed Students (<?= count($students) ?>)</div>
        <?php if ($students): ?>
        <button type="button" class="btn btn-sm btn-blue"
            onclick="downloadSolicitationDocx(<?= htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($students), ENT_QUOTES, 'UTF-8') ?>)">
            <i class="fa-solid fa-print"></i> Download all letters (1 page per student)
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding:0;"><div class="table-responsive">
        <table class="custom-table">
            <thead><tr><th>Control No</th><th>Student</th><th>Year &amp; Section</th><th>Tentative Amount</th><th>Status</th><th>Collected</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
            <?php if (!$students): ?><tr><td colspan="7" class="empty-cell">No students listed yet. Add the students who will receive the solicitation letter above.</td></tr>
            <?php else: foreach ($students as $s): $returned = $s['papers_returned'] === 'yes'; ?>
                <tr>
                    <td><span class="control-pill"><?= htmlspecialchars($s['control_no'] ?? '') ?></span></td>
                    <td><strong><?= htmlspecialchars($s['student_name']) ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($s['student_id'] ?? '') ?></small></td>
                    <td><?= htmlspecialchars($s['year_section'] ?? '—') ?></td>
                    <td>PHP <?= number_format((float)$s['target_amount'], 2) ?></td>
                    <td><span class="badge <?= $returned ? 'badge-approved' : 'badge-pending' ?>"><?= $returned ? 'Returned' : 'Not yet returned' ?></span></td>
                    <td><?= $returned ? '<strong>PHP ' . number_format((float)$s['amount_collected'], 2) . '</strong>' : '—' ?></td>
                    <td style="text-align:right; white-space:nowrap;">
                        <a class="btn btn-sm btn-blue" href="student.php?id=<?= (int)$s['id'] ?>"><i class="fa-solid fa-file-word"></i> Letter &amp; Return</a>
                        <?php if (!$returned): ?>
                        <form method="POST" action="students.php?request=<?= (int)$requestId ?>" style="display:inline;" onsubmit="return confirm('Remove this student from the list?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_student">
                            <input type="hidden" name="request_id" value="<?= (int)$requestId ?>">
                            <input type="hidden" name="return_id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Remove"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>