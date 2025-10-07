<?php
/**
 * Department Catalog - Incident Report Management System
 * List of departments with report buttons
 */

require_once '../config/config.php';
require_login();

$page_title = 'Departments - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

// Get all organizations/departments
$query = "SELECT * FROM organizations ORDER BY org_name";
$stmt = $db->prepare($query);
$stmt->execute();
$departments = $stmt->fetchAll();

// Get recent reports count for each department
foreach ($departments as &$dept) {
    $query = "SELECT COUNT(*) as count FROM incident_reports WHERE organization_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$dept['id']]);
    $result = $stmt->fetch();
    $dept['report_count'] = $result['count'];
}
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-building me-2"></i>Departments
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn ui-btn-ghost" onclick="toggleView('grid')">
                            <i class="fas fa-th me-2"></i>Grid View
                        </button>
                        <button type="button" class="btn ui-btn-ghost" onclick="toggleView('list')">
                            <i class="fas fa-list me-2"></i>List View
                        </button>
                    </div>
                </div>
            </div>

            <div class="row" id="gridView">
                <?php foreach ($departments as $dept): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card department-card ui-card h-100">
                        <div class="card-body text-center">
                            <div class="department-icon mb-3">
                                <?php
                                $icon_class = '';
                                $bg_class = '';
                                switch (strtolower($dept['org_type'])) {
                                    case 'hospital':
                                        $icon_class = 'fas fa-hospital';
                                        $bg_class = 'bg-success';
                                        break;
                                    case 'police':
                                        $icon_class = 'fas fa-shield-alt';
                                        $bg_class = 'bg-primary';
                                        break;
                                    case 'fire department':
                                        $icon_class = 'fas fa-fire-extinguisher';
                                        $bg_class = 'bg-danger';
                                        break;
                                    case 'security':
                                        $icon_class = 'fas fa-user-shield';
                                        $bg_class = 'bg-warning';
                                        break;
                                    case 'emergency services':
                                        $icon_class = 'fas fa-ambulance';
                                        $bg_class = 'bg-info';
                                        break;
                                    case 'others':
                                    case 'other':
                                        $icon_class = 'fas fa-building-columns';
                                        $bg_class = 'bg-secondary';
                                        break;
                                    default:
                                        $icon_class = 'fas fa-building';
                                        $bg_class = 'bg-secondary';
                                        break;
                                }
                                ?>
                                <div class="icon-circle <?php echo $bg_class; ?> mx-auto">
                                    <i class="<?php echo $icon_class; ?> fa-2x text-white"></i>
                                </div>
                            </div>
                            
                            <h5 class="card-title"><?php echo htmlspecialchars($dept['org_name']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($dept['org_type']); ?></p>
                            
                            <?php if ($dept['contact_number']): ?>
                            <p class="card-text small">
                                <i class="fas fa-phone me-1"></i>
                                <?php echo htmlspecialchars($dept['contact_number']); ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="stats mb-3">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-file-alt me-1"></i>
                                    <?php echo $dept['report_count']; ?> Reports
                                </span>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="button" class="btn ui-btn-primary btn-report" 
                                        onclick="openReportForm(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['org_name']); ?>')">
                                    <i class="fas fa-plus me-2"></i>Report Incident
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- List View (Hidden by default) -->
            <div class="table-responsive d-none" id="listView">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Contact</th>
                            <th>Reports</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php
                                    $icon_class = '';
                                    $text_class = '';
                                    switch (strtolower($dept['org_type'])) {
                                        case 'hospital':
                                            $icon_class = 'fas fa-hospital';
                                            $text_class = 'text-success';
                                            break;
                                        case 'police':
                                            $icon_class = 'fas fa-shield-alt';
                                            $text_class = 'text-primary';
                                            break;
                                        case 'fire department':
                                            $icon_class = 'fas fa-fire-extinguisher';
                                            $text_class = 'text-danger';
                                            break;
                                        case 'security':
                                            $icon_class = 'fas fa-user-shield';
                                            $text_class = 'text-warning';
                                            break;
                                        case 'emergency services':
                                            $icon_class = 'fas fa-ambulance';
                                            $text_class = 'text-info';
                                            break;
                                        case 'others':
                                        case 'other':
                                            $icon_class = 'fas fa-building-columns';
                                            $text_class = 'text-secondary';
                                            break;
                                        default:
                                            $icon_class = 'fas fa-building';
                                            $text_class = 'text-secondary';
                                            break;
                                    }
                                    ?>
                                    <i class="<?php echo $icon_class . ' ' . $text_class; ?> me-3 fa-lg"></i>
                                    <strong><?php echo htmlspecialchars($dept['org_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($dept['org_type']); ?></td>
                            <td>
                                <?php if ($dept['contact_number']): ?>
                                    <i class="fas fa-phone me-1"></i>
                                    <?php echo htmlspecialchars($dept['contact_number']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo $dept['report_count']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn ui-btn-primary btn-report" 
                                            onclick="openReportForm(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['org_name']); ?>')">
                                        <i class="fas fa-plus me-1"></i>Report
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Report Form Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content ui-card">
            <div class="modal-header">
                <h5 class="modal-title" id="reportModalLabel">
                    <i class="fas fa-plus me-2"></i>Create Incident Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm" method="POST" action="<?php echo BASE_URL; ?>reports/create.php" enctype="multipart/form-data" onsubmit="handleReportSubmit(event)">
                    <input type="hidden" name="organization_id" id="selectedOrgId">
                    <input type="hidden" name="redirect" value="departments">
                    
                    <!-- Selected Department Info -->
                    <div class="alert alert-info" id="selectedDeptInfo">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Reporting to:</strong> <span id="selectedDeptName"></span>
                    </div>
                    
                    <!-- Incident Details -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="mb-3">
                                <i class="fas fa-file-alt me-2"></i>Incident Details
                            </h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Fire">Fire</option>
                                <option value="Accident">Accident</option>
                                <option value="Security">Security</option>
                                <option value="Medical">Medical</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="incident_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="incident_date" name="incident_date" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="incident_time" class="form-label">Time *</label>
                            <input type="time" class="form-control" id="incident_time" name="incident_time" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="severity_level" class="form-label">Severity *</label>
                            <select class="form-select" id="severity_level" name="severity_level" required>
                                <option value="">Select Severity</option>
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location">
                        </div>
                    </div>
                    
                    <!-- Photos Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="mb-3">
                                <i class="fas fa-camera me-2"></i>Photos (Optional)
                            </h6>
                            <input type="file" class="form-control" id="photos" name="photos[]" multiple accept="image/*">
                            <div class="form-text">You can upload multiple photos. Maximum 5MB per file. Allowed formats: JPG, PNG</div>
                        </div>
                    </div>
                    
                    <!-- Witnesses Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="mb-3">
                                <i class="fas fa-users me-2"></i>Witnesses (Optional)
                            </h6>
                            <div id="witnessesContainer">
                                <div class="witness-entry row mb-2">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" name="witness_name[]" placeholder="Witness Name">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" name="witness_contact[]" placeholder="9XXXXXXXXX (10 digits)" 
                                               pattern="9[0-9]{9}" title="Enter exactly 10 digits starting with 9 (e.g., 9123456789)"
                                               maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn ui-btn-ghost btn-sm" onclick="addWitness()">
                                <i class="fas fa-plus me-1"></i>Add Witness
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn ui-btn-ghost" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="submit" form="reportForm" class="btn ui-btn-primary" id="reportSubmitBtn">
                    <i class="fas fa-plus me-2"></i>Create Report
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.department-card {
    transition: transform 0.2s ease-in-out;
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.department-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.icon-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-report { transition: all 0.2s ease-in-out; }
.btn-report:hover { transform: translateY(-1px); }

.witness-entry { transition: opacity 0.3s ease-in-out; }
</style>

<script>
function toggleView(view) {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    
    if (view === 'grid') {
        gridView.classList.remove('d-none');
        listView.classList.add('d-none');
    } else {
        gridView.classList.add('d-none');
        listView.classList.remove('d-none');
    }
}

function openReportForm(orgId, orgName) {
    try {
        // Set form data
        document.getElementById('selectedOrgId').value = orgId;
        document.getElementById('selectedDeptName').textContent = orgName;
        
        // Set current date and time
        const now = new Date();
        const date = now.toISOString().split('T')[0];
        const time = now.toTimeString().slice(0, 5);
        document.getElementById('incident_date').value = date;
        document.getElementById('incident_time').value = time;
        
        // Clear form fields
        document.getElementById('title').value = '';
        document.getElementById('description').value = '';
        document.getElementById('category').value = '';
        document.getElementById('severity_level').value = '';
        document.getElementById('location').value = '';
        document.getElementById('photos').value = '';
        
        // Clear witnesses
        const witnessesContainer = document.getElementById('witnessesContainer');
        witnessesContainer.innerHTML = `
            <div class="witness-entry row mb-2">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="witness_name[]" placeholder="Witness Name">
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" name="witness_contact[]" placeholder="9XXXXXXXXX (10 digits)" 
                           pattern="9[0-9]{9}" title="Enter exactly 10 digits starting with 9 (e.g., 9123456789)"
                           maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                </div>
            </div>
        `;
        
        // Get modal element and ensure it exists
        const modalEl = document.getElementById('reportModal');
        if (!modalEl) {
            console.error('Modal element not found');
            return;
        }
        
        // Dispose any existing modal instance
        const existingModal = bootstrap.Modal.getInstance(modalEl);
        if (existingModal) {
            existingModal.dispose();
        }
        
        // Create and show new modal instance
        const modal = new bootstrap.Modal(modalEl, {
            backdrop: 'static',
            keyboard: false
        });
        
        // Add event listeners for modal events
        modalEl.addEventListener('shown.bs.modal', function() {
            console.log('Modal shown successfully');
        });
        
        modalEl.addEventListener('hidden.bs.modal', function() {
            console.log('Modal hidden');
        });
        
        // Show modal with fallback
        try {
            modal.show();
        } catch (modalError) {
            console.error('Modal show error:', modalError);
            // Fallback: try to show modal after a short delay
            setTimeout(() => {
                try {
                    modal.show();
                } catch (retryError) {
                    console.error('Modal retry failed:', retryError);
                    alert('Unable to open report form. Please refresh the page and try again.');
                }
            }, 100);
        }
        
    } catch (error) {
        console.error('Error opening report form:', error);
        alert('Error opening report form. Please try again.');
    }
}

function viewDepartment(orgId) {
    // removed view details navigation per request
}

function addWitness() {
    const container = document.getElementById('witnessesContainer');
    const newEntry = document.createElement('div');
    newEntry.className = 'witness-entry row mb-2';
    newEntry.innerHTML = `
        <div class="col-md-6">
            <input type="text" class="form-control" name="witness_name[]" placeholder="Witness Name">
        </div>
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" class="form-control" name="witness_contact[]" placeholder="9XXXXXXXXX (10 digits)" 
                       pattern="9[0-9]{9}" title="Enter exactly 10 digits starting with 9 (e.g., 9123456789)"
                       maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                <button type="button" class="btn ui-btn-ghost" onclick="removeWitness(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(newEntry);
}

function removeWitness(button) {
    button.closest('.witness-entry').remove();
}

async function handleReportSubmit(e) {
    // Show immediate feedback and prevent double submit
    const btn = document.getElementById('reportSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    }
    // Let normal POST proceed; after redirect back with created=1 we show a toast
}

// Ensure DOM is ready and Bootstrap is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any required components
    console.log('Departments page loaded successfully');
});
</script>

<?php if (isset($_GET['created']) && $_GET['created'] == '1'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Success toast/alert after redirect
    const container = document.querySelector('.main-content');
    if (container) {
        const div = document.createElement('div');
        div.className = 'alert alert-success alert-dismissible fade show mt-3';
        div.innerHTML = '<i class="fas fa-check-circle me-2"></i>Report submitted and queued successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        container.prepend(div);
    }
});
</script>
<?php endif; ?>

<?php if (isset($_GET['blocked']) && $_GET['blocked'] == '1'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.main-content');
    if (container) {
        const div = document.createElement('div');
        div.className = 'alert alert-warning alert-dismissible fade show mt-3';
        div.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>You already have a Pending or In Progress report. Finish it before submitting a new one.<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        container.prepend(div);
    }
});
</script>
<?php endif; ?>
</script>

<?php include '../views/footer.php'; ?>
