<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Only Managers/Admins
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (string)($_SESSION['role'] ?? '') !== '101') {
    header('Location: index.php');
    exit;
}

$message = "";
$messageType = "";

// Force-end shift (audit-friendly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_end_shift'])) {
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    if ($shiftId > 0) {
        $end = date('Y-m-d H:i:s');
        $endedBy = (int)($_SESSION['id'] ?? 0);
        $reason = 'Forced end';

        // Prefer audit columns if present; fallback if schema not migrated yet.
        $sql = "UPDATE shift SET End_Time = ?, Ended_By = ?, End_Reason = ? WHERE Shift_ID = ? AND End_Time IS NULL";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $sql = "UPDATE shift SET End_Time = ? WHERE Shift_ID = ? AND End_Time IS NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $end, $shiftId);
        } else {
            $stmt->bind_param("sisi", $end, $endedBy, $reason, $shiftId);
        }

        if ($stmt->execute()) {
            $message = "Shift #$shiftId force-ended at $end";
            $messageType = "success";
        } else {
            $message = "Error force-ending shift: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Delete tip (soft delete to prevent data loss)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tip'])) {
    $tipId = (int)($_POST['tip_id'] ?? 0);
    if ($tipId > 0) {
        $deletedBy = (int)($_SESSION['id'] ?? 0);
        $deletedAt = date('Y-m-d H:i:s');

        $sql = "UPDATE tip SET Is_Deleted = 1, Deleted_At = ?, Deleted_By = ? WHERE Tip_ID = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            // Fallback for older schema (no Is_Deleted columns)
            $stmt = $conn->prepare("DELETE FROM tip WHERE Tip_ID = ?");
            $stmt->bind_param("i", $tipId);
        } else {
            $stmt->bind_param("sii", $deletedAt, $deletedBy, $tipId);
        }

        if ($stmt->execute()) {
            $message = "Tip #$tipId removed";
            $messageType = "success";
        } else {
            $message = "Error removing tip: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Filters
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$employee = $_GET['employee'] ?? 'all';

$employees = [];
$cid = peachtrack_company_id();
$stmt_emp = $conn->prepare("SELECT Employee_ID, Employee_Name, User_Name FROM employee WHERE Company_ID = ? ORDER BY Employee_Name ASC");
if ($stmt_emp) { $stmt_emp->bind_param('i', $cid); $stmt_emp->execute(); $employees = $stmt_emp->get_result()->fetch_all(MYSQLI_ASSOC); }

// Active shifts scoped to current company
$active = [];
$stmt_active = $conn->prepare(
    "SELECT s.Shift_ID, s.Employee_ID, s.Start_Time, e.Employee_Name, e.User_Name
     FROM shift s JOIN employee e ON e.Employee_ID=s.Employee_ID
     WHERE s.End_Time IS NULL AND e.Company_ID = ?
     ORDER BY s.Start_Time DESC"
);
if ($stmt_active) { $stmt_active->bind_param('i', $cid); $stmt_active->execute(); $active = $stmt_active->get_result()->fetch_all(MYSQLI_ASSOC); }

// Shifts in range with totals
$where = "WHERE DATE(s.Start_Time) BETWEEN ? AND ? AND e.Company_ID = ?";
$params = [$from, $to, $cid];
$types = "ssi";
if ($employee !== 'all') {
    $where .= " AND e.Employee_ID = ?";
    $params[] = (int)$employee;
    $types .= "i";
}

$sql = "
SELECT s.Shift_ID, s.Employee_ID, e.Employee_Name, e.User_Name,
       s.Start_Time, s.End_Time, s.Sale_Amount,
       COALESCE(SUM(t.Tip_Amount),0) AS tips_total,
       COALESCE(SUM(CASE WHEN t.Is_It_Cash=1 THEN t.Tip_Amount ELSE 0 END),0) AS tips_cash,
       COALESCE(SUM(CASE WHEN t.Is_It_Cash=0 THEN t.Tip_Amount ELSE 0 END),0) AS tips_elec,
       COUNT(t.Tip_ID) AS tip_count
FROM shift s
JOIN employee e ON e.Employee_ID = s.Employee_ID
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID".
       (peachtrack_has_column($conn,'tip','Is_Deleted') ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)" : "")."

{$where}
GROUP BY s.Shift_ID, s.Employee_ID, e.Employee_Name, e.User_Name, s.Start_Time, s.End_Time, s.Sale_Amount
ORDER BY s.Start_Time DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once "header.php";
?>

<?php if ($message): ?>
  <div class="alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">🕒 Manage Shifts</h2>
      <div class="muted">View shifts, tips, and force-end active shifts.</div>
    </div>
  </div>

  <div style="height:14px"></div>

  <form class="no-print" method="GET" style="display:grid; grid-template-columns: 1fr 1fr 1.5fr auto; gap:12px; align-items:end;">
    <div>
      <label>From</label>
      <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
    </div>
    <div>
      <label>To</label>
      <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
    </div>
    <div>
      <label>Employee</label>
      <select name="employee">
        <option value="all" <?php echo ($employee==='all')?'selected':''; ?>>All employees</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?php echo (int)$e['Employee_ID']; ?>" <?php echo ((string)$employee === (string)$e['Employee_ID'])?'selected':''; ?>>
            <?php echo htmlspecialchars($e['Employee_Name'].' ('.$e['User_Name'].')'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <button class="btn btn-primary" type="submit">Apply</button>
    </div>
  </form>
</div>

<div style="height:14px"></div>

<div class="card">
  <h3 style="margin-top:0;">⚡ Active Shifts (Live)</h3>
  <table class="table">
    <thead>
      <tr>
        <th>Employee</th>
        <th>Start</th>
        <th>Duration</th>
        <th>Shift</th>
        <th class="no-print">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($active)): ?>
        <tr><td colspan="5" class="muted">No active shifts.</td></tr>
      <?php endif; ?>
      <?php foreach ($active as $a): ?>
        <tr>
          <td><?php echo htmlspecialchars($a['Employee_Name']); ?> <span class="muted">(<?php echo htmlspecialchars($a['User_Name']); ?>)</span></td>
          <td><?php echo htmlspecialchars($a['Start_Time']); ?></td>
          <td><strong><span data-start-iso="<?php echo htmlspecialchars(date('c', strtotime($a['Start_Time']))); ?>">00:00:00</span></strong></td>
          <td class="muted">#<?php echo (int)$a['Shift_ID']; ?></td>
          <td class="no-print">
            <form method="POST" style="margin:0;">
              <input type="hidden" name="force_end_shift" value="1" />
              <input type="hidden" name="shift_id" value="<?php echo (int)$a['Shift_ID']; ?>" />
              <button class="btn btn-secondary" type="submit" onclick="return confirm('Force end shift #<?php echo (int)$a['Shift_ID']; ?>?')">Force End</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div style="height:14px"></div>

<div class="card">
  <h3 style="margin-top:0;">📌 Shifts (<?php echo htmlspecialchars($from); ?> → <?php echo htmlspecialchars($to); ?>)</h3>
  <table class="table">
    <thead>
      <tr>
        <th>Employee</th>
        <th>Start</th>
        <th>End</th>
        <th>Sales</th>
        <th>Tips</th>
        <th>Cash/Elec</th>
        <th># Tips</th>
        <th class="no-print">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="muted">No shifts found for this range.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['Employee_Name']); ?> <span class="muted">(<?php echo htmlspecialchars($r['User_Name']); ?>)</span></td>
          <td><?php echo htmlspecialchars($r['Start_Time']); ?></td>
          <td><?php echo $r['End_Time'] ? htmlspecialchars($r['End_Time']) : '<span class="muted">Active</span>'; ?></td>
          <td>$<?php echo htmlspecialchars(number_format((float)$r['Sale_Amount'], 2)); ?></td>
          <td><strong>$<?php echo htmlspecialchars(number_format((float)$r['tips_total'], 2)); ?></strong></td>
          <td class="muted">$<?php echo htmlspecialchars(number_format((float)$r['tips_cash'], 2)); ?> / $<?php echo htmlspecialchars(number_format((float)$r['tips_elec'], 2)); ?></td>
          <td><?php echo htmlspecialchars($r['tip_count']); ?></td>
          <td class="no-print">
            <a class="btn btn-ghost" style="text-decoration:none;" href="edit_shift.php?id=<?php echo (int)$r['Shift_ID']; ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once "footer.php"; ?>
