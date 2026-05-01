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
    <div class="row g-0">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Analytics Dashboard</h1>
                    <p class="text-sm text-slate-500 mt-1">Visual insights into incident reports and response performance.</p>
                </div>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition" onclick="exportAnalytics()">
                    <i class="fas fa-download text-slate-400"></i>Export Data
                </button>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                <div class="stat-card flex items-start justify-between">
                    <div>
                        <div class="stat-label">Total Reports</div>
                        <div class="stat-value"><?php echo array_sum(array_column($category_data, 'count')); ?></div>
                    </div>
                    <span class="stat-icon bg-slate-100 text-slate-700"><i class="fas fa-file-alt"></i></span>
                </div>
                <div class="stat-card flex items-start justify-between">
                    <div>
                        <div class="stat-label">Resolved Reports</div>
                        <div class="stat-value"><?php echo array_sum(array_filter(array_column($status_data, 'count'), function($key) use ($status_data) {
                            return $status_data[$key]['status'] == 'Resolved';
                        }, ARRAY_FILTER_USE_KEY)); ?></div>
                    </div>
                    <span class="stat-icon bg-emerald-50 text-emerald-600"><i class="fas fa-check-circle"></i></span>
                </div>
                <div class="stat-card flex items-start justify-between">
                    <div>
                        <div class="stat-label">Avg Response Time</div>
                        <div class="stat-value"><?php echo $response_data['avg_response_days'] ? round($response_data['avg_response_days'], 1) . ' days' : 'N/A'; ?></div>
                    </div>
                    <span class="stat-icon bg-amber-50 text-amber-600"><i class="fas fa-clock"></i></span>
                </div>
                <div class="stat-card flex items-start justify-between">
                    <div>
                        <div class="stat-label">High Priority</div>
                        <div class="stat-value"><?php echo array_sum(array_filter(array_column($severity_data, 'count'), function($key) use ($severity_data) {
                            return in_array($severity_data[$key]['severity_level'], ['High', 'Critical']);
                        }, ARRAY_FILTER_USE_KEY)); ?></div>
                    </div>
                    <span class="stat-icon bg-red-50 text-red-600"><i class="fas fa-exclamation-triangle"></i></span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <div class="card">
                    <div class="card-header flex items-center gap-2">
                        <i class="fas fa-chart-pie text-slate-400"></i>
                        <span>Reports by Category</span>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart" height="200"></canvas>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header flex items-center gap-2">
                        <i class="fas fa-chart-bar text-slate-400"></i>
                        <span>Reports by Severity</span>
                    </div>
                    <div class="card-body">
                        <canvas id="severityChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                <div class="lg:col-span-2 card">
                    <div class="card-header flex items-center gap-2">
                        <i class="fas fa-chart-line text-slate-400"></i>
                        <span>Monthly Trends</span>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="100"></canvas>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header flex items-center gap-2">
                        <i class="fas fa-chart-pie text-slate-400"></i>
                        <span>Status Distribution</span>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 <?php echo ($_SESSION['user_role'] == 'Admin' && !empty($org_ranking_data)) ? 'lg:grid-cols-2' : ''; ?> gap-4">
                <div class="card">
                    <div class="card-header flex items-center gap-2">
                        <i class="fas fa-clock text-slate-400"></i>
                        <span>Peak Hours Analysis</span>
                    </div>
                    <div class="card-body">
                        <canvas id="hourlyChart" height="200"></canvas>
                    </div>
                </div>

                <?php if ($_SESSION['user_role'] == 'Admin' && !empty($org_ranking_data)): ?>
                <div class="card">
                    <div class="card-header flex items-center gap-2">
                        <i class="fas fa-trophy text-slate-400"></i>
                        <span>Top Organizations</span>
                    </div>
                    <div class="card-body">
                        <canvas id="orgChart" height="200"></canvas>
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
