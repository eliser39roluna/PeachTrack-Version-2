<?php
require_once 'db_config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Super-admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !peachtrack_is_superadmin()) {
    header('Location: login.php');
    exit;
}

// Auto-add Is_Read column if missing (graceful migration)
$chk = $conn->query("SHOW COLUMNS FROM contact_inquiry LIKE 'Is_Read'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE contact_inquiry ADD COLUMN Is_Read TINYINT(1) NOT NULL DEFAULT 0");
}

// Mark a single inquiry as read (AJAX or direct link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $id = (int)($_POST['inquiry_id'] ?? 0);
    if ($id > 0) {
        $s = $conn->prepare("UPDATE contact_inquiry SET Is_Read = 1 WHERE Inquiry_ID = ?");
        $s->bind_param('i', $id);
        $s->execute();
        $s->close();
    }
    header('Location: inbox.php');
    exit;
}

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $conn->query("UPDATE contact_inquiry SET Is_Read = 1");
    header('Location: inbox.php');
    exit;
}

// Delete an inquiry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inquiry'])) {
    $id = (int)($_POST['inquiry_id'] ?? 0);
    if ($id > 0) {
        $s = $conn->prepare("DELETE FROM contact_inquiry WHERE Inquiry_ID = ?");
        $s->bind_param('i', $id);
        $s->execute();
        $s->close();
    }
    header('Location: inbox.php');
    exit;
}

// Auto mark as read when viewing individual inquiry
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId > 0) {
    $s = $conn->prepare("UPDATE contact_inquiry SET Is_Read = 1 WHERE Inquiry_ID = ?");
    $s->bind_param('i', $viewId);
    $s->execute();
    $s->close();
}

// Fetch all inquiries
$filter = $_GET['filter'] ?? 'all'; // all | unread | read
$whereClause = match($filter) {
    'unread' => 'WHERE Is_Read = 0',
    'read'   => 'WHERE Is_Read = 1',
    default  => '',
};
$inquiries = [];
$res = $conn->query("SELECT * FROM contact_inquiry $whereClause ORDER BY Submitted_At DESC");
if ($res) $inquiries = $res->fetch_all(MYSQLI_ASSOC);

// Unread count for badge
$unreadCount = 0;
$uc = $conn->query("SELECT COUNT(*) AS cnt FROM contact_inquiry WHERE Is_Read = 0");
if ($uc) $unreadCount = (int)$uc->fetch_assoc()['cnt'];

// Fetch viewed inquiry detail
$viewedInquiry = null;
if ($viewId > 0) {
    $s = $conn->prepare("SELECT * FROM contact_inquiry WHERE Inquiry_ID = ?");
    $s->bind_param('i', $viewId);
    $s->execute();
    $viewedInquiry = $s->get_result()->fetch_assoc();
    $s->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Inbox | PeachTrack Super Admin</title>
  <link rel="stylesheet" href="style.css"/>
  <link rel="stylesheet" href="dashboard.css"/>
  <style>
    :root{
      --accent:#7c3aed;
      --accent2:#9d68f0;
      --accent-bg:rgba(124,58,237,.10);
      --accent-border:rgba(124,58,237,.22);
    }

    .super-label{
      display:inline-flex;align-items:center;gap:4px;
      background:var(--accent-bg);border:1px solid var(--accent-border);
      color:var(--accent);border-radius:8px;padding:2px 8px;
      font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
      margin-top:2px;
    }
    .sidebar-super .brand-logo-img{
      width:48px;height:48px;object-fit:contain;
      mix-blend-mode:screen;-webkit-mix-blend-mode:screen;
      flex-shrink:0;
    }
    .nav-badge{
      display:inline-flex;align-items:center;justify-content:center;
      background:#ef4444;color:#fff;
      font-size:10px;font-weight:800;
      border-radius:99px;padding:1px 6px;
      margin-left:auto;min-width:18px;line-height:1.6;
    }

    /* ── Inbox layout ── */
    .inbox-wrap{
      display:grid;
      grid-template-columns:1fr 1.6fr;
      gap:16px;
      align-items:start;
    }
    @media(max-width:900px){
      .inbox-wrap{grid-template-columns:1fr;}
    }

    .inbox-list-card{background:rgba(255,255,255,.85);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);overflow:hidden;}
    .inbox-list-header{
      padding:16px 20px;
      display:flex;align-items:center;justify-content:space-between;gap:10px;
      border-bottom:1px solid var(--border);
      flex-wrap:wrap;
    }
    .inbox-list-header h2{margin:0;font-size:1rem;font-weight:800;}

    .filter-tabs{display:flex;gap:6px;}
    .filter-tab{
      padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;
      text-decoration:none;color:var(--muted);border:1px solid transparent;
      transition:all .15s;
    }
    .filter-tab:hover{background:rgba(17,24,39,.06);}
    .filter-tab.active{background:var(--accent-bg);border-color:var(--accent-border);color:var(--accent);}

    .inbox-item{
      display:flex;align-items:flex-start;gap:12px;
      padding:14px 20px;
      border-bottom:1px solid var(--border);
      text-decoration:none;color:inherit;
      transition:background .15s;
      cursor:pointer;
    }
    .inbox-item:last-child{border-bottom:none;}
    .inbox-item:hover{background:rgba(17,24,39,.04);}
    .inbox-item.active-view{background:var(--accent-bg);}
    .inbox-item.unread{background:rgba(124,58,237,.04);}

    .inbox-dot{
      width:9px;height:9px;border-radius:50%;flex-shrink:0;margin-top:5px;
      background:var(--accent);
    }
    .inbox-dot.read{background:transparent;border:1.5px solid var(--border);}

    .inbox-item-body{flex:1;min-width:0;}
    .inbox-item-top{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:3px;}
    .inbox-company{font-size:13.5px;font-weight:800;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .inbox-time{font-size:11px;color:var(--muted);white-space:nowrap;flex-shrink:0;}
    .inbox-contact{font-size:12px;color:var(--muted);margin-bottom:2px;}
    .inbox-preview{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    .empty-inbox{padding:40px 20px;text-align:center;color:var(--muted);font-size:13px;}

    /* ── Detail card ── */
    .detail-card{
      background:rgba(255,255,255,.85);
      border:1px solid var(--border);
      border-radius:18px;
      box-shadow:var(--shadow);
      padding:28px;
    }
    .detail-placeholder{
      height:100%;min-height:300px;
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      color:var(--muted);gap:12px;font-size:13px;
    }
    .detail-placeholder .icon{font-size:40px;opacity:.35;}

    .detail-meta{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;}
    .meta-item{
      display:flex;flex-direction:column;gap:2px;
      background:rgba(17,24,39,.04);border:1px solid var(--border);
      border-radius:10px;padding:10px 14px;
      min-width:120px;
    }
    .meta-item .mlabel{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
    .meta-item .mval{font-size:13.5px;font-weight:700;color:var(--text);}
    .meta-item .mval a{color:var(--primary);text-decoration:none;}
    .meta-item .mval a:hover{text-decoration:underline;}

    .detail-message-box{
      background:rgba(17,24,39,.03);
      border:1px solid var(--border);
      border-radius:12px;
      padding:16px 18px;
      font-size:14px;line-height:1.7;
      color:var(--text);
      white-space:pre-wrap;
      margin-bottom:20px;
    }

    .detail-actions{display:flex;gap:8px;flex-wrap:wrap;}
    .btn-read{
      background:rgba(16,185,129,.10);color:#059669;
      border:1px solid rgba(16,185,129,.25);
      padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;
      cursor:pointer;transition:background .15s;
    }
    .btn-read:hover{background:rgba(16,185,129,.18);}
    .btn-reply{
      background:var(--accent-bg);color:var(--accent);
      border:1px solid var(--accent-border);
      padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;
      text-decoration:none;display:inline-flex;align-items:center;gap:6px;
      transition:background .15s;
    }
    .btn-reply:hover{background:rgba(124,58,237,.18);}
    .btn-delete{
      background:rgba(239,68,68,.08);color:#dc2626;
      border:1px solid rgba(239,68,68,.20);
      padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;
      cursor:pointer;transition:background .15s;margin-left:auto;
    }
    .btn-delete:hover{background:rgba(239,68,68,.16);}

    .status-badge{
      display:inline-flex;align-items:center;gap:5px;
      padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700;
    }
    .status-badge.unread{background:var(--accent-bg);color:var(--accent);border:1px solid var(--accent-border);}
    .status-badge.read{background:rgba(16,185,129,.10);color:#059669;border:1px solid rgba(16,185,129,.25);}
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

    <nav class="nav" style="margin-top:24px;">
      <a href="superadmin.php">&#x1F3E2; Companies</a>
      <a class="active" href="inbox.php" style="display:flex;align-items:center;gap:8px;">
        &#x1F4E5; Inbox
        <?php if ($unreadCount > 0): ?>
          <span class="nav-badge"><?php echo $unreadCount; ?></span>
        <?php endif; ?>
      </a>
    </nav>

    <div style="margin-top:auto;padding:12px;border-top:1px solid var(--border);">
      <div style="font-size:12px;color:var(--muted);margin-bottom:8px;">
        Logged in as<br/>
        <strong style="color:var(--text);"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Super Admin'); ?></strong>
      </div>
      <a class="btn btn-ghost" href="logout.php" style="display:block;text-align:center;text-decoration:none;font-size:13px;">Logout</a>
    </div>
  </aside>

  <!-- Main content -->
  <div class="content">

    <!-- Topbar -->
    <div class="topbar">
      <div class="topbar-card">
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-weight:700;font-size:1rem;">&#x1F4E5; Inbox</span>
          <?php if ($unreadCount > 0): ?>
            <span class="status-badge unread">&#x25CF; <?php echo $unreadCount; ?> unread</span>
          <?php else: ?>
            <span class="status-badge read">&#x2713; All read</span>
          <?php endif; ?>
        </div>
        <div style="font-size:13px;color:var(--muted);"><?php echo date('D, M j Y'); ?></div>
      </div>
    </div>

    <div class="main">
      <div class="inbox-wrap">

        <!-- Left: inquiry list -->
        <div class="inbox-list-card">
          <div class="inbox-list-header">
            <h2>&#x1F4EC; Inquiries</h2>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <!-- Filter tabs -->
              <div class="filter-tabs">
                <a href="inbox.php?filter=all"    class="filter-tab <?php echo $filter==='all'    ? 'active' : ''; ?>">All</a>
                <a href="inbox.php?filter=unread" class="filter-tab <?php echo $filter==='unread' ? 'active' : ''; ?>">Unread</a>
                <a href="inbox.php?filter=read"   class="filter-tab <?php echo $filter==='read'   ? 'active' : ''; ?>">Read</a>
              </div>
              <?php if ($unreadCount > 0): ?>
                <form method="POST" style="margin:0;">
                  <button name="mark_all_read" value="1"
                    class="btn btn-ghost" style="font-size:11px;padding:5px 10px;">
                    Mark all read
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <?php if (empty($inquiries)): ?>
            <div class="empty-inbox">
              <div style="font-size:32px;opacity:.3;">&#x1F4EC;</div>
              No inquiries found.
            </div>
          <?php else: ?>
            <?php foreach ($inquiries as $inq): ?>
              <?php
                $isRead   = (int)$inq['Is_Read'] === 1;
                $isActive = $viewId === (int)$inq['Inquiry_ID'];
              ?>
              <a href="inbox.php?view=<?php echo (int)$inq['Inquiry_ID']; ?>&filter=<?php echo htmlspecialchars($filter); ?>"
                 class="inbox-item <?php echo !$isRead ? 'unread' : ''; ?> <?php echo $isActive ? 'active-view' : ''; ?>">
                <div class="inbox-dot <?php echo $isRead ? 'read' : ''; ?>"></div>
                <div class="inbox-item-body">
                  <div class="inbox-item-top">
                    <span class="inbox-company"><?php echo htmlspecialchars($inq['Company']); ?></span>
                    <span class="inbox-time"><?php echo date('M j, g:ia', strtotime($inq['Submitted_At'])); ?></span>
                  </div>
                  <!-- <div class="inbox-contact"><?php echo htmlspecialchars($inq['Contact_Name']); ?> &middot; <?php echo htmlspecialchars($inq['Email']); ?></div>
                  <div class="inbox-preview"><?php echo htmlspecialchars(substr($inq['Message'], 0, 80)) . (strlen($inq['Message']) > 80 ? '…' : ''); ?></div> -->
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Right: detail view -->
        <div class="detail-card">
          <?php if (!$viewedInquiry): ?>
            <div class="detail-placeholder">
              <div class="icon">&#x1F4E8;</div>
              <span>Select an inquiry to read it</span>
            </div>
          <?php else: ?>
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:18px;flex-wrap:wrap;">
              <div>
                <h2 style="margin:0 0 4px;font-size:1.15rem;font-weight:900;"><?php echo htmlspecialchars($viewedInquiry['Company']); ?></h2>
                <div style="font-size:12.5px;color:var(--muted);">Submitted <?php echo date('D, M j Y \a\t g:i a', strtotime($viewedInquiry['Submitted_At'])); ?></div>
              </div>
              <span class="status-badge <?php echo (int)$viewedInquiry['Is_Read'] ? 'read' : 'unread'; ?>">
                <?php echo (int)$viewedInquiry['Is_Read'] ? '&#x2713; Read' : '&#x25CF; Unread'; ?>
              </span>
            </div>

            <div class="detail-meta">
              <div class="meta-item">
                <span class="mlabel">Contact</span>
                <span class="mval"><?php echo htmlspecialchars($viewedInquiry['Contact_Name']); ?></span>
              </div>
              <div class="meta-item">
                <span class="mlabel">Email</span>
                <span class="mval"><a href="mailto:<?php echo htmlspecialchars($viewedInquiry['Email']); ?>"><?php echo htmlspecialchars($viewedInquiry['Email']); ?></a></span>
              </div>
              <?php if ($viewedInquiry['Phone']): ?>
                <div class="meta-item">
                  <span class="mlabel">Phone</span>
                  <span class="mval"><a href="tel:<?php echo htmlspecialchars($viewedInquiry['Phone']); ?>"><?php echo htmlspecialchars($viewedInquiry['Phone']); ?></a></span>
                </div>
              <?php endif; ?>
              <?php if ($viewedInquiry['Employees']): ?>
                <div class="meta-item">
                  <span class="mlabel">Employees</span>
                  <span class="mval"><?php echo htmlspecialchars($viewedInquiry['Employees']); ?></span>
                </div>
              <?php endif; ?>
            </div>

            <div class="detail-message-box"><?php echo htmlspecialchars($viewedInquiry['Message']); ?></div>

            <div class="detail-actions">
              <?php if (!(int)$viewedInquiry['Is_Read']): ?>
                <form method="POST" style="margin:0;">
                  <input type="hidden" name="inquiry_id" value="<?php echo (int)$viewedInquiry['Inquiry_ID']; ?>"/>
                  <button name="mark_read" value="1" class="btn-read">&#x2713; Mark as Read</button>
                </form>
              <?php endif; ?>
              <a href="mailto:<?php echo htmlspecialchars($viewedInquiry['Email']); ?>?subject=Re: Your PeachTrack Demo Request&body=Hi <?php echo rawurlencode($viewedInquiry['Contact_Name']); ?>,%0D%0A%0D%0AThank you for your inquiry about PeachTrack.%0D%0A"
                 class="btn-reply">&#x2709; Reply via Email</a>
              <form method="POST" style="margin:0 0 0 auto;">
                <input type="hidden" name="inquiry_id" value="<?php echo (int)$viewedInquiry['Inquiry_ID']; ?>"/>
                <button name="delete_inquiry" value="1" class="btn-delete"
                  onclick="return confirm('Delete this inquiry? This cannot be undone.')">&#x1F5D1; Delete</button>
              </form>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</div>
</body>
</html>
