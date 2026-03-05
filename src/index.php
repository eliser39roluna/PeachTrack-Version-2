<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Ensure the user is logged in AND the role is set
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$role = peachtrack_effective_role(); // 101=Manager/Admin, 102=Employee (supports admin switching into employee mode)
$empId = peachtrack_effective_employee_id();
$empName = peachtrack_effective_name();

// Safety: if in employee mode but no target employee is set, exit employee mode.
if (peachtrack_base_role() === '101' && $role === '102' && $empId <= 0) {
    unset($_SESSION['view_as'], $_SESSION['view_employee_id'], $_SESSION['view_employee_name']);
    $role = peachtrack_base_role();
    $empId = peachtrack_base_employee_id();
    $empName = (string)($_SESSION['name'] ?? 'User');
}
$message = "";
$messageType = "";

// Find active shift in DB (real-time truth)
$currentShift = null;
$stmt = $conn->prepare("SELECT Shift_ID, Start_Time FROM shift WHERE Employee_ID = ? AND End_Time IS NULL ORDER BY Shift_ID DESC LIMIT 1");
$stmt->bind_param("i", $empId);
if ($stmt->execute()) {
    $currentShift = $stmt->get_result()->fetch_assoc();
}
$current_shift_id = $currentShift['Shift_ID'] ?? "";
$current_shift_start = $currentShift['Start_Time'] ?? "";

// Handle POST actions (employee)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Start Shift (only if no active shift)
    if (isset($_POST['start_shift'])) {
        if ($current_shift_id) {
            $message = "You already have an active shift (#$current_shift_id).";
            $messageType = "error";
        } else {
            $start_time = date("Y-m-d H:i:s");
            $stmt = $conn->prepare("INSERT INTO shift (Employee_ID, Start_Time, Sale_Amount) VALUES (?, ?, 0.00)");
            $stmt->bind_param("is", $empId, $start_time);
            if ($stmt->execute()) {
                $current_shift_id = $conn->insert_id;
                $current_shift_start = $start_time;
                $message = "Shift started at $start_time (Shift #$current_shift_id).";
                $messageType = "success";
            } else {
                $message = "Error starting shift: " . $conn->error;
                $messageType = "error";
            }
        }
    }

    // Stop Shift
    if (isset($_POST['stop_shift'])) {
        if (!$current_shift_id) {
            $message = "No active shift found.";
            $messageType = "error";
        } else {
            $end_time = date("Y-m-d H:i:s");
            $stmt = $conn->prepare("UPDATE shift SET End_Time = ? WHERE Shift_ID = ?");
            $stmt->bind_param("si", $end_time, $current_shift_id);
            if ($stmt->execute()) {
                // Build shift summary before clearing
                $sumStmt = $conn->prepare(
                    "SELECT 
                        COALESCE(SUM(t.Tip_Amount),0) AS total_tips,
                        COALESCE(SUM(CASE WHEN t.Is_It_Cash=1 THEN t.Tip_Amount ELSE 0 END),0) AS cash_tips,
                        COALESCE(SUM(CASE WHEN t.Is_It_Cash=0 THEN t.Tip_Amount ELSE 0 END),0) AS elec_tips,
                        COALESCE(MAX(s.Sale_Amount),0) AS total_sales
                     FROM shift s
                     LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID" . (peachtrack_has_column($conn,'tip','Is_Deleted') ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)" : "") . "
                     WHERE s.Shift_ID = ?");
                $sumStmt->bind_param("i", $current_shift_id);
                $sumStmt->execute();
                $summary = $sumStmt->get_result()->fetch_assoc() ?: ['total_tips'=>0,'cash_tips'=>0,'elec_tips'=>0,'total_sales'=>0];

                $message = "Shift ended (Shift #$current_shift_id). Summary — Tips: $".number_format((float)$summary['total_tips'],2)." (Cash $".number_format((float)$summary['cash_tips'],2).", Electronic $".number_format((float)$summary['elec_tips'],2).") • Sales: $".number_format((float)$summary['total_sales'],2);
                $messageType = "success";
                $current_shift_id = "";
                $current_shift_start = "";
            } else {
                $message = "Error stopping shift: " . $conn->error;
                $messageType = "error";
            }
        }
    }

    // Submit Tip
    if (isset($_POST['submit_tip'])) {
        if (!$current_shift_id) {
            $message = "Start a shift before submitting tips.";
            $messageType = "error";
        } else {
            $tip_amount = (float)($_POST['tip_amount'] ?? 0);
            $sale_amount = (float)($_POST['sale_amount'] ?? 0);
            $is_cash = (int)($_POST['is_cash'] ?? 1);

            // Validation
            if ($tip_amount <= 0) {
                $message = "Tip amount must be greater than 0.";
                $messageType = "error";
            } elseif ($sale_amount < 0) {
                $message = "Sales amount cannot be negative.";
                $messageType = "error";
            } else {
                $hasTipSale = peachtrack_has_column($conn, 'tip', 'Sale_Amount');

                // Insert tip (supports older schema without tip.Sale_Amount)
                if ($hasTipSale) {
                    $sqlTip = "INSERT INTO tip (Shift_ID, Tip_Amount, Sale_Amount, Is_It_Cash, Tip_Time) VALUES (?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sqlTip);
                    if (!$stmt) {
                        $sqlTip = "INSERT INTO tip (Shift_ID, Tip_Amount, Sale_Amount, Is_It_Cash) VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($sqlTip);
                    }
                    $stmt->bind_param("iddi", $current_shift_id, $tip_amount, $sale_amount, $is_cash);
                } else {
                    $sqlTip = "INSERT INTO tip (Shift_ID, Tip_Amount, Is_It_Cash, Tip_Time) VALUES (?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sqlTip);
                    if (!$stmt) {
                        $sqlTip = "INSERT INTO tip (Shift_ID, Tip_Amount, Is_It_Cash) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($sqlTip);
                    }
                    $stmt->bind_param("idi", $current_shift_id, $tip_amount, $is_cash);
                }

                if ($stmt->execute()) {
                    $upd = $conn->prepare("UPDATE shift SET Sale_Amount = Sale_Amount + ? WHERE Shift_ID = ?");
                    $upd->bind_param("di", $sale_amount, $current_shift_id);
                    $upd->execute();

                    $message = "Tip submitted.";
                    $messageType = "success";
                } else {
                    $message = "Error submitting tip: " . $conn->error;
                    $messageType = "error";
                }
            }
        }
    }
}

require_once "header.php";

// Employee recent tips + active shift totals + quick KPIs
$recentTips = [];
$shiftTotals = ['tips' => 0, 'sales' => 0];
$empKpi = ['tips_today'=>0.0,'tips_week'=>0.0,'tips_last_week'=>0.0];

if ($role === '102') {
    $hasIsDeleted = peachtrack_has_column($conn, 'tip', 'Is_Deleted');
    $tipJoinCond = $hasIsDeleted ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)" : "";

    // Current shift totals
    if ($current_shift_id) {
        $hasTipSale = peachtrack_has_column($conn, 'tip', 'Sale_Amount');
        $stmt = $conn->prepare(
            "SELECT COALESCE(SUM(Tip_Amount),0) AS tips, " .
            ($hasTipSale ? "COALESCE(SUM(Sale_Amount),0)" : "0") . " AS sales\n" .
            "FROM tip\n" .
            "WHERE Shift_ID = ?" . ($hasIsDeleted ? " AND (Is_Deleted IS NULL OR Is_Deleted = 0)" : "")
        );
        $stmt->bind_param('i', $current_shift_id);
        if ($stmt->execute()) {
            $shiftTotals = $stmt->get_result()->fetch_assoc() ?: $shiftTotals;
        }
    }

    // Employee tip KPIs (today / this week / last week)
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
    $lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));

    // Use Tip_Time if present; else use shift.Start_Time
    $hasTipTime = peachtrack_has_column($conn, 'tip', 'Tip_Time');
    $dateExpr = $hasTipTime ? 'DATE(t.Tip_Time)' : 'DATE(s.Start_Time)';

    $sqlEmpKpi = "
SELECT
  COALESCE(SUM(CASE WHEN {$dateExpr} = ? THEN t.Tip_Amount ELSE 0 END),0) AS tips_today,
  COALESCE(SUM(CASE WHEN {$dateExpr} BETWEEN ? AND ? THEN t.Tip_Amount ELSE 0 END),0) AS tips_week,
  COALESCE(SUM(CASE WHEN {$dateExpr} BETWEEN ? AND ? THEN t.Tip_Amount ELSE 0 END),0) AS tips_last_week
FROM tip t
JOIN shift s ON s.Shift_ID = t.Shift_ID
WHERE s.Employee_ID = ? {$tipJoinCond};
";

    $stmt = $conn->prepare($sqlEmpKpi);
    $stmt->bind_param('ssssis', $today, $weekStart, $today, $lastWeekStart, $lastWeekEnd, $empId);
    if ($stmt->execute()) {
        $empKpi = $stmt->get_result()->fetch_assoc() ?: $empKpi;
    }
}

if ($role === '102') {
    // Pull more rows and group them visually by shift date (from shift.Start_Time)
    // Prefer showing the exact time the tip was logged (tip.Tip_Time). Fallback if column doesn't exist.
    $hasIsDeleted = $hasIsDeleted ?? peachtrack_has_column($conn, 'tip', 'Is_Deleted');
    $hasTipSale = peachtrack_has_column($conn, 'tip', 'Sale_Amount');
    $sqlRecent = "SELECT t.Tip_Amount, " . ($hasTipSale ? "t.Sale_Amount" : "s.Sale_Amount") . " AS Sale_Amount, t.Is_It_Cash, s.Start_Time, t.Tip_Time
                  FROM tip t
                  JOIN shift s ON s.Shift_ID = t.Shift_ID
                  WHERE s.Employee_ID = ?" . ($hasIsDeleted ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)" : "") . "
                  ORDER BY s.Start_Time DESC, t.Tip_ID DESC
                  LIMIT 30";

    $stmt = $conn->prepare($sqlRecent);
    if (!$stmt) {
        // Fallback for older schema (no Tip_Time column)
        $sqlRecent = "SELECT t.Tip_Amount, " . ($hasTipSale ? "t.Sale_Amount" : "s.Sale_Amount") . " AS Sale_Amount, t.Is_It_Cash, s.Start_Time
                      FROM tip t
                      JOIN shift s ON s.Shift_ID = t.Shift_ID
                      WHERE s.Employee_ID = ?" . ($hasIsDeleted ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)" : "") . "
                      ORDER BY s.Start_Time DESC, t.Tip_ID DESC
                      LIMIT 30";
        $stmt = $conn->prepare($sqlRecent);
    }

    $stmt->bind_param("i", $empId);
    if ($stmt->execute()) {
        $recentTips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Admin KPIs
$kpiActive = 0;
$kpiTipsToday = 0.0;
$kpiSalesToday = 0.0;
if ($role === '101') {
    $res = $conn->query("SELECT COUNT(*) AS c FROM shift WHERE End_Time IS NULL");
    if ($res) $kpiActive = (int)($res->fetch_assoc()['c'] ?? 0);

    // Use MySQL CURDATE() (timezone set in db_config.php) to avoid UTC vs local date mismatch
    $res = $conn->query("SELECT COALESCE(SUM(t.Tip_Amount),0) AS tips
                         FROM tip t JOIN shift s ON s.Shift_ID=t.Shift_ID
                         WHERE DATE(s.Start_Time)=CURDATE()");
    if ($res) $kpiTipsToday = (float)($res->fetch_assoc()['tips'] ?? 0);

    $res = $conn->query("SELECT COALESCE(SUM(Sale_Amount),0) AS sales
                         FROM shift
                         WHERE DATE(Start_Time)=CURDATE()");
    if ($res) $kpiSalesToday = (float)($res->fetch_assoc()['sales'] ?? 0);
}
?>

<?php if ($message): ?>
  <div class="alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($role === '101'): ?>

  <div class="grid grid-3">
    <div class="card kpi">
      <div>
        <div class="label">Active shifts (live)</div>
        <div class="value" data-kpi-active-shifts><?php echo (int)$kpiActive; ?></div>
      </div>
      <div class="muted">updates every 5s</div>
    </div>

    <div class="card kpi">
      <div>
        <div class="label">Tips today</div>
        <div class="value">$<?php echo htmlspecialchars(number_format($kpiTipsToday, 2)); ?></div>
      </div>
      <div class="muted">based on shift start date</div>
    </div>

    <div class="card kpi">
      <div>
        <div class="label">Sales today</div>
        <div class="value">$<?php echo htmlspecialchars(number_format($kpiSalesToday, 2)); ?></div>
      </div>
      <div class="muted">sum of shifts</div>
    </div>
  </div>

  <div style="height:14px"></div>

  <div class="card">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
      <div>
        <h2 style="margin:0;">🛠️ Admin Dashboard</h2>
        <div class="muted">Active shifts appear here automatically when employees start a shift.</div>
      </div>
      <div class="no-print" style="display:flex; gap:10px;">
        <a class="btn btn-primary" href="reports.php" style="text-decoration:none;">Open Reports</a>
      </div>
    </div>

    <div style="height:12px"></div>

    <table class="table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Username</th>
          <th>Start time</th>
          <th>Duration</th>
          <th>Shift</th>
        </tr>
      </thead>
      <tbody data-active-shifts-body>
        <tr><td colspan="5" class="muted">Loading…</td></tr>
      </tbody>
    </table>
  </div>

<?php else: ?>

  <div class="grid grid-2">
    <div class="card">
      <h3 style="margin-top:0;">Shift</h3>
      <p class="muted" style="margin-top:0;">
        Status:
        <?php if ($current_shift_id): ?>
          <strong>Active</strong> (Shift #<?php echo htmlspecialchars($current_shift_id); ?>)
          <br />
          Started: <strong><?php echo htmlspecialchars($current_shift_start); ?></strong>
          <br />
          Duration: <strong><span data-start-iso="<?php echo htmlspecialchars(date('c', strtotime($current_shift_start ?: 'now'))); ?>">00:00:00</span></strong>
          <br />
          <span class="muted">Totals this shift:</span>
          <strong>$<span data-shift-tips><?php echo htmlspecialchars(number_format((float)($shiftTotals['tips'] ?? 0), 2)); ?></span></strong> tips •
          <strong>$<span data-shift-sales><?php echo htmlspecialchars(number_format((float)($shiftTotals['sales'] ?? 0), 2)); ?></span></strong> sales
          <div style="height:10px"></div>
          <div class="muted" style="font-size:12px;">Your tips summary</div>
          <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <div><strong>$<?php echo htmlspecialchars(number_format((float)($empKpi['tips_today'] ?? 0),2)); ?></strong> <span class="muted">today</span></div>
            <div><strong>$<?php echo htmlspecialchars(number_format((float)($empKpi['tips_week'] ?? 0),2)); ?></strong> <span class="muted">this week</span></div>
            <div><strong>$<?php echo htmlspecialchars(number_format((float)($empKpi['tips_last_week'] ?? 0),2)); ?></strong> <span class="muted">last week</span></div>
          </div>
        <?php else: ?>
          <strong>Not started</strong>
          <br />
          <span class="muted">Totals this shift:</span>
          <strong>$<span data-shift-tips>0.00</span></strong> tips •
          <strong>$<span data-shift-sales>0.00</span></strong> sales
        <?php endif; ?>
      </p>

      <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if (!$current_shift_id): ?>
          <button class="btn btn-primary" type="submit" name="start_shift" value="1">▶ Start Shift</button>
        <?php else: ?>
          <button class="btn btn-secondary" type="submit" name="stop_shift" value="1">■ End Shift</button>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <h3 style="margin-top:0;">Log Tip</h3>
      <form method="POST">
        <label>Tip Amount ($)</label>
        <input type="number" step="0.01" name="tip_amount" required />

        <label>Sale Amount ($) <span class="muted" style="font-weight:400;">(for this entry)</span></label>
        <input type="number" step="0.01" name="sale_amount" required />

        <label>Payment Type</label>
        <select name="is_cash">
          <option value="1">Cash</option>
          <option value="0">Electronic (Card)</option>
        </select>

        <div style="margin-top:12px;">
          <button class="btn btn-primary" type="submit" name="submit_tip" value="1">Submit Tip</button>
        </div>
      </form>
      <div class="muted" style="margin-top:10px; font-size:12px;">Tip logging is tied to your active shift in the database.</div>
    </div>
  </div>

  <div style="height:14px"></div>

  <div class="card">
    <h3 style="margin-top:0;">Recent Tips</h3>
    <?php if (empty($recentTips)): ?>
      <p class="muted">No tips recorded yet.</p>
    <?php else: ?>
      <?php
        // Group tip entries by shift date (based on shift.Start_Time)
        $grouped = [];
        foreach ($recentTips as $t) {
          $day = date('Y-m-d', strtotime($t['Start_Time'] ?? 'now'));
          if (!isset($grouped[$day])) $grouped[$day] = [];
          $grouped[$day][] = $t;
        }
      ?>

      <?php foreach ($grouped as $day => $items): ?>
        <div style="margin: 10px 0 6px; font-weight:900;">
          <?php echo htmlspecialchars(date('F j, Y', strtotime($day))); ?>
        </div>
        <div class="muted" style="margin:-2px 0 8px; font-size:12px;">Tips logged during shifts started on this date</div>

        <table class="table" style="margin-bottom: 14px;">
          <thead>
            <tr>
              <th>Time</th>
              <th>Tip Amount</th>
              <th>Sales Amount</th>
              <th>Method</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $t): ?>
              <tr>
                <td>
                  <?php
                    $timeVal = $t['Tip_Time'] ?? $t['Start_Time'] ?? '';
                    echo $timeVal ? htmlspecialchars(date('g:i A', strtotime($timeVal))) : '-';
                  ?>
                </td>
                <td>$<?php echo htmlspecialchars(number_format((float)$t['Tip_Amount'], 2)); ?></td>
                <td>$<?php echo htmlspecialchars(number_format((float)$t['Sale_Amount'], 2)); ?></td>
                <td><?php echo ((int)$t['Is_It_Cash'] === 1) ? 'Cash' : 'Electronic'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php require_once "footer.php"; ?>
