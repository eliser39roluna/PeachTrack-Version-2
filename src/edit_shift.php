<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (string)($_SESSION['role'] ?? '') !== '101') {
    header('Location: index.php');
    exit;
}

$shiftId = (int)($_GET['id'] ?? 0);
if ($shiftId <= 0) {
    header('Location: manage_shifts.php');
    exit;
}

$message = "";
$messageType = "";

function to_dt_local($sqlDt) {
    if (!$sqlDt) return '';
    $t = strtotime($sqlDt);
    if (!$t) return '';
    return date('Y-m-d\TH:i', $t);
}

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shift'])) {
    $start = trim($_POST['start_time'] ?? '');
    $end = trim($_POST['end_time'] ?? '');
    $sales = (float)($_POST['sale_amount'] ?? 0);

    $endVal = ($end === '') ? null : $end;

    $stmt = $conn->prepare("UPDATE shift SET Start_Time = ?, End_Time = ?, Sale_Amount = ? WHERE Shift_ID = ?");
    $stmt->bind_param("ssdi", $start, $endVal, $sales, $shiftId);
    if ($stmt->execute()) {
        $message = "Shift updated.";
        $messageType = "success";
    } else {
        $message = "Error updating shift: " . $conn->error;
        $messageType = "error";
    }
}

// Add tip
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tip'])) {
    $tip = (float)($_POST['tip_amount'] ?? 0);
    $isCash = (int)($_POST['is_cash'] ?? 1);
    if ($tip <= 0) {
        $message = "Tip amount must be > 0";
        $messageType = "error";
    } else {
        // Prefer Tip_Time column if present
        $sql = "INSERT INTO tip (Shift_ID, Tip_Amount, Is_It_Cash, Tip_Time) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $sql = "INSERT INTO tip (Shift_ID, Tip_Amount, Is_It_Cash) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
        }
        $stmt->bind_param("idi", $shiftId, $tip, $isCash);
        if ($stmt->execute()) {
            $message = "Tip added.";
            $messageType = "success";
        } else {
            $message = "Error adding tip: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Update tip
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tip'])) {
    $tipId = (int)($_POST['tip_id'] ?? 0);
    $tip = (float)($_POST['tip_amount'] ?? 0);
    $isCash = (int)($_POST['is_cash'] ?? 1);

    if ($tip <= 0) {
        $message = "Tip amount must be > 0";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("UPDATE tip SET Tip_Amount = ?, Is_It_Cash = ? WHERE Tip_ID = ? AND Shift_ID = ?");
        $stmt->bind_param("diii", $tip, $isCash, $tipId, $shiftId);
        if ($stmt->execute()) {
            $message = "Tip updated.";
            $messageType = "success";
        } else {
            $message = "Error updating tip: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Delete tip (soft delete to prevent data loss)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tip'])) {
    $tipId = (int)($_POST['tip_id'] ?? 0);
    $deletedBy = (int)($_SESSION['id'] ?? 0);
    $deletedAt = date('Y-m-d H:i:s');

    $sql = "UPDATE tip SET Is_Deleted = 1, Deleted_At = ?, Deleted_By = ? WHERE Tip_ID = ? AND Shift_ID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Fallback for older schema
        $stmt = $conn->prepare("DELETE FROM tip WHERE Tip_ID = ? AND Shift_ID = ?");
        $stmt->bind_param("ii", $tipId, $shiftId);
    } else {
        $stmt->bind_param("siii", $deletedAt, $deletedBy, $tipId, $shiftId);
    }

    if ($stmt->execute()) {
        $message = "Tip removed.";
        $messageType = "success";
    } else {
        $message = "Error removing tip: " . $conn->error;
        $messageType = "error";
    }
}

// Delete shift (hard delete: tips first, then shift)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shift'])) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM tip WHERE Shift_ID = ?");
        $stmt->bind_param("i", $shiftId);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM shift WHERE Shift_ID = ?");
        $stmt->bind_param("i", $shiftId);
        $stmt->execute();

        $conn->commit();
        header('Location: manage_shifts.php?msg=Shift%20deleted');
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $message = "Error deleting shift: " . $e->getMessage();
        $messageType = "error";
    }
}

// Load shift
$stmt = $conn->prepare(
    "SELECT s.Shift_ID, s.Employee_ID, e.Employee_Name, e.User_Name, s.Start_Time, s.End_Time, s.Sale_Amount
     FROM shift s JOIN employee e ON e.Employee_ID = s.Employee_ID
     WHERE s.Shift_ID = ?"
);
$stmt->bind_param("i", $shiftId);
$stmt->execute();
$shift = $stmt->get_result()->fetch_assoc();
if (!$shift) {
    header('Location: manage_shifts.php');
    exit;
}

// Load tips
$tips = [];
$hasIsDeleted = peachtrack_has_column($conn, 'tip', 'Is_Deleted');
$stmt = $conn->prepare("SELECT Tip_ID, Tip_Amount, Is_It_Cash FROM tip WHERE Shift_ID = ?" . ($hasIsDeleted ? " AND (Is_Deleted IS NULL OR Is_Deleted = 0)" : "") . " ORDER BY Tip_ID DESC");
$stmt->bind_param("i", $shiftId);
$stmt->execute();
$tips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once "header.php";
?>

<?php if (isset($_GET['created'])): ?>
  <div class="alert success">Shift created successfully.</div>
<?php endif; ?>

<?php if ($message): ?>
  <div class="alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">✏️ Edit Shift #<?php echo (int)$shift['Shift_ID']; ?></h2>
      <div class="muted"><?php echo htmlspecialchars($shift['Employee_Name']); ?> (<?php echo htmlspecialchars($shift['User_Name']); ?>)</div>
    </div>
    <div class="no-print" style="display:flex; gap:10px;">
      <a class="btn btn-ghost" href="manage_shifts.php" style="text-decoration:none;">Back</a>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="delete_shift" value="1" />
        <button class="btn btn-secondary" type="submit" onclick="return confirm('Delete shift #<?php echo (int)$shift['Shift_ID']; ?> and ALL tips inside it?')">Delete Shift</button>
      </form>
    </div>
  </div>

  <div style="height:14px"></div>

  <form method="POST" class="no-print" style="display:grid; grid-template-columns: 1fr 1fr 1fr auto; gap:12px; align-items:end;">
    <input type="hidden" name="update_shift" value="1" />
    <div>
      <label>Start Time</label>
      <input type="datetime-local" name="start_time" value="<?php echo htmlspecialchars(to_dt_local($shift['Start_Time'])); ?>" required />
    </div>
    <div>
      <label>End Time (empty = active)</label>
      <input type="datetime-local" name="end_time" value="<?php echo htmlspecialchars(to_dt_local($shift['End_Time'])); ?>" />
    </div>
    <div>
      <label>Sales Amount</label>
      <input type="number" step="0.01" name="sale_amount" value="<?php echo htmlspecialchars((string)$shift['Sale_Amount']); ?>" />
    </div>
    <div>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>

  <div class="muted" style="margin-top:12px; font-size:12px;">Tip: leaving End Time empty makes the shift active again.</div>
</div>

<div style="height:14px"></div>

<div class="grid grid-2">
  <div class="card">
    <h3 style="margin-top:0;">➕ Add Tip</h3>
    <form method="POST" class="no-print">
      <input type="hidden" name="add_tip" value="1" />
      <label>Tip Amount</label>
      <input type="number" step="0.01" name="tip_amount" required />
      <label>Method</label>
      <select name="is_cash">
        <option value="1">Cash</option>
        <option value="0">Electronic</option>
      </select>
      <div style="margin-top:12px;">
        <button class="btn btn-primary" type="submit">Add</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3 style="margin-top:0;">💡 Tips in this Shift</h3>
    <?php if (empty($tips)): ?>
      <div class="muted">No tips yet.</div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Tip ID</th>
            <th>Amount</th>
            <th>Method</th>
            <th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tips as $t): ?>
            <tr>
              <td class="muted">#<?php echo (int)$t['Tip_ID']; ?></td>
              <td>$<?php echo htmlspecialchars(number_format((float)$t['Tip_Amount'],2)); ?></td>
              <td><?php echo ((int)$t['Is_It_Cash']===1)?'Cash':'Electronic'; ?></td>
              <td class="no-print">
                <details>
                  <summary class="muted" style="cursor:pointer;">Edit</summary>
                  <form method="POST" style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr auto auto; gap:8px; align-items:end;">
                    <input type="hidden" name="tip_id" value="<?php echo (int)$t['Tip_ID']; ?>" />
                    <input type="hidden" name="update_tip" value="1" />
                    <div>
                      <label>Amount</label>
                      <input type="number" step="0.01" name="tip_amount" value="<?php echo htmlspecialchars((string)$t['Tip_Amount']); ?>" required />
                    </div>
                    <div>
                      <label>Method</label>
                      <select name="is_cash">
                        <option value="1" <?php echo ((int)$t['Is_It_Cash']===1)?'selected':''; ?>>Cash</option>
                        <option value="0" <?php echo ((int)$t['Is_It_Cash']===0)?'selected':''; ?>>Electronic</option>
                      </select>
                    </div>
                    <div>
                      <button class="btn btn-primary" type="submit">Save</button>
                    </div>
                  </form>
                  <form method="POST" style="margin-top:8px;">
                    <input type="hidden" name="tip_id" value="<?php echo (int)$t['Tip_ID']; ?>" />
                    <input type="hidden" name="delete_tip" value="1" />
                    <button class="btn btn-secondary" type="submit" onclick="return confirm('Delete tip #<?php echo (int)$t['Tip_ID']; ?>?')">Delete</button>
                  </form>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once "footer.php"; ?>
