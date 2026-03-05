<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Employees only (supports admin switching into employee mode)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || peachtrack_effective_role() !== '102') {
    header('Location: index.php');
    exit;
}

require_once "header.php";

// Range presets: day | week | month | custom
$range = $_GET['range'] ?? 'month';
$today = date('Y-m-d');

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';

if ($range === 'day') {
    $from = $today;
    $to = $today;
} elseif ($range === 'week') {
    // last 7 days including today
    $from = date('Y-m-d', strtotime('-6 days'));
    $to = $today;
} elseif ($range === 'custom') {
    // keep provided from/to; fallback if empty
    $from = $from ?: date('Y-m-01');
    $to = $to ?: $today;
} else {
    // month (default)
    $range = 'month';
    $from = date('Y-m-01');
    $to = $today;
}

$empId = peachtrack_effective_employee_id();

// Shifts with totals (ignore deleted tips only if schema supports it)
$hasIsDeleted = peachtrack_has_column($conn, 'tip', 'Is_Deleted');
$tipJoinCond = $hasIsDeleted ? " AND (t.Is_Deleted IS NULL OR t.Is_Deleted = 0)" : "";

$sql = "
SELECT s.Shift_ID,
       s.Start_Time,
       s.End_Time,
       COALESCE(s.Sale_Amount,0) AS sales,
       COALESCE(SUM(t.Tip_Amount),0) AS tips,
       COALESCE(SUM(CASE WHEN t.Is_It_Cash=1 THEN t.Tip_Amount ELSE 0 END),0) AS tips_cash,
       COALESCE(SUM(CASE WHEN t.Is_It_Cash=0 THEN t.Tip_Amount ELSE 0 END),0) AS tips_elec,
       COUNT(t.Tip_ID) AS tip_count
FROM shift s
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID{$tipJoinCond}
WHERE s.Employee_ID = ?
  AND DATE(s.Start_Time) BETWEEN ? AND ?
GROUP BY s.Shift_ID, s.Start_Time, s.End_Time, s.Sale_Amount
ORDER BY s.Start_Time DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $empId, $from, $to);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Charts
// Tips by day
$sqlTipsByDay = "
SELECT DATE(s.Start_Time) AS day,
       COALESCE(SUM(t.Tip_Amount),0) AS tips
FROM shift s
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID{$tipJoinCond}
WHERE s.Employee_ID = ?
  AND DATE(s.Start_Time) BETWEEN ? AND ?
GROUP BY DATE(s.Start_Time)
ORDER BY day ASC;
";
$stmt = $conn->prepare($sqlTipsByDay);
$stmt->bind_param('iss', $empId, $from, $to);
$stmt->execute();
$byDayTips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$chartDayLabels = array_map(fn($x)=>$x['day'], $byDayTips);
$chartDayTips = array_map(fn($x)=>(float)$x['tips'], $byDayTips);

// Sales by day
$sqlSalesByDay = "
SELECT DATE(Start_Time) AS day,
       COALESCE(SUM(Sale_Amount),0) AS sales
FROM shift
WHERE Employee_ID = ?
  AND DATE(Start_Time) BETWEEN ? AND ?
GROUP BY DATE(Start_Time)
ORDER BY day ASC;
";
$stmt = $conn->prepare($sqlSalesByDay);
$stmt->bind_param('iss', $empId, $from, $to);
$stmt->execute();
$byDaySales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$chartDaySales = array_map(fn($x)=>(float)$x['sales'], $byDaySales);

// Cash vs electronic
$sqlMethod = "
SELECT
  COALESCE(SUM(CASE WHEN t.Is_It_Cash=1 THEN t.Tip_Amount ELSE 0 END),0) AS cash,
  COALESCE(SUM(CASE WHEN t.Is_It_Cash=0 THEN t.Tip_Amount ELSE 0 END),0) AS elec
FROM shift s
LEFT JOIN tip t ON t.Shift_ID = s.Shift_ID{$tipJoinCond}
WHERE s.Employee_ID = ?
  AND DATE(s.Start_Time) BETWEEN ? AND ?;
";
$stmt = $conn->prepare($sqlMethod);
$stmt->bind_param('iss', $empId, $from, $to);
$stmt->execute();
$method = $stmt->get_result()->fetch_assoc() ?: ['cash'=>0,'elec'=>0];
$cash = (float)($method['cash'] ?? 0);
$elec = (float)($method['elec'] ?? 0);

// KPI range summary
$kpi = ['shifts'=>0,'tips'=>0.0,'sales'=>0.0,'rate'=>0.0];
$kpi['shifts'] = count($rows);
foreach ($rows as $r) {
    $kpi['tips'] += (float)$r['tips'];
    $kpi['sales'] += (float)$r['sales'];
}
$kpi['rate'] = ($kpi['sales'] > 0) ? (($kpi['tips'] / $kpi['sales']) * 100.0) : 0.0;

function fmt_dt($dt) {
    if (!$dt) return '-';
    $t = strtotime($dt);
    if (!$t) return htmlspecialchars($dt);
    return date('M j, Y g:i A', $t);
}

function fmt_duration($start, $end) {
    if (!$start || !$end) return '-';
    $s = strtotime($start);
    $e = strtotime($end);
    if (!$s || !$e || $e < $s) return '-';
    $mins = (int)(($e - $s) / 60);
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return ($h > 0 ? $h.'h ' : '').$m.'m';
}
?>

<div class="grid grid-3">
  <div class="card kpi">
    <div>
      <div class="label">Shifts (range)</div>
      <div class="value"><?php echo (int)$kpi['shifts']; ?></div>
    </div>
    <div class="muted"><?php echo htmlspecialchars($from); ?> → <?php echo htmlspecialchars($to); ?></div>
  </div>

  <div class="card kpi">
    <div>
      <div class="label">Total tips</div>
      <div class="value">$<?php echo htmlspecialchars(number_format($kpi['tips'], 2)); ?></div>
    </div>
    <div class="muted">cash + electronic</div>
  </div>

  <div class="card kpi">
    <div>
      <div class="label">Tip rate</div>
      <div class="value"><?php echo htmlspecialchars(number_format($kpi['rate'], 2)); ?>%</div>
    </div>
    <div class="muted">tips ÷ sales</div>
  </div>
</div>

<div style="height:14px"></div>

<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">📈 My Shifts</h2>
      <div class="muted">View your shift history and totals. Deleted tips are excluded.</div>
    </div>
    <div class="no-print" style="display:flex; gap:10px;">
      <button class="btn btn-ghost" onclick="window.print()">🖨️ Print</button>
    </div>
  </div>

  <div style="height:14px"></div>

  <form class="no-print filter-bar" method="GET" style="grid-template-columns: 1fr 1fr 1fr auto;">
    <div>
      <label>Range</label>
      <select name="range" onchange="this.form.submit()">
        <option value="day" <?php echo ($range==='day')?'selected':''; ?>>Today</option>
        <option value="week" <?php echo ($range==='week')?'selected':''; ?>>Last 7 days</option>
        <option value="month" <?php echo ($range==='month')?'selected':''; ?>>This month</option>
        <option value="custom" <?php echo ($range==='custom')?'selected':''; ?>>Custom</option>
      </select>
    </div>

    <div class="field">
      <label>From</label>
      <input type="text" inputmode="numeric" data-datepicker name="from" value="<?php echo htmlspecialchars($from); ?>" <?php echo ($range==='custom')?'':'disabled'; ?> />
    </div>
    <div class="field">
      <label>To</label>
      <input type="text" inputmode="numeric" data-datepicker name="to" value="<?php echo htmlspecialchars($to); ?>" <?php echo ($range==='custom')?'':'disabled'; ?> />
    </div>
    <div>
      <button class="btn btn-primary" type="submit" <?php echo ($range==='custom')?'':'disabled'; ?>>Apply</button>
    </div>
  </form>

  <div class="muted" style="font-size:12px; margin-top:10px;">Tip: choose <strong>Custom</strong> to edit dates.</div>

  <div style="height:12px"></div>

  <div class="grid grid-2">
    <div class="card">
      <h3 style="margin-top:0;">Cash vs Electronic Tips</h3>
      <canvas id="chartMethod" height="120"></canvas>
      <div class="muted" style="margin-top:10px; font-size:12px;">Your tips by method.</div>
    </div>

    <div class="card">
      <h3 style="margin-top:0;">Tips by Day</h3>
      <canvas id="chartDayTips" height="120"></canvas>
    </div>
  </div>

  <div style="height:14px"></div>

  <div class="card">
    <h3 style="margin-top:0;">Sales by Day</h3>
    <canvas id="chartDaySales" height="110"></canvas>
  </div>

  <div style="height:14px"></div>

  <table class="table table-shifts">
    <thead>
      <tr>
        <th>Shift</th>
        <th>Start</th>
        <th>End</th>
        <th>Duration</th>
        <th>Tips</th>
        <th>Sales</th>
        <th>Tip rate</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="muted">No shifts found for this range.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php $rate = ((float)$r['sales'] > 0) ? (((float)$r['tips'] / (float)$r['sales']) * 100.0) : 0.0; ?>
          <tr>
            <td>#<?php echo (int)$r['Shift_ID']; ?></td>
            <td><?php echo fmt_dt($r['Start_Time']); ?></td>
            <td><?php echo fmt_dt($r['End_Time']); ?></td>
            <td><?php echo htmlspecialchars(fmt_duration($r['Start_Time'], $r['End_Time'])); ?></td>
            <td>$<?php echo htmlspecialchars(number_format((float)$r['tips'],2)); ?></td>
            <td>$<?php echo htmlspecialchars(number_format((float)$r['sales'],2)); ?></td>
            <td><?php echo htmlspecialchars(number_format($rate,2)); ?>%</td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const dayLabels = <?php echo json_encode($chartDayLabels); ?>;
  const dayTips = <?php echo json_encode($chartDayTips); ?>;
  const daySales = <?php echo json_encode($chartDaySales); ?>;
  const cash = <?php echo json_encode($cash); ?>;
  const elec = <?php echo json_encode($elec); ?>;

  const peach = '#ff6b4a';
  const dark = '#111827';

  new Chart(document.getElementById('chartDayTips'), {
    type: 'line',
    data: {
      labels: dayLabels,
      datasets: [{
        label: 'Tips ($)',
        data: dayTips,
        borderColor: peach,
        backgroundColor: 'rgba(255,107,74,.20)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  new Chart(document.getElementById('chartDaySales'), {
    type: 'line',
    data: {
      labels: dayLabels,
      datasets: [{
        label: 'Sales ($)',
        data: daySales,
        borderColor: dark,
        backgroundColor: 'rgba(17,24,39,.12)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  new Chart(document.getElementById('chartMethod'), {
    type: 'doughnut',
    data: {
      labels: ['Cash', 'Electronic'],
      datasets: [{
        data: [cash, elec],
        backgroundColor: [peach, dark],
        borderWidth: 0,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } },
      cutout: '65%'
    }
  });
</script>

<?php require_once "footer.php"; ?>
