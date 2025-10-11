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
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-tachometer-alt me-2"></i><?php echo $_SESSION['organization_name']; ?> Dashboard
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="../reports/organization.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-list me-1"></i>View All Reports
                        </a>
                    </div>
                </div>
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
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        In Progress
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['status_counts']['In Progress'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-spinner fa-2x text-gray-300"></i>
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

                <!-- Recent Reports -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-clock me-2"></i>Recent Reports
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_reports)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No reports assigned to your organization yet.</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($recent_reports as $report): ?>
                            <div class="d-flex align-items-start mb-3">
                                <div class="flex-shrink-0">
                                    <span
                                        class="badge <?php echo get_severity_badge_class($report['severity_level']); ?>">
                                        <?php echo $report['severity_level']; ?>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-2">
                                    <div class="fw-bold">
                                        <a href="../reports/view.php?id=<?php echo $report['id']; ?>"
                                            class="text-decoration-none">
                                            <?php echo htmlspecialchars($report['title']); ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small">
                                        <?php echo htmlspecialchars($report['reporter_name']); ?> •
                                        <?php echo format_date($report['incident_date']); ?>
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                            <?php echo $report['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
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