<?php
/**
 * Main Entry Point
 * MDRRMO-GLAN Incident Reporting and Response Coordination System
 */

require_once 'config/config.php';

// Check if user is logged in
if (is_logged_in()) {
    // Redirect to appropriate dashboard based on role
    switch ($_SESSION['user_role']) {
        case 'Admin':
            redirect('dashboard/admin.php');
            break;
        case 'Organization Account':
            redirect('dashboard/organization.php');
            break;
        case 'Responder':
            redirect('dashboard/responder.php');
            break;
        default:
            redirect('auth/login.php');
    }
} else {
    // Redirect to login page
    redirect('auth/login.php');
}
?>
