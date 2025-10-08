<?php
/**
 * Create Incident Report
 * Incident Report Management System
 */

require_once '../config/config.php';
require_role(['Responder']);

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $incident_date = sanitize_input($_POST['incident_date']);
    $incident_time = sanitize_input($_POST['incident_time']);
    $location = sanitize_input($_POST['location']);
    $severity_level = sanitize_input($_POST['severity_level']);
    $category = sanitize_input($_POST['category']);
    $organization_id = sanitize_input($_POST['organization_id']);
    $redirect = $_POST['redirect'] ?? ''; // optional
    
    // Witness data
    $witness_names = $_POST['witness_name'] ?? [];
    $witness_contacts = $_POST['witness_contact'] ?? [];
    
    if (empty($title) || empty($description) || empty($incident_date) || empty($incident_time) || 
        empty($location) || empty($severity_level) || empty($category) || empty($organization_id)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Validate witness contact numbers
        foreach ($witness_contacts as $contact) {
            if (!empty($contact) && !preg_match('/^09\d{9}$/', $contact)) {
                $error_message = 'Witness contact numbers must be valid Philippine mobile numbers (format: 09XXXXXXXXX).';
                break;
            }
        }
    }
    
    if (empty($error_message)) {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            // Restrict creating new report if responder has Pending or In Progress
            $chk = $db->prepare("SELECT COUNT(*) AS cnt FROM incident_reports WHERE reported_by = ? AND status IN ('Pending','In Progress')");
            $chk->execute([$_SESSION['user_id']]);
            $row = $chk->fetch();
            if ((int)$row['cnt'] > 0) {
                if ($redirect === 'departments') {
                    redirect('reports/departments.php?blocked=1');
                }
                throw new Exception('You already have a report that is Pending or In Progress. Complete it before creating a new one.');
            }

            $db->beginTransaction();
            
            // Insert incident report
            $query = "INSERT INTO incident_reports (title, description, incident_date, incident_time, 
                      location, severity_level, category, reported_by, organization_id) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$title, $description, $incident_date, $incident_time, $location, 
                           $severity_level, $category, $_SESSION['user_id'], $organization_id]);
            
            $report_id = $db->lastInsertId();

            // Ensure report_queue table exists (id, report_id, organization_id, status, priority_number, timestamps)
            $db->exec("CREATE TABLE IF NOT EXISTS report_queue (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                report_id INT NOT NULL,
                organization_id INT NOT NULL,
                status ENUM('Waiting','Approved','Rejected') DEFAULT 'Waiting',
                priority_number INT DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                approved_at TIMESTAMP NULL DEFAULT NULL,
                KEY idx_report_queue_org (organization_id),
                KEY idx_report_queue_status (status),
                KEY idx_report_queue_report (report_id)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

            // Insert into queue as Waiting
            $queueInsert = $db->prepare("INSERT INTO report_queue (report_id, organization_id) VALUES (?, ?)");
            $queueInsert->execute([$report_id, $organization_id]);
            
            // Handle file uploads
            if (!empty($_FILES['photos']['name'][0])) {
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
                    if ($_FILES['photos']['error'][$i] == 0) {
                        $file_extension = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                        
                        if (in_array($file_extension, ALLOWED_EXTENSIONS) && 
                            $_FILES['photos']['size'][$i] <= MAX_FILE_SIZE) {
                            
                            $filename = 'incident_' . $report_id . '_' . time() . '_' . $i . '.' . $file_extension;
                            $file_path = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $file_path)) {
                                $query = "INSERT INTO incident_photos (report_id, file_path) VALUES (?, ?)";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$report_id, 'uploads/' . $filename]);
                            }
                        }
                    }
                }
            }
            
            // Insert witnesses
            for ($i = 0; $i < count($witness_names); $i++) {
                if (!empty($witness_names[$i])) {
                    $query = "INSERT INTO incident_witnesses (report_id, witness_name, witness_contact) 
                              VALUES (?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$report_id, $witness_names[$i], $witness_contacts[$i] ?? '']);
                }
            }
            
            $db->commit();
            
            // Log audit
            log_audit('CREATE', 'incident_reports', $report_id);
            
            // Send SMS notification to organization (outside transaction)
            try {
                // Get organization contact number
                $orgQuery = "SELECT org_name, contact_number FROM organizations WHERE id = ?";
                $orgStmt = $db->prepare($orgQuery);
                $orgStmt->execute([$organization_id]);
                $organization = $orgStmt->fetch();
                
                if ($organization && !empty($organization['contact_number'])) {
                    // Include SMS functionality
                    require_once '../sms.php';
                    
                    $smsMessage = "MDRRMO-GLAN: New incident report #{$report_id} - {$title} ({$severity_level} severity) has been submitted to {$organization['org_name']}. Please check the system for details.";
                    
                    $smsResult = sendSMS($organization['contact_number'], $smsMessage);
                    
                    if (!$smsResult['success']) {
                        error_log("SMS notification failed for report #{$report_id}: " . $smsResult['error']);
                    }
                }
            } catch (Exception $smsError) {
                // Don't fail the report creation if SMS fails
                error_log("SMS notification error for report #{$report_id}: " . $smsError->getMessage());
            }
            
            // Redirect back if requested (e.g., from modal)
            if ($redirect === 'departments') {
                redirect('reports/departments.php?created=1');
            }
            
            $success_message = 'Incident report created successfully!';
            
        } catch (Exception $e) {
            // Only rollback if we're still in a transaction
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error_message = 'Error creating incident report: ' . $e->getMessage();
        }
    }
}

// Get organizations for dropdown
$database = new Database();
$db = $database->getConnection();
$query = "SELECT * FROM organizations ORDER BY org_name";
$stmt = $db->prepare($query);
$stmt->execute();
$organizations = $stmt->fetchAll();

// Check if organization is pre-selected from departments page
$preselected_org_id = $_GET['org_id'] ?? '';

$page_title = 'Create Incident Report - ' . APP_NAME;
include '../views/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-plus-circle me-2"></i>Create Incident Report
                </h1>
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
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>Incident Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                                    <div class="invalid-feedback">
                                        Please provide a title for the incident.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Fire" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Fire') ? 'selected' : ''; ?>>Fire</option>
                                        <option value="Accident" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Accident') ? 'selected' : ''; ?>>Accident</option>
                                        <option value="Security" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Security') ? 'selected' : ''; ?>>Security</option>
                                        <option value="Medical" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Medical') ? 'selected' : ''; ?>>Medical</option>
                                        <option value="Emergency" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Emergency') ? 'selected' : ''; ?>>Emergency</option>
                                        <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Please select a category.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            <div class="invalid-feedback">
                                Please provide a description of the incident.
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="incident_date" class="form-label">Date *</label>
                                    <input type="date" class="form-control" id="incident_date" name="incident_date" 
                                           value="<?php echo isset($_POST['incident_date']) ? $_POST['incident_date'] : date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">
                                        Please select the incident date.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="incident_time" class="form-label">Time *</label>
                                    <input type="time" class="form-control" id="incident_time" name="incident_time" 
                                           value="<?php echo isset($_POST['incident_time']) ? $_POST['incident_time'] : date('H:i'); ?>" required>
                                    <div class="invalid-feedback">
                                        Please select the incident time.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="severity_level" class="form-label">Severity *</label>
                                    <select class="form-select" id="severity_level" name="severity_level" required>
                                        <option value="">Select Severity</option>
                                        <option value="Low" <?php echo (isset($_POST['severity_level']) && $_POST['severity_level'] == 'Low') ? 'selected' : ''; ?>>Low</option>
                                        <option value="Medium" <?php echo (isset($_POST['severity_level']) && $_POST['severity_level'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                        <option value="High" <?php echo (isset($_POST['severity_level']) && $_POST['severity_level'] == 'High') ? 'selected' : ''; ?>>High</option>
                                        <option value="Critical" <?php echo (isset($_POST['severity_level']) && $_POST['severity_level'] == 'Critical') ? 'selected' : ''; ?>>Critical</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Please select a severity level.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="organization_id" class="form-label">Assign to Organization *</label>
                                    <select class="form-select" id="organization_id" name="organization_id" required>
                                        <option value="">Select Organization</option>
                                        <?php foreach ($organizations as $org): ?>
                                            <option value="<?php echo $org['id']; ?>" 
                                                    <?php 
                                                    $is_selected = false;
                                                    if (isset($_POST['organization_id']) && $_POST['organization_id'] == $org['id']) {
                                                        $is_selected = true;
                                                    } elseif (!empty($preselected_org_id) && $preselected_org_id == $org['id']) {
                                                        $is_selected = true;
                                                    }
                                                    echo $is_selected ? 'selected' : '';
                                                    ?>>
                                                <?php echo htmlspecialchars($org['org_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Please select an organization.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" required>
                            <div class="invalid-feedback">
                                Please provide the incident location.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="photos" class="form-label">Photos (Optional)</label>
                            <input type="file" class="form-control" id="photos" name="photos[]" multiple 
                                   accept="image/jpeg,image/jpg,image/png">
                            <div class="form-text">You can upload multiple photos. Maximum 5MB per file. Allowed formats: JPG, PNG</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Witnesses (Optional)</label>
                            <div id="witnesses-container">
                                <div class="row mb-2">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" name="witness_name[]" placeholder="Witness Name">
                                    </div>
                                    <div class="col-md-6">
                            <input type="text" class="form-control" name="witness_contact[]" placeholder="09XXXXXXXXX (11 digits)"
                                   pattern="09[0-9]{9}" title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                                   maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addWitness()">
                                <i class="fas fa-plus me-1"></i>Add Witness
                            </button>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="<?php echo BASE_URL; ?>dashboard/responder.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Create Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function addWitness() {
    const container = document.getElementById('witnesses-container');
    const newWitness = document.createElement('div');
    newWitness.className = 'row mb-2';
    newWitness.innerHTML = `
        <div class="col-md-6">
            <input type="text" class="form-control" name="witness_name[]" placeholder="Witness Name">
        </div>
        <div class="col-md-6">
                            <input type="text" class="form-control" name="witness_contact[]" placeholder="09XXXXXXXXX (11 digits)"
                                   pattern="09[0-9]{9}" title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                                   maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
        </div>
    `;
    container.appendChild(newWitness);
}
</script>

<?php include '../views/footer.php'; ?>
