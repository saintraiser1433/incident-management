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

<div class="container-fluid main-content">
    <div class="flex items-center justify-center min-h-[80vh] py-10">
        <div class="card w-full max-w-2xl">
            <div class="card-body text-center p-8">
                <div class="mb-5 flex justify-center">
                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                        <i class="fas fa-check text-2xl"></i>
                    </div>
                </div>

                <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Report Submitted Successfully</h2>
                <p class="text-sm text-slate-500 mt-2 max-w-md mx-auto">
                    Your incident report has been submitted and is now in the queue for review by the assigned organization.
                </p>

                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5 text-left">
                    <h5 class="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-file-alt text-slate-400"></i>Report Details
                    </h5>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-y-3 text-sm">
                        <dt class="text-slate-500">Report ID</dt>
                        <dd class="sm:col-span-2 font-mono text-slate-900">#<?php echo htmlspecialchars($report['id']); ?></dd>

                        <dt class="text-slate-500">Title</dt>
                        <dd class="sm:col-span-2 text-slate-900"><?php echo htmlspecialchars($report['title']); ?></dd>

                        <dt class="text-slate-500">Reported By</dt>
                        <dd class="sm:col-span-2 text-slate-900"><?php echo htmlspecialchars($report['reported_by']); ?></dd>

                        <dt class="text-slate-500">Assigned To</dt>
                        <dd class="sm:col-span-2 text-slate-900"><?php echo htmlspecialchars($report['org_name']); ?></dd>

                        <dt class="text-slate-500">Status</dt>
                        <dd class="sm:col-span-2"><span class="badge bg-warning">Pending Review</span></dd>

                        <dt class="text-slate-500">Submitted</dt>
                        <dd class="sm:col-span-2 text-slate-900"><?php echo date('M d, Y \a\t g:i A', strtotime($report['created_at'])); ?></dd>
                    </dl>
                </div>

                <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-left">
                    <p class="text-sm font-medium text-blue-800 mb-1.5"><i class="fas fa-info-circle me-1"></i>What happens next?</p>
                    <ul class="text-sm text-blue-800/90 list-disc list-inside space-y-0.5">
                        <li>The assigned organization will review your report</li>
                        <li>You may receive SMS notifications about status updates</li>
                        <li>The report will be processed according to its priority</li>
                    </ul>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-2">
                    <?php if ($redirect_to === 'departments'): ?>
                        <a href="../dashboard/responder.php" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800 transition">
                            <i class="fas fa-home"></i>Back to Departments
                        </a>
                    <?php else: ?>
                        <a href="index.php" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800 transition">
                            <i class="fas fa-list"></i>View All Reports
                        </a>
                    <?php endif; ?>

                    <a href="create.php<?php echo $redirect_to === 'departments' ? '?redirect=departments' : ''; ?>"
                       class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        <i class="fas fa-plus text-slate-400"></i>Submit Another
                    </a>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-200">
                    <p class="text-xs text-slate-500">
                        <i class="fas fa-shield-alt me-1"></i>
                        Your report is secure and will be handled confidentially by the assigned organization.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../views/footer.php'; ?>

