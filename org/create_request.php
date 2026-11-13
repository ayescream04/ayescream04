<?php
/** ORG Portal: Create Solicitation Request */
$pageTitle = 'Create Solicitation';
$pageHeader = 'Create Solicitation Request';
$pageSubtitle = 'Submit a solicitation drive for review by the Office of Student Affairs.';

require_once __DIR__ . '/../config/auth.php';
require_login('org');
$orgId = (int) current_user()['org_id'];
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

$errors = [];
$old = ['event_name' => '', 'purpose' => '', 'target_amount' => '', 'date_needed' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($old as $k => $_) { $old[$k] = trim($_POST[$k] ?? ''); }

    if ($old['event_name'] === '') { $errors[] = 'Event name is required.'; }
    if ($old['purpose'] === '')    { $errors[] = 'Purpose is required.'; }
    if (!is_numeric($old['target_amount']) || (float)$old['target_amount'] <= 0) { $errors[] = 'Tentative amount must be greater than zero.'; }
    $d = DateTime::createFromFormat('Y-m-d', $old['date_needed']);
    if (!$d || $d->format('Y-m-d') !== $old['date_needed']) {
        $errors[] = 'Please choose a valid date needed.';
    } elseif ($old['date_needed'] < date('Y-m-d')) {
        $errors[] = 'Date needed cannot be in the past.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO solicitation_requests (control_no, org_id, event_name, purpose, target_amount, datereq, date_needed, status)
                                   VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'pending')");
            $stmt->execute(['TMP-' . bin2hex(random_bytes(6)), $orgId, $old['event_name'], $old['purpose'], (float)$old['target_amount'], $old['date_needed']]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE solicitation_requests SET control_no = ? WHERE id = ?")
                ->execute([sprintf('SOL-%s-%04d', date('Y'), $newId), $newId]);
            $pdo->commit();
            set_flash('success', 'Solicitation request submitted. It is now awaiting review.');
            header('Location: pending.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = 'Could not save your request. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i><div><?= htmlspecialchars($err) ?></div></div>
<?php endforeach; ?>

<div class="card" style="max-width:820px;">
    <div class="card-body">
        <form method="POST" action="create_request.php">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label required" for="event_name">Event / Activity Name</label>
                <input type="text" id="event_name" name="event_name" class="form-control" maxlength="200" required value="<?= htmlspecialchars($old['event_name']) ?>" placeholder="e.g. Annual Leadership Summit 2026">
            </div>
            <div class="form-group">
                <label class="form-label required" for="purpose">Purpose / Description</label>
                <textarea id="purpose" name="purpose" class="form-control" rows="4" required placeholder="Objectives and how the funds will be used"><?= htmlspecialchars($old['purpose']) ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label required" for="target_amount">Tentative Amount (PHP)</label>
                    <input type="number" id="target_amount" name="target_amount" class="form-control" step="0.01" min="1" required value="<?= htmlspecialchars($old['target_amount']) ?>">
                </div>
                <div class="form-col form-group">
                    <label class="form-label required" for="date_needed">Date Needed</label>
                    <input type="date" id="date_needed" name="date_needed" class="form-control" min="<?= date('Y-m-d') ?>" required value="<?= htmlspecialchars($old['date_needed']) ?>">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <a href="index.php" class="btn btn-outline-primary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>