<?php
// CLI verification: ensure payroll CSV output has no HTML.
$_GET = [
  'export' => 'csv',
  'range' => 'week',
  'mode' => 'all',
];

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
$_SESSION['loggedin'] = true;
$_SESSION['role'] = '101';
$_SESSION['id'] = 10000;
$_SESSION['name'] = 'System Admin';

ob_start();
require __DIR__ . '/../src/payroll.php';
$out = ob_get_clean();

// If payroll.php exits, we won't reach here. So we also write a second runner below.
file_put_contents('php://stderr', "Reached end (unexpected).\n");
file_put_contents('php://stdout', $out);
