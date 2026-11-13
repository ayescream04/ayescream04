<?php
/** ORG Portal: Page of one student solicitor (letter + return record with photo proof) */
$pageTitle = 'Student Page';
$pageHeader = 'Student Solicitor';
$pageSubtitle = 'Create the student\'s solicitation letter and record the return of the letter and money.';

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/students.php';
require_login('org');
$orgId = (int) current_user()['org_id'];
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

ensure_student_columns($pdo);
$studentId = (int) ($_GET['id'] ?? $_POST['return_id'] ?? 0);

/** Loads the student row only if it belongs to one of this org's approved drives. */
function load_student($pdo, $orgId, $studentId)
{
    $q = $pdo->prepare("SELECT ret.* FROM solicitation_returns ret
                        JOIN solicitation_requests r ON ret.request_id = r.id
                        WHERE ret.id = ? AND r.org_id = ? AND r.status = 'approved'");
    $q->execute([(int)$studentId, (int)$orgId]);
    return $q->fetch();
}

$stu = $studentId ? load_student($pdo, $orgId, $studentId) : false;
if (!$stu) {
    set_flash('danger', 'Student record not found.');
    header('Location: students.php');
    exit;
}

/* ---------- Record the return (letter + money) with photo proof ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_return') {
    csrf_verify();
    $back = 'student.php?id=' . (int)$stu['id'];

    if ($stu['audit_status'] === 'audited') {
        set_flash('danger', 'This record is already audited and can no longer be changed.');
        header('Location: ' . $back); exit;
    }

    $dateRet = trim($_POST['date_returned'] ?? '');
    $amount  = max(0, (float) ($_POST['amount_collected'] ?? 0));
    $receipt = trim($_POST['receipt_no'] ?? '');
    $d = DateTime::createFromFormat('Y-m-d', $dateRet);

    if (!$d || $d->format('Y-m-d') !== $dateRet) {
        set_flash('danger', 'Please choose the date the letter and money were returned.');
        header('Location: ' . $back); exit;
    }
    if ($dateRet > date('Y-m-d')) {
        set_flash('danger', 'Date returned cannot be in the future.');
        header('Location: ' . $back); exit;
    }

    $photoError = '';
    $newPhoto = save_proof_photo($_FILES['proof_photo'] ?? null, $stu['id'], $photoError);
    if ($photoError !== '') {
        set_flash('danger', $photoError);
        header('Location: ' . $back); exit;
    }
    if ($newPhoto === null && empty($stu['proof_photo'])) {
        set_flash('danger', 'A photo of the student with the letter and the money is required as proof of return.');
        header('Location: ' . $back); exit;
    }

    $photoToSave = $newPhoto ?? $stu['proof_photo'];
    $pdo->prepare("UPDATE solicitation_returns
                   SET papers_returned = 'yes', date_returned = ?, amount_collected = ?, receipt_no = ?, proof_photo = ?
                   WHERE id = ?")
        ->execute([$dateRet, $amount, $receipt, $photoToSave, $stu['id']]);

    // Remove the replaced photo file
    if ($newPhoto !== null && !empty($stu['proof_photo']) && is_file(proof_dir() . basename($stu['proof_photo']))) {
        @unlink(proof_dir() . basename($stu['proof_photo']));
    }

    set_flash('success', 'Return recorded for ' . $stu['student_name'] . '.');
    header('Location: ' . $back); exit;
}

/* ---------- Page data ---------- */
$req = get_org_request($pdo, $orgId, (int)$stu['request_id']);
$returned = $stu['papers_returned'] === 'yes';
$locked   = $stu['audit_status'] === 'audited';
$proofUrl = !empty($stu['proof_photo']) ? '../assets/uploads/return_proofs/' . rawurlencode(basename($stu['proof_photo'])) : '';

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:16px;">
    <a href="students.php?request=<?= (int)$stu['request_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-arrow-left"></i> Back to students</a>
</div>

<div class="two-col" style="margin-bottom:24px;">
    <!-- Student details -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-user-graduate" style="color:var(--royal-green);"></i> <?= htmlspecialchars($stu['student_name']) ?></div>
            <span class="badge <?= $returned ? 'badge-approved' : 'badge-pending' ?>"><?= $returned ? 'Returned' : 'Not yet returned' ?></span></div>
        <div class="card-body">
            <table style="width:100%; border-collapse:collapse; font-size:.9rem;">
                <tr><td style="padding:7px 0; color:var(--text-muted); width:40%;">Control No</td><td><span class="control-pill"><?= htmlspecialchars($stu['control_no'] ?? '') ?></span></td></tr>
                <tr><td style="padding:7px 0; color:var(--text-muted);">Student ID</td><td><strong><?= htmlspecialchars($stu['student_id'] ?: '—') ?></strong></td></tr>
                <tr><td style="padding:7px 0; color:var(--text-muted);">Year &amp; Section</td><td><strong><?= htmlspecialchars($stu['year_section'] ?: '—') ?></strong></td></tr>
                <tr><td style="padding:7px 0; color:var(--text-muted);">Drive</td><td><span class="control-pill"><?= htmlspecialchars($req['control_no']) ?></span> <?= htmlspecialchars($req['event_name']) ?></td></tr>
                <tr><td style="padding:7px 0; color:var(--text-muted);">Tentative Amount</td><td><strong style="color:var(--forest-green);">PHP <?= number_format((float)$stu['target_amount'], 2) ?></strong></td></tr>
                <tr><td style="padding:7px 0; color:var(--text-muted);">Amount Returned</td><td><strong>PHP <?= number_format((float)$stu['amount_collected'], 2) ?></strong></td></tr>
                <tr><td style="padding:7px 0; color:var(--text-muted);">Audit Status</td><td>
                    <?php $as = $stu['audit_status']; ?>
                    <span class="badge <?= $as === 'audited' ? 'badge-approved' : ($as === 'discrepancy' ? 'badge-denied' : 'badge-pending') ?>"><?= htmlspecialchars(str_replace('_', ' ', $as)) ?></span>
                </td></tr>
            </table>
        </div>
    </div>

    <!-- Letter -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-file-word" style="color:var(--carolina-blue);"></i> Solicitation Letter</div></div>
        <div class="card-body">
            <p style="font-size:.9rem; color:#444; margin-bottom:14px;">
                Create the official solicitation letter for <strong><?= htmlspecialchars($stu['student_name']) ?></strong>.
                It authorizes the student to solicit on behalf of <?= htmlspecialchars($req['org_name']) ?> and shows the tentative amount of
                <strong>PHP <?= number_format((float)$stu['target_amount'], 2) ?></strong>.
            </p>
            <button type="button" class="btn btn-blue"
                onclick="openSolicitationLetterModal(<?= htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($stu), ENT_QUOTES, 'UTF-8') ?>)">
                <i class="fa-solid fa-eye"></i> Preview / Download Letter
            </button>
        </div>
    </div>
</div>

<!-- Return record -->
<div class="card" style="max-width:900px;">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-camera" style="color:var(--lemon-lime);"></i> Return Record &amp; Photo Proof</div></div>
    <div class="card-body">
        <?php if ($locked): ?>
            <div class="alert alert-info"><i class="fa-solid fa-lock"></i><div>This record has been audited by the Office of Student Affairs and can no longer be edited.</div></div>
        <?php endif; ?>

        <?php if ($proofUrl): ?>
            <div style="margin-bottom:16px;">
                <div class="form-label">Current proof photo</div>
                <a href="<?= htmlspecialchars($proofUrl) ?>" target="_blank" title="Open full size">
                    <img src="<?= htmlspecialchars($proofUrl) ?>" alt="Proof of return" style="max-width:260px; max-height:220px; border:1px solid var(--border-color); border-radius:8px; padding:3px; background:#fff;">
                </a>
            </div>
        <?php endif; ?>

        <form method="POST" action="student.php?id=<?= (int)$stu['id'] ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_return">
            <input type="hidden" name="return_id" value="<?= (int)$stu['id'] ?>">
            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label required" for="date_returned">Date Returned</label>
                    <input type="date" id="date_returned" name="date_returned" class="form-control" max="<?= date('Y-m-d') ?>" required
                           value="<?= htmlspecialchars($stu['date_returned'] ?? date('Y-m-d')) ?>" <?= $locked ? 'disabled' : '' ?>>
                </div>
                <div class="form-col form-group">
                    <label class="form-label required" for="amount_collected">Amount Returned (PHP)</label>
                    <input type="number" id="amount_collected" name="amount_collected" class="form-control" step="0.01" min="0" required
                           value="<?= htmlspecialchars(number_format((float)$stu['amount_collected'], 2, '.', '')) ?>" <?= $locked ? 'disabled' : '' ?>>
                </div>
                <div class="form-col form-group">
                    <label class="form-label" for="receipt_no">Receipt No.</label>
                    <input type="text" id="receipt_no" name="receipt_no" class="form-control" maxlength="50" placeholder="OR-XXXXX"
                           value="<?= htmlspecialchars($stu['receipt_no'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?>>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label <?= $proofUrl ? '' : 'required' ?>" for="proof_photo">Photo of the student with the letter and the money</label>
                <input type="file" id="proof_photo" name="proof_photo" class="form-control" accept="image/png,image/jpeg" <?= ($proofUrl || $locked) ? '' : 'required' ?> <?= $locked ? 'disabled' : '' ?>>
                <div class="form-hint">PNG or JPG, up to 4 MB. <?= $proofUrl ? 'Choose a new photo only if you want to replace the current one.' : 'The student, the returned letter and the money should be visible.' ?></div>
            </div>
            <?php if (!$locked): ?>
            <div style="text-align:right;"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $returned ? 'Update Return Record' : 'Save Return Record' ?></button></div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>