<?php
/**
 * View Incident Report
 * Incident Report Management System
 */

require_once '../config/config.php';

$report_id = $_GET['id'] ?? 0;
$is_guest_access = isset($_GET['guest']) && $_GET['guest'] == '1';

// For guest access, we don't require login but need to validate the ticket exists
if (!$is_guest_access) {
    require_login();
}

if (!$report_id) {
    if ($is_guest_access) {
        redirect('search.php?error=invalid_report');
    } else {
        redirect('index.php?error=invalid_report');
    }
}

$database = new Database();
$db = $database->getConnection();

// Get report details first (needed for SMS notifications)
$query = "SELECT ir.*, ir.reported_by as reporter_name, ir.reporter_contact_number, o.org_name, o.org_type, rq.priority_number, rq.assigned_to,
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
    if ($is_guest_access) {
        redirect('search.php?error=report_not_found');
    } else {
        redirect('index.php?error=report_not_found');
    }
}

// Handle POST for updates/comments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Allow updates only for logged-in Admin/Organization users
    // Allow comments for both logged-in users and guests
    if ($action === 'add_update' && !$is_guest_access && ($_SESSION['user_role'] === 'Admin' || $_SESSION['user_role'] === 'Organization Account')) {
        try {
            $text = trim($_POST['update_text'] ?? '');
            if ($text !== '') {
                $q = "INSERT INTO incident_updates (report_id, update_text, updated_by) VALUES (?, ?, ?)";
                $s = $db->prepare($q);
                $s->execute([$report_id, $text, $_SESSION['user_id']]);
                log_audit('CREATE', 'incident_updates', $db->lastInsertId());
                
                // Send SMS to respondent if organization head adds update
                if ($_SESSION['user_role'] === 'Organization Account' && !empty($report['reporter_contact_number'])) {
                    try {
                        require_once '../sms.php';
                        $smsMessage = "MDRRMO-GLAN: Update on your incident report #{$report_id} '{$report['title']}': " . substr($text, 0, 100) . (strlen($text) > 100 ? '...' : '');
                        $smsResult = sendSMS($report['reporter_contact_number'], $smsMessage);
                        if (!$smsResult['success']) {
                            error_log("SMS notification failed for report #{$report_id} update: " . $smsResult['error']);
                        }
                    } catch (Exception $smsError) {
                        error_log("SMS notification error for report #{$report_id} update: " . $smsError->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error adding update: " . $e->getMessage());
        }
    } else if ($action === 'add_comment') {
        try {
            $text = trim($_POST['comment_text'] ?? '');
            $commenter_name = trim($_POST['commenter_name'] ?? '');
            
            if ($text !== '') {
                if ($is_guest_access) {
                    // Guest comment - require name
                    if ($commenter_name === '') {
                        throw new Exception('Please provide your name for the comment.');
                    }
                    // Insert comment with guest name (no user ID)
                    $q = "INSERT INTO incident_comments (report_id, comment_text, commented_by, guest_name) VALUES (?, ?, NULL, ?)";
                    $s = $db->prepare($q);
                    $s->execute([$report_id, $text, $commenter_name]);
                } else {
                    // Logged-in user comment
                    $q = "INSERT INTO incident_comments (report_id, comment_text, commented_by) VALUES (?, ?, ?)";
                    $s = $db->prepare($q);
                    $s->execute([$report_id, $text, $_SESSION['user_id']]);
                    log_audit('CREATE', 'incident_comments', $db->lastInsertId());
                    
                    // Send SMS to respondent if organization head adds comment
                    if ($_SESSION['user_role'] === 'Organization Account' && !empty($report['reporter_contact_number'])) {
                        try {
                            require_once '../sms.php';
                            $smsMessage = "MDRRMO-GLAN: New comment on your incident report #{$report_id} '{$report['title']}': " . substr($text, 0, 100) . (strlen($text) > 100 ? '...' : '');
                            $smsResult = sendSMS($report['reporter_contact_number'], $smsMessage);
                            if (!$smsResult['success']) {
                                error_log("SMS notification failed for report #{$report_id} comment: " . $smsResult['error']);
                            }
                        } catch (Exception $smsError) {
                            error_log("SMS notification error for report #{$report_id} comment: " . $smsError->getMessage());
                        }
                    }
                }
                // Store success message for display
                $_SESSION['success_message'] = 'Comment added successfully!';
            }
        } catch (Exception $e) {
            // Store error message for display
            $_SESSION['error_message'] = $e->getMessage();
        }
        // PRG pattern - preserve guest parameter
        $redirect_url = 'reports/view.php?id=' . urlencode($report_id);
        if ($is_guest_access) {
            $redirect_url .= '&guest=1';
        }
        redirect($redirect_url);
    }
}

// Check access permissions (skip for guest access)
if (!$is_guest_access) {
    if ($_SESSION['user_role'] == 'Organization Account' && $report['organization_id'] != $_SESSION['organization_id']) {
        redirect('index.php?error=access_denied');
    }
}


// Get photos
$query = "SELECT * FROM incident_photos WHERE report_id = ? ORDER BY uploaded_at";
$stmt = $db->prepare($query);
$stmt->execute([$report_id]);
$photos = $stmt->fetchAll();

// Get witnesses
$query = "SELECT * FROM incident_witnesses WHERE report_id = ? ORDER BY created_at";
$stmt = $db->prepare($query);
$stmt->execute([$report_id]);
$witnesses = $stmt->fetchAll();

// Get updates
$query = "SELECT iu.*, u.name as updated_by_name 
          FROM incident_updates iu 
          LEFT JOIN users u ON iu.updated_by = u.id 
          WHERE iu.report_id = ? 
          ORDER BY iu.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$report_id]);
$updates = $stmt->fetchAll();

// Get comments
$query = "SELECT ic.*, COALESCE(u.name, ic.guest_name, 'Unknown') as commented_by_name 
          FROM incident_comments ic 
          LEFT JOIN users u ON ic.commented_by = u.id 
          WHERE ic.report_id = ? 
          ORDER BY ic.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$report_id]);
$comments = $stmt->fetchAll();

$page_title = 'Report #' . $report['id'] . ' - ' . APP_NAME;
include '../views/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php if (!$is_guest_access): ?>
            <?php include '../views/sidebar.php'; ?>
        <?php endif; ?>

        <main class="<?php echo $is_guest_access ? 'col-12' : 'col-md-9 ms-sm-auto col-lg-10'; ?> px-md-4 main-content">
            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-file-alt me-2"></i>Report #<?php echo $report['id']; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <?php if ($is_guest_access): ?>
                            <a href="search.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Search
                            </a>
                        <?php else: ?>
                            <!-- <a href="index.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Reports
                            </a> -->
                        <?php endif; ?>
                        <?php if (!$is_guest_access && (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Admin' || 
                                  (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Organization Account' && $report['organization_id'] == $_SESSION['organization_id']))): ?>
                        <a href="edit.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Report Details -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Report Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Title:</strong><br>
                                    <?php echo htmlspecialchars($report['title']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Category:</strong><br>
                                    <span class="badge bg-info"><?php echo $report['category']; ?></span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Severity:</strong><br>
                                    <span
                                        class="badge <?php echo get_severity_badge_class($report['severity_level']); ?>">
                                        <?php echo $report['severity_level']; ?>
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong><br>
                                    <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                        <?php echo $report['status']; ?>
                                    </span>
                                </div>
                                <?php if (!empty($report['priority_number'])): ?>
                                <div class="col-md-6">
                                    <strong>Priority Number:</strong><br>
                                    <span class="badge bg-success">#<?php echo (int)$report['priority_number']; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Date & Time:</strong><br>
                                    <?php echo format_date($report['incident_date']); ?> at
                                    <?php echo date('g:i A', strtotime($report['incident_time'])); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Location:</strong><br>
                                    <?php echo htmlspecialchars($report['location']); ?>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Assigned Organization:</strong><br>
                                    <?php echo htmlspecialchars($report['org_name']); ?>
                                    <small class="text-muted d-block"><?php echo $report['org_type']; ?></small>
                                </div>
                                <div class="col-md-6">
                                    <strong>Reported By:</strong><br>
                                    <?php echo htmlspecialchars($report['reporter_name']); ?>
                                    <?php if (!empty($report['reporter_contact_number'])): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($report['reporter_contact_number']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($report['assigned_member_name'])): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Assigned Member:</strong><br>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($report['assigned_member_name']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <strong>Description:</strong><br>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                                </div>
                            </div>

                            <div class="text-muted">
                                <small>
                                    <i class="fas fa-clock me-1"></i>
                                    Created: <?php echo format_datetime($report['created_at']); ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Photos -->
                    <?php if (!empty($photos)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-images me-2"></i>Photos (<?php echo count($photos); ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($photos as $photo): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <img src="<?php echo BASE_URL . $photo['file_path']; ?>" class="card-img-top"
                                            style="height: 200px; object-fit: cover;"
                                            onclick="openImageModal('<?php echo BASE_URL . $photo['file_path']; ?>')">
                                        <div class="card-body p-2">
                                            <small class="text-muted">
                                                <?php echo format_datetime($photo['uploaded_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Witnesses -->
                    <?php if (!empty($witnesses)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2"></i>Witnesses (<?php echo count($witnesses); ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact</th>
                                            <th>Added</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($witnesses as $witness): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($witness['witness_name']); ?></td>
                                            <td><?php echo htmlspecialchars($witness['witness_contact']); ?></td>
                                            <td><?php echo format_datetime($witness['created_at']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Updates and Comments -->
                <div class="col-lg-4">
                    <!-- Updates -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-history me-2"></i>Updates (<?php echo count($updates); ?>)
                            </h6>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($updates)): ?>
                            <p class="text-muted text-center">No updates yet.</p>
                            <?php else: ?>
                            <?php foreach ($updates as $update): ?>
                            <div class="border-start border-primary border-3 ps-3 mb-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($update['updated_by_name']); ?></div>
                                <div class="text-muted small"><?php echo format_datetime($update['created_at']); ?>
                                </div>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!$is_guest_access && (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Admin' || (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Organization Account' && $report['organization_id'] == $_SESSION['organization_id']))): ?>
                        <div class="card-footer">
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="add_update">
                                <textarea class="form-control" name="update_text" placeholder="Add update..." rows="2"
                                    required></textarea>
                                <button class="btn btn-primary" type="submit"><i
                                        class="fas fa-paper-plane"></i></button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Comments -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-comments me-2"></i>Comments (<?php echo count($comments); ?>)
                            </h6>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($comments)): ?>
                            <p class="text-muted text-center">No comments yet.</p>
                            <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                            <div class="border-start border-secondary border-3 ps-3 mb-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($comment['commented_by_name']); ?>
                                </div>
                                <div class="text-muted small"><?php echo format_datetime($comment['created_at']); ?>
                                </div>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_guest_access || (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Admin') || (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Organization Account' && $report['organization_id'] == $_SESSION['organization_id'])): ?>
                        <div class="card-footer">
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="add_comment">
                                <?php if ($is_guest_access): ?>
                                    <input type="text" class="form-control" name="commenter_name"
                                        placeholder="Your name..." required style="max-width: 150px;"
                                        value="<?php echo htmlspecialchars($report['reported_by']); ?>"
                                        readonly>
                                <?php endif; ?>
                                <input type="text" class="form-control" name="comment_text"
                                    placeholder="Write a comment..." required>
                                <button class="btn btn-outline-secondary" type="submit"><i
                                        class="fas fa-paper-plane"></i></button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<script>
function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}
</script>

<?php include '../views/footer.php'; ?>