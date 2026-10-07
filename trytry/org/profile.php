<?php
/** ORG Portal: Organization Profile + Members management */
$pageTitle = 'Org Profile';
$pageHeader = 'Organization Profile';
$pageSubtitle = 'Your organization details, password and member list.';

require_once __DIR__ . '/../config/auth.php';
require_login('org');
$orgId = current_user()['org_id'] ?? '';
if (!$orgId) { session_destroy(); header('Location: ../login.php'); exit; }

// Make sure the members table exists with student_id, name, department, and year_section columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        org_id VARCHAR(50) NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        name VARCHAR(150) NOT NULL,
        department VARCHAR(50) NOT NULL DEFAULT '',
        year_section VARCHAR(50) NOT NULL DEFAULT '',
        position VARCHAR(100) NOT NULL DEFAULT 'Member',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_org_members_org (org_id)
    ) CHARACTER SET utf8mb4");

    // Auto-migrate year_section column if missing in existing table
    $colYrSec = $pdo->query("SHOW COLUMNS FROM org_members LIKE 'year_section'")->fetch();
    if (!$colYrSec) {
        $pdo->exec("ALTER TABLE org_members ADD COLUMN year_section VARCHAR(50) NOT NULL DEFAULT '' AFTER department");
    }

    // Auto-migrate department column if missing in existing table
    $colDept = $pdo->query("SHOW COLUMNS FROM org_members LIKE 'department'")->fetch();
    if (!$colDept) {
        $pdo->exec("ALTER TABLE org_members ADD COLUMN department VARCHAR(50) NOT NULL DEFAULT '' AFTER name");
    }

    // Auto-migrate: logo column on organizations (stores only the file name)
    $colLogo = $pdo->query("SHOW COLUMNS FROM organizations LIKE 'logo'")->fetch();
    if (!$colLogo) {
        $pdo->exec("ALTER TABLE organizations ADD COLUMN logo VARCHAR(255) NULL DEFAULT NULL");
    }
} catch (PDOException $e) { /* surfaced by the queries below if it really failed */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email   = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact_no'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $contact === '') {
            set_flash('danger', 'Enter a valid email and contact number.');
        } else {
            $pdo->prepare("UPDATE organizations SET email = ?, contact_no = ? WHERE id = ?")
                ->execute([$email, $contact, $orgId]);
            set_flash('success', 'Profile details updated.');
        }

    } elseif ($action === 'upload_logo') {
        $f = $_FILES['logo'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            set_flash('danger', 'Please choose an image to upload.');
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            set_flash('danger', 'Upload failed. Please try again with a smaller image.');
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            set_flash('danger', 'Logo must be 2 MB or smaller.');
        } else {
            $info  = @getimagesize($f['tmp_name']);
            $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg'];
            if (!$info || !isset($types[$info[2]])) {
                set_flash('danger', 'Logo must be a PNG or JPG image.');
            } else {
                $dir = __DIR__ . '/../assets/uploads/org_logos/';
                if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                    set_flash('danger', 'Could not create the upload folder.');
                } else {
                    $newName = 'org_' . (int)$orgId . '_' . bin2hex(random_bytes(6)) . '.' . $types[$info[2]];
                    if (move_uploaded_file($f['tmp_name'], $dir . $newName)) {
                        $oldQ = $pdo->prepare("SELECT logo FROM organizations WHERE id = ?");
                        $oldQ->execute([$orgId]);
                        $oldLogo = $oldQ->fetchColumn();
                        $pdo->prepare("UPDATE organizations SET logo = ? WHERE id = ?")->execute([$newName, $orgId]);
                        if ($oldLogo && is_file($dir . basename($oldLogo))) { @unlink($dir . basename($oldLogo)); }
                        set_flash('success', 'Organization logo updated.');
                    } else {
                        set_flash('danger', 'Could not save the uploaded file.');
                    }
                }
            }
        }

    } elseif ($action === 'change_password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $row = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $row->execute([current_user()['id']]);
        $hash = $row->fetchColumn();
        if (!$hash || !password_verify($cur, $hash)) {
            set_flash('danger', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            set_flash('danger', 'New password must be at least 6 characters.');
        } else {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), current_user()['id']]);
            set_flash('success', 'Password changed.');
        }

    } elseif ($action === 'add_member') {
        $studentId   = trim($_POST['student_id'] ?? '');
        $name        = trim($_POST['name'] ?? '');
        $dept        = trim($_POST['department'] ?? '');
        $yearSection = trim($_POST['year_section'] ?? '');
        $position    = trim($_POST['position'] ?? '');
        if ($position === '') { $position = 'Member'; }

        $allowedDepts = ['COED', 'BSIS', 'CBOA'];

        if ($studentId === '' || $name === '' || $yearSection === '') {
            set_flash('danger', 'Student ID, Full Name, and Year & Section are required.');
        } elseif (!in_array($dept, $allowedDepts, true)) {
            set_flash('danger', 'Please select a valid department.');
        } elseif (strlen($studentId) > 50 || strlen($name) > 150 || strlen($yearSection) > 50 || strlen($position) > 100) {
            set_flash('danger', 'One of the input fields exceeds the maximum character length.');
        } else {
            $pdo->prepare("INSERT INTO org_members (org_id, student_id, name, department, year_section, position) VALUES (?,?,?,?,?,?)")
                ->execute([$orgId, $studentId, $name, $dept, $yearSection, $position]);
            set_flash('success', 'Member added.');
        }

    } elseif ($action === 'delete_member') {
        $mid = intval($_POST['member_id'] ?? 0);
        $pdo->prepare("DELETE FROM org_members WHERE id = ? AND org_id = ?")->execute([$mid, $orgId]);
        set_flash('success', 'Member removed.');
    }

    header('Location: profile.php');
    exit;
}

$st = $pdo->prepare("SELECT * FROM organizations WHERE id = ?");
$st->execute([$orgId]);
$org = $st->fetch();
if (!$org) { header('Location: ../logout.php'); exit; }

$ms = $pdo->prepare("SELECT * FROM org_members WHERE org_id = ? ORDER BY name ASC");
$ms->execute([$orgId]);
$members = $ms->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="two-col" style="margin-bottom:24px;">
    <!-- Organization Details Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <?php if (!empty($org['logo'])): ?>
                    <img src="../assets/uploads/org_logos/<?= htmlspecialchars(basename($org['logo'])) ?>" alt="" style="width:44px; height:44px; border-radius:50%; object-fit:contain; background:#fff; border:2px solid var(--lemon-lime);">
                <?php else: ?>
                    <span style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--royal-green),var(--lemon-lime)); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:.72rem; overflow:hidden;">
                        <?= htmlspecialchars(substr($org['acronym'], 0, 4)) ?>
                    </span>
                <?php endif; ?>
                <?= htmlspecialchars($org['org_name']) ?> (<?= htmlspecialchars($org['acronym']) ?>)
            </div>
            <span class="badge badge-<?= $org['status'] === 'active' ? 'active' : 'inactive' ?>"><?= htmlspecialchars($org['status']) ?></span>
        </div>
        <div class="card-body">
            <p><strong>President:</strong> <?= htmlspecialchars($org['president']) ?></p>
            <p><strong>Org Student ID:</strong> <?= htmlspecialchars($org['student_id'] ?? 'N/A') ?></p>
            <p><strong>Adviser:</strong> <?= htmlspecialchars($org['adviser']) ?></p>
            <p><strong>Registered:</strong> <?= htmlspecialchars($org['created_at']) ?></p>
            <hr style="margin:16px 0; border:none; border-top:1px solid var(--border-color);">

            <!-- Organization logo (shown on the right side of the solicitation letter) -->
            <form method="POST" action="profile.php" enctype="multipart/form-data" style="margin-bottom:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_logo">
                <label class="form-label" for="orgLogoInput">Organization Logo</label>
                <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                    <?php if (!empty($org['logo'])): ?>
                        <img src="../assets/uploads/org_logos/<?= htmlspecialchars(basename($org['logo'])) ?>" alt="Org logo"
                             style="width:72px; height:72px; object-fit:contain; border:1px solid var(--border-color); border-radius:6px; padding:3px; background:#fff;">
                    <?php else: ?>
                        <div style="width:72px; height:72px; border:1px dashed var(--border-color); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:.7rem; text-align:center;">No logo</div>
                    <?php endif; ?>
                    <div style="flex:1; min-width:200px;">
                        <input type="file" id="orgLogoInput" name="logo" class="form-control" accept="image/png,image/jpeg" required>
                        <div class="form-hint">PNG or JPG, up to 2 MB. Appears on your solicitation letters.</div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-upload"></i> Upload Logo</button>
                </div>
            </form>

            <hr style="margin:16px 0; border:none; border-top:1px solid var(--border-color);">
            
            <form method="POST" action="profile.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label class="form-label required">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($org['email']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Contact No.</label>
                    <input type="text" name="contact_no" class="form-control" required value="<?= htmlspecialchars($org['contact_no']) ?>">
                </div>
                
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-save"></i> Save Profile Details</button>
            </form>
        </div>
    </div>

    <!-- Password Management Card -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-lock"></i> Change Password</div></div>
        <div class="card-body">
            <form method="POST" action="profile.php" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label class="form-label required">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label required">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-key"></i> Update Password</button>
            </form>
        </div>
    </div>
</div>

<!-- Member Roster Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-users" style="color:var(--royal-green);"></i> Members (<?= count($members) ?>)</div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <input type="text" class="form-control" style="width:220px; padding:6px 12px; font-size:.85rem;" placeholder="Search members..." data-table-search="membersTable">
            <button type="button" class="btn btn-primary" onclick="openAddMember()"><i class="fa-solid fa-user-plus"></i> Add Member</button>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="custom-table" id="membersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Department</th>
                        <th>Yr & Section</th>
                        <th>Position</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$members): ?>
                    <tr><td colspan="7" class="empty-cell">No members yet. Click "Add Member" to build your roster.</td></tr>
                <?php else: foreach ($members as $i => $m): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><code><?= htmlspecialchars($m['student_id'] ?? 'N/A') ?></code></td>
                        <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($m['department'] ?: 'N/A') ?></span></td>
                        <td><?= htmlspecialchars($m['year_section'] ?: 'N/A') ?></td>
                        <td><span class="badge badge-active"><?= htmlspecialchars($m['position']) ?></span></td>
                        <td style="text-align:right; white-space:nowrap;">
                            <form method="POST" action="profile.php" style="display:inline;"
                                  onsubmit="return confirm('Remove this member from the organization?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_member">
                                <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Member -->
<div id="memberModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus" style="color:var(--lemon-lime);"></i> Add Member</h3>
            <button type="button" class="modal-close" onclick="closeModal('memberModal')">&times;</button>
        </div>
        <form method="POST" action="profile.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_member">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required" for="memberStudentId">Student ID</label>
                    <input type="text" name="student_id" id="memberStudentId" class="form-control" maxlength="50" required placeholder="e.g. 2026-0001">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="memberName">Full Name</label>
                    <input type="text" name="name" id="memberName" class="form-control" maxlength="150" required placeholder="e.g. John Doe">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="memberDept">Department</label>
                    <select name="department" id="memberDept" class="form-control" required>
                        <option value="" disabled selected>-- Select Department --</option>
                        <option value="COED">COED (College of Education)</option>
                        <option value="BSIS">BSIS (BS Information Systems)</option>
                        <option value="CBOA">CBOA (College of Business & Office Administration)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required" for="memberYearSection">Yr & Section</label>
                    <input type="text" name="year_section" id="memberYearSection" class="form-control" maxlength="50" required placeholder="e.g. 3-A or BSIS 3-A">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="memberPosition">Position</label>
                    <input type="text" name="position" id="memberPosition" class="form-control" maxlength="100" required list="positionList" placeholder="e.g. Member">
                    <datalist id="positionList">
                        <option value="President"><option value="Vice President"><option value="Secretary">
                        <option value="Treasurer"><option value="Auditor"><option value="PIO"><option value="Member">
                    </datalist>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="closeModal('memberModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddMember() {
    document.getElementById('memberStudentId').value = '';
    document.getElementById('memberName').value = '';
    document.getElementById('memberDept').selectedIndex = 0;
    document.getElementById('memberYearSection').value = '';
    document.getElementById('memberPosition').value = 'Member';
    openModal('memberModal');
    document.getElementById('memberStudentId').focus();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>