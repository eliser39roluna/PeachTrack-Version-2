<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Only super-admins (role 100) can access this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !peachtrack_is_superadmin()) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// Flash from company registration redirect
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $message = 'New company registered successfully.';
    $messageType = 'success';
}

// Deactivate a company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_company'])) {
    $targetId = (int)($_POST['company_id'] ?? 0);
    if ($targetId > 0) {
        $stmt = $conn->prepare("UPDATE employee SET Is_Active = 0 WHERE Company_ID = ?");
        $stmt->bind_param('i', $targetId);
        if ($stmt->execute()) {
            $message = "All employees for company #$targetId deactivated.";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
}

// Reactivate a company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_company'])) {
    $targetId = (int)($_POST['company_id'] ?? 0);
    if ($targetId > 0) {
        $stmt = $conn->prepare("UPDATE employee SET Is_Active = 1 WHERE Company_ID = ?");
        $stmt->bind_param('i', $targetId);
        if ($stmt->execute()) {
            $message = "All employees for company #$targetId reactivated.";
            $messageType = 'success';
        } else {
            $message = "Error: " . $conn->error;
            $messageType = 'error';
        }
    }
}

// Change superadmin password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPw = $_POST['current_password'] ?? '';
    $newPw     = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';
    $adminId   = (int)($_SESSION['id'] ?? 0);
    if ($newPw !== $confirmPw) {
        $message = 'New passwords do not match.';
        $messageType = 'error';
    } elseif (strlen($newPw) < 8) {
        $message = 'New password must be at least 8 characters.';
        $messageType = 'error';
    } else {
        $row = $conn->query("SELECT Password FROM credential WHERE Employee_ID = $adminId")->fetch_assoc();
        if (!$row || !password_verify($currentPw, $row['Password'])) {
            $message = 'Current password is incorrect.';
            $messageType = 'error';
        } else {
            $hashed = password_hash($newPw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE credential SET Password = ? WHERE Employee_ID = ?");
            $stmt->bind_param('si', $hashed, $adminId);
            if ($stmt->execute()) {
                $message = 'Password updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error updating password: ' . $conn->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

// Upload company logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_logo'])) {
    $targetId = (int)($_POST['company_id'] ?? 0);
    if ($targetId > 0 && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/webp','image/svg+xml'];
        $extMap  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg'];
        // Detect MIME via magic bytes (fileinfo extension not required)
        $tmp  = $_FILES['logo']['tmp_name'];
        $head = file_get_contents($tmp, false, null, 0, 12);
        if (substr($head,0,3) === "\xff\xd8\xff") {
            $mime = 'image/jpeg';
        } elseif (substr($head,0,4) === "\x89PNG") {
            $mime = 'image/png';
        } elseif (substr($head,0,4) === 'RIFF' && substr($head,8,4) === 'WEBP') {
            $mime = 'image/webp';
        } elseif (preg_match('/^\s*(<\?xml[^>]*>)?\s*<svg[\s>]/i', file_get_contents($tmp, false, null, 0, 256))) {
            $mime = 'image/svg+xml';
        } else {
            $mime = 'application/octet-stream';
        }
        if (in_array($mime, $allowed, true) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
            $ext     = $extMap[$mime];
            $logoDir = __DIR__ . '/assets/img/logos/';
            if (!is_dir($logoDir)) mkdir($logoDir, 0755, true);
            // Remove old logo files for this company
            foreach (glob($logoDir . "co_{$targetId}.*") as $old) @unlink($old);
            $filename = "co_{$targetId}.{$ext}";
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoDir . $filename)) {
                $logoPath = "assets/img/logos/{$filename}";
                $stmt = $conn->prepare("UPDATE company SET Logo_Path = ? WHERE Company_ID = ?");
                $stmt->bind_param('si', $logoPath, $targetId);
                $stmt->execute();
                $stmt->close();
                $message = 'Logo updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to save the uploaded file.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid file. Use PNG, JPG, WebP or SVG up to 2 MB.';
            $messageType = 'error';
        }
    }
    header('Location: superadmin.php' . ($message ? '?msg=' . urlencode($message) . '&type=' . $messageType : ''));
    exit;
}

// Remove company logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_logo'])) {
    $targetId = (int)($_POST['company_id'] ?? 0);
    if ($targetId > 0) {
        $row = $conn->query("SELECT Logo_Path FROM company WHERE Company_ID = $targetId")->fetch_assoc();
        if (!empty($row['Logo_Path'])) {
            @unlink(__DIR__ . '/' . $row['Logo_Path']);
        }
        $conn->query("UPDATE company SET Logo_Path = NULL WHERE Company_ID = $targetId");
        $message = 'Logo removed.';
        $messageType = 'success';
    }
    header('Location: superadmin.php');
    exit;
}

// Unread inquiry count for sidebar badge
$unreadInquiries = 0;
$uq = $conn->query("SELECT COUNT(*) AS cnt FROM contact_inquiry WHERE Is_Read = 0");
if ($uq) $unreadInquiries = (int)$uq->fetch_assoc()['cnt'];

// Load all companies with stats
$sql = "
SELECT c.Company_ID,
       c.Company_Name,
       c.Admin_Email,
       c.Created_At,
       c.Logo_Path,
       COUNT(DISTINCT e.Employee_ID)              AS emp_count,
       COUNT(DISTINCT s.Shift_ID)                 AS shift_count,
       COALESCE(SUM(t.Tip_Amount), 0)             AS total_tips,
       COALESCE(MAX(e.Is_Active), 1)              AS company_active
FROM company c
LEFT JOIN employee e   ON e.Company_ID = c.Company_ID
LEFT JOIN shift s      ON s.Employee_ID = e.Employee_ID
LEFT JOIN tip t        ON t.Shift_ID = s.Shift_ID
GROUP BY c.Company_ID, c.Company_Name, c.Admin_Email, c.Created_At
ORDER BY c.Company_ID ASC
";
$companies = [];
$res = $conn->query($sql);
if ($res) $companies = $res->fetch_all(MYSQLI_ASSOC);

$totalEmployees = array_sum(array_column($companies, 'emp_count'));
$totalShifts    = array_sum(array_column($companies, 'shift_count'));
$totalTips      = array_sum(array_column($companies, 'total_tips'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Super Admin | PeachTrack</title>
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="dashboard.css" />
  <style>
    :root{
      --accent:#7c3aed;
      --accent2:#9d68f0;
      --accent-bg:rgba(124,58,237,.10);
      --accent-border:rgba(124,58,237,.22);
    }
    .kpi-grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
      gap:14px;
      margin-bottom:20px;
    }
    .kpi-card{
      background:rgba(255,255,255,.82);
      border:1px solid var(--border);
      border-radius:16px;
      box-shadow:var(--shadow);
      padding:18px 20px;
      display:flex;
      flex-direction:column;
      gap:6px;
    }
    .kpi-card .kpi-icon{
      width:38px;height:38px;border-radius:12px;
      display:grid;place-items:center;
      font-size:18px;font-weight:800;color:#fff;
      background:linear-gradient(135deg,var(--primary),var(--primary2));
      box-shadow:var(--shadow);
    }
    .kpi-card.accent .kpi-icon{background:linear-gradient(135deg,var(--accent),var(--accent2));}
    .kpi-card .kpi-value{font-size:26px;font-weight:800;color:var(--text);line-height:1;}
    .kpi-card .kpi-label{font-size:12px;color:var(--muted);}
    .badge-super{
      display:inline-flex;align-items:center;gap:5px;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      color:#fff;border-radius:8px;padding:3px 10px;
      font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;
    }
    .company-name-cell strong{font-size:.95rem;}
    .company-name-cell .co-email{font-size:.78rem;color:var(--muted);margin-top:2px;}
    .since-badge{
      display:inline-block;
      background:rgba(17,24,39,.05);
      border:1px solid var(--border);
      border-radius:8px;
      padding:2px 8px;font-size:.75rem;color:var(--muted);
    }
    .stat-pill{
      display:inline-block;
      background:rgba(255,107,74,.08);
      color:var(--primary);
      border-radius:8px;
      padding:2px 8px;font-size:.78rem;font-weight:600;
    }
    .action-cell{display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
    .btn-deactivate{
      background:rgba(239,68,68,.10);color:#dc2626;
      border:1px solid rgba(239,68,68,.22);
      padding:6px 12px;border-radius:10px;font-size:.78rem;font-weight:700;
      cursor:pointer;transition:background .15s;
    }
    .btn-deactivate:hover{background:rgba(239,68,68,.18);}
    .btn-activate{
      background:rgba(16,185,129,.10);color:#059669;
      border:1px solid rgba(16,185,129,.22);
      padding:6px 12px;border-radius:10px;font-size:.78rem;font-weight:700;
      cursor:pointer;transition:background .15s;
    }
    .btn-activate:hover{background:rgba(16,185,129,.18);}
    .status-badge-active{
      display:inline-flex;align-items:center;gap:4px;
      background:rgba(16,185,129,.12);color:#059669;
      border:1px solid rgba(16,185,129,.28);
      border-radius:8px;padding:3px 10px;font-size:.75rem;font-weight:700;
    }
    .status-badge-inactive{
      display:inline-flex;align-items:center;gap:4px;
      background:rgba(239,68,68,.10);color:#dc2626;
      border:1px solid rgba(239,68,68,.24);
      border-radius:8px;padding:3px 10px;font-size:.75rem;font-weight:700;
    }
    tr.row-deactivated td{opacity:.62;}

    /* Change password modal */
    .pw-modal-overlay{
      display:none;position:fixed;inset:0;
      background:rgba(17,24,39,.55);backdrop-filter:blur(4px);
      z-index:1000;align-items:center;justify-content:center;
    }
    .pw-modal-overlay.open{display:flex;}
    .pw-modal-box{
      background:#fff;border-radius:20px;padding:28px 28px 24px;
      width:100%;max-width:400px;box-shadow:0 24px 60px rgba(17,24,39,.22);
      animation:fadeUp .18s ease;
    }
    .pw-field-label{
      display:block;font-size:11px;font-weight:700;text-transform:uppercase;
      letter-spacing:.05em;color:var(--muted);margin-bottom:6px;
    }
    .pw-field-input{
      width:100%;padding:9px 12px;border-radius:10px;
      border:1.5px solid var(--border);font-size:13.5px;
      background:#f9fafb;margin-bottom:14px;box-sizing:border-box;
    }
    .pw-field-input:focus{outline:none;border-color:var(--accent);}
    .btn-pw-save{
      width:100%;padding:11px;border-radius:10px;border:none;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      color:#fff;font-weight:800;font-size:14px;cursor:pointer;transition:opacity .15s;
    }
    .btn-pw-save:hover{opacity:.9;}

    /* ── Logo column ── */
    .logo-thumb{
      width:38px;height:38px;border-radius:10px;
      object-fit:contain;border:1px solid var(--border);
      background:#f9fafb;padding:3px;
    }
    .logo-placeholder{
      width:38px;height:38px;border-radius:10px;
      background:rgba(17,24,39,.06);border:1px dashed var(--border);
      display:grid;place-items:center;font-size:18px;color:var(--muted);
    }
    .btn-logo{
      background:rgba(124,58,237,.09);color:var(--accent);
      border:1px solid rgba(124,58,237,.22);
      padding:6px 12px;border-radius:10px;font-size:.78rem;font-weight:700;
      cursor:pointer;transition:background .15s;white-space:nowrap;
    }
    .btn-logo:hover{background:rgba(124,58,237,.16);}

    /* ── Logo modal ── */
    .logo-modal-overlay{
      display:none;position:fixed;inset:0;
      background:rgba(17,24,39,.55);backdrop-filter:blur(4px);
      z-index:1000;align-items:center;justify-content:center;
    }
    .logo-modal-overlay.open{display:flex;}
    .logo-modal-box{
      background:#fff;border-radius:20px;padding:28px 28px 24px;
      width:100%;max-width:420px;box-shadow:0 24px 60px rgba(17,24,39,.22);
      animation:fadeUp .18s ease;
    }
    @keyframes fadeUp{from{transform:translateY(12px);opacity:0;}to{transform:none;opacity:1;}}
    .logo-modal-header{
      display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;
    }
    .logo-modal-header strong{font-size:15px;font-weight:800;color:var(--text);}
    .logo-modal-close{
      background:none;border:none;cursor:pointer;font-size:20px;
      color:var(--muted);line-height:1;padding:2px 6px;border-radius:8px;
    }
    .logo-modal-close:hover{background:rgba(17,24,39,.07);}
    .logo-preview-wrap{
      display:flex;align-items:center;justify-content:center;
      height:90px;border-radius:14px;
      background:rgba(17,24,39,.04);border:1px solid var(--border);
      margin-bottom:18px;
    }
    .logo-preview-wrap img{max-height:76px;max-width:90%;object-fit:contain;border-radius:8px;}
    .logo-preview-wrap .no-logo{font-size:13px;color:var(--muted);}
    .logo-file-label{
      display:block;font-size:11px;font-weight:700;text-transform:uppercase;
      letter-spacing:.05em;color:var(--muted);margin-bottom:7px;
    }
    .logo-file-input{
      width:100%;padding:9px 12px;border-radius:10px;
      border:1.5px solid var(--border);font-size:13px;
      background:#f9fafb;cursor:pointer;margin-bottom:14px;
    }
    .logo-file-hint{font-size:11.5px;color:var(--muted);margin-bottom:16px;}
    .logo-modal-actions{display:flex;gap:8px;align-items:center;}
    .btn-logo-upload{
      flex:1;padding:10px;border-radius:10px;border:none;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      color:#fff;font-weight:800;font-size:13.5px;cursor:pointer;
      transition:opacity .15s;
    }
    .btn-logo-upload:hover{opacity:.9;}
    .btn-logo-remove{
      padding:10px 14px;border-radius:10px;
      background:rgba(239,68,68,.08);color:#dc2626;
      border:1px solid rgba(239,68,68,.20);
      font-weight:700;font-size:13px;cursor:pointer;transition:background .15s;
    }
    .btn-logo-remove:hover{background:rgba(239,68,68,.16);}
    .sidebar-super .brand-logo-img{
      width:48px;height:48px;object-fit:contain;
      mix-blend-mode:screen;-webkit-mix-blend-mode:screen;
      flex-shrink:0;
    }
    .super-label{
      display:inline-flex;align-items:center;gap:4px;
      background:var(--accent-bg);border:1px solid var(--accent-border);
      color:var(--accent);border-radius:8px;padding:2px 8px;
      font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
      margin-top:2px;
    }
    @media(max-width:700px){
      .kpi-grid{grid-template-columns:1fr 1fr;}
      .table-scroll{overflow-x:auto;}
    }
  </style>
</head>
<body class="app">
<div class="app-shell">

  <!-- Sidebar -->
  <aside class="sidebar sidebar-super" aria-label="Super Admin Sidebar">
    <div class="brand">
      <img src="assets/img/peachtrack-logo.png" class="brand-logo-img" alt="PeachTrack"/>
      <div>
        <h1>PeachTrack</h1>
        <div class="super-label">&#x26A1; Super Admin</div>
      </div>
    </div>

    <style>
    .nav-badge{display:inline-flex;align-items:center;justify-content:center;background:#ef4444;color:#fff;font-size:10px;font-weight:800;border-radius:99px;padding:1px 6px;margin-left:auto;min-width:18px;line-height:1.6;}
    </style>
    <nav class="nav" style="margin-top:24px;">
      <a class="active" href="superadmin.php">&#x1F3E2; Companies</a>
      <a href="inbox.php" style="display:flex;align-items:center;gap:8px;">
        &#x1F4E5; Inbox
        <?php if ($unreadInquiries > 0): ?>
          <span class="nav-badge"><?php echo $unreadInquiries; ?></span>
        <?php endif; ?>
      </a>
    </nav>

    <div style="margin-top:auto;padding:12px;border-top:1px solid var(--border);">
      <div style="font-size:12px;color:var(--muted);margin-bottom:8px;">
        Logged in as<br/>
        <strong style="color:var(--text);"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Super Admin'); ?></strong>
      </div>
      <button type="button" onclick="openPwModal()" class="btn btn-ghost" style="display:block;width:100%;text-align:center;font-size:13px;margin-bottom:6px;cursor:pointer;background:rgba(124,58,237,.07);border:1px solid rgba(124,58,237,.18);color:var(--accent);border-radius:10px;padding:8px;">&#x1F512; Change Password</button>
      <a class="btn btn-ghost" href="logout.php" style="display:block;text-align:center;text-decoration:none;font-size:13px;">Logout</a>
    </div>
  </aside>

  <!-- Main content -->
  <div class="content">

    <!-- Topbar -->
    <div class="topbar">
      <div class="topbar-card">
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-weight:700;font-size:1rem;">Platform Overview</span>
          <span class="badge-super">Super Admin</span>
        </div>
        <div style="font-size:13px;color:var(--muted);"><?php echo date('D, M j Y'); ?></div>
      </div>
    </div>

    <!-- Page body -->
    <div class="main">

      <?php if ($message): ?>
        <div class="alert <?php echo htmlspecialchars($messageType); ?>" style="margin-bottom:16px;">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>

      <!-- KPI row -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-icon">&#x1F3E2;</div>
          <div class="kpi-value"><?php echo count($companies); ?></div>
          <div class="kpi-label">Registered Companies</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon">&#x1F464;</div>
          <div class="kpi-value"><?php echo $totalEmployees; ?></div>
          <div class="kpi-label">Total Employees</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon">&#x1F552;</div>
          <div class="kpi-value"><?php echo $totalShifts; ?></div>
          <div class="kpi-label">Total Shifts</div>
        </div>
        <div class="kpi-card accent">
          <div class="kpi-icon">&#x1F4B0;</div>
          <div class="kpi-value">$<?php echo number_format($totalTips, 0); ?></div>
          <div class="kpi-label">All-time Tips</div>
        </div>
      </div>

      <!-- Companies table -->
      <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
          <div>
            <h2 style="margin:0;font-size:1.05rem;font-weight:800;">&#x1F3E2; All Companies</h2>
            <div class="muted" style="font-size:12px;margin-top:2px;">Every tenant registered on this platform</div>
          </div>
          <a class="btn btn-primary" href="company_register.php" style="text-decoration:none;font-size:13px;">+ Register New Company</a>
        </div>

        <div class="table-scroll">
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>Logo</th>
                <th>Company</th>
                <th>Registered</th>
                <th>Employees</th>
                <th>Shifts</th>
                <th>Total Tips</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($companies)): ?>
                <tr><td colspan="7" class="muted" style="text-align:center;padding:24px;">No companies registered yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($companies as $co): ?>
                <tr<?php echo ($co['company_active'] == 0) ? ' class="row-deactivated"' : ''; ?>>
                  <td class="muted" style="font-size:.8rem;">#<?php echo (int)$co['Company_ID']; ?></td>
                  <td>
                    <?php if (!empty($co['Logo_Path'])): ?>
                      <img src="<?php echo htmlspecialchars($co['Logo_Path']); ?>" class="logo-thumb" alt="logo"/>
                    <?php else: ?>
                      <div class="logo-placeholder">&#x1F4F7;</div>
                    <?php endif; ?>
                  </td>
                  <td class="company-name-cell">
                    <strong><?php echo htmlspecialchars($co['Company_Name']); ?></strong>
                    <div class="co-email"><?php echo htmlspecialchars($co['Admin_Email']); ?></div>
                  </td>
                  <td><span class="since-badge"><?php echo date('M j, Y', strtotime($co['Created_At'])); ?></span></td>
                  <td><span class="stat-pill">&#x1F464; <?php echo (int)$co['emp_count']; ?></span></td>
                  <td><span class="stat-pill">&#x1F552; <?php echo (int)$co['shift_count']; ?></span></td>
                  <td><strong>$<?php echo number_format((float)$co['total_tips'], 2); ?></strong></td>
                  <td>
                    <?php if ($co['company_active'] == 1): ?>
                      <span class="status-badge-active">&#x2713; Active</span>
                    <?php else: ?>
                      <span class="status-badge-inactive">&#x2715; Deactivated</span>
                    <?php endif; ?>
                  </td>
                  <td class="action-cell">
                    <button class="btn-logo" type="button"
                      onclick="openLogoModal(
                        <?php echo (int)$co['Company_ID']; ?>,
                        '<?php echo htmlspecialchars(addslashes($co['Company_Name'])); ?>',
                        '<?php echo htmlspecialchars(addslashes($co['Logo_Path'] ?? '')); ?>'
                      )">&#x1F5BC; Logo</button>
                    <?php if ($co['company_active'] == 1): ?>
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="company_id" value="<?php echo (int)$co['Company_ID']; ?>" />
                      <button class="btn-deactivate" name="deactivate_company" value="1"
                        onclick="return confirm('Deactivate all employees of <?php echo htmlspecialchars(addslashes($co['Company_Name'])); ?>?')">
                        Deactivate
                      </button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="company_id" value="<?php echo (int)$co['Company_ID']; ?>" />
                      <button class="btn-activate" name="reactivate_company" value="1"
                        onclick="return confirm('Reactivate all employees of <?php echo htmlspecialchars(addslashes($co['Company_Name'])); ?>?')">
                        Reactivate
                      </button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /.main -->
  </div><!-- /.content -->
</div><!-- /.app-shell -->

<!-- ── Logo Modal ──────────────────────────────────────────── -->
<div class="logo-modal-overlay" id="logoModalOverlay" onclick="if(event.target===this)closeLogoModal()">
  <div class="logo-modal-box">
    <div class="logo-modal-header">
      <strong id="logoModalTitle">Company Logo</strong>
      <button class="logo-modal-close" type="button" onclick="closeLogoModal()">&#x2715;</button>
    </div>
    <div class="logo-preview-wrap" id="logoPreviewWrap">
      <span class="no-logo">No logo set</span>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="company_id" id="logoModalCompanyId"/>
      <label class="logo-file-label">Upload New Logo</label>
      <input type="file" name="logo" class="logo-file-input" accept="image/png,image/jpeg,image/webp,image/svg+xml" required/>
      <div class="logo-file-hint">PNG, JPG, WebP or SVG &mdash; max 2 MB. Replaces any existing logo.</div>
      <div class="logo-modal-actions">
        <button type="submit" name="upload_logo" value="1" class="btn-logo-upload">&#x2191; Upload Logo</button>
        <span id="logoRemoveWrap"></span>
      </div>
    </form>
  </div>
</div>

<!-- Change Password Modal -->
<div class="pw-modal-overlay" id="pwModalOverlay" onclick="if(event.target===this)closePwModal()">
  <div class="pw-modal-box">
    <div class="logo-modal-header">
      <strong>&#x1F512; Change Password</strong>
      <button class="logo-modal-close" type="button" onclick="closePwModal()">&#x2715;</button>
    </div>
    <form method="POST" autocomplete="off">
      <label class="pw-field-label">Current Password</label>
      <input class="pw-field-input" type="password" name="current_password" required placeholder="Enter current password" />
      <label class="pw-field-label">New Password</label>
      <input class="pw-field-input" type="password" name="new_password" required placeholder="At least 8 characters" minlength="8" />
      <label class="pw-field-label">Confirm New Password</label>
      <input class="pw-field-input" type="password" name="confirm_password" required placeholder="Repeat new password" />
      <button type="submit" name="change_password" value="1" class="btn-pw-save">Update Password</button>
    </form>
  </div>
</div>

<script>
function openPwModal()  { document.getElementById('pwModalOverlay').classList.add('open'); }
function closePwModal() { document.getElementById('pwModalOverlay').classList.remove('open'); }
document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ closePwModal(); closeLogoModal(); } });

function openLogoModal(id, name, logoPath) {
  document.getElementById('logoModalCompanyId').value = id;
  document.getElementById('logoModalTitle').textContent = name + ' — Logo';
  var wrap = document.getElementById('logoPreviewWrap');
  wrap.innerHTML = logoPath
    ? '<img src="' + logoPath + '?t=' + Date.now() + '" alt="logo"/>'
    : '<span class="no-logo">No logo set</span>';
  var rw = document.getElementById('logoRemoveWrap');
  rw.innerHTML = logoPath
    ? '<form method="POST" style="margin:0"><input type="hidden" name="company_id" value="' + id + '"><button type="submit" name="remove_logo" value="1" class="btn-logo-remove" onclick="return confirm(\'Remove this logo?\')">&#x1F5D1; Remove</button></form>'
    : '';
  document.getElementById('logoModalOverlay').classList.add('open');
}
function closeLogoModal() {
  document.getElementById('logoModalOverlay').classList.remove('open');
}
</script>

</body>
</html>
