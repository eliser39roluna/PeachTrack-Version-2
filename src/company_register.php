<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// Only super-admins (role 100) may access this page
if (empty($_SESSION['loggedin']) || (string)($_SESSION['role'] ?? '') !== '100') {
    header('Location: login.php');
    exit;
}

require 'db_config.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $adminName   = trim($_POST['admin_name']   ?? '');
    $adminEmail  = trim($_POST['admin_email']  ?? '');
    $adminUser   = trim($_POST['admin_user']   ?? '');
    $adminPass   = (string)($_POST['admin_pass'] ?? '');
    $adminPass2  = (string)($_POST['admin_pass2'] ?? '');

    // Basic validation
    if (!$companyName || !$adminName || !$adminEmail || !$adminUser || !$adminPass) {
        $error = 'All fields are required.';
    } elseif ($adminPass !== $adminPass2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($adminPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if admin email (company) already taken
        $chk = $conn->prepare('SELECT Company_ID FROM company WHERE Admin_Email = ?');
        $chk->bind_param('s', $adminEmail);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'That company email is already registered.';
        } else {
            // Check if employee username already taken
            $chk2 = $conn->prepare('SELECT Employee_ID FROM employee WHERE User_Name = ?');
            $chk2->bind_param('s', $adminUser);
            $chk2->execute();
            if ($chk2->get_result()->num_rows > 0) {
                $error = 'That username is already taken. Choose a different login username.';
            }
        }
    }

    if (!$error) {
        $conn->begin_transaction();
        try {
            // 1) Create company
            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('INSERT INTO company (Company_Name, Admin_Email, Admin_Password) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $companyName, $adminEmail, $hash);
            $stmt->execute();
            $companyId = (int)$conn->insert_id;

            // 2) Get next Employee_ID
            $res    = $conn->query('SELECT COALESCE(MAX(Employee_ID), 10000) + 1 AS next_id FROM employee');
            $nextId = (int)($res->fetch_assoc()['next_id'] ?? 10001);

            // 3) Create manager employee (Type_Code 101)
            $typeCode = 101;
            $stmt2 = $conn->prepare('INSERT INTO employee (Employee_ID, Company_ID, Type_Code, Employee_Name, User_Name) VALUES (?, ?, ?, ?, ?)');
            $stmt2->bind_param('iiiss', $nextId, $companyId, $typeCode, $adminName, $adminUser);
            $stmt2->execute();

            // 4) Create credential
            $stmt3 = $conn->prepare('INSERT INTO credential (Employee_ID, Password) VALUES (?, ?)');
            $stmt3->bind_param('is', $nextId, $hash);
            $stmt3->execute();

            $conn->commit();

            // If registered by a super-admin, stay in super-admin session and go back to overview
            if (!empty($_SESSION['loggedin']) && (string)($_SESSION['role'] ?? '') === '100') {
                header('Location: superadmin.php?registered=1');
                exit;
            }

            // Otherwise auto-login the new company manager
            session_regenerate_id(true);
            $_SESSION['loggedin']     = true;
            $_SESSION['id']           = $nextId;
            $_SESSION['name']         = $adminName;
            $_SESSION['role']         = '101';
            $_SESSION['company_id']   = $companyId;
            $_SESSION['company_name'] = $companyName;
            header('Location: index.php');
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo !empty($_SESSION['loggedin']) && ($_SESSION['role'] ?? '') === '100' ? 'Register New Company | Super Admin' : 'Register Your Company'; ?> | PeachTrack</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    :root{--primary:#ff6b4a;--primary2:#ff8a72;--text:#111827;--muted:#6b7280;--border:rgba(17,24,39,.12);--shadow:0 18px 50px rgba(17,24,39,.18);}
    body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;color:var(--text);background:radial-gradient(900px 600px at 20% 10%,rgba(255,107,74,.2),transparent 58%),#f9fafb;padding:24px;}
    .card{background:#fff;border-radius:20px;box-shadow:var(--shadow);padding:40px 44px;width:100%;max-width:480px;}
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:28px;}
    .badge{width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,var(--primary),var(--primary2));display:grid;place-items:center;font-size:22px;}
    h1{margin:0;font-size:1.45rem;font-weight:700;}
    .subtitle{margin:0;color:var(--muted);font-size:.875rem;}
    .section-label{font-size:.75rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);margin:20px 0 10px;}
    label{display:block;font-size:.875rem;font-weight:500;margin-bottom:4px;}
    input{width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:1rem;outline:none;transition:border .2s;}
    input:focus{border-color:var(--primary);}
    .gap{margin-bottom:14px;}
    .btn{width:100%;padding:12px;background:var(--primary);color:#fff;border:none;border-radius:12px;font-size:1rem;font-weight:600;cursor:pointer;margin-top:8px;transition:background .2s;}
    .btn:hover{background:var(--primary2);}
    .alert{padding:10px 14px;border-radius:10px;font-size:.875rem;margin-bottom:16px;}
    .alert-error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
    .login-link{text-align:center;margin-top:18px;font-size:.875rem;color:var(--muted);}
    .login-link a{color:var(--primary);font-weight:600;text-decoration:none;}
    hr.section-divider{border:none;border-top:1.5px solid var(--border);margin:18px 0 4px;}
  </style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="badge">🍑</div>
    <div>
      <h1>PeachTrack</h1>
      <p class="subtitle">Register your company</p>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <p class="section-label">Company Info</p>
    <div class="gap">
      <label>Company Name</label>
      <input type="text" name="company_name" placeholder="e.g. Sunrise Cafe"
             value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" required />
    </div>
    <div class="gap">
      <label>Company Email <small style="color:var(--muted)">(used to identify your company)</small></label>
      <input type="email" name="admin_email" placeholder="you@yourcompany.com"
             value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required />
    </div>

    <hr class="section-divider" />
    <p class="section-label">Manager Account</p>
    <div class="gap">
      <label>Your Full Name</label>
      <input type="text" name="admin_name" placeholder="e.g. Jane Smith"
             value="<?php echo htmlspecialchars($_POST['admin_name'] ?? ''); ?>" required />
    </div>
    <div class="gap">
      <label>Login Username</label>
      <input type="text" name="admin_user" placeholder="e.g. jane.smith"
             value="<?php echo htmlspecialchars($_POST['admin_user'] ?? ''); ?>" required autocomplete="username" />
    </div>
    <div class="gap">
      <label>Password</label>
      <input type="password" name="admin_pass" placeholder="Min. 6 characters" required autocomplete="new-password" />
    </div>
    <div class="gap">
      <label>Confirm Password</label>
      <input type="password" name="admin_pass2" placeholder="Repeat password" required autocomplete="new-password" />
    </div>

    <button class="btn" type="submit">Register Company</button>
  </form>

  <p class="login-link">
    <a href="superadmin.php">&larr; Back to Super Admin</a>
  </p>
</div>
</body>
</html>
