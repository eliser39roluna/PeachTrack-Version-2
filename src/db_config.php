<?php
// Local DB configuration for PeachTrack
// Set environment variables (recommended) or edit db_config.local.php
// DO NOT commit real credentials to version control.
//
// To run locally: copy db_config.local.example.php -> db_config.local.php
// and fill in your values. That file is gitignored.
$_local = __DIR__ . '/db_config.local.php';
if (file_exists($_local)) require_once $_local;

$host   = getenv('DB_HOST') ?: (defined('DB_HOST')   ? DB_HOST   : 'localhost');
$port   = (int)(getenv('DB_PORT') ?: (defined('DB_PORT')   ? DB_PORT   : 3306));
$socket = getenv('DB_SOCKET') ?: (defined('DB_SOCKET') ? DB_SOCKET : null);
$db     = getenv('DB_NAME') ?: (defined('DB_NAME')   ? DB_NAME   : 'peachtrack');
$user   = getenv('DB_USER') ?: (defined('DB_USER')   ? DB_USER   : 'root');
$pass   = getenv('DB_PASS') ?: (defined('DB_PASS')   ? DB_PASS   : '');

$conn = new mysqli($host, $user, $pass, $db, $port, $socket);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Use local timezone for date-based dashboards/reports
// (Feb in Alberta is MST = UTC-07:00; adjust if you want DST handling)
$conn->query("SET time_zone = '-07:00'");
// Also set PHP timezone for consistent date() output
@date_default_timezone_set('America/Edmonton');

// ---- Schema helpers (for graceful fallback when DB migrations haven't been run yet)
// --- Session role helpers (supports Admin switching into Employee Mode)
function peachtrack_base_role(): string {
    return (string)($_SESSION['role'] ?? '');
}

function peachtrack_base_employee_id(): int {
    return (int)($_SESSION['id'] ?? 0);
}

function peachtrack_effective_role(): string {
    // If admin is "viewing as employee", treat role as employee for UI/pages that use this.
    $base = peachtrack_base_role();
    if ($base === '101' && isset($_SESSION['view_as']) && $_SESSION['view_as'] === 'employee') {
        return '102';
    }
    return $base;
}

function peachtrack_effective_employee_id(): int {
    $base = peachtrack_base_role();
    if ($base === '101' && isset($_SESSION['view_as']) && $_SESSION['view_as'] === 'employee') {
        return (int)($_SESSION['view_employee_id'] ?? 0);
    }
    return peachtrack_base_employee_id();
}

function peachtrack_effective_name(): string {
    $base = (string)($_SESSION['name'] ?? 'User');
    $baseRole = peachtrack_base_role();
    if ($baseRole === '101' && isset($_SESSION['view_as']) && $_SESSION['view_as'] === 'employee') {
        return (string)($_SESSION['view_employee_name'] ?? $base);
    }
    return $base;
}

function peachtrack_is_admin_base(): bool {
    return peachtrack_base_role() === '101';
}

// ---- Multi-tenancy helpers ----

/**
 * Returns the Company_ID of the currently logged-in session.
 * Super-admins (role 100) have company_id = 0 (can see all).
 */
function peachtrack_company_id(): int {
    return (int)($_SESSION['company_id'] ?? 0);
}

/**
 * Returns true if the user is a super-admin (role 100).
 */
function peachtrack_is_superadmin(): bool {
    return peachtrack_base_role() === '100';
}

/**
 * Returns the company name from the session.
 */
function peachtrack_company_name(): string {
    return (string)($_SESSION['company_name'] ?? 'PeachTrack');
}

/**
 * Returns the company logo path from the session (empty string if none).
 */
function peachtrack_company_logo(): string {
    return (string)($_SESSION['company_logo'] ?? '');
}

// ---- Schema helpers (for graceful fallback when DB migrations haven't been run yet) ----

function peachtrack_has_column(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table.'.'.$column);
    if (array_key_exists($key, $cache)) return $cache[$key];

    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
    $dbName = $dbRow['db'] ?? '';
    if (!$dbName) {
        $cache[$key] = false;
        return false;
    }

    $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    $stmt->bind_param('sss', $dbName, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $cache[$key] = ($res && $res->num_rows > 0);
    return $cache[$key];
}

