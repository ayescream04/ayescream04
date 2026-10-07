<?php
/**
 * Admin: Organizations Management
 * List/profile orgs, add org, delete org
 */

$pageTitle = 'Organizations';
$pageHeader = 'Organization Management';
$pageSubtitle = 'View, add, and manage registered student organizations in the system.';

require_once __DIR__ . '/../config/auth.php';
require_login('admin');

// Handle Add / Toggle / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_org') {
        $orgName   = trim($_POST['org_name'] ?? '');
        $acronym   = trim($_POST['acronym'] ?? '');
        $adviser   = trim($_POST['adviser'] ?? '');
        $president = trim($_POST['president'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $contact   = trim($_POST['contact_no'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';

        if (!($orgName && $acronym && $adviser && $president && $email && $contact && $username && $password !== '')) {
            set_flash('danger', 'All fields are required to add an organization.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Please enter a valid email address.');
        } elseif (strlen($password) < 6) {
            set_flash('danger', 'Password must be at least 6 characters.');
        } else {
            $dup = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
            $dup->execute([$username]);
            if ($dup->fetch()) {
                set_flash('danger', 'That username is already taken. Choose another one.');
            } else {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO organizations (org_name, acronym, adviser, president, email, contact_no) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$orgName, $acronym, $adviser, $president, $email, $contact]);
                    $newOrgId = $pdo->lastInsertId();

                    $hash  = password_hash($password, PASSWORD_DEFAULT);
                    $uStmt = $pdo->prepare("INSERT INTO users (username, password, role, org_id, name) VALUES (?,?,?,?,?)");
                    $uStmt->execute([$username, $hash, 'org', $newOrgId, $president . ' (' . $acronym . ' President)']);
                    $pdo->commit();

                    set_flash('success', 'Organization "' . $orgName . '" added successfully with login account.');
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    set_flash('danger', 'Could not add the organization. Please try again.');
                }
            }
        }
        header('Location: organizations.php');
        exit;
    }

    if ($action === 'toggle_status') {
        $orgId     = intval($_POST['org_id'] ?? 0);
        $newStatus = ($_POST['new_status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        if ($orgId > 0) {
            $pdo->prepare("UPDATE organizations SET status=? WHERE id=?")->execute([$newStatus, $orgId]);
            set_flash('success', 'Organization status updated.');
        }
        header('Location: organizations.php');
        exit;
    }

    if ($action === 'delete_org') {
        $orgId = intval($_POST['org_id'] ?? 0);
        if ($orgId > 0) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM users WHERE org_id=?")->execute([$orgId]);
                $pdo->prepare("DELETE FROM organizations WHERE id=?")->execute([$orgId]);   // cascades to requests/returns/audits
                $pdo->commit();
                set_flash('danger', 'Organization deleted from system.');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                set_flash('danger', 'Could not delete the organization.');
            }
        }
        header('Location: organizations.php');
        exit;
    }
}

// Fetch all organizations with stats
$stmt = $pdo->query("SELECT o.*,
    (SELECT COUNT(*) FROM solicitation_requests WHERE org_id=o.id AND status='pending') as pending_count,
    (SELECT COUNT(*) FROM solicitation_requests WHERE org_id=o.id AND status='approved') as approved_count,
    (SELECT COUNT(*) FROM solicitation_requests WHERE org_id=o.id AND status='denied') as denied_count,
    (SELECT username FROM users WHERE org_id=o.id AND role='org' LIMIT 1) as login_username
    FROM organizations o ORDER BY o.status ASC, o.org_name ASC");
$orgs = $stmt->fetchAll();

// Header outputs HTML, so it is included only after all redirects/POST handling.
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <span style="font-size:0.9rem; color:var(--text-muted);">
            <strong style="color:var(--forest-green);"><?= count($orgs) ?></strong> organization(s) registered.
        </span>
    </div>
    <button class="btn btn-primary" onclick="openModal('addOrgModal')">
        <i class="fa-solid fa-plus-circle"></i> Add Organization
    </button>
</div>

<!-- Organizations Grid -->
<?php if (empty($orgs)): ?>
<div class="card">
    <div class="card-body" style="text-align:center; padding:50px; color:var(--text-muted);">
        <i class="fa-solid fa-sitemap" style="font-size:3rem; margin-bottom:12px; display:block; color:var(--border-color);"></i>
        No organizations registered yet. Click "Add Organization" to get started.
    </div>
</div>
<?php else: ?>
<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(400px,1fr)); gap:20px;">
    <?php foreach ($orgs as $org): ?>
    <div class="card" style="margin-bottom:0; <?= $org['status'] === 'inactive' ? 'opacity:0.7;' : '' ?>">
        <div class="card-header">
            <div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <?php if (!empty($org['logo'])): ?>
                    <img src="../assets/uploads/org_logos/<?= htmlspecialchars(basename($org['logo'])) ?>" alt="" style="width:42px; height:42px; border-radius:50%; object-fit:contain; background:#fff; border:2px solid var(--lemon-lime);">
                    <?php else: ?>
                    <div style="width:42px; height:42px; background:linear-gradient(135deg,var(--royal-green),var(--lemon-lime)); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:0.8rem;">
                        <?= htmlspecialchars(substr($org['acronym'],0,3)) ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:0.95rem;"><?= htmlspecialchars($org['org_name']) ?></strong>
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:6px; align-items:center;">
                <span class="badge badge-<?= $org['status'] === 'active' ? 'active' : 'inactive' ?>">
                    <?= $org['status'] ?>
                </span>
                <button class="btn btn-sm btn-outline-primary" onclick="openOrgProfile(<?= htmlspecialchars(json_encode($org), ENT_QUOTES) ?>)">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
        <div class="card-body" style="padding:14px 20px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.85rem; margin-bottom:14px;">
                <div><strong>President:</strong> <?= htmlspecialchars($org['president']) ?></div>
                <div><strong>Adviser:</strong> <?= htmlspecialchars($org['adviser']) ?></div>
                <div><strong>Email:</strong> <?= htmlspecialchars($org['email']) ?></div>
                <div><strong>Contact:</strong> <?= htmlspecialchars($org['contact_no']) ?></div>
                <div><strong>Login:</strong> <code style="font-size:0.82rem; background:#f0f4ef; padding:2px 6px; border-radius:3px;"><?= htmlspecialchars($org['login_username'] ?? 'N/A') ?></code></div>
                <div><strong>Member since:</strong> <?= date('M j, Y', strtotime($org['created_at'])) ?></div>
            </div>
            <!-- Solicitation Stats -->
            <div style="display:flex; gap:10px; margin-bottom:14px;">
                <span style="background:#fff3cd; color:#856404; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:700;">
                    <i class="fa-solid fa-clock"></i> <?= $org['pending_count'] ?> Pending
                </span>
                <span style="background:var(--yellow-green); color:var(--forest-green); padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:700;">
                    <i class="fa-solid fa-check"></i> <?= $org['approved_count'] ?> Approved
                </span>
                <span style="background:#f8d7da; color:#721c24; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:700;">
                    <i class="fa-solid fa-ban"></i> <?= $org['denied_count'] ?> Denied
                </span>
            </div>
            <!-- Actions -->
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="funds.php?org_id=<?= $org['id'] ?>" class="btn btn-sm btn-blue">
                    <i class="fa-solid fa-coins"></i> Audit Funds
                </a>
                <form method="POST" action="organizations.php" style="display:inline;">
            <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                    <input type="hidden" name="new_status" value="<?= $org['status'] === 'active' ? 'inactive' : 'active' ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-toggle-<?= $org['status'] === 'active' ? 'on' : 'off' ?>"></i>
                        <?= $org['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
                <form method="POST" action="organizations.php" style="display:inline;" 
                      onsubmit="return confirm('PERMANENTLY DELETE this organization and ALL its data? This cannot be undone.');">
            <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_org">
                    <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal: Add Organization -->
<div id="addOrgModal" class="modal-overlay">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <h3><i class="fa-solid fa-plus-circle" style="color:var(--lemon-lime);"></i> Add New Organization</h3>
            <button class="modal-close" onclick="closeModal('addOrgModal')">&times;</button>
        </div>
        <form method="POST" action="organizations.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_org">
            <div class="modal-body">
                <h4 style="font-size:0.9rem; color:var(--forest-green); margin-bottom:14px; border-bottom:1px solid var(--border-color); padding-bottom:6px;">
                    <i class="fa-solid fa-sitemap"></i> Organization Details
                </h4>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label required">Organization Name</label>
                        <input type="text" name="org_name" class="form-control" required placeholder="Full organization name">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label required">Acronym</label>
                        <input type="text" name="acronym" class="form-control" required placeholder="e.g. JPIA">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label required">President / Representative</label>
                        <input type="text" name="president" class="form-control" required placeholder="Full name">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label required">Adviser</label>
                        <input type="text" name="adviser" class="form-control" required placeholder="Faculty adviser name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label required">Official Email</label>
                        <input type="email" name="email" class="form-control" required placeholder="org@university.edu">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label required">Contact Number</label>
                        <input type="text" name="contact_no" class="form-control" required placeholder="+63 9XX XXX XXXX">
                    </div>
                </div>
                <h4 style="font-size:0.9rem; color:var(--forest-green); margin-bottom:14px; border-bottom:1px solid var(--border-color); padding-bottom:6px; margin-top:10px;">
                    <i class="fa-solid fa-lock"></i> Portal Login Account
                </h4>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label required">Username</label>
                        <input type="text" name="username" class="form-control" required placeholder="e.g. jpia2026">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label required">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Min 6 characters">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="closeModal('addOrgModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Add Organization</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Org Profile (View) -->
<div id="orgProfileModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-id-card" style="color:var(--royal-green);"></i> Organization Profile</h3>
            <button class="modal-close" onclick="closeModal('orgProfileModal')">&times;</button>
        </div>
        <div class="modal-body" id="orgProfileBody"></div>
        <div class="modal-footer">
            <button class="btn btn-outline-primary" onclick="closeModal('orgProfileModal')">Close</button>
        </div>
    </div>
</div>

<script>
function openOrgProfile(org) {
    const body = document.getElementById('orgProfileBody');
    body.innerHTML = `
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:20px;">
            ${org.logo
                ? `<img src="../assets/uploads/org_logos/${esc(org.logo.split('/').pop())}" alt="" style="width:56px; height:56px; border-radius:50%; object-fit:contain; background:#fff; border:2px solid var(--lemon-lime);">`
                : `<div style="width:56px; height:56px; background:linear-gradient(135deg,var(--royal-green),var(--lemon-lime)); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:1rem;">${esc(org.acronym.substring(0,3))}</div>`}
            <div>
                <h3 style="font-size:1.1rem; color:var(--forest-green); margin:0;">${esc(org.org_name)}</h3>
                <span class="badge badge-${org.status === 'active' ? 'active' : 'inactive'}">${esc(org.status)}</span>
            </div>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
            ${row('President', org.president)}
            ${row('Adviser', org.adviser)}
            ${row('Email', org.email)}
            ${row('Contact', org.contact_no)}
            ${row('Login Username', org.login_username || 'N/A')}
            ${row('Registered', org.created_at)}
        </table>
    `;
    openModal('orgProfileModal');
}
function row(label, val) {
    return `<tr style="border-bottom:1px solid var(--border-color);">
        <td style="padding:10px 12px; color:var(--text-muted); font-weight:600; width:40%;">${label}</td>
        <td style="padding:10px 12px;">${esc(val)}</td>
    </tr>`;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>