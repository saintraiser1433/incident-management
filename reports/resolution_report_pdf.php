<?php
/**
 * Incident Resolution Report - PDF Export
 */

require_once '../config/config.php';
require_once '../vendor/autoload.php';

use Mpdf\Mpdf;

$report_id = $_GET['id'] ?? 0;

if (!$report_id) {
    redirect('index.php?error=invalid_report');
}

require_login();

$database = new Database();
$db = $database->getConnection();

// Load core report details including org and queue info
$query = "SELECT ir.*, ir.reported_by as reporter_name, ir.reporter_contact_number,
                 ir.family_contact_name, ir.family_contact_number, ir.resolution_notes,
                 o.org_name, o.org_type, rq.priority_number, rq.assigned_to,
                 om.name as assigned_member_name
          FROM incident_reports ir 
          LEFT JOIN organizations o ON ir.organization_id = o.id 
          LEFT JOIN report_queue rq ON rq.report_id = ir.id 
          LEFT JOIN organization_members om ON rq.assigned_to = om.id
          WHERE ir.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$report_id]);
$report = $stmt->fetch();

if (!$report) {
    redirect('index.php?error=report_not_found');
}

// Only allow Admin or owning organization to view
if ($_SESSION['user_role'] === 'Organization Account' && $report['organization_id'] != $_SESSION['organization_id']) {
    redirect('index.php?error=access_denied');
}

// Only generate for Resolved / Closed
if (!in_array($report['status'], ['Resolved', 'Closed'], true)) {
    redirect('view.php?id=' . urlencode($report_id) . '&error=not_resolved');
}

// Load photos
$photoStmt = $db->prepare("SELECT * FROM incident_photos WHERE report_id = ? ORDER BY uploaded_at");
$photoStmt->execute([$report_id]);
$photos = $photoStmt->fetchAll();

// Load witnesses
$witnessStmt = $db->prepare("SELECT * FROM incident_witnesses WHERE report_id = ? ORDER BY created_at");
$witnessStmt->execute([$report_id]);
$witnesses = $witnessStmt->fetchAll();

// Load updates in chronological order
$updatesStmt = $db->prepare("
    SELECT iu.*, u.name AS updated_by_name
    FROM incident_updates iu
    LEFT JOIN users u ON iu.updated_by = u.id
    WHERE iu.report_id = ?
    ORDER BY iu.created_at ASC
");
$updatesStmt->execute([$report_id]);
$updates = $updatesStmt->fetchAll();

// Determine resolution date (first time status changed to Resolved/Closed)
$resolutionDate = null;
foreach ($updates as $u) {
    if (strpos($u['update_text'], "Status changed from") !== false &&
        (strpos($u['update_text'], "to 'Resolved'") !== false || strpos($u['update_text'], "to 'Closed'") !== false)) {
        $resolutionDate = $u['created_at'];
        break;
    }
}
if (!$resolutionDate) {
    $resolutionDate = $report['created_at'];
}

// Build simple HTML for PDF (no external CSS)
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Resolution Report #<?php echo (int)$report['id']; ?></title>
    <style>
        body { font-family: sans-serif; font-size: 11pt; }
        h1, h2, h3, h4, h5 { margin: 0 0 6px; }
        .header { border-bottom: 1px solid #999; margin-bottom: 12px; padding-bottom: 6px; }
        .section { margin-bottom: 10px; }
        .label { font-weight: bold; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #ccc; padding: 4px 6px; font-size: 10pt; }
        .small { font-size: 9pt; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <h2><?php echo htmlspecialchars(APP_NAME); ?></h2>
        <div class="small">Incident Resolution Report</div>
        <div style="text-align:right;">
            <span class="label">Report #:</span> <?php echo (int)$report['id']; ?><br>
            <span class="label">Status:</span> <?php echo htmlspecialchars($report['status']); ?>
        </div>
    </div>

    <div class="section">
        <h3>Incident Details</h3>
        <div><span class="label">Title:</span> <?php echo htmlspecialchars($report['title']); ?></div>
        <div>
            <span class="label">Date &amp; Time:</span>
            <?php echo format_date($report['incident_date']); ?>
            at <?php echo date('g:i A', strtotime($report['incident_time'])); ?>
        </div>
        <div><span class="label">Location:</span> <?php echo htmlspecialchars($report['location']); ?></div>
        <div><span class="label">Category:</span> <?php echo htmlspecialchars($report['category']); ?></div>
        <div><span class="label">Severity:</span> <?php echo htmlspecialchars($report['severity_level']); ?></div>
    </div>

    <div class="section">
        <h3>Parties Involved</h3>
        <div>
            <span class="label">Assigned Organization:</span>
            <?php echo htmlspecialchars($report['org_name']); ?>
            (<?php echo htmlspecialchars($report['org_type']); ?>)
        </div>
        <?php if (!empty($report['assigned_member_name'])): ?>
            <div><span class="label">Assigned Member:</span> <?php echo htmlspecialchars($report['assigned_member_name']); ?></div>
        <?php endif; ?>
        <div>
            <span class="label">Reporter:</span>
            <?php echo htmlspecialchars($report['reporter_name']); ?>
            <?php if (!empty($report['reporter_contact_number'])): ?>
                (<?php echo htmlspecialchars($report['reporter_contact_number']); ?>)
            <?php endif; ?>
        </div>
        <?php if (!empty($report['family_contact_name']) || !empty($report['family_contact_number'])): ?>
            <div>
                <span class="label">Family / Emergency Contact:</span>
                <?php echo htmlspecialchars($report['family_contact_name'] ?? ''); ?>
                <?php if (!empty($report['family_contact_number'])): ?>
                    (<?php echo htmlspecialchars($report['family_contact_number']); ?>)
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h3>Incident Description</h3>
        <div><?php echo nl2br(htmlspecialchars($report['description'])); ?></div>
    </div>

    <div class="section">
        <h3>Resolution Summary</h3>
        <div>
            <?php
            if (!empty($report['resolution_notes'])) {
                echo nl2br(htmlspecialchars($report['resolution_notes']));
            } else {
                $lastText = '';
                if (!empty($updates)) {
                    $lastText = $updates[count($updates) - 1]['update_text'];
                }
                echo nl2br(htmlspecialchars($lastText ?: 'No dedicated resolution notes recorded.'));
            }
            ?>
        </div>
    </div>

    <div class="section">
        <h3>Timeline Summary</h3>
        <div><span class="label">Reported On:</span> <?php echo format_datetime($report['created_at']); ?></div>
        <div><span class="label">Resolved On:</span> <?php echo format_datetime($resolutionDate); ?></div>
        <div><span class="label">Total Updates:</span> <?php echo count($updates); ?></div>
    </div>

    <?php if (!empty($updates)): ?>
        <div class="section">
            <h3>Detailed Updates Timeline</h3>
            <?php foreach ($updates as $update): ?>
                <div class="small">
                    <?php echo format_datetime($update['created_at']); ?>
                    • <?php echo htmlspecialchars($update['updated_by_name'] ?? 'System'); ?>
                </div>
                <div><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></div>
                <br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($witnesses)): ?>
        <div class="section">
            <h3>Witnesses</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Recorded On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($witnesses as $w): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($w['witness_name']); ?></td>
                            <td><?php echo htmlspecialchars($w['witness_contact']); ?></td>
                            <td><?php echo format_datetime($w['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="section">
        <h3>Sign-off</h3>
        <p class="label">Prepared By:</p>
        <div style="border-bottom: 1px solid #000; height: 24px; margin-bottom: 4px;"></div>
        <div class="small">Name &amp; Signature</div>
        <p class="small" style="margin-top:8px;">
            Date Generated: <?php echo format_datetime(date('Y-m-d H:i:s')); ?>
        </p>
    </div>
</body>
</html>
<?php

$html = ob_get_clean();

$mpdf = new Mpdf([
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 15,
]);

$mpdf->SetTitle('Resolution Report #' . (int)$report['id']);
$mpdf->WriteHTML($html);

$filename = 'resolution_report_' . (int)$report['id'] . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);

exit;

