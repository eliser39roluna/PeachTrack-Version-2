<?php
// Database connection
// MySQL connection settings
$host = '127.0.0.1';
$port = 8889;
$db   = 'peachtrack';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get form data
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$privilege = $_POST['privilege'] ?? 'user';

$message = '';
$messageType = ''; // 'success' or 'error'

// Ensure username ends with @fuzzypeach
if (!str_ends_with($username, '@fuzzypeach')) {
    $username .= '@fuzzypeach';
	
}

// Validate input
if ($username && $password && in_array($privilege, ['user', 'admin'])) {
    try {
        // Insert user
        $stmt = $pdo->prepare("INSERT INTO login_users (username, password, privilege) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $privilege]);

        $message = "✅ User <strong>$username</strong> added successfully.";
        $messageType = 'success';

        // Fetch all users after successful insert
        $users = [];
        $stmt = $pdo->query("SELECT id, username, privilege FROM login_users ORDER BY id DESC");
        $users = $stmt->fetchAll();

    } catch (Exception $e) {
        $message = "❌ Error adding user: " . $e->getMessage();
        $messageType = 'error';
        $users = []; // fallback in case of error
    }
} else {
    $message = "⚠️ Please fill out all fields correctly.";
    $messageType = 'error';
    $users = []; // fallback when input is not valid
}

?>
<!-- HTML Response with Centered Message -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User Result</title>
  
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
<div class="container">
  <div class="topbar">
    <div class="brand"><span class="dot"></span> PeachTrack</div>
    <div class="nav">
      <a href="index.php">Home</a>
      <a href="profile.php">Profile</a>
      <a class="danger" href="logout.php">Logout</a>
    </div>
  </div>
</div>

    <div class="container">
        <div class="message-box <?= htmlspecialchars($messageType) ?>">
            <h2><?= $messageType === 'success' ? 'Success!' : 'Error' ?></h2>
            <p><?= $message ?></p>
            <a href="welcome.php" class="back-link">← Back to Dashboard</a>
        </div>

        <?php if (!empty($users)): ?>
            <h3>Created Users</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Privilege</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['privilege']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>