<?php
require __DIR__ . '/src/db_config.php';
$chk = $conn->query("SHOW COLUMNS FROM company LIKE 'Logo_Path'");
if ($chk && $chk->num_rows === 0) {
    $r = $conn->query("ALTER TABLE company ADD COLUMN Logo_Path VARCHAR(300) NULL DEFAULT NULL");
    echo $r ? "Column Logo_Path added OK\n" : "Error: " . $conn->error . "\n";
} else {
    echo "Column already exists, skipping.\n";
}
