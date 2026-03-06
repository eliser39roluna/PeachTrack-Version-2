<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Managers only
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (string)($_SESSION['role'] ?? '') !== '101') {
    header('Location: index.php');
    exit;
}

$companyId   = (int)($_SESSION['company_id'] ?? 0);
$message     = '';
$messageType = '';

// Add service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $name  = trim($_POST['service_name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    if ($name === '') {
        $message     = 'Service name is required.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO service (Company_ID, Service_Name, Price) VALUES (?, ?, ?)");
        $stmt->bind_param('isd', $companyId, $name, $price);
        if ($stmt->execute()) {
            $message     = 'Service added.';
            $messageType = 'success';
        } else {
            $message     = 'Error: ' . $conn->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Deactivate service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_service'])) {
    $sid  = (int)($_POST['service_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE service SET Is_Active = 0 WHERE Service_ID = ? AND Company_ID = ?");
    $stmt->bind_param('ii', $sid, $companyId);
    $stmt->execute();
    $message     = 'Service deactivated.';
    $messageType = 'success';
}

// Reactivate service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_service'])) {
    $sid  = (int)($_POST['service_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE service SET Is_Active = 1 WHERE Service_ID = ? AND Company_ID = ?");
    $stmt->bind_param('ii', $sid, $companyId);
    $stmt->execute();
    $message     = 'Service reactivated.';
    $messageType = 'success';
}

// Load services — newest first
$services = [];
$stmt = $conn->prepare("SELECT Service_ID, Service_Name, Price, Is_Active FROM service WHERE Company_ID = ? ORDER BY Service_ID DESC");
if ($stmt) {
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

require_once "header.php";
?>

<?php if ($message): ?>
  <div class="alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card">
  <h2 style="margin-top:0;">&#x1F6CE; Manage Services</h2>
  <p class="muted">Add the services your staff perform. Employees must select a service when logging a tip — the sale amount auto-fills with the price.</p>

  <form method="POST" style="display:grid; grid-template-columns: 1fr 160px auto; gap:12px; align-items:end; margin-top:18px;">
    <div>
      <label>Service Name <span style="color:#dc2626;">*</span></label>
      <input type="text" name="service_name" placeholder="e.g. Haircut, Color, Blowout" required />
    </div>
    <div>
      <label>Price ($)</label>
      <input type="number" step="0.01" min="0" name="price" placeholder="0.00" />
    </div>
    <div>
      <button class="btn btn-primary" type="submit" name="add_service" value="1">Add Service</button>
    </div>
  </form>
</div>

<div style="height:14px"></div>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
    <h3 style="margin:0;">All Services</h3>
    <input type="text" id="svcSearch" placeholder="Search services..." oninput="filterServices()"
      style="padding:7px 12px;border-radius:10px;border:1.5px solid var(--border);font-size:13px;width:220px;" />
  </div>
  <?php if (empty($services)): ?>
    <p class="muted">No services added yet. Use the form above to add your first service.</p>
  <?php else: ?>
    <table class="table" id="svcTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Service Name</th>
          <th>Price</th>
          <th>Status</th>
          <th class="no-print">Actions</th>
        </tr>
      </thead>
      <tbody id="svcTbody">
        <?php foreach ($services as $svc): ?>
          <tr data-name="<?php echo strtolower(htmlspecialchars($svc['Service_Name'])); ?>"
              style="<?php echo ((int)$svc['Is_Active'] === 0) ? 'opacity:0.62;' : ''; ?>">
            <td class="muted" style="font-size:.8rem;">#<?php echo (int)$svc['Service_ID']; ?></td>
            <td><strong><?php echo htmlspecialchars($svc['Service_Name']); ?></strong></td>
            <td>$<?php echo number_format((float)$svc['Price'], 2); ?></td>
            <td>
              <?php if ((int)$svc['Is_Active'] === 1): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;background:rgba(16,185,129,.12);color:#059669;border:1px solid rgba(16,185,129,.28);border-radius:8px;padding:3px 10px;font-size:.75rem;font-weight:700;">&#x2713; Active</span>
              <?php else: ?>
                <span style="display:inline-flex;align-items:center;gap:4px;background:rgba(239,68,68,.10);color:#dc2626;border:1px solid rgba(239,68,68,.24);border-radius:8px;padding:3px 10px;font-size:.75rem;font-weight:700;">&#x2715; Deactivated</span>
              <?php endif; ?>
            </td>
            <td class="no-print">
              <?php if ((int)$svc['Is_Active'] === 1): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="service_id" value="<?php echo (int)$svc['Service_ID']; ?>" />
                  <button class="btn btn-secondary" type="submit" name="deactivate_service" value="1"
                    onclick="return confirm('Deactivate &quot;<?php echo htmlspecialchars(addslashes($svc['Service_Name'])); ?>&quot;? Employees won\'t see it in the tip form.')">
                    Deactivate
                  </button>
                </form>
              <?php else: ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="service_id" value="<?php echo (int)$svc['Service_ID']; ?>" />
                  <button class="btn btn-primary" type="submit" name="reactivate_service" value="1">Reactivate</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div id="svcFooter" style="margin-top:12px;display:flex;align-items:center;gap:12px;font-size:13px;color:var(--muted);">
      <span id="svcCount"></span>
      <button id="svcToggleBtn" type="button" class="btn btn-ghost" style="font-size:12px;padding:4px 12px;" onclick="toggleShowAll()"></button>
    </div>
  <?php endif; ?>
</div>

<script>
const SVC_PAGE = 5;
let svcShowAll = false;

function filterServices() {
  const q = document.getElementById('svcSearch').value.toLowerCase().trim();
  svcShowAll = q.length > 0; // auto-expand when searching
  renderServices();
}

function toggleShowAll() {
  svcShowAll = !svcShowAll;
  renderServices();
}

function renderServices() {
  const q      = document.getElementById('svcSearch').value.toLowerCase().trim();
  const rows   = Array.from(document.querySelectorAll('#svcTbody tr'));
  const matched = rows.filter(r => !q || r.dataset.name.includes(q));

  rows.forEach(r => r.style.display = 'none');
  const visible = svcShowAll ? matched : matched.slice(0, SVC_PAGE);
  visible.forEach(r => r.style.display = '');

  const countEl  = document.getElementById('svcCount');
  const btnEl    = document.getElementById('svcToggleBtn');
  const footer   = document.getElementById('svcFooter');

  countEl.textContent = q
    ? matched.length + ' result' + (matched.length !== 1 ? 's' : '') + ' for "' + q + '"'
    : 'Showing ' + visible.length + ' of ' + matched.length + ' service' + (matched.length !== 1 ? 's' : '');

  if (matched.length > SVC_PAGE && !q) {
    btnEl.style.display = '';
    btnEl.textContent   = svcShowAll ? 'Show less' : 'Show all ' + matched.length;
    footer.style.display = 'flex';
  } else if (q) {
    btnEl.style.display  = 'none';
    footer.style.display = 'flex';
  } else {
    btnEl.style.display  = 'none';
    footer.style.display = matched.length > 0 ? 'flex' : 'none';
  }
}

document.addEventListener('DOMContentLoaded', renderServices);
</script>

<?php require_once "footer.php"; ?>
