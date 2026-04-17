<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "sysarchstudents";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── SESSION TIMEOUT MANAGEMENT (2 hours) ──
if (isset($_SESSION['login_time'])) {
    $session_timeout = 2 * 60 * 60; // 2 hours in seconds
    $elapsed_time = time() - $_SESSION['login_time'];
    
    if ($elapsed_time > $session_timeout) {
        // Session expired - destroy it
        session_destroy();
        header("Location: Loginpage.php?session_expired=1");
        exit();
    }
}
?>