<?php
/**
 * My Reports (Responder View)
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Responder']);

$page_title = 'My Reports - ' . APP_NAME;
include '../views/header.php';

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for user's reports
$database = new Database();
$db = $database->getConnection();

$query = "SELECT ir.*, o.org_name, rq.status AS queue_status, rq.priority_number 
          FROM incident_reports ir 
          LEFT JOIN organizations o ON ir.organization_id = o.id 
          LEFT JOIN report_queue rq ON rq.report_id = ir.id 
          WHERE ir.reported_by = ?";
$params = [$_SESSION['user_id']];

if ($status_filter) {
    $query .= " AND ir.status = ?";
    $params[] = $status_filter;
}

if ($severity_filter) {
    $query .= " AND ir.severity_level = ?";
    $params[] = $severity_filter;
}

if ($category_filter) {
    $query .= " AND ir.category = ?";
    $params[] = $category_filter;
}

if ($date_from) {
    $query .= " AND ir.incident_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND ir.incident_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY ir.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-file-alt me-2"></i>My Reports
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="create.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i>New Report
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-filter me-2"></i>Filters
                    </h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select form-select-sm" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Resolved" <?php echo $status_filter == 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="Closed" <?php echo $status_filter == 'Closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="severity" class="form-label">Severity</label>
                            <select class="form-select form-select-sm" id="severity" name="severity">
                                <option value="">All Severity</option>
                                <option value="Low" <?php echo $severity_filter == 'Low' ? 'selected' : ''; ?>>Low</option>
                                <option value="Medium" <?php echo $severity_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="High" <?php echo $severity_filter == 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Critical" <?php echo $severity_filter == 'Critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select form-select-sm" id="category" name="category">
                                <option value="">All Categories</option>
                                <option value="Fire" <?php echo $category_filter == 'Fire' ? 'selected' : ''; ?>>Fire</option>
                                <option value="Accident" <?php echo $category_filter == 'Accident' ? 'selected' : ''; ?>>Accident</option>
                                <option value="Security" <?php echo $category_filter == 'Security' ? 'selected' : ''; ?>>Security</option>
                                <option value="Medical" <?php echo $category_filter == 'Medical' ? 'selected' : ''; ?>>Medical</option>
                                <option value="Emergency" <?php echo $category_filter == 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                                <option value="Other" <?php echo $category_filter == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" 
                                   value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" 
                                   value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <a href="<?php echo BASE_URL; ?>reports/my-reports.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times me-1"></i>Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Reports Table -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>My Reports (<?php echo count($reports); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($reports)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-plus fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No reports found</h5>
                            <p class="text-muted">You haven't submitted any reports yet, or no reports match your filters.</p>
                            <a href="create.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Create Your First Report
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Queue/Priority</th>
                                        <th>Organization</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td>#<?php echo $report['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($report['title']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($report['description'], 0, 50)) . '...'; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $report['category']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo get_severity_badge_class($report['severity_level']); ?>">
                                                    <?php echo $report['severity_level']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                                    <?php echo $report['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($report['queue_status'] === 'Waiting'): ?>
                                                    <span class="badge bg-secondary">Waiting</span>
                                                <?php elseif ($report['queue_status'] === 'Approved' && $report['priority_number']): ?>
                                                    <span class="badge bg-success">Priority #<?php echo (int)$report['priority_number']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['org_name']); ?></td>
                                            <td>
                                                <?php echo format_date($report['incident_date']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($report['incident_time'])); ?></small>
                                            </td>
                                            <td>
                                                <a href="view.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../views/footer.php'; ?>
