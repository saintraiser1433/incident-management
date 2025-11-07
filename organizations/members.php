<?php
/**
 * Organization Members Management
 * Incident Report Management System
 * Allows Organization Account users (heads) to manage their organization members (tags only, no login)
 */

require_once '../config/config.php';
require_role(['Organization Account']);

$page_title = 'Organization Members - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Ensure organization_members table exists
$db->exec("CREATE TABLE IF NOT EXISTS organization_members (
    id INT NOT NULL AUTO_INCREMENT,
    organization_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) DEFAULT NULL,
    email VARCHAR(191) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_org_members_org (organization_id),
    KEY idx_org_members_name (name)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create') {
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email'] ?? '');
        $contact_number = sanitize_input($_POST['contact_number'] ?? '');
        
        if (empty($name)) {
            $error_message = 'Name is required.';
        } elseif (!empty($contact_number) && !preg_match('/^09\d{9}$/', $contact_number)) {
            $error_message = 'Contact number must be a valid Philippine mobile number (format: 09XXXXXXXXX).';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Invalid email address.';
        } else {
            try {
                // Check if member with same name already exists in this organization
                $query = "SELECT id FROM organization_members WHERE name = ? AND organization_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $_SESSION['organization_id']]);
                if ($stmt->fetch()) {
                    $error_message = 'A member with this name already exists in your organization.';
                } else {
                    $query = "INSERT INTO organization_members (organization_id, name, contact_number, email) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$_SESSION['organization_id'], $name, $contact_number ?: null, $email ?: null]);
                    
                    log_audit('CREATE', 'organization_members', $db->lastInsertId());
                    $success_message = 'Member added successfully!';
                }
            } catch (Exception $e) {
                $error_message = 'Error adding member: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'update') {
        $id = sanitize_input($_POST['id']);
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email'] ?? '');
        $contact_number = sanitize_input($_POST['contact_number'] ?? '');
        
        if (empty($name)) {
            $error_message = 'Name is required.';
        } elseif (!empty($contact_number) && !preg_match('/^09\d{9}$/', $contact_number)) {
            $error_message = 'Contact number must be a valid Philippine mobile number (format: 09XXXXXXXXX).';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Invalid email address.';
        } else {
            try {
                // Verify member belongs to same organization
                $verifyQuery = "SELECT id FROM organization_members WHERE id = ? AND organization_id = ?";
                $verifyStmt = $db->prepare($verifyQuery);
                $verifyStmt->execute([$id, $_SESSION['organization_id']]);
                if (!$verifyStmt->fetch()) {
                    $error_message = 'Member not found or access denied.';
                } else {
                    // Check if another member with same name exists (excluding current member)
                    $query = "SELECT id FROM organization_members WHERE name = ? AND organization_id = ? AND id != ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$name, $_SESSION['organization_id'], $id]);
                    if ($stmt->fetch()) {
                        $error_message = 'A member with this name already exists in your organization.';
                    } else {
                        $query = "UPDATE organization_members SET name = ?, contact_number = ?, email = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$name, $contact_number ?: null, $email ?: null, $id]);
                        
                        log_audit('UPDATE', 'organization_members', $id);
                        $success_message = 'Member updated successfully!';
                    }
                }
            } catch (Exception $e) {
                $error_message = 'Error updating member: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'delete') {
        $id = sanitize_input($_POST['id']);
        
        try {
            // Verify member belongs to same organization
            $verifyQuery = "SELECT id FROM organization_members WHERE id = ? AND organization_id = ?";
            $verifyStmt = $db->prepare($verifyQuery);
            $verifyStmt->execute([$id, $_SESSION['organization_id']]);
            if (!$verifyStmt->fetch()) {
                $error_message = 'Member not found or access denied.';
            } else {
                // Check if member has assigned reports
                $query = "SELECT COUNT(*) as count FROM report_queue WHERE assigned_to = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                $assign_count = $stmt->fetch()['count'];
                
                if ($assign_count > 0) {
                    $error_message = 'Cannot delete member with assigned reports. Please reassign reports first.';
                } else {
                    $query = "DELETE FROM organization_members WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    
                    log_audit('DELETE', 'organization_members', $id);
                    $success_message = 'Member deleted successfully!';
                }
            }
        } catch (Exception $e) {
            $error_message = 'Error deleting member: ' . $e->getMessage();
        }
    }
}

// Get all members for this organization
$query = "SELECT om.*, 
          (SELECT COUNT(*) FROM report_queue WHERE assigned_to = om.id) as assigned_reports_count
          FROM organization_members om 
          WHERE om.organization_id = ?
          ORDER BY om.name";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['organization_id']]);
$members = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-users me-2"></i>Organization Members
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                            <i class="fas fa-plus me-1"></i>Add Member
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
            
            <!-- Members Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Members (<?php echo count($members); ?>)
                    </h6>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>Members are tags for assignment only (no login access)
                    </small>
                </div>
                <div class="card-body">
                    <?php if (empty($members)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No members found</h5>
                            <p class="text-muted">Add members to your organization to assign reports to them.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Contact Number</th>
                                        <th>Assigned Reports</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($member['email'])): ?>
                                                    <?php echo htmlspecialchars($member['email']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($member['contact_number'])): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($member['contact_number']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">No contact</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo (int)$member['assigned_reports_count']; ?> report(s)
                                                </span>
                                            </td>
                                            <td><?php echo format_date($member['created_at']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editMember(<?php echo htmlspecialchars(json_encode($member)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteMember(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['name']); ?>', <?php echo (int)$member['assigned_reports_count']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

<!-- Create Member Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Members are tags for assignment only. They cannot login to the system.</small>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="Optional">
                        <div class="form-text">Optional - for reference only</div>
                    </div>
                    <div class="mb-3">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" 
                               placeholder="09XXXXXXXXX (11 digits)" pattern="09[0-9]{9}" 
                               title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                               maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        <div class="form-text">Optional - for reference only</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email" 
                               placeholder="Optional">
                        <div class="form-text">Optional - for reference only</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="edit_contact_number" name="contact_number" 
                               placeholder="09XXXXXXXXX (11 digits)" pattern="09[0-9]{9}" 
                               title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                               maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        <div class="form-text">Optional - for reference only</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Member</button>
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
                    <h5 class="modal-title">Delete Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the member <strong id="delete_name"></strong>?</p>
                    <p id="delete_warning" class="text-danger" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        This member has <strong id="delete_count"></strong> assigned report(s). Please reassign them first.
                    </p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete_submit">Delete Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMember(member) {
    document.getElementById('edit_id').value = member.id;
    document.getElementById('edit_name').value = member.name;
    document.getElementById('edit_email').value = member.email || '';
    document.getElementById('edit_contact_number').value = member.contact_number || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteMember(id, name, assignedCount) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    
    const warningDiv = document.getElementById('delete_warning');
    const countSpan = document.getElementById('delete_count');
    const submitBtn = document.getElementById('delete_submit');
    
    if (assignedCount > 0) {
        warningDiv.style.display = 'block';
        countSpan.textContent = assignedCount;
        submitBtn.disabled = true;
        submitBtn.classList.add('disabled');
    } else {
        warningDiv.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.classList.remove('disabled');
    }
    
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../views/footer.php'; ?>
