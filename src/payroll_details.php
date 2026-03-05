<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Managers/Admins only
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (string)($_SESSION['role'] ?? '') !== '101') {
    header('Location: index.php');
    exit;
}

$employeeId = (int)($_GET['employee_id'] ?? 0);
if ($employeeId <= 0) {
    header('Location: payroll.php');
    exit;
}

// Range presets (same as payroll)
$range = $_GET['range'] ?? 'week';
$today = date('Y-m-d');
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';

if ($range === 'day') {
    $from = $today;
    $to = $today;
} elseif ($range === 'month') {
    $from = date('Y-m-01');
    $to = $today;
} elseif ($range === 'custom') {
    $from = $from ?: date('Y-m-d', strtotime('-6 days'));
    $to = $to ?: $today;
} else {
    $range = 'week';
    $from = date('Y-m-d', strtotime('-6 days'));
    $to = $today;
}

$mode = $_GET['mode'] ?? 'unpaid'; // unpaid | paid | all

$hasTipTime = peachtrack_has_column($conn, 'tip', 'Tip_Time');
$hasPayPeriod = peachtrack_has_column($conn, 'tip', 'Pay_Period_ID');
$hasIsDeleted = peachtrack_has_column($conn, 'tip', 'Is_Deleted');

$tipDateExpr = $hasTipTime ? 'DATE(t.Tip_Time)' : 'DATE(s.Start_Time)';
$deletedFilter = $hasIsDeleted ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0) " : "";

$paidFilter = "";
if ($hasPayPeriod) {
    if ($mode === 'unpaid') $paidFilter = " AND t.Pay_Period_ID IS NULL ";
    elseif ($mode === 'paid') $paidFilter = " AND t.Pay_Period_ID IS NOT NULL ";
}

// Load employee — verify it belongs to the current company
$cid = peachtrack_company_id();
$stmt = $conn->prepare("SELECT Employee_ID, Employee_Name, User_Name FROM employee WHERE Employee_ID = ? AND Company_ID = ?");
$stmt->bind_param('ii', $employeeId, $cid);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
if (!$emp) {
    header('Location: payroll.php');
    exit;
}

// Tips (detail rows)
$sqlTips = "
SELECT t.Tip_ID,
       t.Tip_Amount,
       ".(peachtrack_has_column($conn,'tip','Sale_Amount') ? "t.Sale_Amount" : "0.00 AS Sale_Amount").",
       t.Is_It_Cash,
       s.Shift_ID,
       s.Start_Time,
       s.End_Time".($hasTipTime ? ", t.Tip_Time" : "").",
       ".($hasPayPeriod ? "t.Pay_Period_ID" : "NULL AS Pay_Period_ID")."
FROM shift s
JOIN tip t ON t.Shift_ID = s.Shift_ID
WHERE s.Employee_ID = ?
  AND {$tipDateExpr} BETWEEN ? AND ?
  {$deletedFilter}
  {$paidFilter}
ORDER BY s.Start_Time DESC, t.Tip_ID DESC;
";

$stmt = $conn->prepare($sqlTips);
$stmt->bind_param('iss', $employeeId, $from, $to);
$stmt->execute();
$tips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Shifts summary
$sqlShifts = "
SELECT s.Shift_ID,
       s.Start_Time,
       s.End_Time,
       COALESCE(s.Sale_Amount,0) AS shift_sales,
       COALESCE(SUM(t.Tip_Amount),0) AS tips_total
FROM shift s
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID".($hasIsDeleted ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)" : "")."
WHERE s.Employee_ID = ?
  AND DATE(s.Start_Time) BETWEEN ? AND ?
GROUP BY s.Shift_ID, s.Start_Time, s.End_Time, s.Sale_Amount
ORDER BY s.Start_Time DESC;
";
$stmt = $conn->prepare($sqlShifts);
$stmt->bind_param('iss', $employeeId, $from, $to);
$stmt->execute();
$shifts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Totals
$tot = ['tips'=>0.0,'sales'=>0.0,'cash'=>0.0,'elec'=>0.0,'count'=>0];
foreach ($tips as $t) {
    $tot['tips'] += (float)$t['Tip_Amount'];
    $tot['sales'] += (float)($t['Sale_Amount'] ?? 0);
    $tot['count']++;
    if ((int)$t['Is_It_Cash'] === 1) $tot['cash'] += (float)$t['Tip_Amount'];
    else $tot['elec'] += (float)$t['Tip_Amount'];
}

function fmt_dt($dt) {
    if (!$dt) return '-';
    $ts = strtotime($dt);
    return $ts ? date('M j, Y g:i A', $ts) : htmlspecialchars($dt);
}

require_once "header.php";
?>

<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">Payroll Details — <?php echo htmlspecialchars($emp['Employee_Name']); ?></h2>
      <div class="muted">
        <?php echo htmlspecialchars($from); ?> → <?php echo htmlspecialchars($to); ?>
        • Status: <?php echo htmlspecialchars($mode); ?>
      </div>
    </div>
    <div class="no-print" style="display:flex; gap:10px; flex-wrap:wrap;">
      <a class="btn btn-ghost" style="text-decoration:none;" href="payroll.php?<?php echo http_build_query(['from'=>$from,'to'=>$to,'range'=>$range,'mode'=>$mode,'employee'=>$employeeId]); ?>">← Back to Payroll</a>
      <button class="btn btn-ghost" onclick="window.print()">🖨️ Print</button>
    </div>
  </div>

  <div style="height:12px"></div>

  <div class="grid grid-3">
    <div class="card kpi">
      <div>
        <div class="label">Total tips</div>
        <div class="value">$<?php echo htmlspecialchars(number_format($tot['tips'],2)); ?></div>
      </div>
      <div class="muted"><?php echo (int)$tot['count']; ?> entries</div>
    </div>

    <div class="card kpi">
      <div>
        <div class="label">Cash / Electronic</div>
        <div class="value">$<?php echo htmlspecialchars(number_format($tot['cash'],2)); ?> / $<?php echo htmlspecialchars(number_format($tot['elec'],2)); ?></div>
      </div>
      <div class="muted">by method</div>
    </div>

    <div class="card kpi">
      <div>
        <div class="label">Total sales (entries)</div>
        <div class="value">$<?php echo htmlspecialchars(number_format($tot['sales'],2)); ?></div>
      </div>
      <div class="muted">sum of entry sales</div>
    </div>
  </div>

  <div style="height:14px"></div>

  <div class="card">
    <h3 style="margin-top:0;">Shifts in range</h3>
    <table class="table">
      <thead>
        <tr>
          <th>Shift</th>
          <th>Start</th>
          <th>End</th>
          <th>Shift Sales</th>
          <th>Tips (sum)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$shifts): ?>
          <tr><td colspan="5" class="muted">No shifts found.</td></tr>
        <?php else: ?>
          <?php foreach ($shifts as $s): ?>
            <tr>
              <td>#<?php echo (int)$s['Shift_ID']; ?></td>
              <td><?php echo fmt_dt($s['Start_Time']); ?></td>
              <td><?php echo fmt_dt($s['End_Time']); ?></td>
              <td>$<?php echo htmlspecialchars(number_format((float)$s['shift_sales'],2)); ?></td>
              <td>$<?php echo htmlspecialchars(number_format((float)$s['tips_total'],2)); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="height:14px"></div>

  <div class="card">
    <h3 style="margin-top:0;">Tip entries</h3>
    <table class="table">
      <thead>
        <tr>
          <th>Time</th>
          <th>Shift</th>
          <th>Tip</th>
          <th>Sale</th>
          <th>Method</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$tips): ?>
          <tr><td colspan="6" class="muted">No tips found for this range/status.</td></tr>
        <?php else: ?>
          <?php foreach ($tips as $t): ?>
            <?php
              $hasRealTipTime = ($hasTipTime && !empty($t['Tip_Time']));
              $timeVal = $hasRealTipTime ? $t['Tip_Time'] : ($t['Start_Time'] ?? '');
              $status = 'n/a';
              if ($hasPayPeriod) $status = ((int)($t['Pay_Period_ID'] ?? 0) > 0) ? 'paid' : 'unpaid';
            ?>
            <tr>
              <td>
                <?php
                  echo $timeVal ? htmlspecialchars(date('M j, g:i A', strtotime($timeVal))) : '-';
                  if (!$hasRealTipTime) echo " <span class=\"muted\" style=\"font-size:12px;\">(shift start)</span>";
                ?>
              </td>
              <td>#<?php echo (int)$t['Shift_ID']; ?></td>
              <td>$<?php echo htmlspecialchars(number_format((float)$t['Tip_Amount'],2)); ?></td>
              <td>$<?php echo htmlspecialchars(number_format((float)($t['Sale_Amount'] ?? 0),2)); ?></td>
              <td><?php echo ((int)$t['Is_It_Cash'] === 1) ? 'Cash' : 'Electronic'; ?></td>
              <td class="muted"><?php echo htmlspecialchars($status); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="muted" style="margin-top:12px; font-size:12px;">Tip: Print → Save as PDF if you need to attach it to payroll notes.</div>
  </div>
</div>

<?php require_once "footer.php"; ?>
