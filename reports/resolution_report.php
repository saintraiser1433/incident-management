<?php
/**
 * Incident Resolution Report (Printable)
 * Incident Report Management System
 */

require_once '../config/config.php';

$report_id = $_GET['id'] ?? 0;

if (!$report_id) {
    redirect('index.php?error=invalid_report');
}

// Require login; guests cannot access the formal resolution report
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

// Only allow Admin, owning organization account, or assigned organization member to view
if ($_SESSION['user_role'] === 'Organization Account' && $report['organization_id'] != $_SESSION['organization_id']) {
    redirect('index.php?error=access_denied');
}

if ($_SESSION['user_role'] === 'Organization Member') {
    // Verify this member belongs to the organization and is assigned to this report
    try {
        $memberStmt = $db->prepare("SELECT id FROM organization_members WHERE user_id = ? AND organization_id = ?");
        $memberStmt->execute([$_SESSION['user_id'], $report['organization_id']]);
        $member = $memberStmt->fetch();

        if (!$member || (int)$report['assigned_to'] !== (int)$member['id']) {
            redirect('index.php?error=access_denied');
        }
    } catch (Exception $e) {
        error_log("Error checking organization member access to resolution report: " . $e->getMessage());
        redirect('index.php?error=access_denied');
    }
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
    // fallback to report created_at
    $resolutionDate = $report['created_at'];
}

$page_title = 'Resolution Report #' . $report['id'] . ' - ' . APP_NAME;
include '../views/header.php';
?>

<div class="container-fluid resolution-report-page">
    <div class="row">
        <main class="col-12 px-md-4 main-content">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                <h1 class="h2 mb-0">
                    <i class="fas fa-file-contract me-2"></i>Incident Resolution Report
                </h1>
                <div class="btn-toolbar">
                    <button class="btn btn-outline-secondary btn-sm me-2" onclick="window.history.back();">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </button>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary btn-sm" onclick="window.print();">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                        <a
                            href="resolution_report_pdf.php?id=<?php echo $report['id']; ?>"
                            class="btn btn-primary btn-sm"
                            target="_blank"
                        >
                            <i class="fas fa-file-pdf me-1"></i>PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h3 class="mb-1"><?php echo htmlspecialchars(APP_NAME); ?></h3>
                            <p class="mb-0 text-muted">Incident Resolution Report</p>
                        </div>
                        <div class="text-end">
                            <h4 class="mb-1">Report #<?php echo $report['id']; ?></h4>
                            <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                <?php echo $report['status']; ?>
                            </span>
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Incident Details</h5>
                            <p class="mb-1"><strong>Title:</strong> <?php echo htmlspecialchars($report['title']); ?></p>
                            <p class="mb-1">
                                <strong>Date & Time:</strong>
                                <?php echo format_date($report['incident_date']); ?>
                                at <?php echo date('g:i A', strtotime($report['incident_time'])); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Location:</strong>
                                <?php echo htmlspecialchars($report['location']); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Category:</strong>
                                <?php echo htmlspecialchars($report['category']); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Severity:</strong>
                                <?php echo htmlspecialchars($report['severity_level']); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h5>Parties Involved</h5>
                            <p class="mb-1">
                                <strong>Assigned Organization:</strong><br>
                                <?php echo htmlspecialchars($report['org_name']); ?>
                                <span class="text-muted d-block">
                                    <?php echo htmlspecialchars($report['org_type']); ?>
                                </span>
                            </p>
                            <?php if (!empty($report['assigned_member_name'])): ?>
                                <p class="mb-1">
                                    <strong>Assigned Member:</strong><br>
                                    <?php echo htmlspecialchars($report['assigned_member_name']); ?>
                                </p>
                            <?php endif; ?>
                            <p class="mb-1">
                                <strong>Reporter:</strong><br>
                                <?php echo htmlspecialchars($report['reporter_name']); ?>
                                <?php if (!empty($report['reporter_contact_number'])): ?>
                                    <span class="text-muted d-block">
                                        <i class="fas fa-phone me-1"></i>
                                        <?php echo htmlspecialchars($report['reporter_contact_number']); ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($report['family_contact_name']) || !empty($report['family_contact_number'])): ?>
                                <p class="mb-1">
                                    <strong>Family / Emergency Contact:</strong><br>
                                    <?php if (!empty($report['family_contact_name'])): ?>
                                        <?php echo htmlspecialchars($report['family_contact_name']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($report['family_contact_number'])): ?>
                                        <span class="text-muted d-block">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($report['family_contact_number']); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h5>Incident Description</h5>
                        <div class="border rounded p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Resolution Summary</h5>
                            <div class="border rounded p-3 bg-light">
                                <?php if (!empty($report['resolution_notes'])): ?>
                                    <?php echo nl2br(htmlspecialchars($report['resolution_notes'])); ?>
                                <?php else: ?>
                                    <?php
                                    // fallback: use last update text if resolution_notes not provided
                                    $lastText = '';
                                    if (!empty($updates)) {
                                        $lastText = $updates[count($updates) - 1]['update_text'];
                                    }
                                    echo nl2br(htmlspecialchars($lastText ?: 'No dedicated resolution notes recorded.'));
                                    ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5>Timeline Summary</h5>
                            <p class="mb-1">
                                <strong>Reported On:</strong>
                                <?php echo format_datetime($report['created_at']); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Resolved On:</strong>
                                <?php echo format_datetime($resolutionDate); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Total Updates:</strong>
                                <?php echo count($updates); ?>
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($updates)): ?>
                        <div class="mb-3">
                            <h5>Detailed Updates Timeline</h5>
                            <div class="timeline border rounded p-3">
                                <?php foreach ($updates as $update): ?>
                                    <div class="mb-3">
                                        <div class="small text-muted">
                                            <?php echo format_datetime($update['created_at']); ?> •
                                            <?php echo htmlspecialchars($update['updated_by_name'] ?? 'System'); ?>
                                        </div>
                                        <div><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($witnesses)): ?>
                        <div class="mb-3">
                            <h5>Witnesses</h5>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
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
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($photos)): ?>
                        <div class="mb-3">
                            <h5>Photos</h5>
                            <div class="row">
                                <?php foreach ($photos as $photo): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="border rounded p-1">
                                            <img
                                                src="<?php echo BASE_URL . $photo['file_path']; ?>"
                                                alt="Incident photo"
                                                style="width: 100%; height: 180px; object-fit: cover;"
                                            >
                                            <div class="small text-muted text-center mt-1">
                                                <?php echo format_datetime($photo['uploaded_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Prepared By:</strong></p>
                                <div class="signature-box border-bottom" style="height: 40px;"></div>
                                <p class="small text-muted mt-1">Name & Signature</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1"><strong>Date Generated:</strong></p>
                                <p class="mb-0"><?php echo format_datetime(date('Y-m-d H:i:s')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
@media print {
    body {
        background-color: #ffffff !important;
    }
    .navbar,
    .no-print {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 20px !important;
    }
}
</style>

<?php include '../views/footer.php'; ?>

