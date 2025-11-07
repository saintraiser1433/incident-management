<?php
/**
 * Application Configuration
 * Incident Report Management System
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Application settings
define('APP_NAME', 'MDRRMO-GLAN Incident Reporting and Response Coordination System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost:8090/incident-management/');

// File upload settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour

// Include database configuration
require_once __DIR__ . '/database.php';

// Utility functions
function sanitize_input($data)
{
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function redirect($url)
{
    header("Location: " . BASE_URL . $url);
    exit();
}

function is_logged_in()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function require_login()
{
    if (!is_logged_in()) {
        redirect('auth/login.php');
    }
}

function require_role($required_roles)
{
    require_login();

    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }

    if (!in_array($_SESSION['user_role'], $required_roles)) {
        redirect('dashboard/index.php?error=access_denied');
    }
}

function log_audit($action, $table_name, $record_id = null)
{
    if (!is_logged_in())
        return;

    $database = new Database();
    $db = $database->getConnection();

    $query = "INSERT INTO audit_logs (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $action, $table_name, $record_id]);
}

function format_date($date)
{
    return date('M d, Y', strtotime($date));
}

function format_datetime($datetime)
{
    return date('M d, Y g:i A', strtotime($datetime));
}

function get_severity_badge_class($severity)
{
    switch ($severity) {
        case 'Low':
            return 'badge-success';
        case 'Medium':
            return 'badge-warning';
        case 'High':
            return 'badge-danger';
        case 'Critical':
            return 'badge-dark';
        default:
            return 'badge-secondary';
    }
}

function get_status_badge_class($status)
{
    switch ($status) {
        case 'Pending':
            return 'badge-warning';
        case 'In Progress':
            return 'badge-info';
        case 'Resolved':
            return 'badge-success';
        case 'Closed':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}
?>