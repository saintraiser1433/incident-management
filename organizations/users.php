<?php
/**
 * Users Management (Admin View)
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Admin']);

$page_title = 'Users - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create') {
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $role = sanitize_input($_POST['role']);
        $organization_id = sanitize_input($_POST['organization_id']);
        $contact_number = sanitize_input($_POST['contact_number']);
        
        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            $error_message = 'Name, email, password, and role are required.';
        } elseif ($role != 'Admin' && $role != 'Responder' && empty($organization_id)) {
            $error_message = 'Organization is required for Organization Account users.';
        } elseif ($role === 'Responder' && !empty($contact_number) && !preg_match('/^09\d{9}$/', $contact_number)) {
            $error_message = 'Contact number must be a valid Philippine mobile number (format: 09XXXXXXXXX).';
        } else {
            try {
                // Check if email already exists
                $query = "SELECT id FROM users WHERE email = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error_message = 'Email address already exists.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $query = "INSERT INTO users (name, email, password, role, organization_id, contact_number) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$name, $email, $hashed_password, $role, $organization_id ?: null, $contact_number ?: null]);
                    
                    log_audit('CREATE', 'users', $db->lastInsertId());
                    $success_message = 'User created successfully!';
                }
            } catch (Exception $e) {
                $error_message = 'Error creating user: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'update') {
        $id = sanitize_input($_POST['id']);
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $role = sanitize_input($_POST['role']);
        $organization_id = sanitize_input($_POST['organization_id']);
        $contact_number = sanitize_input($_POST['contact_number']);
        $password = $_POST['password'];
        
        if (empty($name) || empty($email) || empty($role)) {
            $error_message = 'Name, email, and role are required.';
        } elseif ($role != 'Admin' && $role != 'Responder' && empty($organization_id)) {
            $error_message = 'Organization is required for Organization Account users.';
        } elseif ($role === 'Responder' && !empty($contact_number) && !preg_match('/^09\d{9}$/', $contact_number)) {
            $error_message = 'Contact number must be a valid Philippine mobile number (format: 09XXXXXXXXX).';
        } else {
            try {
                // Check if email already exists (excluding current user)
                $query = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    $error_message = 'Email address already exists.';
                } else {
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $query = "UPDATE users SET name = ?, email = ?, password = ?, role = ?, organization_id = ?, contact_number = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$name, $email, $hashed_password, $role, $organization_id ?: null, $contact_number ?: null, $id]);
                    } else {
                        $query = "UPDATE users SET name = ?, email = ?, role = ?, organization_id = ?, contact_number = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$name, $email, $role, $organization_id ?: null, $contact_number ?: null, $id]);
                    }
                    
                    log_audit('UPDATE', 'users', $id);
                    $success_message = 'User updated successfully!';
                }
            } catch (Exception $e) {
                $error_message = 'Error updating user: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'delete') {
        $id = sanitize_input($_POST['id']);
        
        if ($id == $_SESSION['user_id']) {
            $error_message = 'You cannot delete your own account.';
        } else {
            try {
                // Check if user has reports
                $query = "SELECT COUNT(*) as count FROM incident_reports WHERE reported_by = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                $report_count = $stmt->fetch()['count'];
                
                if ($report_count > 0) {
                    $error_message = 'Cannot delete user with existing reports.';
                } else {
                    $query = "DELETE FROM users WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    
                    log_audit('DELETE', 'users', $id);
                    $success_message = 'User deleted successfully!';
                }
            } catch (Exception $e) {
                $error_message = 'Error deleting user: ' . $e->getMessage();
            }
        }
    }
}

// Handle search and filtering
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM users u LEFT JOIN organizations o ON u.organization_id = o.id $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get users with pagination
$query = "SELECT u.*, o.org_name 
          FROM users u 
          LEFT JOIN organizations o ON u.organization_id = o.id 
          $where_clause
          ORDER BY u.name 
          LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get organizations for dropdown
$query = "SELECT * FROM organizations ORDER BY org_name";
$stmt = $db->prepare($query);
$stmt->execute();
$organizations = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-users me-2"></i>Users
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                            <i class="fas fa-plus me-1"></i>Add User
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
                                   placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="role" class="form-label">Filter by Role</label>
                            <select class="form-select" id="role" name="role">
                                <option value="">All Roles</option>
                                <option value="Admin" <?php echo $role_filter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="Organization Account" <?php echo $role_filter === 'Organization Account' ? 'selected' : ''; ?>>Organization Account</option>
                                <option value="Responder" <?php echo $role_filter === 'Responder' ? 'selected' : ''; ?>>Responder</option>
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
                                <a href="users.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Users (<?php echo $total_records; ?>)
                    </h6>
                    <small class="text-muted">
                        Showing <?php echo count($users); ?> of <?php echo $total_records; ?> users
                    </small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Organization</th>
                                    <th>Contact Number</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>#<?php echo $user['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                <span class="badge bg-success ms-1">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php
                                            $role_badges = [
                                                'Admin' => 'bg-danger',
                                                'Organization Account' => 'bg-warning',
                                                'Responder' => 'bg-info'
                                            ];
                                            $badge_class = $role_badges[$user['role']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $user['role']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['org_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if (!empty($user['contact_number'])): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['contact_number']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">No contact</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo format_date($user['created_at']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
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
                        <nav aria-label="Users pagination">
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
                                (<?php echo $total_records; ?> total users)
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role *</label>
                        <select class="form-select" id="role" name="role" required onchange="toggleOrganization()">
                            <option value="">Select Role</option>
                            <option value="Admin">Admin</option>
                            <option value="Organization Account">Organization Account</option>
                            <option value="Responder">Responder</option>
                        </select>
                    </div>
                    <div class="mb-3" id="organization_field">
                        <label for="organization_id" class="form-label">Organization *</label>
                        <select class="form-select" id="organization_id" name="organization_id">
                            <option value="">Select Organization</option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?php echo $org['id']; ?>"><?php echo htmlspecialchars($org['org_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="contact_field">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" 
                               placeholder="09XXXXXXXXX (11 digits)" pattern="09[0-9]{9}" 
                               title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                               maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        <div class="form-text">Enter a Philippine mobile number for SMS notifications (optional for non-Responder roles)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role *</label>
                        <select class="form-select" id="edit_role" name="role" required onchange="toggleEditOrganization()">
                            <option value="">Select Role</option>
                            <option value="Admin">Admin</option>
                            <option value="Organization Account">Organization Account</option>
                            <option value="Responder">Responder</option>
                        </select>
                    </div>
                    <div class="mb-3" id="edit_organization_field">
                        <label for="edit_organization_id" class="form-label">Organization *</label>
                        <select class="form-select" id="edit_organization_id" name="organization_id">
                            <option value="">Select Organization</option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?php echo $org['id']; ?>"><?php echo htmlspecialchars($org['org_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="edit_contact_field">
                        <label for="edit_contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="edit_contact_number" name="contact_number" 
                               placeholder="09XXXXXXXXX (11 digits)" pattern="09[0-9]{9}" 
                               title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                               maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        <div class="form-text">Enter a Philippine mobile number for SMS notifications (optional for non-Responder roles)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
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
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user <strong id="delete_name"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleOrganization() {
    const role = document.getElementById('role').value;
    const orgField = document.getElementById('organization_field');
    const orgSelect = document.getElementById('organization_id');
    const contactInput = document.getElementById('contact_number');
    
    if (role === 'Organization Account') {
        orgField.style.display = 'block';
        orgSelect.required = true;
        contactInput.required = false;
    } else if (role === 'Responder') {
        orgField.style.display = 'none';
        orgSelect.required = false;
        contactInput.required = true;
    } else {
        orgField.style.display = 'none';
        orgSelect.required = false;
        contactInput.required = false;
    }
}

function toggleEditOrganization() {
    const role = document.getElementById('edit_role').value;
    const orgField = document.getElementById('edit_organization_field');
    const orgSelect = document.getElementById('edit_organization_id');
    const contactInput = document.getElementById('edit_contact_number');
    
    if (role === 'Organization Account') {
        orgField.style.display = 'block';
        orgSelect.required = true;
        contactInput.required = false;
    } else if (role === 'Responder') {
        orgField.style.display = 'none';
        orgSelect.required = false;
        contactInput.required = true;
    } else {
        orgField.style.display = 'none';
        orgSelect.required = false;
        contactInput.required = false;
    }
}

function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_organization_id').value = user.organization_id || '';
    document.getElementById('edit_contact_number').value = user.contact_number || '';
    
    toggleEditOrganization();
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteUser(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Initialize organization field visibility on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleOrganization();
});
</script>

<?php include '../views/footer.php'; ?>
