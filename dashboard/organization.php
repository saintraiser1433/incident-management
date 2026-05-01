<?php
/**
 * Organization Dashboard
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Organization Account']);

$page_title = 'Organization Dashboard - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

// Get organization statistics
$stats = [];

// Total reports for this organization
$query = "SELECT COUNT(*) as total FROM incident_reports WHERE organization_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['organization_id']]);
$stats['total_reports'] = $stmt->fetch()['total'];

// Reports by status for this organization
$query = "SELECT status, COUNT(*) as count FROM incident_reports WHERE organization_id = ? GROUP BY status";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['organization_id']]);
$status_counts = $stmt->fetchAll();
$stats['status_counts'] = array_column($status_counts, 'count', 'status');

// Reports by severity for this organization
$query = "SELECT severity_level, COUNT(*) as count FROM incident_reports WHERE organization_id = ? GROUP BY severity_level";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['organization_id']]);
$severity_counts = $stmt->fetchAll();
$stats['severity_counts'] = array_column($severity_counts, 'count', 'severity_level');

// Reports by category for this organization
$query = "SELECT category, COUNT(*) as count FROM incident_reports WHERE organization_id = ? GROUP BY category";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['organization_id']]);
$category_counts = $stmt->fetchAll();
$stats['category_counts'] = array_column($category_counts, 'count', 'category');

// Recent reports for this organization
$query = "SELECT ir.*, ir.reported_by as reporter_name 
          FROM incident_reports ir 
          WHERE ir.organization_id = ? 
          ORDER BY ir.incident_date DESC, ir.incident_time DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['organization_id']]);
$recent_reports = $stmt->fetchAll();

// Monthly trends for this organization (last 6 months)
$query = "SELECT DATE_FORMAT(incident_date, '%Y-%m') as month, COUNT(*) as count 
          FROM incident_reports 
          WHERE organization_id = ? AND incident_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
          GROUP BY DATE_FORMAT(incident_date, '%Y-%m') 
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['organization_id']]);
$monthly_trends = $stmt->fetchAll();

$logoStmt = $db->prepare('SELECT logo_path FROM organizations WHERE id = ?');
$logoStmt->execute([$_SESSION['organization_id']]);
$org_logo_path = $logoStmt->fetchColumn();
$org_logo_path = $org_logo_path ?: null;
?>

<div class="container-fluid">
    <div class="row g-0">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
                <div class="flex items-start gap-4">
                    <?php if (!empty($org_logo_path)): ?>
                        <img src="<?php echo htmlspecialchars(BASE_URL . $org_logo_path); ?>" alt="" class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 rounded-xl border border-slate-200 bg-white object-contain p-1">
                    <?php endif; ?>
                    <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                        <?php echo htmlspecialchars($_SESSION['organization_name']); ?>
                    </h1>
                    <p class="text-sm text-slate-500 mt-1">Organization-wide incident overview.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="../reports/organization.php" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                        <i class="fas fa-list"></i>View All Reports
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
                        <div class="stat-label">In Progress</div>
                        <div class="stat-value"><?php echo $stats['status_counts']['In Progress'] ?? 0; ?></div>
                    </div>
                    <span class="stat-icon bg-blue-50 text-blue-600"><i class="fas fa-spinner"></i></span>
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
                            <i class="fas fa-clock text-slate-400"></i>
                            <span>Recent Reports</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_reports)): ?>
                                <div class="text-center py-8">
                                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                                        <i class="fas fa-inbox text-xl"></i>
                                    </div>
                                    <p class="text-sm text-slate-500">No reports assigned to your organization yet.</p>
                                </div>
                            <?php else: ?>
                            <ul class="space-y-3">
                                <?php foreach ($recent_reports as $report): ?>
                                <li class="flex items-start gap-3">
                                    <span class="badge <?php echo get_severity_badge_class($report['severity_level']); ?> shrink-0">
                                        <?php echo $report['severity_level']; ?>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <a href="../reports/view.php?id=<?php echo $report['id']; ?>"
                                           class="text-sm font-medium text-slate-900 hover:underline truncate block">
                                            <?php echo htmlspecialchars($report['title']); ?>
                                        </a>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            <?php echo htmlspecialchars($report['reporter_name']); ?> ·
                                            <?php echo format_date($report['incident_date']); ?>
                                        </p>
                                        <div class="mt-1.5">
                                            <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                                <?php echo $report['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
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

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryData = <?php echo json_encode($stats['category_counts']); ?>;
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(categoryData),
        datasets: [{
            data: Object.values(categoryData),
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
</script>

<?php include '../views/footer.php'; ?>