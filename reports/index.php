<?php
/**
 * All Incident Reports (Admin View)
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Admin']);

$page_title = 'All Incident Reports - ' . APP_NAME;
include '../views/header.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$category_filter = $_GET['category'] ?? '';
$organization_filter = $_GET['organization'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$database = new Database();
$db = $database->getConnection();

$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(ir.title LIKE ? OR ir.description LIKE ? OR ir.reported_by LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "ir.status = ?";
    $params[] = $status_filter;
}

if ($severity_filter) {
    $where_conditions[] = "ir.severity_level = ?";
    $params[] = $severity_filter;
}

if ($category_filter) {
    $where_conditions[] = "ir.category = ?";
    $params[] = $category_filter;
}

if ($organization_filter) {
    $where_conditions[] = "ir.organization_id = ?";
    $params[] = $organization_filter;
}

if ($date_from) {
    $where_conditions[] = "ir.incident_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "ir.incident_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM incident_reports ir 
                LEFT JOIN organizations o ON ir.organization_id = o.id 
                WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get reports with pagination
$query = "SELECT ir.*, ir.reported_by as reporter_name, o.org_name, rq.priority_number 
          FROM incident_reports ir 
          LEFT JOIN organizations o ON ir.organization_id = o.id 
          LEFT JOIN report_queue rq ON rq.report_id = ir.id 
          WHERE $where_clause
          ORDER BY ir.created_at DESC
          LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get organizations for filter
$query = "SELECT * FROM organizations ORDER BY org_name";
$stmt = $db->prepare($query);
$stmt->execute();
$organizations = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row g-0">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">All Incident Reports</h1>
                    <p class="text-sm text-slate-500 mt-1">Browse, filter and export reports across organizations.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition" onclick="exportReports()">
                        <i class="fas fa-download text-slate-400"></i>Export
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header flex items-center gap-2">
                    <i class="fas fa-filter text-slate-400"></i>
                    <span>Filters</span>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control form-control-sm" id="search" name="search" 
                                   placeholder="Search by title, description, or reporter..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                        </div>
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
                            <label for="organization" class="form-label">Organization</label>
                            <select class="form-select form-select-sm" id="organization" name="organization">
                                <option value="">All Organizations</option>
                                <?php foreach ($organizations as $org): ?>
                                    <option value="<?php echo $org['id']; ?>" 
                                            <?php echo $organization_filter == $org['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($org['org_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
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
                        <div class="col-12 flex items-center gap-2 pt-2">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                                <i class="fas fa-search"></i>Apply Filters
                            </button>
                            <a href="<?php echo BASE_URL; ?>reports/index.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                                <i class="fas fa-times text-slate-400"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reports Table -->
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-list text-slate-400"></i>
                        <span>Reports</span>
                        <span class="badge bg-secondary"><?php echo $total_records; ?></span>
                    </div>
                    <small class="text-slate-500">
                        Showing <?php echo count($reports); ?> of <?php echo $total_records; ?>
                    </small>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($reports)): ?>
                        <div class="text-center py-12">
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                                <i class="fas fa-inbox text-xl"></i>
                            </div>
                            <h5 class="text-base font-medium text-slate-900">No reports found</h5>
                            <p class="text-sm text-slate-500 mt-1">Try adjusting your filters or create a new report.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Organization</th>
                                        <th>Reporter</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td class="font-mono text-xs text-slate-500">#<?php echo $report['id']; ?></td>
                                            <td>
                                                <div class="font-medium text-slate-900"><?php echo htmlspecialchars($report['title'] ?? ''); ?></div>
                                                <div class="text-xs text-slate-500 mt-0.5 truncate max-w-xs"><?php echo htmlspecialchars(substr((string) ($report['description'] ?? ''), 0, 60)) . '...'; ?></div>
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
                                                    <span class="text-slate-400 text-xs">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-sm text-slate-700"><?php echo htmlspecialchars($report['org_name'] ?? ''); ?></td>
                                            <td class="text-sm text-slate-700"><?php echo htmlspecialchars($report['reporter_name'] ?? ''); ?></td>
                                            <td>
                                                <div class="text-sm text-slate-700"><?php echo format_date($report['incident_date']); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo !empty($report['incident_time']) ? date('g:i A', strtotime($report['incident_time'])) : ''; ?></div>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <a href="view.php?id=<?php echo $report['id']; ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-100 transition" title="View">
                                                        <i class="fas fa-eye text-xs"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $report['id']; ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-600 hover:bg-slate-100 transition" title="Edit">
                                                        <i class="fas fa-edit text-xs"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Reports pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <!-- Previous Page -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Page Numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                            <?php echo $total_pages; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Next Page -->
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <div class="text-center text-muted">
                            <small>
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                                (<?php echo $total_records; ?> total reports)
                            </small>
                        </div>
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
    link.download = 'incident_reports_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../views/footer.php'; ?>
