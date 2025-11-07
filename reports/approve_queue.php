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
$assigned_to = isset($_POST['assigned_to']) && !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

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
        assigned_to INT NULL DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        approved_at TIMESTAMP NULL DEFAULT NULL,
        KEY idx_report_queue_org (organization_id),
        KEY idx_report_queue_status (status),
        KEY idx_report_queue_report (report_id),
        KEY idx_report_queue_assigned (assigned_to)
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

    // Validate assigned member if provided
    if ($assigned_to !== null) {
        $memberCheck = $db->prepare("SELECT id FROM organization_members WHERE id = ? AND organization_id = ?");
        $memberCheck->execute([$assigned_to, $_SESSION['organization_id']]);
        if (!$memberCheck->fetch()) {
            $db->rollBack();
            redirect('reports/organization.php?error=Invalid+member+selected');
        }
    }

    // Get next priority number within this organization
    $stmt = $db->prepare("SELECT MAX(priority_number) AS max_p FROM report_queue WHERE organization_id = ? AND status = 'Approved'");
    $stmt->execute([$queue['organization_id']]);
    $row = $stmt->fetch();
    $next_priority = (int)($row['max_p'] ?? 0) + 1;

    // Approve and assign priority and member
    $update = $db->prepare("UPDATE report_queue SET status = 'Approved', priority_number = ?, assigned_to = ?, approved_at = NOW() WHERE id = ?");
    $update->execute([$next_priority, $assigned_to, $queue_id]);

    // Update the linked incident report status to In Progress
    $updReport = $db->prepare("UPDATE incident_reports SET status = 'In Progress' WHERE id = ?");
    $updReport->execute([$queue['report_id']]);

    // Log an incident update visible to responder
    $assignedMemberName = '';
    if ($assigned_to !== null) {
        $memberQuery = $db->prepare("SELECT name FROM organization_members WHERE id = ?");
        $memberQuery->execute([$assigned_to]);
        $member = $memberQuery->fetch();
        $assignedMemberName = $member ? $member['name'] : '';
    }
    
    $msg = "Report approved by organization. Assigned priority number #" . $next_priority . ". Status set to In Progress.";
    if ($assignedMemberName) {
        $msg .= " Assigned to: " . $assignedMemberName . ".";
    }
    $insUpdate = $db->prepare("INSERT INTO incident_updates (report_id, update_text, updated_by) VALUES (?, ?, ?)");
    $insUpdate->execute([$queue['report_id'], $msg, $_SESSION['user_id']]);

    $db->commit();
    
    // Send SMS notification for approval (ONLY to responders, NOT to departments)
    try {
        // Get responder details for SMS notification
        $detailsQuery = "SELECT ir.reported_by as reporter_name, ir.reporter_contact_number as reporter_contact,
                               ir.title, ir.severity_level, ir.organization_id, o.org_name, o.contact_number as org_contact
                        FROM incident_reports ir 
                        LEFT JOIN organizations o ON ir.organization_id = o.id
                        WHERE ir.id = ?";
        $detailsStmt = $db->prepare($detailsQuery);
        $detailsStmt->execute([$queue['report_id']]);
        $details = $detailsStmt->fetch();
        
        // Debug: Log the details retrieved
        error_log("=== APPROVAL SMS DEBUG START ===");
        error_log("Approval SMS Debug - Report ID: {$queue['report_id']}");
        error_log("Approval SMS Debug - Reporter Name: " . ($details['reporter_name'] ?? 'NULL'));
        error_log("Approval SMS Debug - Reporter Contact: " . ($details['reporter_contact'] ?? 'NULL'));
        error_log("Approval SMS Debug - Organization: " . ($details['org_name'] ?? 'NULL'));
        error_log("Approval SMS Debug - Organization Contact: " . ($details['org_contact'] ?? 'NULL'));
        error_log("Approval SMS Debug - Report Title: " . ($details['title'] ?? 'NULL'));
        
        // Include SMS functionality
        require_once '../sms.php';
        
        // CRITICAL: Send SMS ONLY to responder (reporter), NEVER to organization
        if ($details && !empty($details['reporter_contact'])) {
            $smsMessage = "MDRRMO-GLAN: Your incident report #{$queue['report_id']} '{$details['title']}' has been approved and assigned priority #{$next_priority}. Status: In Progress.";
            
            // Add assigned member information to SMS if available
            if ($assignedMemberName) {
                $smsMessage .= " Assigned member {$assignedMemberName} will assist you with this report.";
            }
            
            error_log("=== SENDING SMS TO RESPONDER ONLY ===");
            error_log("SMS Recipient: RESPONDER - {$details['reporter_contact']}");
            error_log("SMS Message: {$smsMessage}");
            error_log("CONFIRMATION: This SMS is going to RESPONDER, NOT to organization");
            
            $smsResult = sendSMS($details['reporter_contact'], $smsMessage);
            
            if (!$smsResult['success']) {
                error_log("SMS notification failed for responder report #{$queue['report_id']} approval: " . $smsResult['error']);
            } else {
                error_log("SMS notification sent successfully to RESPONDER for report #{$queue['report_id']} approval");
            }
        } else {
            error_log("=== NO SMS SENT - RESPONDER HAS NO CONTACT NUMBER ===");
            error_log("No contact number found for responder of report #{$queue['report_id']} - Reporter: " . ($details['reporter_name'] ?? 'Unknown'));
            error_log("DEBUG: Details array: " . print_r($details, true));
            error_log("IMPORTANT: No SMS will be sent to organization during approval");
        }
        error_log("=== APPROVAL SMS DEBUG END ===");
        
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


