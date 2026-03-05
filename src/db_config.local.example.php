<?php
// EXAMPLE local credentials file for PeachTrack.
// 1. Copy this file: cp db_config.local.example.php db_config.local.php
// 2. Fill in YOUR actual values in db_config.local.php
// 3. db_config.local.php is gitignored - your real password will never be committed.
define('DB_HOST',   'localhost');
define('DB_PORT',   3306);
define('DB_SOCKET', null);
define('DB_NAME',   'peachtrack');
define('DB_USER',   'root');
define('DB_PASS',   'YOUR_DB_PASSWORD_HERE');
