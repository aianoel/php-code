<?php
session_start();
require_once '../includes/config.php';

// Log logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'logout', 'User logged out');
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php?message=logged_out');
exit();
?>
