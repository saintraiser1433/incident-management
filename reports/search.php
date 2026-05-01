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

<div class="container-fluid main-content">
    <div class="max-w-5xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Search Ticket</h1>
                <p class="text-sm text-slate-500 mt-1">Look up the status of your incident report.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>dashboard/responder.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                <i class="fas fa-arrow-left text-slate-400"></i>Back to Departments
            </a>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="card">
                <div class="card-header flex items-center gap-2">
                    <i class="fas fa-ticket-alt text-slate-400"></i>
                    <span>Search by Ticket Number</span>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation space-y-4" novalidate>
                        <div>
                            <label for="report_id" class="form-label">Ticket Number</label>
                            <div class="input-group">
                                <span class="input-group-text">#</span>
                                <input type="text" class="form-control" id="report_id" name="report_id"
                                       value="<?php echo htmlspecialchars($report_id); ?>"
                                       placeholder="Enter ticket number" required>
                            </div>
                            <div class="form-text">Enter the ticket number you received when you submitted your report.</div>
                        </div>

                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                            <i class="fas fa-search"></i>Search Ticket
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header flex items-center gap-2">
                    <i class="fas fa-info-circle text-slate-400"></i>
                    <span>How to Find Your Ticket</span>
                </div>
                <div class="card-body space-y-3">
                    <div class="alert alert-info mb-0">
                        <p class="text-sm font-medium mb-1"><i class="fas fa-lightbulb me-1"></i>Where to find your ticket number:</p>
                        <ul class="text-sm list-disc list-inside space-y-0.5 mb-0">
                            <li>Check your email confirmation (if provided)</li>
                            <li>Look at the success page after submitting your report</li>
                            <li>Contact the assigned organization directly</li>
                            <li>Check any SMS notifications you received</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning mb-0">
                        <p class="text-sm font-medium mb-1"><i class="fas fa-shield-alt me-1"></i>Privacy Notice:</p>
                        <p class="text-sm mb-0">You can only view tickets that you have the ticket number for. This ensures your privacy and the security of incident reports.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($report): ?>
            <div class="card mt-6">
                <div class="card-header flex items-center gap-2">
                    <i class="fas fa-file-alt text-slate-400"></i>
                    <span>Ticket #<?php echo $report['id']; ?></span>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <div class="md:col-span-2">
                            <h4 class="text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($report['title']); ?></h4>
                            <p class="text-sm text-slate-600 mt-2"><?php echo htmlspecialchars($report['description']); ?></p>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs uppercase tracking-wider text-slate-500 font-medium">Status</dt>
                                    <dd class="mt-1">
                                        <?php
                                        $status_badges = ['Pending' => 'bg-warning', 'In Progress' => 'bg-info', 'Resolved' => 'bg-success', 'Closed' => 'bg-secondary'];
                                        $badge_class = $status_badges[$report['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $report['status']; ?></span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wider text-slate-500 font-medium">Assigned To</dt>
                                    <dd class="mt-1 text-slate-900"><?php echo htmlspecialchars($report['org_name']); ?></dd>
                                </div>
                            </dl>
                        </div>
                        <div class="md:text-right">
                            <a href="view.php?id=<?php echo $report['id']; ?>&guest=1" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 transition">
                                <i class="fas fa-eye"></i>View Full Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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

