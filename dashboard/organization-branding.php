<?php
/**
 * Organization logo / branding (Organization Account)
 */

require_once '../config/config.php';
require_role(['Organization Account']);

$page_title = 'Organization Logo - ' . APP_NAME;
include '../views/header.php';

$database = new Database();
$db = $database->getConnection();

$orgId = (int) $_SESSION['organization_id'];
$success_message = '';
$error_message = '';

$stmt = $db->prepare('SELECT logo_path, org_name FROM organizations WHERE id = ?');
$stmt->execute([$orgId]);
$orgRow = $stmt->fetch();
$current_logo_path = $orgRow['logo_path'] ?? null;
$org_display_name = $orgRow['org_name'] ?? $_SESSION['organization_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_logo'])) {
    $prevStmt = $db->prepare('SELECT logo_path FROM organizations WHERE id = ?');
    $prevStmt->execute([$orgId]);
    $prevLogoPath = $prevStmt->fetchColumn() ?: null;

    if (!empty($_POST['remove_logo'])) {
        delete_organization_logo_disk($prevLogoPath);
        $clr = $db->prepare('UPDATE organizations SET logo_path = NULL WHERE id = ?');
        $clr->execute([$orgId]);
        log_audit('UPDATE', 'organizations', $orgId);
        $success_message = 'Logo removed.';
        $current_logo_path = null;
    } else {
        $logoResult = save_organization_logo_upload($orgId);
        if (!empty($logoResult['skipped'])) {
            if (empty($prevLogoPath)) {
                $error_message = 'Please choose an image file to upload.';
            } else {
                $success_message = 'No changes were made.';
            }
        } elseif (!empty($logoResult['error'])) {
            $error_message = $logoResult['error'];
        } elseif (!empty($logoResult['path'])) {
            $logoStmt = $db->prepare('UPDATE organizations SET logo_path = ? WHERE id = ?');
            $logoStmt->execute([$logoResult['path'], $orgId]);
            log_audit('UPDATE', 'organizations', $orgId);
            $success_message = 'Logo updated successfully.';
            $current_logo_path = $logoResult['path'];
        }
    }
}
?>

<div class="container-fluid">
    <div class="row g-0">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Organization logo</h1>
                    <p class="text-sm text-slate-500 mt-1"><?php echo htmlspecialchars($org_display_name); ?> — upload or replace the logo shown on your dashboard and sidebar.</p>
                </div>
                <a href="organization.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    <i class="fas fa-arrow-left text-slate-400"></i>Back to dashboard
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card max-w-lg">
                <div class="card-header flex items-center gap-2">
                    <i class="fas fa-image text-slate-400"></i>
                    <span>Logo image</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($current_logo_path)): ?>
                        <div class="mb-4 p-4 rounded-xl border border-slate-200 bg-slate-50 flex justify-center">
                            <img src="<?php echo htmlspecialchars(BASE_URL . $current_logo_path); ?>" alt="Organization logo" class="max-h-32 max-w-full object-contain rounded-lg bg-white p-2 border border-slate-200">
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-slate-500 mb-4">No logo uploaded yet. Administrators can also set a logo when editing your organization.</p>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="save_logo" value="1">
                        <div>
                            <label for="org_logo" class="form-label">Upload new logo</label>
                            <input type="file" class="form-control" id="org_logo" name="org_logo" accept="image/jpeg,image/png,image/webp,image/gif">
                            <div class="form-text">JPEG, PNG, WebP, or GIF. Maximum <?php echo ORG_LOGO_MAX_BYTES / 1024 / 1024; ?>MB.</div>
                        </div>
                        <?php if (!empty($current_logo_path)): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1">
                                <label class="form-check-label" for="remove_logo">Remove logo</label>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                            <i class="fas fa-save"></i>Save
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../views/footer.php'; ?>
