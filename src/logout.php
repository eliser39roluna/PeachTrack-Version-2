<?php
// Feature 3: Unset all session variables and destroy the session
session_start();
$_SESSION = array();
session_destroy();
header("location: login.php");
exit;
?>