<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (string)($_SESSION['role'] ?? '') !== '101') {
    header('Location: index.php');
    exit;
}

$message = "";
$messageType = "";

// Fetch employees scoped to current company
$employees = [];
$cid = peachtrack_company_id();
$stmt_emp = $conn->prepare("SELECT Employee_ID, Employee_Name, User_Name FROM employee WHERE Company_ID = ? ORDER BY Employee_Name ASC");
if ($stmt_emp) { $stmt_emp->bind_param('i', $cid); $stmt_emp->execute(); $employees = $stmt_emp->get_result()->fetch_all(MYSQLI_ASSOC); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empId = (int)($_POST['employee_id'] ?? 0);
    $start = trim($_POST['start_time'] ?? '');
    $end = trim($_POST['end_time'] ?? '');
    $sales = (float)($_POST['sale_amount'] ?? 0);

    if ($empId <= 0 || $start === '') {
        $message = "Employee and start time are required.";
        $messageType = "error";
    } else {
        // Basic validation
        if ($sales < 0) {
            $message = "Sales amount cannot be negative.";
            $messageType = "error";
        } else {
            // Prevent double active shifts for the same employee
            $endVal = ($end === '') ? null : $end;

            if ($endVal === null) {
                $chk = $conn->prepare("SELECT Shift_ID FROM shift WHERE Employee_ID = ? AND End_Time IS NULL LIMIT 1");
                $chk->bind_param('i', $empId);
                $chk->execute();
                $active = $chk->get_result()->fetch_assoc();
                if ($active) {
                    $message = "That employee already has an active shift (#".(int)$active['Shift_ID']."). End it first (Manage Shifts) before creating a new active shift.";
                    $messageType = "error";
                }
            }

            // If end time is provided, it must be after start
            if (!$message && $endVal !== null && strtotime($endVal) !== false && strtotime($start) !== false) {
                if (strtotime($endVal) < strtotime($start)) {
                    $message = "End time cannot be before start time.";
                    $messageType = "error";
                }
            }

            // If creating a completed shift, ensure it doesn't overlap an existing active shift
            if (!$message && $endVal !== null) {
                $chk = $conn->prepare("SELECT Shift_ID FROM shift WHERE Employee_ID = ? AND End_Time IS NULL AND Start_Time <= ? LIMIT 1");
                $chk->bind_param('is', $empId, $endVal);
                $chk->execute();
                $active = $chk->get_result()->fetch_assoc();
                if ($active) {
                    $message = "This employee has an active shift (#".(int)$active['Shift_ID'].") that overlaps your end time. End it first.";
                    $messageType = "error";
                }
            }

            if (!$message) {
                $stmt = $conn->prepare("INSERT INTO shift (Employee_ID, Start_Time, End_Time, Sale_Amount) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issd", $empId, $start, $endVal, $sales);
                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    header("Location: edit_shift.php?id=$newId&created=1");
                    exit;
                } else {
                    $message = "Error creating shift: " . $conn->error;
                    $messageType = "error";
                }
            }
        }
    }
}

require_once "header.php";
?>

<?php if ($message): ?>
  <div class="alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card">
  <h2 style="margin-top:0;">➕ Create Shift (Admin)</h2>
  <div class="muted">You can create completed shifts (with end time) or active shifts (leave end time empty).</div>

  <div style="height:14px"></div>

  <form method="POST" style="display:grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap:12px; align-items:end;">
    <div>
      <label>Employee</label>
      <select name="employee_id" required>
        <option value="">Select employee…</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?php echo (int)$e['Employee_ID']; ?>">
            <?php echo htmlspecialchars($e['Employee_Name'].' ('.$e['User_Name'].')'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Start Time</label>
      <input type="datetime-local" name="start_time" required />
    </div>
    <div>
      <label>End Time (optional)</label>
      <input type="datetime-local" name="end_time" />
    </div>
    <div>
      <label>Sales Amount</label>
      <input type="number" step="0.01" name="sale_amount" value="0.00" />
    </div>

    <div style="grid-column: 1 / -1;">
      <button class="btn btn-primary" type="submit">Create Shift</button>
      <a class="btn btn-ghost" href="manage_shifts.php" style="text-decoration:none; margin-left:8px;">Cancel</a>
    </div>
  </form>

  <div class="muted" style="margin-top:12px; font-size:12px;">
    Note: your database uses server timezone; times you enter will be stored exactly as provided.
  </div>
</div>

<?php require_once "footer.php"; ?>
