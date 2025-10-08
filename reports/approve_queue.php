<?php
/**
 * Approve a queued report and assign next priority number
 */

require_once '../config/config.php';
require_role(['Organization Account']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('reports/organization.php');
}

$queue_id = isset($_POST['queue_id']) ? (int)$_POST['queue_id'] : 0;
if ($queue_id <= 0) {
    redirect('reports/organization.php?error=Invalid+queue+item');
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Ensure report_queue table exists (in case migrations not run yet)
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

    // Load queue item and validate organization ownership
    $stmt = $db->prepare("SELECT * FROM report_queue WHERE id = ? FOR UPDATE");
    $stmt->execute([$queue_id]);
    $queue = $stmt->fetch();
    if (!$queue || (int)$queue['organization_id'] !== (int)$_SESSION['organization_id']) {
        $db->rollBack();
        redirect('reports/organization.php?error=Queue+item+not+found');
    }

    if ($queue['status'] !== 'Waiting') {
        $db->rollBack();
        redirect('reports/organization.php?error=Queue+item+already+processed');
    }

    // Get next priority number within this organization
    $stmt = $db->prepare("SELECT MAX(priority_number) AS max_p FROM report_queue WHERE organization_id = ? AND status = 'Approved'");
    $stmt->execute([$queue['organization_id']]);
    $row = $stmt->fetch();
    $next_priority = (int)($row['max_p'] ?? 0) + 1;

    // Approve and assign priority
    $update = $db->prepare("UPDATE report_queue SET status = 'Approved', priority_number = ?, approved_at = NOW() WHERE id = ?");
    $update->execute([$next_priority, $queue_id]);

    // Update the linked incident report status to In Progress
    $updReport = $db->prepare("UPDATE incident_reports SET status = 'In Progress' WHERE id = ?");
    $updReport->execute([$queue['report_id']]);

    // Log an incident update visible to responder
    $msg = "Report approved by organization. Assigned priority number #" . $next_priority . ". Status set to In Progress.";
    $insUpdate = $db->prepare("INSERT INTO incident_updates (report_id, update_text, updated_by) VALUES (?, ?, ?)");
    $insUpdate->execute([$queue['report_id'], $msg, $_SESSION['user_id']]);

    $db->commit();
    
    // Send SMS notification for approval (ONLY to responders, NOT to departments)
    try {
        // Get responder details for SMS notification
        $detailsQuery = "SELECT u.name as reporter_name, u.email as reporter_email, u.contact_number as reporter_contact,
                               ir.title, ir.severity_level
                        FROM incident_reports ir 
                        LEFT JOIN users u ON ir.reported_by = u.id 
                        WHERE ir.id = ?";
        $detailsStmt = $db->prepare($detailsQuery);
        $detailsStmt->execute([$queue['report_id']]);
        $details = $detailsStmt->fetch();
        
        // Debug: Log the details retrieved
        error_log("Approval SMS Debug - Report ID: {$queue['report_id']}");
        error_log("Approval SMS Debug - Reporter Name: " . ($details['reporter_name'] ?? 'NULL'));
        error_log("Approval SMS Debug - Reporter Contact: " . ($details['reporter_contact'] ?? 'NULL'));
        error_log("Approval SMS Debug - Report Title: " . ($details['title'] ?? 'NULL'));
        error_log("Approval SMS Debug - Full Details Array: " . print_r($details, true));
        
        // Include SMS functionality
        require_once '../sms.php';
        
        // Send SMS ONLY to responder (reporter) about approval
        if ($details && !empty($details['reporter_contact'])) {
            $smsMessage = "MDRRMO-GLAN: Your incident report #{$queue['report_id']} '{$details['title']}' has been approved and assigned priority #{$next_priority}. Status: In Progress.";
            error_log("Sending SMS to responder: {$details['reporter_contact']} - Message: {$smsMessage}");
            
            // Double-check: Make sure we're sending to the responder, not the organization
            error_log("CONFIRMATION: Sending SMS to RESPONDER contact number: {$details['reporter_contact']}");
            
            $smsResult = sendSMS($details['reporter_contact'], $smsMessage);
            
            if (!$smsResult['success']) {
                error_log("SMS notification failed for responder report #{$queue['report_id']} approval: " . $smsResult['error']);
            } else {
                error_log("SMS notification sent successfully to responder for report #{$queue['report_id']} approval");
            }
        } else {
            error_log("❌ CRITICAL: No contact number found for responder of report #{$queue['report_id']} - Reporter: " . ($details['reporter_name'] ?? 'Unknown'));
            error_log("❌ This is why SMS is not being sent to responder!");
            error_log("DEBUG: Details array: " . print_r($details, true));
            
            // TEMPORARY FIX: Send SMS to a default responder number for testing
            // REMOVE THIS AFTER YOU ADD REAL CONTACT NUMBERS TO RESPONDERS
            $defaultResponderNumber = "09123456789"; // CHANGE THIS TO A REAL NUMBER FOR TESTING
            $smsMessage = "MDRRMO-GLAN: Your incident report #{$queue['report_id']} '{$details['title']}' has been approved and assigned priority #{$next_priority}. Status: In Progress.";
            error_log("⚠️ TEMPORARY: Sending SMS to default responder number: {$defaultResponderNumber}");
            
            $smsResult = sendSMS($defaultResponderNumber, $smsMessage);
            if ($smsResult['success']) {
                error_log("✅ TEMPORARY SMS sent successfully to default number");
            } else {
                error_log("❌ TEMPORARY SMS failed: " . $smsResult['error']);
            }
        }
        
    } catch (Exception $smsError) {
        // Don't fail the approval if SMS fails
        error_log("SMS notification error for report #{$queue['report_id']} approval: " . $smsError->getMessage());
    }
    
    redirect('reports/organization.php?approved=1&priority=' . $next_priority);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    redirect('reports/organization.php?error=' . urlencode('Failed to approve queue item'));
}


