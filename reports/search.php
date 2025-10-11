<?php
/**
 * Ticket Search Page (Guest Access)
 * Incident Report Management System
 */

require_once '../config/config.php';

$error_message = '';
$report = null;
$report_id = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $report_id = sanitize_input($_POST['report_id'] ?? '');
    
    if (empty($report_id)) {
        $error_message = 'Please enter a ticket number.';
    } else {
        // Validate that it's a number
        if (!is_numeric($report_id)) {
            $error_message = 'Please enter a valid ticket number.';
        } else {
            // Get report details
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT ir.*, o.org_name 
                      FROM incident_reports ir 
                      LEFT JOIN organizations o ON ir.organization_id = o.id 
                      WHERE ir.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$report_id]);
            $report = $stmt->fetch();
            
            if (!$report) {
                $error_message = 'Ticket #' . $report_id . ' not found. Please check the ticket number and try again.';
            }
        }
    }
}

$page_title = 'Search Ticket - ' . APP_NAME;
include '../views/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-search me-2"></i>Search Ticket
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo BASE_URL; ?>dashboard/responder.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Departments
                    </a>
                </div>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-ticket-alt me-2"></i>Search by Ticket Number
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="report_id" class="form-label">Ticket Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">#</span>
                                        <input type="text" class="form-control" id="report_id" name="report_id" 
                                               value="<?php echo htmlspecialchars($report_id); ?>" 
                                               placeholder="Enter ticket number" required>
                                    </div>
                                    <div class="form-text">Enter the ticket number you received when you submitted your report</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Search Ticket
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>How to Find Your Ticket
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h6 class="alert-heading">
                                    <i class="fas fa-lightbulb me-2"></i>Where to find your ticket number:
                                </h6>
                                <ul class="mb-0">
                                    <li>Check your email confirmation (if provided)</li>
                                    <li>Look at the success page after submitting your report</li>
                                    <li>Contact the assigned organization directly</li>
                                    <li>Check any SMS notifications you received</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-warning">
                                <h6 class="alert-heading">
                                    <i class="fas fa-shield-alt me-2"></i>Privacy Notice:
                                </h6>
                                <p class="mb-0">
                                    You can only view tickets that you have the ticket number for. 
                                    This ensures your privacy and the security of incident reports.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($report): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt me-2"></i>Ticket #<?php echo $report['id']; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4><?php echo htmlspecialchars($report['title']); ?></h4>
                                        <p class="text-muted"><?php echo htmlspecialchars($report['description']); ?></p>
                                        
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <strong>Status:</strong>
                                                <?php
                                                $status_badges = [
                                                    'Pending' => 'bg-warning',
                                                    'In Progress' => 'bg-info',
                                                    'Resolved' => 'bg-success',
                                                    'Closed' => 'bg-secondary'
                                                ];
                                                $badge_class = $status_badges[$report['status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $report['status']; ?></span>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong>Assigned To:</strong>
                                                <?php echo htmlspecialchars($report['org_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <a href="view.php?id=<?php echo $report['id']; ?>&guest=1" class="btn btn-primary btn-lg">
                                            <i class="fas fa-eye me-2"></i>View Full Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../views/footer.php'; ?>

