<?php
/**
 * Organizations Management (Admin View)
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Admin']);

$page_title = 'Organizations - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create') {
        $org_name = sanitize_input($_POST['org_name']);
        $org_type = sanitize_input($_POST['org_type']);
        $contact_number = sanitize_input($_POST['contact_number']);
        $address = sanitize_input($_POST['address']);
        
        if (empty($org_name) || empty($org_type)) {
            $error_message = 'Organization name and type are required.';
        } elseif (!empty($contact_number) && !preg_match('/^9\d{9}$/', $contact_number)) {
            $error_message = 'Contact number must be a valid Philippine mobile number (format: 9XXXXXXXXX).';
        } else {
            try {
                $query = "INSERT INTO organizations (org_name, org_type, contact_number, address) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$org_name, $org_type, $contact_number, $address]);
                
                log_audit('CREATE', 'organizations', $db->lastInsertId());
                $success_message = 'Organization created successfully!';
            } catch (Exception $e) {
                $error_message = 'Error creating organization: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'update') {
        $id = sanitize_input($_POST['id']);
        $org_name = sanitize_input($_POST['org_name']);
        $org_type = sanitize_input($_POST['org_type']);
        $contact_number = sanitize_input($_POST['contact_number']);
        $address = sanitize_input($_POST['address']);
        
        if (empty($org_name) || empty($org_type)) {
            $error_message = 'Organization name and type are required.';
        } elseif (!empty($contact_number) && !preg_match('/^9\d{9}$/', $contact_number)) {
            $error_message = 'Contact number must be a valid Philippine mobile number (format: 9XXXXXXXXX).';
        } else {
            try {
                $query = "UPDATE organizations SET org_name = ?, org_type = ?, contact_number = ?, address = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$org_name, $org_type, $contact_number, $address, $id]);
                
                log_audit('UPDATE', 'organizations', $id);
                $success_message = 'Organization updated successfully!';
            } catch (Exception $e) {
                $error_message = 'Error updating organization: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'delete') {
        $id = sanitize_input($_POST['id']);
        
        try {
            // Check if organization has users or reports
            $query = "SELECT COUNT(*) as count FROM users WHERE organization_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $user_count = $stmt->fetch()['count'];
            
            $query = "SELECT COUNT(*) as count FROM incident_reports WHERE organization_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $report_count = $stmt->fetch()['count'];
            
            if ($user_count > 0 || $report_count > 0) {
                $error_message = 'Cannot delete organization with existing users or reports.';
            } else {
                $query = "DELETE FROM organizations WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                
                log_audit('DELETE', 'organizations', $id);
                $success_message = 'Organization deleted successfully!';
            }
        } catch (Exception $e) {
            $error_message = 'Error deleting organization: ' . $e->getMessage();
        }
    }
}

// Get all organizations with user and report counts
// Handle search and filtering
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(o.org_name LIKE ? OR o.address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "o.org_type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM organizations o $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get organizations with pagination
$query = "SELECT o.*, 
          COUNT(DISTINCT u.id) as user_count,
          COUNT(DISTINCT ir.id) as report_count
          FROM organizations o 
          LEFT JOIN users u ON o.id = u.organization_id 
          LEFT JOIN incident_reports ir ON o.id = ir.organization_id 
          $where_clause
          GROUP BY o.id 
          ORDER BY o.org_name
          LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$organizations = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-building me-2"></i>Organizations
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                            data-bs-target="#createModal">
                            <i class="fas fa-plus me-1"></i>Add Organization
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Search and Filter Controls -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by name or address..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label">Filter by Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">All Types</option>
                                <option value="Hospital" <?php echo $type_filter === 'Hospital' ? 'selected' : ''; ?>>Hospital</option>
                                <option value="Police" <?php echo $type_filter === 'Police' ? 'selected' : ''; ?>>Police</option>
                                <option value="Fire Department" <?php echo $type_filter === 'Fire Department' ? 'selected' : ''; ?>>Fire Department</option>
                                <option value="Security" <?php echo $type_filter === 'Security' ? 'selected' : ''; ?>>Security</option>
                                <option value="Emergency Services" <?php echo $type_filter === 'Emergency Services' ? 'selected' : ''; ?>>Emergency Services</option>
                                <option value="Other" <?php echo $type_filter === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Organizations Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Organizations (<?php echo $total_records; ?>)
                    </h6>
                    <small class="text-muted">
                        Showing <?php echo count($organizations); ?> of <?php echo $total_records; ?> organizations
                    </small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Contact</th>
                                    <th>Users</th>
                                    <th>Reports</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($organizations as $org): ?>
                                <tr>
                                    <td>#<?php echo $org['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($org['org_name']); ?></strong>
                                        <?php if ($org['address']): ?>
                                        <br><small
                                            class="text-muted"><?php echo htmlspecialchars($org['address']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $org['org_type'] ?: 'Other'; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($org['contact_number']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $org['user_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $org['report_count']; ?></span>
                                    </td>
                                    <td><?php echo format_date($org['created_at']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="editOrganization(<?php echo htmlspecialchars(json_encode($org)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($org['user_count'] == 0 && $org['report_count'] == 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="deleteOrganization(<?php echo $org['id']; ?>, '<?php echo htmlspecialchars($org['org_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Organizations pagination">
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
                                (<?php echo $total_records; ?> total organizations)
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Create Organization Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Organization</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="org_name" class="form-label">Organization Name *</label>
                        <input type="text" class="form-control" id="org_name" name="org_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="org_type" class="form-label">Organization Type *</label>
                        <select class="form-select" id="org_type" name="org_type" required>
                            <option value="">Select Type</option>
                            <option value="Hospital">Hospital</option>
                            <option value="Police">Police</option>
                            <option value="Fire Department">Fire Department</option>
                            <option value="Security">Security</option>
                            <option value="Emergency Services">Emergency Services</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" 
                               pattern="9[0-9]{9}" title="Enter exactly 10 digits starting with 9 (e.g., 9123456789)"
                               maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        <div class="form-text">Enter a Philippine mobile number (format: 9XXXXXXXXX)</div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Organization</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Organization Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Organization</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_org_name" class="form-label">Organization Name *</label>
                        <input type="text" class="form-control" id="edit_org_name" name="org_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_org_type" class="form-label">Organization Type *</label>
                        <select class="form-select" id="edit_org_type" name="org_type" required>
                            <option value="">Select Type</option>
                            <option value="Hospital">Hospital</option>
                            <option value="Police">Police</option>
                            <option value="Fire Department">Fire Department</option>
                            <option value="Security">Security</option>
                            <option value="Emergency Services">Emergency Services</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="edit_contact_number" name="contact_number" 
                               pattern="9[0-9]{9}" title="Enter exactly 10 digits starting with 9 (e.g., 9123456789)"
                               maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        <div class="form-text">Enter a Philippine mobile number (format: 9XXXXXXXXX)</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Organization</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Organization</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the organization <strong id="delete_name"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Organization</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editOrganization(org) {
    document.getElementById('edit_id').value = org.id;
    document.getElementById('edit_org_name').value = org.org_name;
    document.getElementById('edit_org_type').value = org.org_type;
    document.getElementById('edit_contact_number').value = org.contact_number || '';
    document.getElementById('edit_address').value = org.address || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteOrganization(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../views/footer.php'; ?>