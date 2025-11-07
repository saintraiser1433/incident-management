<?php
/**
 * Organization Reports View
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Organization Account']);

$page_title = 'Organization Reports - ' . APP_NAME;
include '../views/header.php';

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query for organization's reports
$database = new Database();
$db = $database->getConnection();

$query = "SELECT ir.*, ir.reported_by as reporter_name, rq.priority_number, rq.assigned_to,
          om.name as assigned_member_name
          FROM incident_reports ir 
          LEFT JOIN report_queue rq ON rq.report_id = ir.id 
          LEFT JOIN organization_members om ON rq.assigned_to = om.id
          WHERE ir.organization_id = ?";
$params = [$_SESSION['organization_id']];

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

// Fetch waiting queue for this organization
$queueQuery = "SELECT rq.id as queue_id, rq.created_at as queued_at, ir.*, ir.reported_by as reporter_name 
               FROM report_queue rq 
               JOIN incident_reports ir ON rq.report_id = ir.id 
               WHERE rq.organization_id = ? AND rq.status = 'Waiting' 
               ORDER BY rq.created_at ASC";
$queueStmt = $db->prepare($queueQuery);
$queueStmt->execute([$_SESSION['organization_id']]);
$waiting_queue = $queueStmt->fetchAll();

// Fetch organization members for assignment dropdown
$membersQuery = "SELECT id, name FROM organization_members WHERE organization_id = ? ORDER BY name";
$membersStmt = $db->prepare($membersQuery);
$membersStmt->execute([$_SESSION['organization_id']]);
$members = $membersStmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-file-alt me-2"></i><?php echo $_SESSION['organization_name']; ?> Reports
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportReports()">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
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
                            <a href="<?php echo BASE_URL; ?>reports/organization.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times me-1"></i>Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Waiting Queue -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-clipboard-list me-2"></i>Waiting Queue
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($waiting_queue)): ?>
                        <div class="text-muted">No items in the queue.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Queued At</th>
                                        <th>Report</th>
                                        <th>Category</th>
                                        <th>Severity</th>
                                        <th>Reporter</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($waiting_queue as $q): ?>
                                        <tr>
                                            <td><small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($q['queued_at'])); ?></small></td>
                                            <td>
                                                <strong>#<?php echo $q['id']; ?> - <?php echo htmlspecialchars($q['title']); ?></strong>
                                                <div class="text-muted small"><?php echo htmlspecialchars(substr($q['description'], 0, 60)) . '...'; ?></div>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $q['category']; ?></span></td>
                                            <td><span class="badge <?php echo get_severity_badge_class($q['severity_level']); ?>"><?php echo $q['severity_level']; ?></span></td>
                                            <td><?php echo htmlspecialchars($q['reporter_name']); ?></td>
                                            <td>
                                                <form method="POST" action="approve_queue.php" class="d-inline">
                                                    <input type="hidden" name="queue_id" value="<?php echo $q['queue_id']; ?>">
                                                    <?php if (!empty($members)): ?>
                                                        <div class="mb-2">
                                                            <select name="assigned_to" class="form-select form-select-sm">
                                                                <option value="">Assign to member (optional)</option>
                                                                <?php foreach ($members as $member): ?>
                                                                    <option value="<?php echo $member['id']; ?>">
                                                                        <?php echo htmlspecialchars($member['name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    <?php endif; ?>
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check me-1"></i>Approve & Assign Priority
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reports Table -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Reports (<?php echo count($reports); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($reports)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No reports found</h5>
                            <p class="text-muted">No reports have been assigned to your organization yet.</p>
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
                                        <th>Priority</th>
                                        <th>Assigned To</th>
                                        <th>Reporter</th>
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
                                                <?php if (!empty($report['priority_number'])): ?>
                                                    <span class="badge bg-success">#<?php echo (int)$report['priority_number']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($report['assigned_member_name'])): ?>
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($report['assigned_member_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                                            <td>
                                                <?php echo format_date($report['incident_date']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($report['incident_time'])); ?></small>
                                            </td>
                                            <td>
                                                <a href="view.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
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

<script>
function exportReports() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    
    // Create download link
    const link = document.createElement('a');
    link.href = 'export.php?' + params.toString();
    link.download = 'organization_reports_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../views/footer.php'; ?>
