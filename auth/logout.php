<?php
/**
 * Logout Page
 * Incident Report Management System
 */

require_once '../config/config.php';

// Log logout action if user is logged in
if (is_logged_in()) {
    log_audit('LOGOUT', 'users', $_SESSION['user_id']);
}

// Destroy session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
redirect('auth/login.php');
?>
