<?php
/**
 * Sidebar Component
 * MDRRMO-GLAN Incident Reporting and Response Coordination System
 */

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_path = $_SERVER['PHP_SELF'];
function isActive($needles, $current_page, $current_path) {
    if (!is_array($needles)) { $needles = [$needles]; }
    foreach ($needles as $n) {
        if ($current_page === $n) return true;
        if (strpos($current_path, '/' . trim($n, '/')) !== false) return true;
    }
    return false;
}
?>

<div class="col-md-3 col-lg-2 sidebar">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <?php if ($_SESSION['user_role'] == 'Admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('admin', $current_page, $current_path) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>dashboard/admin.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive(['reports','view','edit'], $current_page, $current_path) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>reports/index.php">
                        <i class="fas fa-file-alt"></i>
                        All Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'index' && strpos($current_path, '/organizations/') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>organizations/index.php">
                        <i class="fas fa-building"></i>
                        Organizations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'users' && strpos($current_path, '/organizations/') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>organizations/users.php">
                        <i class="fas fa-users"></i>
                        Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('analytics', $current_page, $current_path) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>dashboard/analytics.php">
                        <i class="fas fa-chart-bar"></i>
                        Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('audit', $current_page, $current_path) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>dashboard/audit.php">
                        <i class="fas fa-history"></i>
                        Audit Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('sms-settings', $current_page, $current_path) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>dashboard/sms-settings.php">
                        <i class="fas fa-sms"></i>
                        SMS Settings
                    </a>
                </li>
                
            <?php elseif ($_SESSION['user_role'] == 'Organization Account'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('organization', $current_page, $current_path) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>dashboard/organization.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($current_path, '/reports/organization') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>reports/organization.php">
                        <i class="fas fa-file-alt"></i>
                        Our Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('analytics', $current_page, $current_path) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>dashboard/analytics.php">
                        <i class="fas fa-chart-bar"></i>
                        Analytics
                    </a>
                </li>
                
            <?php else: // Responder ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('responder', $current_page, $current_path) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>dashboard/responder.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($current_path, '/reports/departments') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>reports/departments.php">
                        <i class="fas fa-building"></i>
                        Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($current_path, '/reports/my-reports') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>reports/my-reports.php">
                        <i class="fas fa-file-alt"></i>
                        My Reports
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <hr class="my-3" style="border-color: rgba(255,255,255,0.2);">
        
        <div class="text-center text-white-50">
            <small>
                <i class="fas fa-info-circle me-1"></i>
                <?php echo $_SESSION['organization_name'] ?: 'System'; ?>
            </small>
        </div>
    </div>
</div>
