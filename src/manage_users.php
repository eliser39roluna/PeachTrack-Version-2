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

function hash_pw($pw) {
    return password_hash($pw, PASSWORD_BCRYPT);
}

// Create employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_employee'])) {
    $name = trim($_POST['employee_name'] ?? '');
    $username = trim($_POST['user_name'] ?? '');
    $type = (int)($_POST['type_code'] ?? 102);
    $pw = (string)($_POST['password'] ?? '');

    if ($name === '' || $username === '' || $pw === '' || !in_array($type, [101,102], true)) {
        $message = "Please fill all fields.";
        $messageType = "error";
    } else {
        // Get next Employee_ID
        $res = $conn->query("SELECT COALESCE(MAX(Employee_ID), 10000) + 1 AS next_id FROM employee");
        $nextId = (int)($res ? ($res->fetch_assoc()['next_id'] ?? 10001) : 10001);

        $conn->begin_transaction();
        try {
            $cid = peachtrack_company_id();
            $stmt = $conn->prepare("INSERT INTO employee (Employee_ID, Company_ID, Type_Code, Employee_Name, User_Name) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiss", $nextId, $cid, $type, $name, $username);
            $stmt->execute();

            $hash = hash_pw($pw);
            $stmt2 = $conn->prepare("INSERT INTO credential (Employee_ID, Password) VALUES (?, ?)");
            $stmt2->bind_param("is", $nextId, $hash);
            $stmt2->execute();

            $conn->commit();
            $message = "Employee created: $name ($username) with ID #$nextId";
            $messageType = "success";
        } catch (Throwable $e) {
            $conn->rollback();
            $message = "Error creating employee: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $empId = (int)($_POST['employee_id'] ?? 0);
    $newPw = (string)($_POST['new_password'] ?? '');
    if ($empId <= 0 || $newPw === '') {
        $message = "Employee ID and new password required.";
        $messageType = "error";
    } else {
        $hash = hash_pw($newPw);
        $stmt = $conn->prepare("UPDATE credential SET Password = ? WHERE Employee_ID = ?");
        $stmt->bind_param("si", $hash, $empId);
        if ($stmt->execute()) {
            $message = "Password reset for Employee #$empId";
            $messageType = "success";
        } else {
            $message = "Error resetting password: " . $conn->error;
            $messageType = "error";
        }
    }
}

// Fetch employees scoped to the current company
$rows = [];
$cid = peachtrack_company_id();
$stmt_emp = $conn->prepare("SELECT e.Employee_ID, e.Employee_Name, e.User_Name, e.Type_Code, COALESCE(e.Is_Active,1) AS Is_Active FROM employee e WHERE e.Company_ID = ? ORDER BY e.Employee_ID ASC");
if ($stmt_emp) {
    $stmt_emp->bind_param('i', $cid);
    $stmt_emp->execute();
    $rows = $stmt_emp->get_result()->fetch_all(MYSQLI_ASSOC);
}

require_once "header.php";
?>

<?php if ($message): ?>
  <div class="alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card">
    <h2 style="margin-top:0;">👤 Create Employee</h2>
    <form method="POST">
      <input type="hidden" name="create_employee" value="1" />

      <label>Employee Name</label>
      <input name="employee_name" required />

      <label>Username (e.g. john@peach)</label>
      <input name="user_name" required />

      <label>Role</label>
      <select name="type_code">
        <option value="102">Employee</option>
        <option value="101">Manager/Admin</option>
      </select>

      <label>Temp Password</label>
      <input name="password" type="password" required />

      <div style="margin-top:12px;">
        <button class="btn btn-primary" type="submit">Create</button>
      </div>
      <div class="muted" style="margin-top:10px; font-size:12px;">
        Passwords are stored securely using bcrypt.
      </div>
    </form>
  </div>

  <div class="card">
    <h2 style="margin-top:0;">🔑 Reset Password</h2>
    <form method="POST">
      <input type="hidden" name="reset_password" value="1" />

      <label>Employee ID</label>
      <input name="employee_id" type="number" required />

      <label>New Password</label>
      <input name="new_password" type="password" required />

      <div style="margin-top:12px;">
        <button class="btn btn-secondary" type="submit">Reset</button>
      </div>
    </form>
  </div>
</div>

<div style="height:14px"></div>

<div class="card">
  <h2 style="margin-top:0;">📋 Employees</h2>
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Username</th>
        <th>Role</th>
        <th>Status</th>
        <th class="no-print">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['Employee_ID']); ?></td>
          <td><?php echo htmlspecialchars($r['Employee_Name']); ?></td>
          <td><?php echo htmlspecialchars($r['User_Name']); ?></td>
          <td><?php echo ((string)$r['Type_Code'] === '101') ? 'Manager/Admin' : 'Employee'; ?></td>
          <td>
            <?php echo ((int)($r['Is_Active'] ?? 1) === 1) ? '<span class="muted">Active</span>' : '<span class="muted">Deactivated</span>'; ?>
          </td>
          <td class="no-print">
            <a class="btn btn-ghost" style="text-decoration:none;" href="edit_user.php?id=<?php echo (int)$r['Employee_ID']; ?>">Manage</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="muted">No employees found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once "footer.php"; ?>
