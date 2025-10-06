<?php
/**
 * Responder Dashboard
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Responder']);

$page_title = 'Responder Dashboard - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

// Get responder statistics
$stats = [];

// Total reports by this responder
$query = "SELECT COUNT(*) as total FROM incident_reports WHERE reported_by = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$stats['total_reports'] = $stmt->fetch()['total'];

// Reports by status for this responder
$query = "SELECT status, COUNT(*) as count FROM incident_reports WHERE reported_by = ? GROUP BY status";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$status_counts = $stmt->fetchAll();
$stats['status_counts'] = array_column($status_counts, 'count', 'status');

// Reports by severity for this responder
$query = "SELECT severity_level, COUNT(*) as count FROM incident_reports WHERE reported_by = ? GROUP BY severity_level";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$severity_counts = $stmt->fetchAll();
$stats['severity_counts'] = array_column($severity_counts, 'count', 'severity_level');

// Recent reports by this responder
$query = "SELECT ir.*, o.org_name 
          FROM incident_reports ir 
          LEFT JOIN organizations o ON ir.organization_id = o.id 
          WHERE ir.reported_by = ? 
          ORDER BY ir.incident_date DESC, ir.incident_time DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$recent_reports = $stmt->fetchAll();

// Monthly trends for this responder (last 6 months)
$query = "SELECT DATE_FORMAT(incident_date, '%Y-%m') as month, COUNT(*) as count 
          FROM incident_reports 
          WHERE reported_by = ? AND incident_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
          GROUP BY DATE_FORMAT(incident_date, '%Y-%m') 
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$monthly_trends = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-tachometer-alt me-2"></i>My Dashboard
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="../reports/create.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i>New Report
                        </a>
                        <a href="../reports/my-reports.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>My Reports
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Welcome Message -->
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                Welcome back, <?php echo $_SESSION['user_name']; ?>! You can create new incident reports and track the status of your submitted reports.
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        My Reports
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
                                        Pending
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
                                        Resolved
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
                                <i class="fas fa-chart-line me-2"></i>My Report Activity
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendsChart" height="100"></canvas>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Reports by Severity
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="severityChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Reports -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-clock me-2"></i>My Recent Reports
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_reports)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-file-plus fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">You haven't submitted any reports yet.</p>
                                    <a href="../reports/create.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i>Create Your First Report
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_reports as $report): ?>
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="flex-shrink-0">
                                            <span class="badge <?php echo get_severity_badge_class($report['severity_level']); ?>">
                                                <?php echo $report['severity_level']; ?>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-2">
                                            <div class="fw-bold">
                                                <a href="../reports/view.php?id=<?php echo $report['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($report['title']); ?>
                                                </a>
                                            </div>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($report['org_name']); ?> • 
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
            label: 'My Reports',
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

// Severity Chart
const severityCtx = document.getElementById('severityChart').getContext('2d');
const severityData = <?php echo json_encode($stats['severity_counts']); ?>;
new Chart(severityCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(severityData),
        datasets: [{
            data: Object.values(severityData),
            backgroundColor: [
                '#28a745', // Low - Green
                '#ffc107', // Medium - Yellow
                '#dc3545', // High - Red
                '#343a40'  // Critical - Dark
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
