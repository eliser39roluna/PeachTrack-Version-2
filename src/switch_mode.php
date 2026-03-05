<?php
require_once "db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Only base admins can switch
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || peachtrack_base_role() !== '101') {
    header('Location: index.php');
    exit;
}

// Exit employee mode
if (isset($_GET['exit']) && (string)$_GET['exit'] === '1') {
    unset($_SESSION['view_as'], $_SESSION['view_employee_id'], $_SESSION['view_employee_name']);
    header('Location: index.php');
    exit;
}

// Determine which employee to switch into.
// Default: use admin's own employee profile if set; otherwise pick the first employee.
$targetId = (int)($_GET['employee_id'] ?? 0);

if ($targetId <= 0) {
    // If you later add a column like employee.Linked_Admin_ID or store a preference, use it here.
    $res = $conn->query("SELECT Employee_ID, Employee_Name FROM employee ORDER BY Employee_ID ASC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    $targetId = (int)($row['Employee_ID'] ?? 0);
}

if ($targetId <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("SELECT Employee_ID, Employee_Name FROM employee WHERE Employee_ID = ?");
$stmt->bind_param('i', $targetId);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();

if (!$emp) {
    header('Location: index.php');
    exit;
}

$_SESSION['view_as'] = 'employee';
$_SESSION['view_employee_id'] = (int)$emp['Employee_ID'];
$_SESSION['view_employee_name'] = (string)$emp['Employee_Name'];

header('Location: index.php');
exit;
