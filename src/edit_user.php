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

$empId = (int)($_GET['id'] ?? 0);
if ($empId <= 0) {
    header('Location: manage_users.php');
    exit;
}

$message = '';
$messageType = '';

// Update employee fields
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    $name = trim($_POST['employee_name'] ?? '');
    $username = trim($_POST['user_name'] ?? '');
    $type = (int)($_POST['type_code'] ?? 102);

    if ($name === '' || $username === '' || !in_array($type, [101,102], true)) {
        $message = 'Please fill all fields.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE employee SET Employee_Name = ?, User_Name = ?, Type_Code = ? WHERE Employee_ID = ?");
        $stmt->bind_param('ssii', $name, $username, $type, $empId);
        if ($stmt->execute()) {
            $message = 'User updated.';
            $messageType = 'success';
        } else {
            $message = 'Error updating user: ' . $conn->error;
            $messageType = 'error';
        }
    }
}

// Deactivate / Reactivate employee (soft change to prevent data loss)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    // Prevent manager from deactivating themselves while logged in
    if ((int)($_SESSION['id'] ?? 0) === $empId) {
        $message = 'You cannot deactivate your own account while logged in.';
        $messageType = 'error';
    } else {
        $newActive = (int)($_POST['is_active'] ?? 1);
        // Check schema supports Is_Active
        $schemaOk = false;
        $check = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='employee' AND COLUMN_NAME='Is_Active' LIMIT 1");
        if ($check && $check->num_rows > 0) $schemaOk = true;

        if (!$schemaOk) {
            $message = 'Deactivate feature requires a DB update (add employee.Is_Active). Run alter_employee_deactivate.sql in phpMyAdmin.';
            $messageType = 'error';
        } else {
            // Safety: do not allow deactivation if the employee has an active shift
            if ($newActive === 0) {
                $stmtCheck = $conn->prepare("SELECT COUNT(*) AS c FROM shift WHERE Employee_ID = ? AND End_Time IS NULL");
                $stmtCheck->bind_param('i', $empId);
                $stmtCheck->execute();
                $row = $stmtCheck->get_result()->fetch_assoc();
                $activeCount = (int)($row['c'] ?? 0);

                if ($activeCount > 0) {
                    $message = 'Cannot deactivate this user because they have an active shift. Force-end the shift in Manage Shifts, then deactivate.';
                    $messageType = 'error';
                    // stop here
                } else {
                    $stmt = $conn->prepare("UPDATE employee SET Is_Active = ? WHERE Employee_ID = ?");
                    $stmt->bind_param('ii', $newActive, $empId);
                    if ($stmt->execute()) {
                        $message = 'User deactivated.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error updating status: ' . $conn->error;
                        $messageType = 'error';
                    }
                }
            } else {
                // Reactivation allowed anytime
                $stmt = $conn->prepare("UPDATE employee SET Is_Active = ? WHERE Employee_ID = ?");
                $stmt->bind_param('ii', $newActive, $empId);
                if ($stmt->execute()) {
                    $message = 'User reactivated.';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating status: ' . $conn->error;
                    $messageType = 'error';
                }
            }
        }
    }
}

// Load employee
$stmt = $conn->prepare("SELECT Employee_ID, Employee_Name, User_Name, Type_Code, COALESCE(Is_Active,1) AS Is_Active FROM employee WHERE Employee_ID = ?");
$stmt->bind_param('i', $empId);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
if (!$emp) {
    header('Location: manage_users.php');
    exit;
}

require_once "header.php";
?>

<?php if ($message): ?>
  <div class="alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card">
  <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">✏️ Manage User #<?php echo (int)$emp['Employee_ID']; ?></h2>
      <div class="muted">Update employee details or deactivate/reactivate the account (recommended to prevent data loss).</div>
    </div>
    <div class="no-print" style="display:flex; gap:10px; align-items:center;">
      <a class="btn btn-ghost" href="manage_users.php" style="text-decoration:none;">Back</a>

      <form method="POST" style="margin:0;">
        <input type="hidden" name="toggle_active" value="1" />
        <?php if ((int)($emp['Is_Active'] ?? 1) === 1): ?>
          <input type="hidden" name="is_active" value="0" />
          <button class="btn btn-secondary" type="submit" onclick="return confirm('Deactivate this user? Their data (shifts/tips) will be kept.')">Deactivate</button>
        <?php else: ?>
          <input type="hidden" name="is_active" value="1" />
          <button class="btn btn-primary" type="submit" onclick="return confirm('Reactivate this user account?')">Reactivate</button>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div style="height:14px"></div>

  <form method="POST" class="no-print" style="display:grid; grid-template-columns: 1fr 1fr 1fr auto; gap:12px; align-items:end;">
    <input type="hidden" name="update_employee" value="1" />

    <div>
      <label>Employee Name</label>
      <input name="employee_name" value="<?php echo htmlspecialchars($emp['Employee_Name']); ?>" required />
    </div>

    <div>
      <label>Username</label>
      <input name="user_name" value="<?php echo htmlspecialchars($emp['User_Name']); ?>" required />
    </div>

    <div>
      <label>Role</label>
      <select name="type_code">
        <option value="102" <?php echo ((string)$emp['Type_Code']==='102')?'selected':''; ?>>Employee</option>
        <option value="101" <?php echo ((string)$emp['Type_Code']==='101')?'selected':''; ?>>Manager/Admin</option>
      </select>
    </div>

    <div>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>

  <div class="muted" style="margin-top:12px; font-size:12px;">
    Tip: Use Manage Users to reset password (password hashes are stored in the credential table).
  </div>
</div>

<?php require_once "footer.php"; ?>
