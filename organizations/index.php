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
        $latitude = isset($_POST['latitude']) ? sanitize_input($_POST['latitude']) : null;
        $longitude = isset($_POST['longitude']) ? sanitize_input($_POST['longitude']) : null;
        
        if (empty($org_name) || empty($org_type)) {
            $error_message = 'Organization name and type are required.';
        } elseif (!empty($contact_number) && !preg_match('/^09\d{9}$/', $contact_number)) {
            $error_message = 'Contact number must be a valid Philippine mobile number (format: 09XXXXXXXXX).';
        } else {
            try {
                $query = "INSERT INTO organizations (org_name, org_type, contact_number, address, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $org_name,
                    $org_type,
                    $contact_number,
                    $address,
                    $latitude !== '' ? $latitude : null,
                    $longitude !== '' ? $longitude : null
                ]);
                
                $newOrgId = (int) $db->lastInsertId();
                log_audit('CREATE', 'organizations', $newOrgId);
                $success_message = 'Organization created successfully!';

                $logoResult = save_organization_logo_upload($newOrgId);
                if (!empty($logoResult['error'])) {
                    $error_message = 'Logo could not be saved: ' . $logoResult['error'];
                } elseif (!empty($logoResult['path'])) {
                    $logoStmt = $db->prepare('UPDATE organizations SET logo_path = ? WHERE id = ?');
                    $logoStmt->execute([$logoResult['path'], $newOrgId]);
                }
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
        $latitude = isset($_POST['latitude']) ? sanitize_input($_POST['latitude']) : null;
        $longitude = isset($_POST['longitude']) ? sanitize_input($_POST['longitude']) : null;
        
        if (empty($org_name) || empty($org_type)) {
            $error_message = 'Organization name and type are required.';
        } elseif (!empty($contact_number) && !preg_match('/^09\d{9}$/', $contact_number)) {
            $error_message = 'Contact number must be a valid Philippine mobile number (format: 09XXXXXXXXX).';
        } else {
            try {
                $prevLogoStmt = $db->prepare('SELECT logo_path FROM organizations WHERE id = ?');
                $prevLogoStmt->execute([$id]);
                $prevLogoPath = $prevLogoStmt->fetchColumn() ?: null;

                $query = "UPDATE organizations SET org_name = ?, org_type = ?, contact_number = ?, address = ?, latitude = ?, longitude = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $org_name,
                    $org_type,
                    $contact_number,
                    $address,
                    $latitude !== '' ? $latitude : null,
                    $longitude !== '' ? $longitude : null,
                    $id
                ]);

                $removeLogo = !empty($_POST['remove_logo']);
                if ($removeLogo) {
                    delete_organization_logo_disk($prevLogoPath);
                    $clr = $db->prepare('UPDATE organizations SET logo_path = NULL WHERE id = ?');
                    $clr->execute([$id]);
                } else {
                    $logoResult = save_organization_logo_upload((int) $id);
                    if (!empty($logoResult['error'])) {
                        $error_message = 'Logo could not be saved: ' . $logoResult['error'];
                    } elseif (!empty($logoResult['path'])) {
                        $logoStmt = $db->prepare('UPDATE organizations SET logo_path = ? WHERE id = ?');
                        $logoStmt->execute([$logoResult['path'], $id]);
                    }
                }

                log_audit('UPDATE', 'organizations', $id);
                if (empty($error_message)) {
                    $success_message = 'Organization updated successfully!';
                } else {
                    $success_message = 'Organization details saved.';
                }
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
                $lpStmt = $db->prepare('SELECT logo_path FROM organizations WHERE id = ?');
                $lpStmt->execute([$id]);
                $delLogo = $lpStmt->fetchColumn();
                if (!empty($delLogo)) {
                    delete_organization_logo_disk($delLogo);
                }

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
    <div class="row g-0">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Organizations</h1>
                    <p class="text-sm text-slate-500 mt-1">Manage partner agencies, hospitals, and response teams.</p>
                </div>
                <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 transition" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="fas fa-plus"></i>Add Organization
                </button>
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
                                    <th>Logo</th>
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
                                        <?php if (!empty($org['logo_path'] ?? null)): ?>
                                            <img src="<?php echo htmlspecialchars(BASE_URL . ($org['logo_path'] ?? '')); ?>" alt="" width="40" height="40" class="rounded-lg border border-slate-200 object-contain bg-white" style="max-height:40px">
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($org['org_name']); ?></strong>
                                        <?php if ($org['address']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($org['address']); ?></small>
                                        <?php endif; ?>
                                        <?php if (!is_null($org['latitude']) && !is_null($org['longitude'])): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo number_format($org['latitude'], 5); ?>,
                                                <?php echo number_format($org['longitude'], 5); ?>
                                            </small>
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
            <form method="POST" enctype="multipart/form-data">
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
                               pattern="09[0-9]{9}" title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                               maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        <div class="form-text">Enter a Philippine mobile number (format: 09XXXXXXXXX)</div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="org_logo_create" class="form-label">Organization logo (optional)</label>
                        <input type="file" class="form-control" id="org_logo_create" name="org_logo" accept="image/jpeg,image/png,image/webp,image/gif">
                        <div class="form-text">JPEG, PNG, WebP, or GIF. Max <?php echo ORG_LOGO_MAX_BYTES / 1024 / 1024; ?>MB. Shown on the organization dashboard and sidebar.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt me-1"></i>Pin Organization Location on Map (Optional)
                        </label>
                        <div id="org-map-create" style="height: 260px; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(0,0,0,0.1);"></div>
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                        <div class="form-text">
                            Click on the map to set the organization's location. This will be used to center the incident map for this organization.
                        </div>
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
            <form method="POST" enctype="multipart/form-data">
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
                               pattern="09[0-9]{9}" title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                               maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                        <div class="form-text">Enter a Philippine mobile number (format: 09XXXXXXXXX)</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Organization logo</label>
                        <div id="edit_logo_preview_wrap" class="mb-2 hidden">
                            <img id="edit_logo_preview" src="" alt="Current logo" class="rounded-lg border border-slate-200 bg-white p-1" style="max-height: 72px; max-width: 160px; object-fit: contain;">
                        </div>
                        <input type="file" class="form-control" id="org_logo_edit" name="org_logo" accept="image/jpeg,image/png,image/webp,image/gif">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_logo" id="edit_remove_logo" value="1">
                            <label class="form-check-label" for="edit_remove_logo">Remove current logo</label>
                        </div>
                        <div class="form-text">JPEG, PNG, WebP, or GIF. Max <?php echo ORG_LOGO_MAX_BYTES / 1024 / 1024; ?>MB.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt me-1"></i>Organization Location on Map (Optional)
                        </label>
                        <div id="org-map-edit" style="height: 260px; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(0,0,0,0.1);"></div>
                        <input type="hidden" id="edit_latitude" name="latitude">
                        <input type="hidden" id="edit_longitude" name="longitude">
                        <div class="form-text">
                            Click on the map to update the organization's location.
                        </div>
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
window.APP_BASE_URL = <?php echo json_encode(BASE_URL); ?>;

function initOrgMap(mapElementId, latInputId, lngInputId, initialLat, initialLng) {
    const el = document.getElementById(mapElementId);
    const latInput = document.getElementById(latInputId);
    const lngInput = document.getElementById(lngInputId);
    if (!el || typeof L === 'undefined') {
        return null;
    }

    const defaultCenter = [6.0523, 125.2896];
    const hasInitial = initialLat !== null && initialLng !== null && initialLat !== '' && initialLng !== '';
    const center = hasInitial ? [parseFloat(initialLat), parseFloat(initialLng)] : defaultCenter;
    const zoom = hasInitial ? 14 : 12;

    const map = L.map(mapElementId);
    map.setView(center, zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;
    if (hasInitial && !isNaN(center[0]) && !isNaN(center[1])) {
        marker = L.marker(center).addTo(map);
        latInput.value = center[0].toFixed(8);
        lngInput.value = center[1].toFixed(8);
    }

    function setMarker(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map);
        }
        latInput.value = lat.toFixed(8);
        lngInput.value = lng.toFixed(8);
    }

    map.on('click', function(e) {
        setMarker(e.latlng.lat, e.latlng.lng);
    });

    return map;
}

function editOrganization(org) {
    document.getElementById('edit_id').value = org.id;
    document.getElementById('edit_org_name').value = org.org_name;
    document.getElementById('edit_org_type').value = org.org_type;
    document.getElementById('edit_contact_number').value = org.contact_number || '';
    document.getElementById('edit_address').value = org.address || '';
    document.getElementById('edit_latitude').value = org.latitude !== null ? org.latitude : '';
    document.getElementById('edit_longitude').value = org.longitude !== null ? org.longitude : '';
    document.getElementById('edit_remove_logo').checked = false;
    document.getElementById('org_logo_edit').value = '';

    var wrap = document.getElementById('edit_logo_preview_wrap');
    var img = document.getElementById('edit_logo_preview');
    if (org.logo_path) {
        img.src = window.APP_BASE_URL + org.logo_path;
        wrap.classList.remove('hidden');
    } else {
        img.removeAttribute('src');
        wrap.classList.add('hidden');
    }

    const modalEl = document.getElementById('editModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    // Initialize map after modal is shown to ensure correct sizing
    modalEl.addEventListener('shown.bs.modal', function onShown() {
        initOrgMap(
            'org-map-edit',
            'edit_latitude',
            'edit_longitude',
            org.latitude !== null ? org.latitude : null,
            org.longitude !== null ? org.longitude : null
        );
        modalEl.removeEventListener('shown.bs.modal', onShown);
    });
}

// Initialize map for create modal when shown
document.getElementById('createModal').addEventListener('shown.bs.modal', function() {
    initOrgMap('org-map-create', 'latitude', 'longitude', null, null);
});

function deleteOrganization(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../views/footer.php'; ?>