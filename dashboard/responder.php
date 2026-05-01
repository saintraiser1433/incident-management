<?php
/**
 * Departments Dashboard (Guest Access)
 * Incident Report Management System
 */

require_once '../config/config.php';
// Remove role requirement - allow guest access
// require_role(['Responder']);

$database = new Database();
$db = $database->getConnection();

// Check if this is an Organization Member to show "My Assigned Issues"
$is_member = is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Organization Member';
$my_reports = [];
$my_resolved_reports = [];

if ($is_member) {
    // Find linked organization_members row for this user
    $memberStmt = $db->prepare("SELECT id FROM organization_members WHERE user_id = ? AND organization_id = ?");
    $memberStmt->execute([$_SESSION['user_id'], $_SESSION['organization_id']]);
    $member = $memberStmt->fetch();

    if ($member) {
        $member_id = (int)$member['id'];

        // Get only Pending or In Progress incident reports assigned to this member
        $myQuery = "SELECT ir.*, 
                           rq.priority_number,
                           rq.status AS queue_status
                    FROM report_queue rq
                    JOIN incident_reports ir ON rq.report_id = ir.id
                    WHERE rq.assigned_to = ?
                      AND ir.status IN ('Pending','In Progress')
                    ORDER BY ir.created_at DESC";
        $myStmt = $db->prepare($myQuery);
        $myStmt->execute([$member_id]);
        $my_reports = $myStmt->fetchAll();

        // Get Resolved / Closed incident reports assigned to this member
        $resolvedQuery = "SELECT ir.*, 
                                 rq.priority_number,
                                 rq.status AS queue_status
                          FROM report_queue rq
                          JOIN incident_reports ir ON rq.report_id = ir.id
                          WHERE rq.assigned_to = ?
                            AND ir.status IN ('Resolved','Closed')
                          ORDER BY ir.created_at DESC";
        $resolvedStmt = $db->prepare($resolvedQuery);
        $resolvedStmt->execute([$member_id]);
        $my_resolved_reports = $resolvedStmt->fetchAll();
    }

    $page_title = 'My Assigned Issues - ' . APP_NAME;
} else {
    $page_title = 'Departments - ' . APP_NAME;
}

include '../views/header.php';

// Get organizations/departments (always available for guests and members)
$query = "SELECT o.*, COUNT(ir.id) as report_count 
          FROM organizations o 
          LEFT JOIN incident_reports ir ON o.id = ir.organization_id 
          GROUP BY o.id 
          ORDER BY o.org_name";
$stmt = $db->prepare($query);
$stmt->execute();
$organizations = $stmt->fetchAll();

$staff_org_logo_path = null;
if ($is_member && !empty($_SESSION['organization_id'])) {
    $lgStmt = $db->prepare('SELECT logo_path FROM organizations WHERE id = ?');
    $lgStmt->execute([(int) $_SESSION['organization_id']]);
    $staff_org_logo_path = $lgStmt->fetchColumn() ?: null;
}
?>

<div class="container-fluid main-content">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
            <div class="flex items-start gap-4">
                <?php if ($is_member && !empty($staff_org_logo_path)): ?>
                    <img src="<?php echo htmlspecialchars(BASE_URL . $staff_org_logo_path); ?>" alt="" class="h-12 w-12 shrink-0 rounded-xl border border-slate-200 bg-white object-contain p-1">
                <?php endif; ?>
                <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                    <?php echo $is_member ? 'My Assigned Issues' : 'Departments'; ?>
                </h1>
                <p class="text-sm text-slate-500 mt-1">
                    <?php echo $is_member ? 'Issues currently assigned to you across pending and resolved states.' : 'Choose a department to file an incident report.'; ?>
                </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if (!is_logged_in()): ?>
                    <a href="<?php echo BASE_URL; ?>auth/login.php" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                        <i class="fas fa-sign-in-alt"></i>Staff login
                    </a>
                <?php endif; ?>
                <a href="../reports/search.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    <i class="fas fa-search text-slate-400"></i>Search Ticket
                </a>
                <?php if (!$is_member): ?>
                    <div class="inline-flex rounded-lg border border-slate-300 bg-white p-1">
                        <button type="button" class="active inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium text-slate-700" id="gridViewBtn">
                            <i class="fas fa-th"></i>Grid
                        </button>
                        <button type="button" class="inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium text-slate-500 hover:text-slate-700" id="listViewBtn">
                            <i class="fas fa-list"></i>List
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
            
            <?php if ($is_member): ?>
                <!-- My Assigned Issues Table -->
                <div class="card mb-4">
                    <div class="card-header flex items-center gap-2">
                        <i class="fas fa-clipboard-list text-slate-400"></i>
                        <span>Pending / In Progress Issues Assigned to Me</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_reports)): ?>
                            <div class="text-muted">No pending or in-progress issues are currently assigned to you.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Severity</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_reports as $r): ?>
                                            <tr>
                                                <td>#<?php echo $r['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($r['title']); ?></strong>
                                                    <div class="text-muted small">
                                                        <?php echo htmlspecialchars(substr($r['description'], 0, 80)) . '...'; ?>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-info"><?php echo $r['category']; ?></span></td>
                                                <td>
                                                    <span class="badge <?php echo get_severity_badge_class($r['severity_level']); ?>">
                                                        <?php echo $r['severity_level']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo get_status_badge_class($r['status']); ?>">
                                                        <?php echo $r['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($r['priority_number'])): ?>
                                                        <span class="badge bg-success">#<?php echo (int)$r['priority_number']; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo format_date($r['incident_date']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('g:i A', strtotime($r['incident_time'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="../reports/view.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Resolved / Closed Issues Table -->
                <div class="card mb-4">
                    <div class="card-header flex items-center gap-2">
                        <i class="fas fa-check-double text-slate-400"></i>
                        <span>Resolved / Closed Issues Assigned to Me</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_resolved_reports)): ?>
                            <div class="text-muted">You have no resolved or closed issues yet.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Severity</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_resolved_reports as $r): ?>
                                            <tr>
                                                <td>#<?php echo $r['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($r['title']); ?></strong>
                                                    <div class="text-muted small">
                                                        <?php echo htmlspecialchars(substr($r['description'], 0, 80)) . '...'; ?>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-info"><?php echo $r['category']; ?></span></td>
                                                <td>
                                                    <span class="badge <?php echo get_severity_badge_class($r['severity_level']); ?>">
                                                        <?php echo $r['severity_level']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo get_status_badge_class($r['status']); ?>">
                                                        <?php echo $r['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($r['priority_number'])): ?>
                                                        <span class="badge bg-success">#<?php echo (int)$r['priority_number']; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo format_date($r['incident_date']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('g:i A', strtotime($r['incident_time'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="../reports/view.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary mb-1">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (in_array($r['status'], ['Resolved','Closed'], true)): ?>
                                                        <a href="../reports/resolution_report.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-success" target="_blank">
                                                            <i class="fas fa-file-contract"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Departments Grid (for guests / non-members) -->
            <?php if (!$is_member): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="departmentsGrid">
                <?php foreach ($organizations as $org): ?>
                    <?php
                    $icon_class = '';
                    $icon_bg = '';
                    switch ($org['org_type']) {
                        case 'Hospital':           $icon_class = 'fas fa-hospital';          $icon_bg = 'bg-emerald-50 text-emerald-600'; break;
                        case 'Fire Department':    $icon_class = 'fas fa-fire-extinguisher'; $icon_bg = 'bg-red-50 text-red-600'; break;
                        case 'Police':             $icon_class = 'fas fa-shield-alt';        $icon_bg = 'bg-blue-50 text-blue-600'; break;
                        case 'Emergency Services': $icon_class = 'fas fa-ambulance';         $icon_bg = 'bg-sky-50 text-sky-600'; break;
                        case 'Security':           $icon_class = 'fas fa-shield-alt';        $icon_bg = 'bg-amber-50 text-amber-600'; break;
                        default:                   $icon_class = 'fas fa-building';          $icon_bg = 'bg-slate-100 text-slate-600';
                    }
                    ?>
                    <div class="card group">
                        <div class="card-body">
                            <div class="flex items-start justify-between mb-4">
                                <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl <?php echo $icon_bg; ?> text-lg">
                                    <i class="<?php echo $icon_class; ?>"></i>
                                </span>
                                <span class="badge bg-secondary"><?php echo $org['report_count']; ?> reports</span>
                            </div>

                            <h3 class="text-base font-semibold text-slate-900 leading-tight"><?php echo htmlspecialchars($org['org_name']); ?></h3>
                            <p class="text-sm text-slate-500 mt-0.5"><?php echo htmlspecialchars($org['org_type']); ?></p>

                            <div class="mt-3 flex items-center gap-2 text-sm text-slate-600">
                                <i class="fas fa-phone text-slate-400 text-xs"></i>
                                <span><?php echo htmlspecialchars($org['contact_number']); ?></span>
                            </div>

                            <a href="../reports/create.php?org_id=<?php echo $org['id']; ?>&redirect=departments"
                               class="mt-5 w-full inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                                <i class="fas fa-plus"></i>Report Incident
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Departments List (Hidden by default) -->
            <div class="d-none" id="departmentsList">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th>Contact</th>
                                        <th>Reports</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($organizations as $org): ?>
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                                        <i class="fas fa-building"></i>
                                                    </span>
                                                    <span class="font-medium text-slate-900"><?php echo htmlspecialchars($org['org_name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="text-sm text-slate-700"><?php echo htmlspecialchars($org['org_type']); ?></td>
                                            <td class="text-sm text-slate-700"><?php echo htmlspecialchars($org['contact_number']); ?></td>
                                            <td class="text-sm text-slate-700"><?php echo $org['report_count']; ?></td>
                                            <td>
                                                <a href="../reports/create.php?org_id=<?php echo $org['id']; ?>&redirect=departments"
                                                   class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 transition">
                                                    <i class="fas fa-plus"></i>Report Incident
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
    </div>
</div>

<?php if (!$is_member): ?>
<script>
// View toggle functionality (only when departments grid/list are visible)
document.getElementById('gridViewBtn').addEventListener('click', function() {
    document.getElementById('departmentsGrid').classList.remove('d-none');
    document.getElementById('departmentsList').classList.add('d-none');
    this.classList.add('active');
    document.getElementById('listViewBtn').classList.remove('active');
});

document.getElementById('listViewBtn').addEventListener('click', function() {
    document.getElementById('departmentsGrid').classList.add('d-none');
    document.getElementById('departmentsList').classList.remove('d-none');
    this.classList.add('active');
    document.getElementById('gridViewBtn').classList.remove('active');
});
</script>
<?php endif; ?>

<?php include '../views/footer.php'; ?>
