<?php
require_once 'config.php';

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── SESSION TIMEOUT MANAGEMENT ──
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['login_time'])) {
    $elapsed_time = time() - $_SESSION['login_time'];
    
    if ($elapsed_time > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: Loginpage.php?session_expired=1");
        exit();
    }
}

// ── CSRF PROTECTION ──
if (session_status() === PHP_SESSION_ACTIVE) {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function csrf_input() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token()) . '">';
}

/**
 * Automatically manages sit-in sessions:
 * 1. Activates 'Approved' reservations when the time arrives.
 * 2. Completes 'Active' sessions from previous days.
 */
function auto_manage_sessions($conn) {
    // 1. Activate approved reservations for today whose time has arrived
    $conn->query("UPDATE sit_in_sessions 
                  SET Status = 'Active' 
                  WHERE Type = 'Reservation' 
                    AND Status = 'Approved' 
                    AND SessionDate = CURDATE() 
                    AND TimeIn <= CURTIME()");

    // 2. Auto-complete any 'Active' sessions from previous days (prevent zombies)
    $conn->query("UPDATE sit_in_sessions 
                  SET Status = 'Completed', TimeOut = '21:00:00' 
                  WHERE Status = 'Active' 
                    AND SessionDate < CURDATE()");
}
?>