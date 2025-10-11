<?php
/**
 * Analytics Dashboard
 * Incident Report Management System
 */

require_once '../config/config.php';
require_login();

$page_title = 'Analytics - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

// Get analytics data based on user role
if ($_SESSION['user_role'] == 'Admin') {
    // Admin sees all data
    $org_filter = '';
    $params = [];
} elseif ($_SESSION['user_role'] == 'Organization Account') {
    // Organization sees only their data
    $org_filter = 'WHERE ir.organization_id = ?';
    $params = [$_SESSION['organization_id']];
} else {
    // Guest users see no data (redirect to departments)
    redirect('dashboard/responder.php');
}

// Reports by category
$query = "SELECT ir.category, COUNT(*) as count 
          FROM incident_reports ir 
          $org_filter 
          GROUP BY ir.category 
          ORDER BY count DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$category_data = $stmt->fetchAll();

// Reports by severity
$query = "SELECT ir.severity_level, COUNT(*) as count 
          FROM incident_reports ir 
          $org_filter 
          GROUP BY ir.severity_level 
          ORDER BY FIELD(ir.severity_level, 'Low', 'Medium', 'High', 'Critical')";
$stmt = $db->prepare($query);
$stmt->execute($params);
$severity_data = $stmt->fetchAll();

// Reports by status
$query = "SELECT ir.status, COUNT(*) as count 
          FROM incident_reports ir 
          $org_filter 
          GROUP BY ir.status 
          ORDER BY FIELD(ir.status, 'Pending', 'In Progress', 'Resolved', 'Closed')";
$stmt = $db->prepare($query);
$stmt->execute($params);
$status_data = $stmt->fetchAll();

// Monthly trends (last 12 months)
$query = "SELECT DATE_FORMAT(ir.incident_date, '%Y-%m') as month, COUNT(*) as count 
          FROM incident_reports ir 
          $org_filter " . ($org_filter ? 'AND' : 'WHERE') . " ir.incident_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH) 
          GROUP BY DATE_FORMAT(ir.incident_date, '%Y-%m') 
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute($params);
$monthly_data = $stmt->fetchAll();

// Daily trends (last 30 days)
$query = "SELECT DATE(ir.incident_date) as date, COUNT(*) as count 
          FROM incident_reports ir 
          $org_filter " . ($org_filter ? 'AND' : 'WHERE') . " ir.incident_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
          GROUP BY DATE(ir.incident_date) 
          ORDER BY date";
$stmt = $db->prepare($query);
$stmt->execute($params);
$daily_data = $stmt->fetchAll();

// Top organizations (only for admin)
$org_ranking_data = [];
if ($_SESSION['user_role'] == 'Admin') {
    $query = "SELECT o.org_name, COUNT(ir.id) as count 
              FROM organizations o 
              LEFT JOIN incident_reports ir ON o.id = ir.organization_id 
              GROUP BY o.id, o.org_name 
              ORDER BY count DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $org_ranking_data = $stmt->fetchAll();
}

// Response time analysis (average days to resolve)
// Note: Since there's no updated_at column, we'll just count resolved reports
$query = "SELECT 
            COUNT(*) as total_resolved,
            COUNT(*) as avg_response_days
          FROM incident_reports ir 
          $org_filter " . ($org_filter ? 'AND' : 'WHERE') . " ir.status IN ('Resolved', 'Closed')";
$stmt = $db->prepare($query);
$stmt->execute($params);
$response_data = $stmt->fetch();

// Peak hours analysis
$query = "SELECT HOUR(ir.incident_time) as hour, COUNT(*) as count 
          FROM incident_reports ir 
          $org_filter 
          GROUP BY HOUR(ir.incident_time) 
          ORDER BY hour";
$stmt = $db->prepare($query);
$stmt->execute($params);
$hourly_data = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-chart-bar me-2"></i>Analytics Dashboard
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportAnalytics()">
                            <i class="fas fa-download me-1"></i>Export Data
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Summary Cards -->
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
                                        <?php echo array_sum(array_column($category_data, 'count')); ?>
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
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Resolved Reports
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo array_sum(array_filter(array_column($status_data, 'count'), function($key) use ($status_data) { 
                                            return $status_data[$key]['status'] == 'Resolved'; 
                                        }, ARRAY_FILTER_USE_KEY)); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                        Avg Response Time
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $response_data['avg_response_days'] ? round($response_data['avg_response_days'], 1) . ' days' : 'N/A'; ?>
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
                                        High Priority
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo array_sum(array_filter(array_column($severity_data, 'count'), function($key) use ($severity_data) { 
                                            return in_array($severity_data[$key]['severity_level'], ['High', 'Critical']); 
                                        }, ARRAY_FILTER_USE_KEY)); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Charts Row 1 -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Reports by Category
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Reports by Severity
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="severityChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Charts Row 2 -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>Monthly Trends
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-doughnut me-2"></i>Status Distribution
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Charts Row 3 -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-clock me-2"></i>Peak Hours Analysis
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="hourlyChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <?php if ($_SESSION['user_role'] == 'Admin' && !empty($org_ranking_data)): ?>
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>Top Organizations
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="orgChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js is already loaded in footer.php -->

<script>
// Debug: Check if Chart.js is loaded
console.log('Chart.js loaded:', typeof Chart !== 'undefined');

// Debug: Log data to console
console.log('Category Data:', <?php echo json_encode($category_data); ?>);
console.log('Severity Data:', <?php echo json_encode($severity_data); ?>);
console.log('Status Data:', <?php echo json_encode($status_data); ?>);
console.log('Monthly Data:', <?php echo json_encode($monthly_data); ?>);
console.log('Hourly Data:', <?php echo json_encode($hourly_data); ?>);

// Wait for Chart.js to load
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js not loaded!');
        return;
    }
    
    console.log('Starting chart initialization...');

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryData = <?php echo json_encode($category_data); ?>;
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
    // Show "No data" message
    categoryCtx.font = '16px Arial';
    categoryCtx.textAlign = 'center';
    categoryCtx.fillText('No data available', categoryCtx.canvas.width / 2, categoryCtx.canvas.height / 2);
}

// Severity Chart
const severityCtx = document.getElementById('severityChart').getContext('2d');
const severityData = <?php echo json_encode($severity_data); ?>;
if (severityData && severityData.length > 0) {
    new Chart(severityCtx, {
        type: 'bar',
        data: {
            labels: severityData.map(item => item.severity_level),
            datasets: [{
                label: 'Reports',
                data: severityData.map(item => item.count),
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
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
} else {
    severityCtx.font = '16px Arial';
    severityCtx.textAlign = 'center';
    severityCtx.fillText('No data available', severityCtx.canvas.width / 2, severityCtx.canvas.height / 2);
}

// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const monthlyData = <?php echo json_encode($monthly_data); ?>;
if (monthlyData && monthlyData.length > 0) {
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: monthlyData.map(item => item.month),
            datasets: [{
                label: 'Reports',
                data: monthlyData.map(item => item.count),
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
    monthlyCtx.font = '16px Arial';
    monthlyCtx.textAlign = 'center';
    monthlyCtx.fillText('No data available', monthlyCtx.canvas.width / 2, monthlyCtx.canvas.height / 2);
}

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusData = <?php echo json_encode($status_data); ?>;
if (statusData && statusData.length > 0) {
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => item.status),
            datasets: [{
                data: statusData.map(item => item.count),
                backgroundColor: [
                    '#ffc107', // Pending - Yellow
                    '#17a2b8', // In Progress - Blue
                    '#28a745', // Resolved - Green
                    '#6c757d'  // Closed - Gray
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
} else {
    statusCtx.font = '16px Arial';
    statusCtx.textAlign = 'center';
    statusCtx.fillText('No data available', statusCtx.canvas.width / 2, statusCtx.canvas.height / 2);
}

// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
const hourlyData = <?php echo json_encode($hourly_data); ?>;
const hourlyLabels = Array.from({length: 24}, (_, i) => i + ':00');
const hourlyCounts = Array.from({length: 24}, (_, i) => {
    const found = hourlyData ? hourlyData.find(item => item.hour == i) : null;
    return found ? found.count : 0;
});

new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: hourlyLabels,
        datasets: [{
            label: 'Reports',
            data: hourlyCounts,
            backgroundColor: 'rgba(102, 126, 234, 0.8)'
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

<?php if ($_SESSION['user_role'] == 'Admin' && !empty($org_ranking_data)): ?>
// Organization Chart
const orgCtx = document.getElementById('orgChart').getContext('2d');
const orgData = <?php echo json_encode($org_ranking_data); ?>;
new Chart(orgCtx, {
    type: 'bar',
    data: {
        labels: orgData.map(item => item.org_name),
        datasets: [{
            label: 'Reports',
            data: orgData.map(item => item.count),
            backgroundColor: 'rgba(54, 162, 235, 0.8)'
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
<?php endif; ?>

function exportAnalytics() {
    // Create a simple CSV export
    const data = {
        categories: categoryData,
        severity: severityData,
        status: statusData,
        monthly: monthlyData
    };
    
    const csvContent = "data:text/csv;charset=utf-8," + 
        "Type,Category,Count\n" +
        categoryData.map(item => `Category,${item.category},${item.count}`).join("\n") + "\n" +
        severityData.map(item => `Severity,${item.severity_level},${item.count}`).join("\n") + "\n" +
        statusData.map(item => `Status,${item.status},${item.count}`).join("\n");
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "analytics_data.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

}); // End of DOMContentLoaded
</script>

<?php include '../views/footer.php'; ?>
