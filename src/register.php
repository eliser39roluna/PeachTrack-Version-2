<?php
require_once "db_config.php";
require_once "header.php";

if ($_SESSION['role'] != 101) {
    header("location: index.php");
    exit;
}

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $raw_pass = $_POST['password'];
    $emp_id   = $_POST['emp_id']; // Manual ID per prompt requirement
    $role     = $_POST['role'];

    // Validation
    if (!str_ends_with($username, '@peach')) {
        $msg = "<div class='alert error'>Username must end with @peach</div>";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Employee Table
            $stmt = $conn->prepare("INSERT INTO EMPLOYEE (Employee_ID, Type_Code, Employee_Name, User_Name) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $emp_id, $role, $fullname, $username);
            $stmt->execute();

            // 2. Credential Table
            $hash = password_hash($raw_pass, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("INSERT INTO CREDENTIAL (Employee_ID, Password) VALUES (?, ?)");
            $stmt2->bind_param("is", $emp_id, $hash);
            $stmt2->execute();

            $conn->commit();
            $msg = "<div class='alert success'>Employee <strong>$fullname</strong> added successfully!</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "<div class='alert error'>Error: ID or Username might already exist.</div>";
        }
    }
}
?>

<div class="page-header">
    <div class="breadcrumbs">Home / Management / Staff</div>
    <h2>Register New Employee</h2>
</div>

<?= $msg ?>

<div class="card" style="max-width: 600px;">
    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="fullname" required placeholder="e.g. Jane Doe">
        </div>
        
        <div class="grid-2">
            <div class="form-group">
                <label>Employee ID (5 digits)</label>
                <input type="number" name="emp_id" required placeholder="10001">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="102">Employee (Staff)</option>
                    <option value="101">Manager (Admin)</option>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Username (must end in @peach)</label>
                <input type="text" name="username" required placeholder="jane@peach">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Create Account</button>
    </form>
</div>

<?php require_once "footer.php"; ?>