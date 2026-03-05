<?php
require_once "../db_config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Only managers/admins
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (string)($_SESSION['role'] ?? '') !== '101') {
    http_response_code(403);
    echo json_encode(["error" => "forbidden"]);
    exit;
}

$cid = peachtrack_company_id();
$sql = "
SELECT s.Shift_ID, s.Employee_ID, s.Start_Time,
       e.Employee_Name, e.User_Name
FROM shift s
JOIN employee e ON e.Employee_ID = s.Employee_ID
WHERE s.End_Time IS NULL AND e.Company_ID = ?
ORDER BY s.Start_Time DESC
";

$items = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $start = $r['Start_Time'];
        // best-effort ISO for JS duration timers
        $startIso = $start ? (date('c', strtotime($start))) : '';
        $items[] = [
            'shift_id' => (int)$r['Shift_ID'],
            'employee_id' => (int)$r['Employee_ID'],
            'employee_name' => $r['Employee_Name'],
            'user_name' => $r['User_Name'],
            'start_time' => $start,
            'start_iso' => $startIso,
        ];
    }
}

echo json_encode(["items" => $items]);
