<?php
/**
 * Sidebar Component
 * MDRRMO-GLAN Incident Reporting and Response Coordination System
 */

$sidebar_org_logo_path = null;
if (is_logged_in() && !empty($_SESSION['organization_id'])) {
    try {
        $__dbLogo = (new Database())->getConnection();
        if ($__dbLogo) {
            $__st = $__dbLogo->prepare('SELECT logo_path FROM organizations WHERE id = ? LIMIT 1');
            $__st->execute([(int) $_SESSION['organization_id']]);
            $sidebar_org_logo_path = $__st->fetchColumn();
            if ($sidebar_org_logo_path === false) {
                $sidebar_org_logo_path = null;
            }
        }
    } catch (Exception $e) {
        $sidebar_org_logo_path = null;
    }
}

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
    <div class="position-sticky pt-2">
        <p class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
            <?php echo isset($_SESSION['user_role']) ? htmlspecialchars($_SESSION['user_role']) : 'Navigation'; ?>
        </p>
        <ul class="nav flex-column">
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('admin', $current_page, $current_path) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>dashboard/admin.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive(['reports','view','edit'], $current_page, $current_path) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>reports/index.php">
                        <i class="fas fa-file-alt"></i>
                        <span>All Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($current_path, '/reports/completion_summary.php') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>reports/completion_summary.php">
                        <i class="fas fa-chart-pie"></i>
                        <span>Completion Summary</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($current_path, '/reports/map.php') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>reports/map.php">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Incident Map</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'index' && strpos($current_path, '/organizations/') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>organizations/index.php">
                        <i class="fas fa-building"></i>
                        <span>Organizations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'users' && strpos($current_path, '/organizations/') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>organizations/users.php">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('analytics', $current_page, $current_path) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>dashboard/analytics.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('audit', $current_page, $current_path) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>dashboard/audit.php">
                        <i class="fas fa-history"></i>
                        <span>Audit Logs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('sms-settings', $current_page, $current_path) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>dashboard/sms-settings.php">
                        <i class="fas fa-sms"></i>
                        <span>SMS Settings</span>
                    </a>
                </li>

            <?php elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Organization Account'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('organization', $current_page, $current_path) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>dashboard/organization.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($current_path, '/reports/organization') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>reports/organization.php">
                        <i class="fas fa-file-alt"></i>
                        <span>Our Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($current_path, '/reports/completion_summary.php') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>reports/completion_summary.php">
                        <i class="fas fa-chart-pie"></i>
                        <span>Completion Summary</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($current_path, '/reports/map.php') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>reports/map.php">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Incident Map</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'members' && strpos($current_path, '/organizations/') !== false) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>organizations/members.php">
                        <i class="fas fa-users"></i>
                        <span>Members</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page === 'organization-branding') ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>dashboard/organization-branding.php">
                        <i class="fas fa-image"></i>
                        <span>Organization logo</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActive('analytics', $current_page, $current_path) ? 'active' : ''; ?>"
                       href="<?php echo BASE_URL; ?>dashboard/analytics.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                </li>

            <?php endif; ?>
        </ul>

        <?php if (is_logged_in()): ?>
        <ul class="nav flex-column px-2 mt-2 pt-2 border-top border-slate-200">
            <li class="nav-item">
                <a class="nav-link rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700"
                   href="<?php echo BASE_URL; ?>auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <hr class="my-3">

        <div class="px-3 py-2 mt-2 rounded-lg bg-slate-50 border border-slate-200 mx-2">
            <?php if (!empty($sidebar_org_logo_path)): ?>
            <div class="flex justify-center mb-2">
                <img src="<?php echo htmlspecialchars(BASE_URL . $sidebar_org_logo_path); ?>" alt="" class="max-h-12 max-w-[90%] object-contain rounded-md border border-slate-200 bg-white p-1">
            </div>
            <?php endif; ?>
            <p class="text-[11px] uppercase tracking-wider text-slate-400 mb-1">Organization</p>
            <p class="text-xs font-medium text-slate-700 truncate">
                <i class="fas fa-info-circle me-1 text-slate-400"></i>
                <?php echo isset($_SESSION['organization_name']) ? htmlspecialchars($_SESSION['organization_name']) : 'System'; ?>
            </p>
        </div>
    </div>
</div>
