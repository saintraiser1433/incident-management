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

// Handle POST for updates/comments/actions
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
    } else if ($action === 'member_resolve' && !$is_guest_access && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Organization Member') {
        try {
            $reason = trim($_POST['resolution_reason'] ?? '');
            if ($reason === '') {
                $_SESSION['error_message'] = 'Please provide a resolution reason.';
            } else {
                // Find the organization_members row linked to this user in this organization
                $memberStmt = $db->prepare("SELECT id FROM organization_members WHERE user_id = ? AND organization_id = ?");
                $memberStmt->execute([$_SESSION['user_id'], $report['organization_id']]);
                $member = $memberStmt->fetch();

                if (!$member) {
                    $_SESSION['error_message'] = 'You are not recognized as a member of this organization.';
                } else {
                    $memberId = (int)$member['id'];

                    // Ensure this member is the assignee for this report
                    if ((int)($report['assigned_to'] ?? 0) !== $memberId) {
                        $_SESSION['error_message'] = 'You are not assigned to this report.';
                    } elseif (!in_array($report['status'], ['Pending', 'In Progress'], true)) {
                        $_SESSION['error_message'] = 'Only Pending or In Progress reports can be resolved by a member.';
                    } else {
                        // Update incident status and resolution notes
                        $oldStatus = $report['status'];
                        $upd = $db->prepare("UPDATE incident_reports SET status = 'Resolved', resolution_notes = ? WHERE id = ?");
                        $upd->execute([$reason, $report_id]);

                        // Log status change in incident_updates
                        $msg = "Status changed from '{$oldStatus}' to 'Resolved' by assigned member. Resolution reason: " . $reason;
                        $q = "INSERT INTO incident_updates (report_id, update_text, updated_by) VALUES (?, ?, ?)";
                        $s = $db->prepare($q);
                        $s->execute([$report_id, $msg, $_SESSION['user_id']]);
                        log_audit('CREATE', 'incident_updates', $db->lastInsertId());

                        $_SESSION['success_message'] = 'Issue resolved successfully.';
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error resolving by member: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to resolve the issue. Please try again.';
        }
        // Redirect back to avoid form resubmission
        redirect('reports/view.php?id=' . urlencode($report_id));
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
$member_can_resolve = false;
$member_can_view_resolution = false;
if (!$is_guest_access) {
    if ($_SESSION['user_role'] == 'Organization Account' && $report['organization_id'] != $_SESSION['organization_id']) {
        redirect('index.php?error=access_denied');
    }

    // Determine if current user (Organization Member) is allowed to resolve / view resolution report
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Organization Member') {
        try {
            $memberStmt = $db->prepare("SELECT id FROM organization_members WHERE user_id = ? AND organization_id = ?");
            $memberStmt->execute([$_SESSION['user_id'], $report['organization_id']]);
            $member = $memberStmt->fetch();
            if ($member && (int)$report['assigned_to'] === (int)$member['id']) {
                if (in_array($report['status'], ['Pending', 'In Progress'], true)) {
                    $member_can_resolve = true;
                }
                if (in_array($report['status'], ['Resolved', 'Closed'], true)) {
                    $member_can_view_resolution = true;
                }
            }
        } catch (Exception $e) {
            error_log("Error checking member resolve permission: " . $e->getMessage());
        }
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
    <div class="row g-0">
        <?php if (!$is_guest_access): ?>
            <?php include '../views/sidebar.php'; ?>
        <?php endif; ?>

        <main class="<?php echo $is_guest_access ? 'col-12' : 'col-md-9 ms-sm-auto col-lg-10'; ?> main-content">

            <!-- Page Header (shadcn-style) -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 mb-6 shadow-sm">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-red-50 text-red-600 shrink-0">
                            <i class="fas fa-file-alt text-lg"></i>
                        </span>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-slate-500 font-medium">Incident</p>
                            <h1 class="text-xl font-semibold tracking-tight text-slate-900 leading-tight">
                                <?php echo htmlspecialchars($report['title']); ?>
                            </h1>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs text-slate-500">#<?php echo $report['id']; ?></span>
                                <span class="badge <?php echo get_status_badge_class($report['status']); ?>">
                                    <?php echo $report['status']; ?>
                                </span>
                                <span class="badge <?php echo get_severity_badge_class($report['severity_level']); ?>">
                                    <?php echo $report['severity_level']; ?>
                                </span>
                                <span class="badge bg-info"><?php echo $report['category']; ?></span>
                                <?php if (!empty($report['priority_number'])): ?>
                                    <span class="badge bg-success">Priority #<?php echo (int)$report['priority_number']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($is_guest_access): ?>
                            <a href="search.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                                <i class="fas fa-arrow-left text-slate-400"></i>Back to Search
                            </a>
                        <?php endif; ?>
                        <?php if (
                            !$is_guest_access &&
                            (
                                (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Admin') ||
                                (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Organization Account' && $report['organization_id'] == $_SESSION['organization_id'])
                            )
                        ): ?>
                            <a href="edit.php?id=<?php echo $report['id']; ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                                <i class="fas fa-edit text-slate-400"></i>Edit
                            </a>
                            <?php if (in_array($report['status'], ['Resolved', 'Closed'], true)): ?>
                                <a href="resolution_report.php?id=<?php echo $report['id']; ?>" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition" target="_blank">
                                    <i class="fas fa-file-contract"></i>Resolution Report
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!$is_guest_access && $member_can_view_resolution): ?>
                            <a href="resolution_report.php?id=<?php echo $report['id']; ?>" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition" target="_blank">
                                <i class="fas fa-file-contract"></i>Resolution Report
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Report Details -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="card">
                        <div class="card-header flex items-center gap-2">
                            <i class="fas fa-info-circle text-slate-400"></i>
                            <span>Report Details</span>
                        </div>
                        <div class="card-body">
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                                <div>
                                    <dt class="text-xs uppercase tracking-wider text-slate-500 font-medium mb-1">Date &amp; Time</dt>
                                    <dd class="text-sm text-slate-900">
                                        <?php echo format_date($report['incident_date']); ?>
                                        <span class="text-slate-500">at <?php echo date('g:i A', strtotime($report['incident_time'])); ?></span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wider text-slate-500 font-medium mb-1">Location</dt>
                                    <dd class="text-sm text-slate-900"><?php echo htmlspecialchars($report['location']); ?></dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wider text-slate-500 font-medium mb-1">Assigned Organization</dt>
                                    <dd class="text-sm text-slate-900">
                                        <?php echo htmlspecialchars($report['org_name']); ?>
                                        <span class="block text-xs text-slate-500"><?php echo $report['org_type']; ?></span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wider text-slate-500 font-medium mb-1">Reported By</dt>
                                    <dd class="text-sm text-slate-900">
                                        <?php echo htmlspecialchars($report['reporter_name']); ?>
                                        <?php if (!empty($report['reporter_contact_number'])): ?>
                                            <span class="block text-xs text-slate-500">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($report['reporter_contact_number']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <?php if (!empty($report['assigned_member_name'])): ?>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs uppercase tracking-wider text-slate-500 font-medium mb-1">Assigned Member</dt>
                                    <dd>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 border border-blue-200">
                                            <i class="fas fa-user"></i><?php echo htmlspecialchars($report['assigned_member_name']); ?>
                                        </span>
                                    </dd>
                                </div>
                                <?php endif; ?>
                            </dl>

                            <div class="mt-6">
                                <dt class="text-xs uppercase tracking-wider text-slate-500 font-medium mb-2">Description</dt>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 leading-relaxed">
                                    <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                                </div>
                            </div>

                            <div class="mt-5 text-xs text-slate-500 flex items-center gap-1.5">
                                <i class="fas fa-clock"></i>
                                Created: <?php echo format_datetime($report['created_at']); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!is_null($report['latitude']) && !is_null($report['longitude'])): ?>
                    <div class="card">
                        <div class="card-header flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-slate-400"></i>
                            <span>Incident Location</span>
                        </div>
                        <div class="card-body">
                            <div id="incident-map-view" style="height: 320px; border-radius: 0.5rem; overflow: hidden; border: 1px solid #e2e8f0;"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Photos -->
                    <?php if (!empty($photos)): ?>
                    <div class="card">
                        <div class="card-header flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-images text-slate-400"></i>
                                <span>Photos</span>
                            </div>
                            <span class="badge bg-secondary"><?php echo count($photos); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <?php foreach ($photos as $photo): ?>
                                <div class="group rounded-lg overflow-hidden border border-slate-200 cursor-pointer hover:shadow-card transition" onclick="openImageModal('<?php echo BASE_URL . $photo['file_path']; ?>')">
                                    <img src="<?php echo BASE_URL . $photo['file_path']; ?>"
                                         class="w-full h-40 object-cover group-hover:scale-105 transition-transform duration-300">
                                    <div class="px-3 py-2 text-xs text-slate-500">
                                        <?php echo format_datetime($photo['uploaded_at']); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Witnesses -->
                    <?php if (!empty($witnesses)): ?>
                    <div class="card">
                        <div class="card-header flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-users text-slate-400"></i>
                                <span>Witnesses</span>
                            </div>
                            <span class="badge bg-secondary"><?php echo count($witnesses); ?></span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0">
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
                                            <td class="text-sm text-slate-900"><?php echo htmlspecialchars($witness['witness_name']); ?></td>
                                            <td class="text-sm text-slate-700"><?php echo htmlspecialchars($witness['witness_contact']); ?></td>
                                            <td class="text-xs text-slate-500"><?php echo format_datetime($witness['created_at']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Updates, Resolution, and Comments -->
                <div class="space-y-4">
                    <?php if ($member_can_resolve): ?>
                    <!-- Member Resolve Card -->
                    <div class="card border-emerald-200">
                        <div class="card-header flex items-center gap-2 bg-emerald-50">
                            <i class="fas fa-check-circle text-emerald-600"></i>
                            <span class="text-emerald-700">Resolve This Issue</span>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="space-y-3">
                                <input type="hidden" name="action" value="member_resolve">
                                <div>
                                    <label for="resolution_reason" class="form-label">Resolution Reason <span class="text-red-500">*</span></label>
                                    <textarea class="form-control" id="resolution_reason" name="resolution_reason" rows="3"
                                              placeholder="Describe what you did to resolve this issue..." required></textarea>
                                </div>
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition">
                                    <i class="fas fa-check"></i>Mark as Resolved
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Updates (Activity Timeline) -->
                    <div class="card">
                        <div class="card-header flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-history text-slate-400"></i>
                                <span>Updates</span>
                            </div>
                            <span class="badge bg-secondary"><?php echo count($updates); ?></span>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($updates)): ?>
                                <p class="text-sm text-slate-500 text-center py-4">No updates yet.</p>
                            <?php else: ?>
                            <ul class="space-y-4">
                                <?php foreach ($updates as $update): ?>
                                <li class="flex gap-3">
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-white text-xs font-semibold">
                                        <?php echo strtoupper(substr($update['updated_by_name'] ?: 'S', 0, 1)); ?>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-slate-900 truncate"><?php echo htmlspecialchars($update['updated_by_name']); ?></span>
                                            <span class="text-xs text-slate-500"><?php echo format_datetime($update['created_at']); ?></span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-700"><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></p>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php if (!$is_guest_access && (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Admin' || (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Organization Account' && $report['organization_id'] == $_SESSION['organization_id']))): ?>
                        <div class="card-footer">
                            <form method="POST" class="space-y-2">
                                <input type="hidden" name="action" value="add_update">
                                <textarea class="form-control" name="update_text" placeholder="Add update..." rows="2" required></textarea>
                                <div class="flex justify-end">
                                    <button class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 transition" type="submit">
                                        <i class="fas fa-paper-plane"></i>Post Update
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Comments -->
                    <div class="card">
                        <div class="card-header flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-comments text-slate-400"></i>
                                <span>Comments</span>
                            </div>
                            <span class="badge bg-secondary"><?php echo count($comments); ?></span>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($comments)): ?>
                                <p class="text-sm text-slate-500 text-center py-4">No comments yet.</p>
                            <?php else: ?>
                            <ul class="space-y-4">
                                <?php foreach ($comments as $comment): ?>
                                <li class="flex gap-3">
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-700 text-xs font-semibold">
                                        <?php echo strtoupper(substr($comment['commented_by_name'] ?: 'A', 0, 1)); ?>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-slate-900 truncate"><?php echo htmlspecialchars($comment['commented_by_name']); ?></span>
                                            <span class="text-xs text-slate-500"><?php echo format_datetime($comment['created_at']); ?></span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-700"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_guest_access || (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Admin') || (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'Organization Account' && $report['organization_id'] == $_SESSION['organization_id'])): ?>
                        <div class="card-footer">
                            <form method="POST" class="space-y-2">
                                <input type="hidden" name="action" value="add_comment">
                                <?php if ($is_guest_access): ?>
                                    <input type="text" class="form-control" name="commenter_name"
                                        placeholder="Your name..." required
                                        value="<?php echo htmlspecialchars($report['reported_by']); ?>"
                                        readonly>
                                <?php endif; ?>
                                <input type="text" class="form-control" name="comment_text" placeholder="Write a comment..." required>
                                <div class="flex justify-end">
                                    <button class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition" type="submit">
                                        <i class="fas fa-paper-plane text-slate-400"></i>Comment
                                    </button>
                                </div>
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

// Static incident map for this report (if coordinates are available)
document.addEventListener('DOMContentLoaded', function() {
    var mapContainer = document.getElementById('incident-map-view');
    if (!mapContainer || typeof L === 'undefined') {
        return;
    }

    var lat = <?php echo is_null($report['latitude']) ? 'null' : (float)$report['latitude']; ?>;
    var lng = <?php echo is_null($report['longitude']) ? 'null' : (float)$report['longitude']; ?>;

    if (lat === null || lng === null) {
        return;
    }

    var map = L.map('incident-map-view').setView([lat, lng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map)
        .bindPopup('<?php echo htmlspecialchars(addslashes($report['title'])); ?>')
        .openPopup();
});
</script>

<?php include '../views/footer.php'; ?>