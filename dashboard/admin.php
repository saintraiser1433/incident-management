<?php
/**
 * Admin Dashboard
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Admin']);

$page_title = 'Admin Dashboard - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total reports
$query = "SELECT COUNT(*) as total FROM incident_reports";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_reports'] = $stmt->fetch()['total'];

// Reports by status
$query = "SELECT status, COUNT(*) as count FROM incident_reports GROUP BY status";
$stmt = $db->prepare($query);
$stmt->execute();
$status_counts = $stmt->fetchAll();
$stats['status_counts'] = array_column($status_counts, 'count', 'status');

// Reports by severity
$query = "SELECT severity_level, COUNT(*) as count FROM incident_reports GROUP BY severity_level";
$stmt = $db->prepare($query);
$stmt->execute();
$severity_counts = $stmt->fetchAll();
$stats['severity_counts'] = array_column($severity_counts, 'count', 'severity_level');

// Reports by category
$query = "SELECT category, COUNT(*) as count FROM incident_reports GROUP BY category";
$stmt = $db->prepare($query);
$stmt->execute();
$category_counts = $stmt->fetchAll();
$stats['category_counts'] = array_column($category_counts, 'count', 'category');

// Reports by organization
$query = "SELECT o.org_name, COUNT(ir.id) as count 
          FROM organizations o 
          LEFT JOIN incident_reports ir ON o.id = ir.organization_id 
          GROUP BY o.id, o.org_name 
          ORDER BY count DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$org_counts = $stmt->fetchAll();

// Recent reports
$query = "SELECT ir.*, ir.reported_by as reporter_name, o.org_name 
          FROM incident_reports ir 
          LEFT JOIN organizations o ON ir.organization_id = o.id 
          ORDER BY ir.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_reports = $stmt->fetchAll();

// Monthly trends (last 6 months)
$query = "SELECT DATE_FORMAT(incident_date, '%Y-%m') as month, COUNT(*) as count 
          FROM incident_reports 
          WHERE incident_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
          GROUP BY DATE_FORMAT(incident_date, '%Y-%m') 
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute();
$monthly_trends = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row g-0">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Admin Dashboard</h1>
                    <p class="text-sm text-slate-500 mt-1">Overview of incidents across all organizations.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="../reports/index.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        <i class="fas fa-file-alt text-slate-400"></i>View all reports
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                <div class="stat-card flex items-start justify-between">
                    <div>
                        <div class="stat-label">Total Reports</div>
                        <div class="stat-value"><?php echo $stats['total_reports']; ?></div>
                    </div>
                    <span class="stat-icon bg-slate-100 text-slate-700"><i class="fas fa-file-alt"></i></span>
                </div>

                <div class="stat-card flex items-start justify-between">
                    <div>
                        <div class="stat-label">Pending Reports</div>
                        <div class="stat-value"><?php echo $stats['status_counts']['Pending'] ?? 0; ?></div>
                    </div>
                    <span class="stat-icon bg-amber-50 text-amber-600"><i class="fas fa-clock"></i></span>
                </div>

                <div class="stat-card flex items-start justify-between">
                    <div>
                        <div class="stat-label">High / Critical</div>
                        <div class="stat-value"><?php echo ($stats['severity_counts']['High'] ?? 0) + ($stats['severity_counts']['Critical'] ?? 0); ?></div>
                    </div>
                    <span class="stat-icon bg-red-50 text-red-600"><i class="fas fa-exclamation-triangle"></i></span>
                </div>

                <div class="stat-card flex items-start justify-between">
                    <div>
                        <div class="stat-label">Resolved Reports</div>
                        <div class="stat-value"><?php echo $stats['status_counts']['Resolved'] ?? 0; ?></div>
                    </div>
                    <span class="stat-icon bg-emerald-50 text-emerald-600"><i class="fas fa-check-circle"></i></span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 space-y-4">
                    <div class="card">
                        <div class="card-header flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-chart-line text-slate-400"></i>
                                <span>Monthly Trends</span>
                            </div>
                            <span class="text-xs text-slate-500">Last 6 months</span>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendsChart" height="100"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header flex items-center gap-2">
                            <i class="fas fa-chart-pie text-slate-400"></i>
                            <span>Reports by Category</span>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="card">
                        <div class="card-header flex items-center gap-2">
                            <i class="fas fa-trophy text-slate-400"></i>
                            <span>Organization Rankings</span>
                        </div>
                        <div class="card-body">
                            <ul class="space-y-3">
                                <?php foreach ($org_counts as $index => $org): ?>
                                <li class="flex items-center gap-3">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white text-xs font-semibold">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate"><?php echo htmlspecialchars($org['org_name'] ?? ''); ?></p>
                                        <p class="text-xs text-slate-500"><?php echo $org['count']; ?> reports</p>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header flex items-center gap-2">
                            <i class="fas fa-clock text-slate-400"></i>
                            <span>Recent Reports</span>
                        </div>
                        <div class="card-body">
                            <ul class="space-y-3">
                                <?php foreach ($recent_reports as $report): ?>
                                <?php
                                    $icon_class = '';
                                    $icon_bg = 'bg-slate-100 text-slate-600';
                                    switch ($report['category']) {
                                        case 'Fire':       $icon_class = 'fas fa-fire'; $icon_bg = 'bg-red-50 text-red-600'; break;
                                        case 'Accident':   $icon_class = 'fas fa-car-crash'; $icon_bg = 'bg-amber-50 text-amber-600'; break;
                                        case 'Security':   $icon_class = 'fas fa-shield-alt'; $icon_bg = 'bg-blue-50 text-blue-600'; break;
                                        case 'Medical':    $icon_class = 'fas fa-user-md'; $icon_bg = 'bg-emerald-50 text-emerald-600'; break;
                                        case 'Emergency':  $icon_class = 'fas fa-exclamation-triangle'; $icon_bg = 'bg-red-50 text-red-600'; break;
                                        default:           $icon_class = 'fas fa-exclamation-circle'; break;
                                    }
                                ?>
                                <li class="flex items-start gap-3">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg <?php echo $icon_bg; ?> shrink-0">
                                        <i class="<?php echo $icon_class; ?>"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <a href="../reports/view.php?id=<?php echo $report['id']; ?>"
                                           class="text-sm font-medium text-slate-900 hover:text-slate-700 hover:underline truncate block">
                                            <?php echo htmlspecialchars($report['title'] ?? ''); ?>
                                        </a>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            <span class="badge <?php echo get_severity_badge_class($report['severity_level']); ?>">
                                                <?php echo $report['severity_level']; ?>
                                            </span>
                                            <span><?php echo htmlspecialchars($report['org_name'] ?? ''); ?></span>
                                            <span>·</span>
                                            <span><?php echo format_date($report['incident_date']); ?></span>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Monthly Trends Chart
const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
const monthlyTrendsData = <?php echo json_encode($monthly_trends); ?>;
if (monthlyTrendsData && monthlyTrendsData.length > 0) {
    new Chart(monthlyTrendsCtx, {
        type: 'line',
        data: {
            labels: monthlyTrendsData.map(item => item.month),
            datasets: [{
                label: 'Reports',
                data: monthlyTrendsData.map(item => item.count),
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
} else {
    monthlyTrendsCtx.font = '16px Arial';
    monthlyTrendsCtx.textAlign = 'center';
    monthlyTrendsCtx.fillText('No data available', monthlyTrendsCtx.canvas.width / 2, monthlyTrendsCtx.canvas.height /
        2);
}

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryData = <?php echo json_encode($category_counts); ?>;
if (categoryData && categoryData.length > 0) {
    new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: categoryData.map(item => item.category),
            datasets: [{
                data: categoryData.map(item => item.count),
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF',
                    '#FF9F40'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
} else {
    categoryCtx.font = '16px Arial';
    categoryCtx.textAlign = 'center';
    categoryCtx.fillText('No data available', categoryCtx.canvas.width / 2, categoryCtx.canvas.height / 2);
}
</script>

<?php include '../views/footer.php'; ?>