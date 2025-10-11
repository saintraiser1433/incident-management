<?php
/**
 * Departments Dashboard (Guest Access)
 * Incident Report Management System
 */

require_once '../config/config.php';
// Remove role requirement - allow guest access
// require_role(['Responder']);

$page_title = 'Departments - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

// Get organizations/departments
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
                    <i class="fas fa-building me-2"></i>Departments
                </h1>
                <div class="btn-group" role="group">
                    <a href="../reports/search.php" class="btn btn-outline-secondary">
                        <i class="fas fa-search me-1"></i>Search Ticket
                    </a>
                    <button type="button" class="btn btn-outline-primary active" id="gridViewBtn">
                        <i class="fas fa-th me-1"></i>Grid View
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="listViewBtn">
                        <i class="fas fa-list me-1"></i>List View
                    </button>
                </div>
            </div>
            
            
            <!-- Departments Grid -->
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
        </div>
    </div>
</div>

<script>
// View toggle functionality
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

<?php include '../views/footer.php'; ?>
