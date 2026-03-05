<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require 'db_config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // If Is_Active column exists, block deactivated accounts. Fallback gracefully if DB hasn't been migrated yet.
    // Also fetch Company_ID if multi-tenancy migration has been run.
    $hasCompany = peachtrack_has_column($conn, 'employee', 'Company_ID');
    $companySelect = $hasCompany ? ', e.Company_ID' : '';
    $companyJoin   = $hasCompany ? ' LEFT JOIN company co ON co.Company_ID = e.Company_ID' : '';
    $companyName   = $hasCompany ? ', co.Company_Name, co.Logo_Path' : '';

    $sql = "SELECT e.Employee_ID, e.Employee_Name, e.Type_Code, c.Password{$companySelect}{$companyName}
            FROM employee e
            JOIN credential c ON e.Employee_ID = c.Employee_ID
            {$companyJoin}
            WHERE e.User_Name = ?
              AND (e.Is_Active IS NULL OR e.Is_Active = 1)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Fallback for older schema (no Is_Active column)
        $sql = "SELECT e.Employee_ID, e.Employee_Name, e.Type_Code, c.Password
                FROM employee e
                JOIN credential c ON e.Employee_ID = c.Employee_ID
                WHERE e.User_Name = ?";
        $stmt = $conn->prepare($sql);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['Password'])) {
            session_regenerate_id(true);
            $_SESSION['loggedin']     = true;
            $_SESSION['id']           = $user['Employee_ID'];
            $_SESSION['name']         = $user['Employee_Name'];
            $_SESSION['role']         = $user['Type_Code'];
            $_SESSION['company_id']   = (int)($user['Company_ID'] ?? 0);
            $_SESSION['company_name'] = (string)($user['Company_Name'] ?? 'PeachTrack');
            $_SESSION['company_logo'] = (string)($user['Logo_Path'] ?? '');
            // Super admin goes to their own panel
            $redirect = ((string)$user['Type_Code'] === '100') ? 'superadmin.php' : 'index.php';
            header("Location: $redirect");
            exit;
        }
    }
    $error = "Invalid username or password.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign In | PeachTrack</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    :root{
      --primary:#ff6b4a;
      --primary2:#ff8a72;
      --text:#111827;
      --muted:#6b7280;
      --border:rgba(17,24,39,.12);
      --shadow:0 18px 50px rgba(17,24,39,.22);
    }

    *{box-sizing:border-box;}

    body{
      margin:0;
      min-height:100vh;
      display:grid;
      place-items:center;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
      color:var(--text);
      background:
        radial-gradient(1000px 700px at 15% 10%, rgba(255,107,74,.18), transparent 58%),
        radial-gradient(900px 600px at 90% 0%,  rgba(255,138,114,.14), transparent 60%),
        linear-gradient(135deg, rgba(17,24,39,.28), rgba(17,24,39,.10)),
        url('assets/img/login-bg2.jpg');
      background-repeat:no-repeat;
      background-size:cover;
      background-position:center;
      padding:28px;
    }

    .wrap{
      width:100%;
      max-width:1000px;
      display:grid;
      grid-template-columns:1.15fr 1fr;
      gap:16px;
      align-items:stretch;
    }

    .panel{
      border-radius:24px;
      overflow:hidden;
      position:relative;
      box-shadow:var(--shadow);
      border:1px solid rgba(255,255,255,.22);
    }

    /* ── Left: platform brand panel ─────────────────────────── */
    .brand-panel{
      background:rgba(15,8,3,.72);
      backdrop-filter:blur(18px);
      -webkit-backdrop-filter:blur(18px);
      border:1px solid rgba(255,138,114,.18);
      position:relative;
      overflow:hidden;
    }
    /* subtle peach glow accent top-right */
    .brand-panel::before{
      content:'';
      position:absolute;inset:0;
      background:
        radial-gradient(600px 400px at 110% -10%, rgba(255,107,74,.28), transparent 60%),
        radial-gradient(400px 400px at -10% 110%, rgba(255,138,114,.14), transparent 55%);
      pointer-events:none;
    }
    .brand-inner{
      height:100%;
      padding:36px 32px;
      display:flex;
      flex-direction:column;
      gap:30px;
      color:#fff;
      position:relative;
      z-index:1;
    }

    .brand-logo{text-align:center;margin-bottom:4px;}
    .logo-img{
      width:210px;height:auto;
      display:block;margin:0 auto;
      mix-blend-mode:screen;
      -webkit-mix-blend-mode:screen;
    }
    .logo-sub{
      margin:6px 0 0;
      font-size:11px;font-weight:600;letter-spacing:.1em;
      text-transform:uppercase;color:rgba(255,138,114,.9);
      text-align:center;
    }

    .brand-pitch h2{
      margin:0 0 10px;
      font-size:28px;font-weight:900;line-height:1.2;
      letter-spacing:-.4px;color:#fff;
    }
    .brand-pitch h2 span{
      background:linear-gradient(90deg,var(--primary2),#ffd580);
      -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    }
    .brand-pitch p{margin:0;font-size:13.5px;line-height:1.65;color:rgba(255,255,255,.80);}

    .brand-cta{
      margin-top:4px;
    }
    .btn-demo{
      display:inline-flex;align-items:center;gap:8px;
      width:100%;
      padding:13px 18px;
      border-radius:12px;
      border:1px solid rgba(255,138,114,.18);
      background:rgba(255,255,255,.06);
      color:#fff;
      font-size:13.5px;font-weight:700;
      text-decoration:none;
      cursor:pointer;
      transition:background .2s, border-color .2s, transform .15s;
      justify-content:center;
    }
    .btn-demo:hover{
      background:rgba(255,255,255,.10);
      border-color:rgba(255,138,114,.40);
      transform:translateY(-1px);
    }
    .btn-demo-icon{
      width:30px;height:30px;border-radius:8px;flex-shrink:0;
      background:linear-gradient(135deg,var(--primary),var(--primary2));
      display:grid;place-items:center;font-size:15px;
      box-shadow:0 4px 12px rgba(255,107,74,.40);
    }
    .btn-demo-text strong{display:block;font-size:13px;font-weight:800;line-height:1.2;}
    .btn-demo-text small{font-size:10.5px;opacity:.70;font-weight:500;}

    .brand-footer{
      margin-top:auto;
      display:flex;align-items:center;gap:8px;
      font-size:11px;color:rgba(255,255,255,.50);
      flex-wrap:wrap;
    }
    .dot{width:3px;height:3px;border-radius:50%;background:rgba(255,138,114,.6);}

    /* ── Right: login card ───────────────────────────────────── */
    .login-card{
      background:rgba(15,8,3,.72);
      backdrop-filter:blur(18px);
      -webkit-backdrop-filter:blur(18px);
      border:1px solid rgba(255,138,114,.18);
      padding:36px 32px;
      display:flex;
      flex-direction:column;
      justify-content:center;
      color:#fff;
    }

    .login-header{margin-bottom:24px;}
    .login-header h3{margin:0 0 6px;font-size:24px;font-weight:900;color:#fff;}
    .login-header p{margin:0;font-size:13px;color:rgba(255,255,255,.65);line-height:1.5;}

    .form-group{margin-bottom:16px;}
    label{
      display:block;font-weight:700;font-size:11px;
      letter-spacing:.06em;text-transform:uppercase;
      margin-bottom:7px;color:rgba(255,255,255,.80);
    }
    input[type=text], input[type=password]{
      width:100%;
      padding:12px 15px;
      border-radius:12px;
      border:1.5px solid rgba(255,138,114,.22);
      background:rgba(255,255,255,.94);
      color:#111827;
      font-size:14px;
      outline:none;
      transition:border-color .2s, box-shadow .2s;
    }
    input:focus{
      border-color:var(--primary);
      box-shadow:0 0 0 4px rgba(255,107,74,.20);
      background:#fff;
    }

    .btn-login{
      width:100%;
      margin-top:8px;
      padding:14px;
      border-radius:12px;
      border:0;
      cursor:pointer;
      font-weight:800;
      font-size:15px;
      letter-spacing:.02em;
      color:#fff;
      background:linear-gradient(135deg,var(--primary),var(--primary2));
      box-shadow:0 12px 32px rgba(255,107,74,.40);
      transition:opacity .2s, box-shadow .2s, transform .15s;
    }
    .btn-login:hover{opacity:.93;box-shadow:0 16px 40px rgba(255,107,74,.48);transform:translateY(-1px);}
    .btn-login:active{transform:translateY(0);}

    .alert{padding:11px 14px;border-radius:12px;margin-bottom:16px;font-size:13px;font-weight:600;}
    .alert.error{background:rgba(239,68,68,.18);border:1px solid rgba(239,68,68,.35);color:#fca5a5;}
    .alert.success{background:rgba(16,185,129,.18);border:1px solid rgba(16,185,129,.35);color:#6ee7b7;}

    .login-footer{
      margin-top:20px;
      padding-top:18px;
      border-top:1px solid rgba(255,138,114,.15);
      font-size:12.5px;
      color:rgba(255,255,255,.60);
      line-height:1.8;
    }
    .login-footer a{color:var(--primary2);font-weight:700;text-decoration:none;}
    .login-footer a:hover{color:#fff;text-decoration:underline;}

    .saas-badge{
      display:inline-flex;align-items:center;gap:6px;
      background:linear-gradient(135deg,var(--primary),var(--primary2));
      color:#fff;
      border-radius:10px;
      padding:5px 12px;font-size:11px;font-weight:800;letter-spacing:.06em;
      text-transform:uppercase;margin-bottom:18px;
      box-shadow:0 6px 18px rgba(255,107,74,.35);
    }

    @media(max-width:860px){
      .wrap{grid-template-columns:1fr;}
      .brand-panel{display:none;}
    }
    @media(max-width:500px){
      .login-card{padding:24px 18px;}
    }
  </style>
</head>
<body>

  <div class="wrap">

    <!-- Left: platform sell panel -->
    <section class="panel brand-panel" aria-label="PeachTrack Platform">
      <div class="brand-inner">

        <div class="brand-logo">
          <img src="assets/img/peachtrack-logo.png" class="logo-img" alt="PeachTrack"/>
          <p class="logo-sub">Workforce &amp; Payroll Management</p>
        </div>

        <div class="brand-pitch">
          <h2>Everything your team<br/>needs&mdash;<span>in one place</span>.</h2>
          <p>PeachTrack powers shift scheduling, tip tracking, and payroll reporting for service businesses of every size.</p>
        </div>

        <div class="brand-cta">
          <a href="contact.php" class="btn-demo">
            <div class="btn-demo-icon">&#x1F4E9;</div>
            <div class="btn-demo-text">
              <strong>For More Details - Click Me</strong>
              <small>Get PeachTrack for your business?</small>
            </div>
          </a>
        </div>

        <div class="brand-footer">
          <span>Multi-company ready</span><div class="dot"></div>
          <span>Secure login</span><div class="dot"></div>
          <span>Role-based access</span><div class="dot"></div>
          <span>Live shift view</span>
        </div>

      </div>
    </section>

    <!-- Right: login form -->
    <section class="panel login-card" aria-label="Sign In">

      <div class="saas-badge">&#x1F3E2; Company Portal</div>

      <div class="login-header">
        <h3>Welcome back</h3>
        <p>Sign in to your company&rsquo;s PeachTrack workspace.</p>
      </div>

      <?php if(isset($_GET['registered'])): ?>
        <div class="alert success">&#x2713; Company registered! You can now sign in.</div>
      <?php endif; ?>

      <?php if(isset($error)): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="login.php" method="POST" autocomplete="on">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required
                 placeholder="your.username" autocomplete="username" />
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required
                 placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" autocomplete="current-password" />
        </div>

        <button type="submit" class="btn-login">Sign In &rarr;</button>
      </form>

      <div class="login-footer">
        <span style="opacity:.65;">Don&rsquo;t have an account? Ask your manager to add you.</span>
      </div>

    </section>

  </div>

</body>
</html>
