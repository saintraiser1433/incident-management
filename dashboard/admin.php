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
    <div class="row">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                </h1>

            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Reports
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['total_reports']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Pending Reports
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['status_counts']['Pending'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        High/Critical Severity
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo ($stats['severity_counts']['High'] ?? 0) + ($stats['severity_counts']['Critical'] ?? 0); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Resolved Reports
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['status_counts']['Resolved'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Charts -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>Monthly Trends
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendsChart" height="100"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Reports by Category
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Organization Rankings -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>Organization Rankings
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($org_counts as $index => $org): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <span class="badge bg-primary rounded-circle"
                                        style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($org['org_name']); ?></div>
                                    <div class="text-muted small"><?php echo $org['count']; ?> reports</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Reports -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-clock me-2"></i>Recent Reports
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($recent_reports as $report): ?>
                            <div class="d-flex align-items-start mb-3">
                                <div class="flex-shrink-0 me-2">
                                    <?php
                                        $icon_class = '';
                                        switch ($report['category']) {
                                            case 'Fire':
                                                $icon_class = 'fas fa-fire text-danger';
                                                break;
                                            case 'Accident':
                                                $icon_class = 'fas fa-car-crash text-warning';
                                                break;
                                            case 'Security':
                                                $icon_class = 'fas fa-shield-alt text-info';
                                                break;
                                            case 'Medical':
                                                $icon_class = 'fas fa-user-md text-success';
                                                break;
                                            case 'Emergency':
                                                $icon_class = 'fas fa-exclamation-triangle text-danger';
                                                break;
                                            default:
                                                $icon_class = 'fas fa-exclamation-circle text-secondary';
                                                break;
                                        }
                                        ?>
                                    <i class="<?php echo $icon_class; ?> fa-lg"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">
                                        <a href="../reports/view.php?id=<?php echo $report['id']; ?>"
                                            class="text-decoration-none">
                                            <?php echo htmlspecialchars($report['title']); ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small">
                                        <span
                                            class="badge <?php echo get_severity_badge_class($report['severity_level']); ?> me-1">
                                            <?php echo $report['severity_level']; ?>
                                        </span>
                                        <?php echo htmlspecialchars($report['org_name']); ?> •
                                        <?php echo format_date($report['incident_date']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
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