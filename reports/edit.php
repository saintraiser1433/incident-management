<?php
/**
 * Edit Incident Report
 * Incident Report Management System
 */

require_once '../config/config.php';
require_login();

$report_id = $_GET['id'] ?? 0;

if (!$report_id) {
    redirect('index.php?error=invalid_report');
}

$database = new Database();
$db = $database->getConnection();

// Get report details
$query = "SELECT * FROM incident_reports WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$report_id]);
$report = $stmt->fetch();

if (!$report) {
    redirect('index.php?error=report_not_found');
}

// Check access permissions
if ($_SESSION['user_role'] == 'Organization Account' && $report['organization_id'] != $_SESSION['organization_id']) {
    redirect('index.php?error=access_denied');
}

if ($_SESSION['user_role'] == 'Responder' && $report['reported_by'] != $_SESSION['user_id']) {
    redirect('index.php?error=access_denied');
}

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
    $status = sanitize_input($_POST['status']);
    
    if (empty($title) || empty($description) || empty($incident_date) || empty($incident_time) || 
        empty($location) || empty($severity_level) || empty($category) || empty($status)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            // Enforce status transition rules
            $currentStatus = $report['status'];
            if ($currentStatus === 'Resolved') {
                throw new Exception('This report is Resolved and can no longer be edited.');
            }
            if ($currentStatus === 'Pending' && $status !== 'Pending') {
                throw new Exception('Status cannot be changed from Pending here. Approve the queue to start In Progress.');
            }
            if ($currentStatus === 'In Progress') {
                $allowed = ['In Progress','Resolved','Closed'];
                if (!in_array($status, $allowed, true)) {
                    throw new Exception('Invalid status change. From In Progress you can only set Resolved or Closed.');
                }
            }
            $db->beginTransaction();
            
            // Update incident report
            $query = "UPDATE incident_reports SET 
                      title = ?, description = ?, incident_date = ?, incident_time = ?, 
                      location = ?, severity_level = ?, category = ?, status = ? 
                      WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$title, $description, $incident_date, $incident_time, 
                           $location, $severity_level, $category, $status, $report_id]);
            
            // Add update if status changed
            if ($status != $report['status']) {
                $update_text = "Status changed from '{$report['status']}' to '{$status}'";
                $query = "INSERT INTO incident_updates (report_id, update_text, updated_by) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$report_id, $update_text, $_SESSION['user_id']]);
            }
            
            // Add update if other fields changed
            $changes = [];
            if ($title != $report['title']) $changes[] = "Title updated";
            if ($description != $report['description']) $changes[] = "Description updated";
            if ($severity_level != $report['severity_level']) $changes[] = "Severity changed from '{$report['severity_level']}' to '{$severity_level}'";
            if ($category != $report['category']) $changes[] = "Category changed from '{$report['category']}' to '{$category}'";
            
            if (!empty($changes)) {
                $update_text = implode(', ', $changes);
                $query = "INSERT INTO incident_updates (report_id, update_text, updated_by) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$report_id, $update_text, $_SESSION['user_id']]);
            }
            
            $db->commit();
            
            // Log audit
            log_audit('UPDATE', 'incident_reports', $report_id);
            
            // Send SMS notification for status changes (ONLY to responders, NOT to departments)
            if ($status != $report['status']) {
                try {
                    // Get reporter details for SMS notification
                    $detailsQuery = "SELECT u.name as reporter_name, u.email as reporter_email, u.contact_number as reporter_contact
                                    FROM incident_reports ir 
                                    LEFT JOIN users u ON ir.reported_by = u.id 
                                    WHERE ir.id = ?";
                    $detailsStmt = $db->prepare($detailsQuery);
                    $detailsStmt->execute([$report_id]);
                    $details = $detailsStmt->fetch();
                    
                    // Debug: Log the details retrieved
                    error_log("=== STATUS CHANGE SMS DEBUG START ===");
                    error_log("Status Change SMS Debug - Report ID: {$report_id}");
                    error_log("Status Change SMS Debug - Reporter Name: " . ($details['reporter_name'] ?? 'NULL'));
                    error_log("Status Change SMS Debug - Reporter Contact: " . ($details['reporter_contact'] ?? 'NULL'));
                    error_log("Status Change SMS Debug - New Status: {$status}");
                    
                    // Include SMS functionality
                    require_once '../sms.php';
                    
                    // CRITICAL: Send SMS ONLY to responder (reporter), NEVER to organization
                    if ($details && !empty($details['reporter_contact'])) {
                        $smsMessage = "MDRRMO-GLAN: Your incident report #{$report_id} status has been updated to '{$status}' by {$_SESSION['user_name']}. Please check the system for details.";
                        
                        error_log("=== SENDING SMS TO RESPONDER ONLY ===");
                        error_log("SMS Recipient: RESPONDER - {$details['reporter_contact']}");
                        error_log("SMS Message: {$smsMessage}");
                        error_log("CONFIRMATION: This SMS is going to RESPONDER, NOT to organization");
                        
                        $smsResult = sendSMS($details['reporter_contact'], $smsMessage);
                        
                        if (!$smsResult['success']) {
                            error_log("SMS notification failed for responder report #{$report_id} status update: " . $smsResult['error']);
                        } else {
                            error_log("SMS notification sent successfully to RESPONDER for report #{$report_id} status update");
                        }
                    } else {
                        error_log("=== NO SMS SENT - RESPONDER HAS NO CONTACT NUMBER ===");
                        error_log("No contact number found for responder of report #{$report_id} - Reporter: " . ($details['reporter_name'] ?? 'Unknown'));
                        error_log("DEBUG: Details array: " . print_r($details, true));
                        error_log("IMPORTANT: No SMS will be sent to organization during status change");
                    }
                    error_log("=== STATUS CHANGE SMS DEBUG END ===");
                    
                } catch (Exception $smsError) {
                    // Don't fail the update if SMS fails
                    error_log("SMS notification error for report #{$report_id} status update: " . $smsError->getMessage());
                }
            }
            
            $success_message = 'Incident report updated successfully!';
            
            // Refresh report data
            $query = "SELECT * FROM incident_reports WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$report_id]);
            $report = $stmt->fetch();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = 'Error updating incident report: ' . $e->getMessage();
        }
    }
}

$page_title = 'Edit Report #' . $report['id'] . ' - ' . APP_NAME;
include '../views/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-edit me-2"></i>Edit Report #<?php echo $report['id']; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="view.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-eye me-1"></i>View Report
                        </a>
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
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>Report Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($report['title']); ?>" required>
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
                                        <option value="Fire" <?php echo $report['category'] == 'Fire' ? 'selected' : ''; ?>>Fire</option>
                                        <option value="Accident" <?php echo $report['category'] == 'Accident' ? 'selected' : ''; ?>>Accident</option>
                                        <option value="Security" <?php echo $report['category'] == 'Security' ? 'selected' : ''; ?>>Security</option>
                                        <option value="Medical" <?php echo $report['category'] == 'Medical' ? 'selected' : ''; ?>>Medical</option>
                                        <option value="Emergency" <?php echo $report['category'] == 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                                        <option value="Other" <?php echo $report['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Please select a category.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($report['description']); ?></textarea>
                            <div class="invalid-feedback">
                                Please provide a description of the incident.
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="incident_date" class="form-label">Date *</label>
                                    <input type="date" class="form-control" id="incident_date" name="incident_date" 
                                           value="<?php echo $report['incident_date']; ?>" required>
                                    <div class="invalid-feedback">
                                        Please select the incident date.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="incident_time" class="form-label">Time *</label>
                                    <input type="time" class="form-control" id="incident_time" name="incident_time" 
                                           value="<?php echo $report['incident_time']; ?>" required>
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
                                        <option value="Low" <?php echo $report['severity_level'] == 'Low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="Medium" <?php echo $report['severity_level'] == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="High" <?php echo $report['severity_level'] == 'High' ? 'selected' : ''; ?>>High</option>
                                        <option value="Critical" <?php echo $report['severity_level'] == 'Critical' ? 'selected' : ''; ?>>Critical</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Please select a severity level.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <?php
                                    $statusOptions = [];
                                    if ($report['status'] === 'Resolved') {
                                        $statusOptions = ['Resolved' => 'Resolved'];
                                    } elseif ($report['status'] === 'Pending') {
                                        $statusOptions = ['Pending' => 'Pending'];
                                    } else { // In Progress or Closed
                                        $statusOptions = [
                                            'In Progress' => 'In Progress',
                                            'Resolved' => 'Resolved',
                                            'Closed' => 'Closed'
                                        ];
                                    }
                                    ?>
                                    <select class="form-select" id="status" name="status" required <?php echo $report['status'] === 'Resolved' ? 'disabled' : ''; ?>>
                                        <?php foreach ($statusOptions as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo $report['status'] == $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($report['status'] === 'Resolved'): ?>
                                        <div class="form-text text-muted">Resolved reports cannot be modified.</div>
                                    <?php elseif ($report['status'] === 'Pending'): ?>
                                        <div class="form-text text-muted">Approve the queue to move this to In Progress.</div>
                                    <?php endif; ?>
                                    <div class="invalid-feedback">
                                        Please select a status.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($report['location']); ?>" required>
                            <div class="invalid-feedback">
                                Please provide the incident location.
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="view.php?id=<?php echo $report['id']; ?>" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../views/footer.php'; ?>
