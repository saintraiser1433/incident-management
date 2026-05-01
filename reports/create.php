<?php
/**
 * Create Incident Report (Guest Access)
 * Incident Report Management System
 */

require_once '../config/config.php';
// Remove role requirement - allow guest access
// require_role(['Responder']);

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize_input($_POST['title'] ?? '');
    $reported_by = sanitize_input($_POST['reported_by'] ?? '');
    $reporter_contact_number = sanitize_input($_POST['reporter_contact_number'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    // Automatically set current date and time
    $incident_date = date('Y-m-d');
    $incident_time = date('H:i');
    $location = sanitize_input($_POST['location'] ?? '');
    $latitude = isset($_POST['latitude']) ? sanitize_input($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) ? sanitize_input($_POST['longitude']) : null;
    $severity_level = sanitize_input($_POST['severity_level'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $organization_id = sanitize_input($_POST['organization_id'] ?? '');
    $family_contact_name = sanitize_input($_POST['family_contact_name'] ?? '');
    $family_contact_number = sanitize_input($_POST['family_contact_number'] ?? '');
    $redirect = $_POST['redirect'] ?? ''; // optional
    
    // Witness data
    $witness_names = $_POST['witness_name'] ?? [];
    $witness_contacts = $_POST['witness_contact'] ?? [];
    
    if (empty($title) || empty($reported_by) || empty($description) || 
        empty($location) || empty($severity_level) || empty($category) || empty($organization_id)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Validate reporter contact number if provided
        if (!empty($reporter_contact_number) && !preg_match('/^09\d{9}$/', $reporter_contact_number)) {
            $error_message = 'Reporter contact number must be a valid Philippine mobile number (format: 09XXXXXXXXX).';
        }
        // Validate reporter contact number if provided
        if (empty($error_message)) {
            foreach ($witness_contacts as $contact) {
                if (!empty($contact) && !preg_match('/^09\d{9}$/', $contact)) {
                    $error_message = 'Witness contact numbers must be valid Philippine mobile numbers (format: 09XXXXXXXXX).';
                    break;
                }
            }
        }
        // Validate family contact number if provided
        if (empty($error_message) && !empty($family_contact_number) && !preg_match('/^09\d{9}$/', $family_contact_number)) {
            $error_message = 'Family contact number must be a valid Philippine mobile number (format: 09XXXXXXXXX).';
        }
    }
    
    if (empty($error_message)) {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            // For guest users, skip the existing report check
            if (is_logged_in()) {
                // Restrict creating new report if user has Pending or In Progress
                $chk = $db->prepare("SELECT COUNT(*) AS cnt FROM incident_reports WHERE reported_by = ? AND status IN ('Pending','In Progress')");
                $chk->execute([$_SESSION['user_id']]);
                $row = $chk->fetch();
                if ((int)$row['cnt'] > 0) {
                    if ($redirect === 'departments') {
                        redirect('reports/departments.php?blocked=1');
                    }
                    throw new Exception('You already have a report that is Pending or In Progress. Complete it before creating a new one.');
                }
            }

            // Ensure report_queue table exists (outside transaction to avoid auto-commit issues)
            $db->exec("CREATE TABLE IF NOT EXISTS report_queue (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                report_id INT NOT NULL,
                organization_id INT NOT NULL,
                status ENUM('Waiting','Approved','Rejected') DEFAULT 'Waiting',
                priority_number INT DEFAULT NULL,
                assigned_to INT NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                approved_at TIMESTAMP NULL DEFAULT NULL,
                KEY idx_report_queue_org (organization_id),
                KEY idx_report_queue_status (status),
                KEY idx_report_queue_report (report_id),
                KEY idx_report_queue_assigned (assigned_to)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

            $db->beginTransaction();
            
            // Insert incident report
            $query = "INSERT INTO incident_reports (title, description, incident_date, incident_time, 
                      location, latitude, longitude, severity_level, category, reported_by, reporter_contact_number, family_contact_name, family_contact_number, organization_id) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $title,
                $description,
                $incident_date,
                $incident_time,
                $location,
                $latitude !== '' ? $latitude : null,
                $longitude !== '' ? $longitude : null,
                $severity_level,
                $category,
                $reported_by,
                $reporter_contact_number ?: null,
                $family_contact_name ?: null,
                $family_contact_number ?: null,
                $organization_id
            ]);
            
            $report_id = $db->lastInsertId();

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
                // For guest users, we'll still send SMS notifications
                $currentUserRole = null;
                if (is_logged_in()) {
                    // Get current user's role to determine if SMS should be sent
                    $userQuery = "SELECT role FROM users WHERE id = ?";
                    $userStmt = $db->prepare($userQuery);
                    $userStmt->execute([$_SESSION['user_id']]);
                    $currentUser = $userStmt->fetch();
                    $currentUserRole = $currentUser['role'] ?? null;
                }
                
                error_log("=== REPORT CREATION SMS DEBUG ===");
                error_log("Report Creation - Report ID: {$report_id}");
                error_log("Report Creation - Current User Role: " . ($currentUserRole ?? 'Guest User'));
                error_log("Report Creation - Report Title: {$title}");
                
                // Send SMS for both guest users and responders
                if (!$currentUserRole || $currentUserRole === 'Responder') {
                    // Include SMS functionality
                    require_once '../sms.php';
                    
                    // 1. Send SMS to ORGANIZATION (department)
                    $orgQuery = "SELECT org_name, contact_number FROM organizations WHERE id = ?";
                    $orgStmt = $db->prepare($orgQuery);
                    $orgStmt->execute([$organization_id]);
                    $organization = $orgStmt->fetch();
                    
                    if ($organization && !empty($organization['contact_number'])) {
                        $orgSmsMessage = "MDRRMO-GLAN: New incident report #{$report_id} - {$title} ({$severity_level} severity) has been submitted to {$organization['org_name']}. Please check the system for details.";
                        
                        error_log("=== SENDING SMS TO ORGANIZATION ===");
                        error_log("SMS Recipient: ORGANIZATION - {$organization['contact_number']}");
                        error_log("SMS Message: {$orgSmsMessage}");
                        
                        $orgSmsResult = sendSMS($organization['contact_number'], $orgSmsMessage);
                        
                        if (!$orgSmsResult['success']) {
                            error_log("SMS notification to organization failed for report #{$report_id}: " . $orgSmsResult['error']);
                        } else {
                            error_log("SMS notification sent successfully to ORGANIZATION for report #{$report_id}");
                        }
                    } else {
                        error_log("No contact number found for organization ID: {$organization_id}");
                    }
                    
                    // 2. Send SMS to RESPONDER (confirmation) - only if logged in
                    if (is_logged_in()) {
                        $responderQuery = "SELECT name, contact_number FROM users WHERE id = ?";
                        $responderStmt = $db->prepare($responderQuery);
                        $responderStmt->execute([$_SESSION['user_id']]);
                        $responder = $responderStmt->fetch();
                        
                        if ($responder && !empty($responder['contact_number'])) {
                            $responderSmsMessage = "MDRRMO-GLAN: Your incident report #{$report_id} '{$title}' has been successfully submitted to {$organization['org_name']}. You will be notified of any updates.";
                            
                            error_log("=== SENDING SMS TO RESPONDER ===");
                            error_log("SMS Recipient: RESPONDER - {$responder['contact_number']}");
                            error_log("SMS Message: {$responderSmsMessage}");
                            
                            $responderSmsResult = sendSMS($responder['contact_number'], $responderSmsMessage);
                            
                            if (!$responderSmsResult['success']) {
                                error_log("SMS notification to responder failed for report #{$report_id}: " . $responderSmsResult['error']);
                            } else {
                                error_log("SMS notification sent successfully to RESPONDER for report #{$report_id}");
                            }
                        } else {
                            error_log("No contact number found for responder ID: {$_SESSION['user_id']}");
                        }
                    } else {
                        error_log("Guest user report created - no SMS confirmation sent to reporter");
                    }
                } else {
                    error_log("=== NO SMS SENT ===");
                    error_log("Reason: Report created by " . ($currentUser['role'] ?? 'Unknown') . " (not Responder)");
                    error_log("Only Responders trigger SMS notifications");
                }
                error_log("=== REPORT CREATION SMS DEBUG END ===");
            } catch (Exception $smsError) {
                // Don't fail the report creation if SMS fails
                error_log("SMS notification error for report #{$report_id}: " . $smsError->getMessage());
            }
            
            // Redirect to success page
            $redirect_url = "reports/success.php?id={$report_id}";
            if ($redirect === 'departments') {
                $redirect_url .= "&redirect=departments";
            }
            redirect($redirect_url);
            
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

<div class="container-fluid main-content">
    <div class="max-w-5xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Create Incident Report</h1>
                <p class="text-sm text-slate-500 mt-1">Provide details so responders can act quickly and accurately.</p>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header flex items-center gap-2">
                <i class="fas fa-file-alt text-slate-400"></i>
                <span>Incident Details</span>
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
                                    <label for="reported_by" class="form-label">Reported By *</label>
                                    <input type="text" class="form-control" id="reported_by" name="reported_by" 
                                           value="<?php echo isset($_POST['reported_by']) ? htmlspecialchars($_POST['reported_by']) : ''; ?>" 
                                           placeholder="Enter your name" required>
                                    <div class="invalid-feedback">
                                        Please provide your name.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="reporter_contact_number" class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" id="reporter_contact_number" name="reporter_contact_number" 
                                           value="<?php echo isset($_POST['reporter_contact_number']) ? htmlspecialchars($_POST['reporter_contact_number']) : ''; ?>" 
                                           placeholder="09XXXXXXXXX (11 digits)" pattern="09[0-9]{9}" 
                                           title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                                           maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                                    <div class="form-text">Optional - for SMS notifications about your report</div>
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
                                    <label for="incident_date" class="form-label">Report Date</label>
                                    <input type="text" class="form-control" id="incident_date" 
                                           value="<?php echo date('M d, Y'); ?>" readonly>
                                    <div class="form-text">Automatically set to current date</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="incident_time" class="form-label">Report Time</label>
                                    <input type="text" class="form-control" id="incident_time" 
                                           value="<?php echo date('g:i A'); ?>" readonly>
                                    <div class="form-text">Automatically set to current time</div>
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
                            <div class="form-text">
                                Type the address or landmark, then use the map below to drop a precise pin.
                            </div>
                            <div class="invalid-feedback">
                                Please provide the incident location.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt me-1"></i>Pin Location on Map (Optional but Recommended)
                            </label>
                            <div class="flex flex-wrap items-stretch gap-2 mb-2">
                                <button type="button" id="btn-share-location" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 transition shrink-0">
                                    <i class="fas fa-location-crosshairs text-sky-600"></i>
                                    <span>Use my current location</span>
                                </button>
                                <p id="geo-status" class="flex-1 min-w-[200px] text-sm text-slate-600 self-center m-0" role="status" aria-live="polite"></p>
                            </div>
                            <p class="form-text text-xs sm:text-sm mb-2">
                                <i class="fas fa-mobile-alt me-1"></i>
                                On phones and tablets, tap the button above — your browser will ask permission to use your location, then the map will center on your position.
                            </p>
                            <div id="incident-map" style="height: 320px; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(0,0,0,0.12);"></div>
                            <input type="hidden" id="latitude" name="latitude" value="<?php echo isset($_POST['latitude']) ? htmlspecialchars($_POST['latitude']) : ''; ?>">
                            <input type="hidden" id="longitude" name="longitude" value="<?php echo isset($_POST['longitude']) ? htmlspecialchars($_POST['longitude']) : ''; ?>">
                            <div class="form-text">
                                Or click on the map to drop or move the pin. You can use both the button and map together.
                            </div>
                        </div>

                        <div class="mb-3">
                            <h5 class="mb-2">
                                <i class="fas fa-user-friends me-1"></i>Family / Emergency Contact (Optional)
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="family_contact_name" class="form-label">Contact Name</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="family_contact_name"
                                        name="family_contact_name"
                                        value="<?php echo isset($_POST['family_contact_name']) ? htmlspecialchars($_POST['family_contact_name']) : ''; ?>"
                                        placeholder="Name of family or emergency contact person"
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label for="family_contact_number" class="form-label">Contact Number</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="family_contact_number"
                                        name="family_contact_number"
                                        value="<?php echo isset($_POST['family_contact_number']) ? htmlspecialchars($_POST['family_contact_number']) : ''; ?>"
                                        placeholder="09XXXXXXXXX (11 digits)"
                                        pattern="09[0-9]{9}"
                                        title="Enter exactly 11 digits starting with 09 (e.g., 09123456789)"
                                        maxlength="11"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                    >
                                    <div class="form-text">
                                        They may receive SMS updates when the incident is in progress or resolved.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="photos" class="form-label">
                                <i class="fas fa-camera me-1"></i>Photos (Optional)
                            </label>
                            <input type="file" class="form-control" id="photos" name="photos[]" multiple 
                                   accept="image/jpeg,image/jpg,image/png">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                You can select and upload multiple photos at once. Maximum 5MB per file. 
                                Allowed formats: JPG, JPEG, PNG
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    Tip: Hold Ctrl (or Cmd on Mac) to select multiple files, or drag and drop multiple files
                                </small>
                            </div>
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
                        
                        <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-2">
                            <a href="<?php echo BASE_URL; ?>dashboard/responder.php" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                                <i class="fas fa-times text-slate-400"></i>Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                                <i class="fas fa-save"></i>Create Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
    </div>
</div>

<script>
// Map + geolocation for incident pin
document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('incident-map');
    if (mapElement && typeof L !== 'undefined') {
        const baseUrl = '<?php echo BASE_URL; ?>';
        const defaultCenter = [6.0523, 125.2896]; // Default to Glan, Sarangani (can adjust as needed)
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const locationInput = document.getElementById('location');

        let map = L.map('incident-map');

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let marker = null;
        let reverseGeocodeTimeout = null;

        function setMarker(lat, lng) {
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }
            latInput.value = lat.toFixed(8);
            lngInput.value = lng.toFixed(8);

            // Debounced reverse geocoding to update the location text field
            if (reverseGeocodeTimeout) {
                clearTimeout(reverseGeocodeTimeout);
            }
            reverseGeocodeTimeout = setTimeout(function() {
                try {
                    // Call local reverse geocoding proxy to avoid CORS issues
                    const url = baseUrl + 'reverse_geocode.php?lat=' +
                        encodeURIComponent(lat) +
                        '&lng=' +
                        encodeURIComponent(lng);
                    fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                        .then(function(response) { return response.ok ? response.json() : null; })
                        .then(function(data) {
                            if (!data || !data.success || !data.display_name) {
                                return;
                            }
                            if (locationInput) {
                                // Always update the location field when pin moves
                                locationInput.value = data.display_name;
                            }
                        })
                        .catch(function() {
                            // Silent fail - pin still works even if reverse geocode fails
                        });
                } catch (e) {
                    // Silent fail
                }
            }, 400);
        }

        // Initialize center
        const parsedInitialLat = parseFloat(latInput.value);
        const parsedInitialLng = parseFloat(lngInput.value);
        const hasInitial =
            latInput.value !== '' &&
            lngInput.value !== '' &&
            !Number.isNaN(parsedInitialLat) &&
            !Number.isNaN(parsedInitialLng);

        if (hasInitial) {
            map.setView([parsedInitialLat, parsedInitialLng], 16);
            setMarker(parsedInitialLat, parsedInitialLng);
        } else {
            map.setView(defaultCenter, 13);
        }

        map.on('click', function(e) {
            setMarker(e.latlng.lat, e.latlng.lng);
        });

        function applySharedLocation(lat, lng) {
            map.setView([lat, lng], 17);
            setMarker(lat, lng);
        }

        const btnShare = document.getElementById('btn-share-location');
        const geoStatus = document.getElementById('geo-status');
        if (btnShare) {
            btnShare.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    if (geoStatus) {
                        geoStatus.textContent = 'Your browser does not support geolocation.';
                        geoStatus.classList.remove('text-emerald-700', 'text-slate-600');
                        geoStatus.classList.add('text-amber-800');
                    }
                    return;
                }
                btnShare.disabled = true;
                if (geoStatus) {
                    geoStatus.textContent = 'Waiting for location permission…';
                    geoStatus.classList.remove('text-emerald-700', 'text-amber-800');
                    geoStatus.classList.add('text-slate-600');
                }
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        btnShare.disabled = false;
                        const lat = pos.coords.latitude;
                        const lng = pos.coords.longitude;
                        applySharedLocation(lat, lng);
                        if (geoStatus) {
                            geoStatus.textContent = 'Location placed on the map. Adjust the pin if needed.';
                            geoStatus.classList.remove('text-slate-600', 'text-amber-800');
                            geoStatus.classList.add('text-emerald-700');
                        }
                    },
                    function(err) {
                        btnShare.disabled = false;
                        let msg = 'Could not read your location.';
                        if (err && err.code === 1) {
                            msg = 'Permission denied. Allow location for this site in your browser or phone settings, then try again.';
                        } else if (err && err.code === 2) {
                            msg = 'Location unavailable. Try again outdoors or check GPS.';
                        } else if (err && err.code === 3) {
                            msg = 'Location request timed out. Try again.';
                        }
                        if (geoStatus) {
                            geoStatus.textContent = msg;
                            geoStatus.classList.remove('text-slate-600', 'text-emerald-700');
                            geoStatus.classList.add('text-amber-800');
                        }
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 20000,
                        maximumAge: 0
                    }
                );
            });
        }
    }
}
);

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

// Photo upload preview functionality
document.getElementById('photos').addEventListener('change', function(e) {
    const files = e.target.files;
    const fileList = document.getElementById('file-list');
    
    // Remove existing file list if it exists
    if (fileList) {
        fileList.remove();
    }
    
    if (files.length > 0) {
        // Create file list container
        const container = document.createElement('div');
        container.id = 'file-list';
        container.className = 'mt-2';
        
        const header = document.createElement('div');
        header.className = 'alert alert-info';
        header.innerHTML = `<i class="fas fa-images me-1"></i>Selected ${files.length} file(s):`;
        container.appendChild(header);
        
        const list = document.createElement('ul');
        list.className = 'list-group list-group-flush';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const listItem = document.createElement('li');
            listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
            
            const fileInfo = document.createElement('div');
            fileInfo.innerHTML = `
                <i class="fas fa-file-image me-2 text-primary"></i>
                <strong>${file.name}</strong>
                <small class="text-muted ms-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
            `;
            
            const status = document.createElement('span');
            if (file.size <= 5 * 1024 * 1024) {
                status.className = 'badge bg-success';
                status.textContent = 'Ready';
            } else {
                status.className = 'badge bg-danger';
                status.textContent = 'Too large';
            }
            
            listItem.appendChild(fileInfo);
            listItem.appendChild(status);
            list.appendChild(listItem);
        }
        
        container.appendChild(list);
        e.target.parentNode.appendChild(container);
    }
});
</script>

<?php include '../views/footer.php'; ?>
