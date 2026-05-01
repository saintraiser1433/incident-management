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

// Public base URL (must end with /). Auto-detected from the current request so links work on any host/port/virtual host.
$app_base_url = 'http://localhost/incident-management/';
if (!empty($_SERVER['HTTP_HOST'])) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $protocol = $https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    if ($docRoot && $appRoot) {
        $docRoot = str_replace('\\', '/', $docRoot);
        $appRoot = str_replace('\\', '/', $appRoot);
        if (strpos($appRoot, $docRoot) === 0) {
            $path = substr($appRoot, strlen($docRoot));
            $path = $path === '' ? '/' : '/' . trim($path, '/') . '/';
            $app_base_url = $protocol . $host . $path;
        }
    }
}
define('BASE_URL', $app_base_url);

// File upload settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Organization logos (stored under uploads/org_logos/)
define('ORG_LOGO_MAX_BYTES', 2 * 1024 * 1024); // 2MB
define('ORG_LOGO_WEB_PREFIX', 'uploads/org_logos/');

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

function organization_logo_url($relativePath)
{
    if (empty($relativePath)) {
        return null;
    }
    return BASE_URL . $relativePath;
}

function delete_organization_logo_disk($relativePath)
{
    if (empty($relativePath) || strpos($relativePath, '..') !== false) {
        return;
    }
    if (strpos($relativePath, ORG_LOGO_WEB_PREFIX) !== 0) {
        return;
    }
    $full = realpath(__DIR__ . '/../' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
    $base = realpath(__DIR__ . '/../uploads/org_logos');
    if ($full && $base && strpos($full, $base) === 0 && is_file($full)) {
        @unlink($full);
    }
}

/**
 * Saves $_FILES['org_logo'] for an organization. Overwrites any existing logo files for that org id.
 *
 * @return array{path?: string, error?: string, skipped?: bool}
 */
function save_organization_logo_upload($orgId)
{
    if (empty($_FILES['org_logo']) || ($_FILES['org_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['skipped' => true];
    }
    $f = $_FILES['org_logo'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['error' => 'Logo upload failed. Please try again.'];
    }
    if (($f['size'] ?? 0) > ORG_LOGO_MAX_BYTES) {
        return ['error' => 'Logo must be ' . (ORG_LOGO_MAX_BYTES / 1024 / 1024) . 'MB or smaller.'];
    }
    $tmp = $f['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['error' => 'Invalid upload.'];
    }
    if (!class_exists('finfo')) {
        return ['error' => 'Server missing fileinfo extension; cannot validate logo.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($map[$mime])) {
        return ['error' => 'Logo must be JPEG, PNG, WebP, or GIF.'];
    }
    $ext = $map[$mime];
    $dir = __DIR__ . '/../uploads/org_logos/';
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['error' => 'Could not create upload directory.'];
        }
    }
    $basename = 'org_' . (int) $orgId . '.' . $ext;
    foreach (glob($dir . 'org_' . (int) $orgId . '.*') ?: [] as $existing) {
        if (is_file($existing)) {
            @unlink($existing);
        }
    }
    $dest = $dir . $basename;
    if (!move_uploaded_file($tmp, $dest)) {
        return ['error' => 'Could not save logo file.'];
    }
    return ['path' => ORG_LOGO_WEB_PREFIX . $basename];
}
?>