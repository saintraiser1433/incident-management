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
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
                <h1 class="h2">
                    <?php if ($is_member): ?>
                        <i class="fas fa-tasks me-2"></i>My Assigned Issues
                    <?php else: ?>
                        <i class="fas fa-building me-2"></i>Departments
                    <?php endif; ?>
                </h1>
                <div class="btn-group" role="group">
                    <a href="../reports/search.php" class="btn btn-outline-secondary">
                        <i class="fas fa-search me-1"></i>Search Ticket
                    </a>
                    <?php if (!$is_member): ?>
                        <button type="button" class="btn btn-outline-primary active" id="gridViewBtn">
                            <i class="fas fa-th me-1"></i>Grid View
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="listViewBtn">
                            <i class="fas fa-list me-1"></i>List View
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($is_member): ?>
                <!-- My Assigned Issues Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-clipboard-list me-2"></i>Pending / In Progress Issues Assigned to Me
                        </h6>
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
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-check-double me-2"></i>Resolved / Closed Issues Assigned to Me
                        </h6>
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
            <div class="row" id="departmentsGrid">
                <?php foreach ($organizations as $org): ?>
                    <?php
                    // Get icon and color based on organization type
                    $icon_class = '';
                    $bg_color = '';
                    switch ($org['org_type']) {
                        case 'Hospital':
                            $icon_class = 'fas fa-hospital';
                            $bg_color = 'bg-success';
                            break;
                        case 'Fire Department':
                            $icon_class = 'fas fa-fire-extinguisher';
                            $bg_color = 'bg-danger';
                            break;
                        case 'Police':
                            $icon_class = 'fas fa-shield-alt';
                            $bg_color = 'bg-primary';
                            break;
                        case 'Emergency Services':
                            $icon_class = 'fas fa-ambulance';
                            $bg_color = 'bg-info';
                            break;
                        case 'Security':
                            $icon_class = 'fas fa-shield-alt';
                            $bg_color = 'bg-warning';
                            break;
                        default:
                            $icon_class = 'fas fa-building';
                            $bg_color = 'bg-secondary';
                    }
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <!-- Icon -->
                                <div class="mb-3">
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center <?php echo $bg_color; ?>" 
                                         style="width: 80px; height: 80px;">
                                        <i class="<?php echo $icon_class; ?> fa-2x text-white"></i>
                                    </div>
                                </div>
                                
                                <!-- Department Info -->
                                <h5 class="card-title fw-bold"><?php echo htmlspecialchars($org['org_name']); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($org['org_type']); ?></p>
                                
                                <!-- Contact -->
                                <div class="mb-2">
                                    <i class="fas fa-phone me-1"></i>
                                    <span><?php echo htmlspecialchars($org['contact_number']); ?></span>
                                </div>
                                
                                <!-- Reports Count -->
                                <div class="mb-3">
                                    <i class="fas fa-file-alt me-1"></i>
                                    <span><?php echo $org['report_count']; ?> Reports</span>
                                </div>
                                
                                <!-- Action Button -->
                                <a href="../reports/create.php?org_id=<?php echo $org['id']; ?>&redirect=departments" 
                                   class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Report Incident
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Departments List (Hidden by default) -->
            <div class="d-none" id="departmentsList">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
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
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary me-3" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-building text-white"></i>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($org['org_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($org['org_type']); ?></td>
                                            <td><?php echo htmlspecialchars($org['contact_number']); ?></td>
                                            <td><?php echo $org['report_count']; ?></td>
                                            <td>
                                                <a href="../reports/create.php?org_id=<?php echo $org['id']; ?>&redirect=departments" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-plus me-1"></i>Report Incident
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
