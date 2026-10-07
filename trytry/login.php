<?php
require_once __DIR__ . '/config/auth.php';   // session, $pdo, is_logged_in(), current_user(), set_flash()

/* ------------------------------------------------------------------
 * DEMO MODE
 * true  = demo accounts + sample data are created automatically and
 *         the demo credentials are shown on the login page.
 * false = turn this OFF before real use (the box disappears and nothing
 *         is seeded). Delete the demo users/org from the database too.
 * ------------------------------------------------------------------ */
const DEMO_MODE = true;

const DEMO_ACCOUNTS = [
    'admin' => ['username' => 'demo_admin', 'password' => 'admin123',  'role' => 'admin', 'name' => 'Demo Admin (Student Affairs)'],
    'org'   => ['username' => 'demo_org', 'password' => 'org12345',  'role' => 'org',   'name' => 'Juan Dela Cruz (DEMO President)'],
];

/** Creates the demo organization, both demo users and a little sample data. Safe to call repeatedly. */
function seed_demo(PDO $pdo): void
{
    // Insert the user, or repair it if the username already exists (wrong password/role/org).
    $upsert = function (array $acc, ?int $orgId) use ($pdo): void {
        $hash = password_hash($acc['password'], PASSWORD_DEFAULT);
        $chk = $pdo->prepare('SELECT id, password, role, org_id FROM users WHERE username = ?');
        $chk->execute([$acc['username']]);
        $row = $chk->fetch();
        if (!$row) {
            $pdo->prepare('INSERT INTO users (username, password, role, org_id, name) VALUES (?,?,?,?,?)')
                ->execute([$acc['username'], $hash, $acc['role'], $orgId, $acc['name']]);
        } elseif (!password_verify($acc['password'], $row['password'])
                  || $row['role'] !== $acc['role']
                  || (int)$row['org_id'] !== (int)$orgId) {
            $pdo->prepare('UPDATE users SET password = ?, role = ?, org_id = ?, name = ? WHERE id = ?')
                ->execute([$hash, $acc['role'], $orgId, $acc['name'], $row['id']]);
        }
    };

    // Admin user
    $upsert(DEMO_ACCOUNTS['admin'], null);

    // Demo organization
    $o = DEMO_ACCOUNTS['org'];
    $find = $pdo->prepare("SELECT id FROM organizations WHERE acronym = 'DEMO' LIMIT 1");
    $find->execute();
    $orgId = $find->fetchColumn();
    $newOrg = false;
    if (!$orgId) {
        $pdo->prepare('INSERT INTO organizations (org_name, acronym, department, adviser, president, email, contact_no)
                       VALUES (?,?,?,?,?,?,?)')
            ->execute(['Demo Student Organization', 'DEMO', 'College of Computer Studies', 'Prof. Maria Santos',
                       'Juan Dela Cruz', 'demo@bcc.edu.ph', '0917 000 0000']);
        $orgId = (int)$pdo->lastInsertId();
        $newOrg = true;
    }

    // Org user
    $upsert($o, (int)$orgId);

    // Sample requests/returns, only the first time
    if ($newOrg) {
        $ins = $pdo->prepare("INSERT INTO solicitation_requests
            (control_no, org_id, event_name, purpose, target_amount, datereq, date_needed, status, admin_remarks, approved_by, approved_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $today = date('Y-m-d');
        $ins->execute(['SOL-DEMO-0001', $orgId, 'Tech Summit 2026', 'Funds for speakers, venue and food during the annual tech summit.',
                       15000, date('Y-m-d', strtotime('-20 days')), date('Y-m-d', strtotime('+10 days')),
                       'approved', 'Approved as per Office of Student Affairs guidelines.', 'Dean of Student Affairs', date('Y-m-d H:i:s', strtotime('-15 days'))]);
        $approvedId = (int)$pdo->lastInsertId();
        $ins->execute(['SOL-DEMO-0002', $orgId, 'Coding Bootcamp', 'Learning materials and certificates for 40 student participants.',
                       8000, date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+25 days')),
                       'pending', null, null, null]);
        $ins->execute(['SOL-DEMO-0003', $orgId, 'Org Anniversary Party', 'Party expenses.',
                       5000, date('Y-m-d', strtotime('-12 days')), date('Y-m-d', strtotime('+5 days')),
                       'denied', 'Not aligned with approved student activities; please add a detailed budget.', 'Dean of Student Affairs', date('Y-m-d H:i:s', strtotime('-9 days'))]);

        $ret = $pdo->prepare("INSERT INTO solicitation_returns
            (request_id, student_name, student_id, papers_returned, date_returned, amount_collected, receipt_no, remarks, audit_status)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $ret->execute([$approvedId, 'Ana Reyes',  '2023-10011', 'yes', date('Y-m-d', strtotime('-3 days')), 2500, 'OR-00101', 'All papers returned.', 'audited']);
        $ret->execute([$approvedId, 'Mark Lopez', '2023-10024', 'yes', date('Y-m-d', strtotime('-2 days')), 1800, 'OR-00102', '', 'pending_audit']);
        $ret->execute([$approvedId, 'Lea Garcia', '2023-10037', 'no',  null,                                 0,    '',         'Papers not yet returned.', 'pending_audit']);
    }
}

$demoError = '';
if (DEMO_MODE) {
    try {
        seed_demo($pdo);
    } catch (Throwable $e) {
        $demoError = 'Demo setup failed. Make sure the tables from schema.sql exist in database "sol_system_db".';
    }
}

// Redirect authenticated users
if (is_logged_in()) {
    $user = current_user();
    header('Location: ' . ($user['role'] === 'admin' ? 'admin/index.php' : 'org/index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['pass'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both Username and Password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['name']     = $user['name'];
            $_SESSION['org_id']   = $user['org_id'];

            set_flash('success', 'Welcome back, ' . $user['name'] . '!');
            header('Location: ' . ($user['role'] === 'admin' ? 'admin/index.php' : 'org/index.php'));
            exit;
        }
        $error = 'Invalid Username or Password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BCC USOGSTS | Login</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: sans-serif;
    }

    body {
      position: relative;
      isolation: isolate;
      background-color: #C0DE7B;
      color: #054204;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    body::before {
      content: "";
      position: fixed;
      inset: 0;
      background-image: url('bcclogo.jfif');
      background-repeat: no-repeat;
      background-position: center;
      background-size: contain;
      opacity: 0.2;
      z-index: -1;
      pointer-events: none;
    }

    .header-title {
      background: #79B425;
      border-left: 6px solid #1D6517;
      padding: 1rem;
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.1rem;
      font-weight: bold;
    }

    .main {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 1rem;
    }

    .card {
      width: 100%;
      max-width: 380px;
      background: #fff;
      padding: 2rem;
      border-radius: 8px;
      border: 1px solid #cde0cc;
      border-top: 5px solid #79B425;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .card h2 {
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .alert-error {
      background-color: #f8d7da;
      color: #842029;
      padding: 0.75rem;
      border-radius: 4px;
      border: 1px solid #f5c2c7;
      font-size: 0.85rem;
      margin-bottom: 1.2rem;
      text-align: center;
    }

    label {
      display: block;
      font-weight: bold;
      margin-bottom: 0.3rem;
      font-size: 0.85rem;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 0.6rem;
      border: 1px solid #cde0cc;
      border-radius: 4px;
      outline: none;
    }

    input:focus {
      border-color: #79B425;
    }

    .field {
      margin-bottom: 1.2rem;
    }

    .password-field {
      position: relative;
      display: flex;
      align-items: center;
    }

    .password-field input {
      padding-right: 38px;
    }

    .eye-btn {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
    }

    .eye-btn svg {
      width: 20px;
      height: 20px;
      display: block;
    }

    .btn {
      width: 100%;
      padding: 0.75rem;
      background: #1D6517;
      color: #fff;
      border: none;
      border-radius: 4px;
      font-weight: bold;
      cursor: pointer;
    }

    .btn:hover {
      background: #054204;
    }

    .demo-box {
      margin-top: 1.4rem;
      padding: 0.9rem;
      border: 1px dashed #79B425;
      border-radius: 6px;
      background: #f4f8f3;
      font-size: 0.82rem;
    }
    .demo-box h3 { font-size: 0.85rem; margin-bottom: 0.6rem; text-align: center; }
    .demo-row {
      display: flex; justify-content: space-between; align-items: center;
      gap: 0.5rem; padding: 0.4rem 0; border-top: 1px solid #cde0cc;
    }
    .demo-row:first-of-type { border-top: none; }
    .demo-row code { background: #fff; padding: 1px 5px; border-radius: 3px; border: 1px solid #cde0cc; }
    .demo-use {
      background: #79B425; color: #054204; border: none; border-radius: 4px;
      padding: 0.3rem 0.7rem; font-weight: bold; cursor: pointer; white-space: nowrap;
    }
    .demo-use:hover { background: #6aa11f; }
  </style>
</head>
<body>

  <header class="header-title">
    UNIFIED STUDENT ORGANIZATION GOVERNANCE AND SOLICITATION TRACKING SYSTEM-BCC
  </header>

  <main class="main">
    <div class="card">
      <h2>LOGIN</h2>

      <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form action="" method="post">
        <div class="field">
          <label for="username">Username</label>
          <input 
            type="text" 
            id="username" 
            name="username" 
            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : ''; ?>" 
            required
            autocomplete="username"
          >
        </div>

        <div class="field">
          <label for="pass">Password</label>
          <div class="password-field">
            <input type="password" id="pass" name="pass" required autocomplete="current-password">
            <button type="button" class="eye-btn" onclick="togglePassword()" aria-label="Show or hide password">
              <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#054204" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn">ENTER</button>
      </form>

      <?php if (DEMO_MODE): ?>
        <div class="demo-box">
          <h3>DEMO ACCOUNTS</h3>
          <?php if ($demoError): ?>
            <div class="alert-error" style="margin-bottom:0;"><?php echo htmlspecialchars($demoError, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php else: ?>
            <?php foreach (DEMO_ACCOUNTS as $acc): ?>
              <div class="demo-row">
                <div>
                  <strong><?php echo $acc['role'] === 'admin' ? 'Admin' : 'Organization'; ?></strong><br>
                  <code><?php echo htmlspecialchars($acc['username'], ENT_QUOTES, 'UTF-8'); ?></code> /
                  <code><?php echo htmlspecialchars($acc['password'], ENT_QUOTES, 'UTF-8'); ?></code>
                </div>
                <button type="button" class="demo-use"
                        data-u="<?php echo htmlspecialchars($acc['username'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-p="<?php echo htmlspecialchars($acc['password'], ENT_QUOTES, 'UTF-8'); ?>">Use</button>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script>
    const eyeOpenPath = `<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />`;
    const eyeClosedPath = `<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />`;

    function togglePassword() {
      const pass = document.getElementById("pass");
      const icon = document.getElementById("eye-icon");
      const isPassword = pass.type === "password";
      pass.type = isPassword ? "text" : "password";
      icon.innerHTML = isPassword ? eyeClosedPath : eyeOpenPath;
    }

    document.querySelectorAll('.demo-use').forEach(function (b) {
      b.addEventListener('click', function () {
        document.getElementById('username').value = b.dataset.u;
        document.getElementById('pass').value = b.dataset.p;
        document.getElementById('pass').focus();
      });
    });
  </script>

</body>
</html>