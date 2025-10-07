<?php
/**
 * Audit Logs
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Admin']);

$page_title = 'Audit Logs - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$table_filter = $_GET['table'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(al.action LIKE ? OR al.table_name LIKE ? OR al.record_id LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($user_filter) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where_conditions[] = "al.action = ?";
    $params[] = $action_filter;
}

if ($table_filter) {
    $where_conditions[] = "al.table_name = ?";
    $params[] = $table_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(al.timestamp) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(al.timestamp) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM audit_logs al 
                LEFT JOIN users u ON al.user_id = u.id 
                WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get audit logs with pagination
$query = "SELECT al.*, u.name as user_name 
          FROM audit_logs al 
          LEFT JOIN users u ON al.user_id = u.id 
          WHERE $where_clause
          ORDER BY al.timestamp DESC 
          LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll();

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM audit_logs al WHERE 1=1";
$count_params = [];

if ($user_filter) {
    $count_query .= " AND al.user_id = ?";
    $count_params[] = $user_filter;
}

if ($action_filter) {
    $count_query .= " AND al.action = ?";
    $count_params[] = $action_filter;
}

if ($table_filter) {
    $count_query .= " AND al.table_name = ?";
    $count_params[] = $table_filter;
}

if ($date_from) {
    $count_query .= " AND DATE(al.timestamp) >= ?";
    $count_params[] = $date_from;
}

if ($date_to) {
    $count_query .= " AND DATE(al.timestamp) <= ?";
    $count_params[] = $date_to;
}

$stmt = $db->prepare($count_query);
$stmt->execute($count_params);
$total_logs = $stmt->fetch()['total'];
$total_pages = ceil($total_logs / $limit);

// Get users for filter
$query = "SELECT * FROM users ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll();

// Get unique actions and tables
$query = "SELECT DISTINCT action FROM audit_logs ORDER BY action";
$stmt = $db->prepare($query);
$stmt->execute();
$actions = $stmt->fetchAll();

$query = "SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name";
$stmt = $db->prepare($query);
$stmt->execute();
$tables = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-history me-2"></i>Audit Logs
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportAuditLogs()">
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
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control form-control-sm" id="search" name="search" 
                                   placeholder="Search by action, table, record ID, or user..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="user" class="form-label">User</label>
                            <select class="form-select form-select-sm" id="user" name="user">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="action" class="form-label">Action</label>
                            <select class="form-select form-select-sm" id="action" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo $action['action']; ?>" 
                                            <?php echo $action_filter == $action['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($action['action']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="table" class="form-label">Table</label>
                            <select class="form-select form-select-sm" id="table" name="table">
                                <option value="">All Tables</option>
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?php echo $table['table_name']; ?>" 
                                            <?php echo $table_filter == $table['table_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($table['table_name']); ?>
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
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <a href="<?php echo BASE_URL; ?>dashboard/audit.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times me-1"></i>Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Audit Logs Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Audit Logs (<?php echo $total_records; ?> total)
                    </h6>
                    <small class="text-muted">
                        Showing <?php echo count($audit_logs); ?> of <?php echo $total_records; ?> logs
                    </small>
                </div>
                <div class="card-body">
                    <?php if (empty($audit_logs)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No audit logs found</h5>
                            <p class="text-muted">Try adjusting your filters or check back later.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Record ID</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audit_logs as $log): ?>
                                        <tr>
                                            <td>#<?php echo $log['id']; ?></td>
                                            <td>
                                                <?php if ($log['user_name']): ?>
                                                    <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $action_badges = [
                                                    'CREATE' => 'bg-success',
                                                    'UPDATE' => 'bg-warning',
                                                    'DELETE' => 'bg-danger',
                                                    'LOGIN' => 'bg-info',
                                                    'LOGOUT' => 'bg-secondary'
                                                ];
                                                $badge_class = $action_badges[$log['action']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $log['action']; ?></span>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($log['table_name']); ?></code>
                                            </td>
                                            <td>
                                                <?php if ($log['record_id']): ?>
                                                    #<?php echo $log['record_id']; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo format_datetime($log['timestamp']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Audit logs pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function exportAuditLogs() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    
    // Create download link
    const link = document.createElement('a');
    link.href = 'export.php?' + params.toString();
    link.download = 'audit_logs_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../views/footer.php'; ?>
