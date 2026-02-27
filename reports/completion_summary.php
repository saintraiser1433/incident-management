<?php
/**
 * Monthly / Yearly Completion Summary
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Admin', 'Organization Account']);

$database = new Database();
$db = $database->getConnection();

$is_admin = $_SESSION['user_role'] === 'Admin';
$selected_year = isset($_GET['year']) && $_GET['year'] !== '' ? (int) $_GET['year'] : (int) date('Y');
$selected_month = isset($_GET['month']) && $_GET['month'] !== '' ? (int) $_GET['month'] : 0; // 0 = all months

// For organization accounts, lock to their org
$selected_org_id = null;
if (!$is_admin) {
    $selected_org_id = $_SESSION['organization_id'] ?? null;
} elseif (isset($_GET['organization_id']) && $_GET['organization_id'] !== '') {
    $selected_org_id = (int) $_GET['organization_id'];
}

// Years available in data
$yearsStmt = $db->query("SELECT DISTINCT YEAR(created_at) AS year FROM incident_reports ORDER BY year DESC");
$years = $yearsStmt->fetchAll();

// Organizations for admin filter
$organizations = [];
if ($is_admin) {
    $orgStmt = $db->query("SELECT id, org_name FROM organizations ORDER BY org_name");
    $organizations = $orgStmt->fetchAll();
}

// Build base WHERE for incident_reports (unaliased)
$where = ["YEAR(incident_reports.created_at) = ?"];
$params = [$selected_year];

if ($selected_month > 0 && $selected_month <= 12) {
    $where[] = "MONTH(incident_reports.created_at) = ?";
    $params[] = $selected_month;
}

if ($selected_org_id) {
    $where[] = "incident_reports.organization_id = ?";
    $params[] = $selected_org_id;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Overall counts
$summaryQuery = "
    SELECT
        COUNT(*) AS total_reports,
        SUM(status IN ('Resolved','Closed')) AS completed_reports
    FROM incident_reports
    {$whereSql}
";
$summaryStmt = $db->prepare($summaryQuery);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: ['total_reports' => 0, 'completed_reports' => 0];

$completion_rate = 0;
if ($summary['total_reports'] > 0) {
    $completion_rate = round(($summary['completed_reports'] / $summary['total_reports']) * 100, 1);
}

// Breakdown by category
$categoryQuery = "
    SELECT
        category,
        COUNT(*) AS total,
        SUM(status IN ('Resolved','Closed')) AS completed
    FROM incident_reports
    {$whereSql}
    GROUP BY category
    ORDER BY category
";
$categoryStmt = $db->prepare($categoryQuery);
$categoryStmt->execute($params);
$categoryBreakdown = $categoryStmt->fetchAll();

// Breakdown by severity
$severityQuery = "
    SELECT
        severity_level,
        COUNT(*) AS total,
        SUM(status IN ('Resolved','Closed')) AS completed
    FROM incident_reports
    {$whereSql}
    GROUP BY severity_level
    ORDER BY FIELD(severity_level, 'Low','Medium','High','Critical')
";
$severityStmt = $db->prepare($severityQuery);
$severityStmt->execute($params);
$severityBreakdown = $severityStmt->fetchAll();

// Breakdown by organization (admin only)
$orgBreakdown = [];
if ($is_admin) {
    // Build a separate WHERE for the aliased incident_reports (ir)
    $orgWhere = ["YEAR(ir.created_at) = ?"];
    $orgParams = [$selected_year];

    if ($selected_month > 0 && $selected_month <= 12) {
        $orgWhere[] = "MONTH(ir.created_at) = ?";
        $orgParams[] = $selected_month;
    }

    if ($selected_org_id) {
        $orgWhere[] = "ir.organization_id = ?";
        $orgParams[] = $selected_org_id;
    }

    $orgWhereSql = 'WHERE ' . implode(' AND ', $orgWhere);

    $orgQuery = "
        SELECT
            o.org_name,
            COUNT(ir.id) AS total,
            SUM(ir.status IN ('Resolved','Closed')) AS completed
        FROM incident_reports ir
        LEFT JOIN organizations o ON ir.organization_id = o.id
        {$orgWhereSql}
        GROUP BY ir.organization_id, o.org_name
        ORDER BY total DESC
    ";
    $orgStmt = $db->prepare($orgQuery);
    $orgStmt->execute($orgParams);
    $orgBreakdown = $orgStmt->fetchAll();
}

// Monthly trend within year (for chart)
$trendWhere = ["YEAR(incident_reports.created_at) = ?"];
$trendParams = [$selected_year];

if ($selected_org_id) {
    $trendWhere[] = "incident_reports.organization_id = ?";
    $trendParams[] = $selected_org_id;
}

$trendWhereSql = 'WHERE ' . implode(' AND ', $trendWhere);

$trendQuery = "
    SELECT
        MONTH(incident_reports.created_at) AS month,
        COUNT(*) AS total,
        SUM(status IN ('Resolved','Closed')) AS completed
    FROM incident_reports
    {$trendWhereSql}
    GROUP BY MONTH(incident_reports.created_at)
    ORDER BY month
";
$trendStmt = $db->prepare($trendQuery);
$trendStmt->execute($trendParams);
$trendData = $trendStmt->fetchAll();

$page_title = 'Completion Summary - ' . APP_NAME;
include '../views/header.php';

function month_name($m) {
    return date('F', mktime(0, 0, 0, $m, 1));
}
?>

<div class="container-fluid completion-summary-page">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-chart-pie me-2"></i>Completion Summary
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0 no-print">
                    <div class="btn-group me-2">
                        <button onclick="window.print();" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-print me-1"></i>Print / Export
                        </button>
                    </div>
                </div>
            </div>

            <div class="card mb-3 no-print">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2"></i>Filters
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="year" class="form-label">Year</label>
                            <select name="year" id="year" class="form-select">
                                <?php foreach ($years as $row): ?>
                                    <option
                                        value="<?php echo (int) $row['year']; ?>"
                                        <?php echo ((int) $row['year'] === $selected_year) ? 'selected' : ''; ?>
                                    >
                                        <?php echo (int) $row['year']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="month" class="form-label">Month</label>
                            <select name="month" id="month" class="form-select">
                                <option value="0"<?php echo $selected_month === 0 ? ' selected' : ''; ?>>Whole Year</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option
                                        value="<?php echo $m; ?>"
                                        <?php echo $selected_month === $m ? 'selected' : ''; ?>
                                    >
                                        <?php echo month_name($m); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <?php if ($is_admin): ?>
                            <div class="col-md-4">
                                <label for="organization_id" class="form-label">Organization</label>
                                <select name="organization_id" id="organization_id" class="form-select">
                                    <option value="">All Organizations</option>
                                    <?php foreach ($organizations as $org): ?>
                                        <option
                                            value="<?php echo $org['id']; ?>"
                                            <?php echo ($selected_org_id && (int) $selected_org_id === (int) $org['id']) ? 'selected' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars($org['org_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="col-md-4">
                                <label class="form-label">Organization</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($_SESSION['organization_name'] ?? ''); ?>"
                                    disabled
                                >
                            </div>
                        <?php endif; ?>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>Apply
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <strong>Period:</strong><br>
                                <?php
                                if ($selected_month > 0) {
                                    echo month_name($selected_month) . ' ' . $selected_year;
                                } else {
                                    echo 'Whole Year ' . $selected_year;
                                }
                                ?>
                            </p>
                            <?php if ($selected_org_id && $is_admin): ?>
                                <p class="mb-2">
                                    <strong>Organization:</strong><br>
                                    <?php
                                    $orgName = '';
                                    foreach ($organizations as $org) {
                                        if ((int) $org['id'] === (int) $selected_org_id) {
                                            $orgName = $org['org_name'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($orgName);
                                    ?>
                                </p>
                            <?php elseif (!$is_admin): ?>
                                <p class="mb-2">
                                    <strong>Organization:</strong><br>
                                    <?php echo htmlspecialchars($_SESSION['organization_name'] ?? ''); ?>
                                </p>
                            <?php endif; ?>
                            <hr>
                            <p class="mb-1">
                                <strong>Total Incidents:</strong>
                                <span class="float-end"><?php echo (int) $summary['total_reports']; ?></span>
                            </p>
                            <p class="mb-1">
                                <strong>Resolved / Closed:</strong>
                                <span class="float-end"><?php echo (int) $summary['completed_reports']; ?></span>
                            </p>
                            <p class="mb-0">
                                <strong>Completion Rate:</strong>
                                <span class="float-end">
                                    <?php echo $completion_rate; ?>%
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-thermometer-half me-2"></i>By Severity
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($severityBreakdown)): ?>
                                <p class="text-muted mb-0">No data for selected period.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>Severity</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Completed</th>
                                                <th class="text-end">Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($severityBreakdown as $row): 
                                                $rate = $row['total'] > 0
                                                    ? round(($row['completed'] / $row['total']) * 100, 1)
                                                    : 0;
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['severity_level']); ?></td>
                                                    <td class="text-end"><?php echo (int) $row['total']; ?></td>
                                                    <td class="text-end"><?php echo (int) $row['completed']; ?></td>
                                                    <td class="text-end"><?php echo $rate; ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-column me-2"></i>Monthly Trend (Completed vs Total)
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="completionTrendChart" height="120"></canvas>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-layer-group me-2"></i>By Category
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($categoryBreakdown)): ?>
                                <p class="text-muted mb-0">No data for selected period.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Completed</th>
                                                <th class="text-end">Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categoryBreakdown as $row): 
                                                $rate = $row['total'] > 0
                                                    ? round(($row['completed'] / $row['total']) * 100, 1)
                                                    : 0;
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                                    <td class="text-end"><?php echo (int) $row['total']; ?></td>
                                                    <td class="text-end"><?php echo (int) $row['completed']; ?></td>
                                                    <td class="text-end"><?php echo $rate; ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($is_admin): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-building me-2"></i>By Organization
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($orgBreakdown)): ?>
                                    <p class="text-muted mb-0">No data for selected period.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Organization</th>
                                                    <th class="text-end">Total</th>
                                                    <th class="text-end">Completed</th>
                                                    <th class="text-end">Rate</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($orgBreakdown as $row): 
                                                    $rate = $row['total'] > 0
                                                        ? round(($row['completed'] / $row['total']) * 100, 1)
                                                        : 0;
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['org_name']); ?></td>
                                                        <td class="text-end"><?php echo (int) $row['total']; ?></td>
                                                        <td class="text-end"><?php echo (int) $row['completed']; ?></td>
                                                        <td class="text-end"><?php echo $rate; ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
@media print {
    body {
        background-color: #ffffff !important;
    }
    .navbar,
    .sidebar,
    .no-print,
    .btn,
    .btn-group {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 20px !important;
    }
    .completion-summary-page .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('completionTrendChart').getContext('2d');
    const trendData = <?php echo json_encode($trendData); ?>;

    const labels = trendData.map(item => {
        const m = parseInt(item.month, 10);
        const date = new Date(2000, m - 1, 1);
        return date.toLocaleString('default', { month: 'short' });
    });

    const total = trendData.map(item => parseInt(item.total, 10));
    const completed = trendData.map(item => parseInt(item.completed, 10));

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total',
                    data: total,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1
                },
                {
                    label: 'Completed',
                    data: completed,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgb(75, 192, 192)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<?php include '../views/footer.php'; ?>

