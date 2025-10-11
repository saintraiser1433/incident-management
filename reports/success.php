<?php
/**
 * Report Submission Success Page
 * Incident Report Management System
 */

require_once '../config/config.php';

// Get report ID from URL parameter
$report_id = $_GET['id'] ?? null;
$redirect_to = $_GET['redirect'] ?? 'departments';

if (!$report_id) {
    // If no report ID, redirect to appropriate page
    if ($redirect_to === 'departments') {
        redirect('dashboard/responder.php');
    } else {
        redirect('reports/index.php');
    }
}

// Get report details for display
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
    // Report not found, redirect
    if ($redirect_to === 'departments') {
        redirect('dashboard/responder.php');
    } else {
        redirect('reports/index.php');
    }
}

$page_title = 'Report Submitted Successfully - ' . APP_NAME;
include '../views/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-center align-items-center min-vh-100">
                <div class="card shadow-lg border-0" style="max-width: 600px; width: 100%;">
                    <div class="card-body text-center p-5">
                        <!-- Success Icon -->
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white" 
                                 style="width: 80px; height: 80px; font-size: 2.5rem;">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        
                        <!-- Success Message -->
                        <h2 class="card-title text-success mb-3">Report Submitted Successfully!</h2>
                        <p class="text-muted mb-4">
                            Your incident report has been submitted and is now in the queue for review by the assigned organization.
                        </p>
                        
                        <!-- Report Details -->
                        <div class="bg-light rounded p-4 mb-4 text-start">
                            <h5 class="mb-3">
                                <i class="fas fa-file-alt text-primary me-2"></i>
                                Report Details
                            </h5>
                            <div class="row">
                                <div class="col-sm-4">
                                    <strong>Report ID:</strong>
                                </div>
                                <div class="col-sm-8">
                                    #<?php echo htmlspecialchars($report['id']); ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <strong>Title:</strong>
                                </div>
                                <div class="col-sm-8">
                                    <?php echo htmlspecialchars($report['title']); ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <strong>Reported By:</strong>
                                </div>
                                <div class="col-sm-8">
                                    <?php echo htmlspecialchars($report['reported_by']); ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <strong>Assigned To:</strong>
                                </div>
                                <div class="col-sm-8">
                                    <?php echo htmlspecialchars($report['org_name']); ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <strong>Status:</strong>
                                </div>
                                <div class="col-sm-8">
                                    <span class="badge bg-warning">Pending Review</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <strong>Submitted:</strong>
                                </div>
                                <div class="col-sm-8">
                                    <?php echo date('M d, Y \a\t g:i A', strtotime($report['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Next Steps -->
                        <div class="alert alert-info mb-4">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>
                                What happens next?
                            </h6>
                            <ul class="mb-0 text-start">
                                <li>The assigned organization will review your report</li>
                                <li>You may receive SMS notifications about status updates</li>
                                <li>The report will be processed according to its priority</li>
                            </ul>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <?php if ($redirect_to === 'departments'): ?>
                                <!-- Guest user - back to departments -->
                                <a href="../dashboard/responder.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-home me-2"></i>
                                    Back to Departments
                                </a>
                            <?php else: ?>
                                <!-- Logged in user - back to reports -->
                                <a href="index.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-list me-2"></i>
                                    View All Reports
                                </a>
                            <?php endif; ?>
                            
                            <a href="create.php<?php echo $redirect_to === 'departments' ? '?redirect=departments' : ''; ?>" 
                               class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-plus me-2"></i>
                                Submit Another Report
                            </a>
                        </div>
                        
                        <!-- Additional Info -->
                        <div class="mt-4 pt-3 border-top">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>
                                Your report is secure and will be handled confidentially by the assigned organization.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.min-vh-100 {
    min-height: 100vh;
}

.card {
    border-radius: 15px;
}

.bg-success {
    background-color: #28a745 !important;
}

.btn-lg {
    padding: 12px 24px;
    font-size: 1.1rem;
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

.bg-light {
    background-color: #f8f9fa !important;
}
</style>

<?php include '../views/footer.php'; ?>

