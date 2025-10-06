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
        
        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            $error_message = 'Name, email, password, and role are required.';
        } elseif ($role != 'Admin' && empty($organization_id)) {
            $error_message = 'Organization is required for non-admin users.';
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
                    $query = "INSERT INTO users (name, email, password, role, organization_id) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$name, $email, $hashed_password, $role, $organization_id ?: null]);
                    
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
        $password = $_POST['password'];
        
        if (empty($name) || empty($email) || empty($role)) {
            $error_message = 'Name, email, and role are required.';
        } elseif ($role != 'Admin' && empty($organization_id)) {
            $error_message = 'Organization is required for non-admin users.';
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
                        $query = "UPDATE users SET name = ?, email = ?, password = ?, role = ?, organization_id = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$name, $email, $hashed_password, $role, $organization_id ?: null, $id]);
                    } else {
                        $query = "UPDATE users SET name = ?, email = ?, role = ?, organization_id = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$name, $email, $role, $organization_id ?: null, $id]);
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

// Get all users with organization info
$query = "SELECT u.*, o.org_name 
          FROM users u 
          LEFT JOIN organizations o ON u.organization_id = o.id 
          ORDER BY u.name";
$stmt = $db->prepare($query);
$stmt->execute();
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
            
            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Users (<?php echo count($users); ?>)
                    </h6>
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
    
    if (role === 'Admin') {
        orgField.style.display = 'none';
        orgSelect.required = false;
    } else {
        orgField.style.display = 'block';
        orgSelect.required = true;
    }
}

function toggleEditOrganization() {
    const role = document.getElementById('edit_role').value;
    const orgField = document.getElementById('edit_organization_field');
    const orgSelect = document.getElementById('edit_organization_id');
    
    if (role === 'Admin') {
        orgField.style.display = 'none';
        orgSelect.required = false;
    } else {
        orgField.style.display = 'block';
        orgSelect.required = true;
    }
}

function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_organization_id').value = user.organization_id || '';
    
    toggleEditOrganization();
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteUser(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../views/footer.php'; ?>
