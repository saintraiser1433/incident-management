<?php
/**
 * Incident Record Form (IRF) — PDF aligned with PNP IRF layout
 */

require_once '../config/config.php';
require_once '../vendor/autoload.php';

use Mpdf\Mpdf;

$report_id = $_GET['id'] ?? 0;

if (!$report_id) {
    redirect('index.php?error=invalid_report');
}

require_login();

function irf_h($s)
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function _img_data_uri($absPath)
{
    if (!$absPath || !is_file($absPath)) {
        return null;
    }
    $mime = function_exists('mime_content_type') ? @mime_content_type($absPath) : null;
    if (!$mime) {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png'
            : ($ext === 'gif' ? 'image/gif'
            : ($ext === 'webp' ? 'image/webp' : 'image/jpeg'));
    }
    $bin = @file_get_contents($absPath);
    if ($bin === false) {
        return null;
    }
    return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT ir.*, ir.resolution_notes,
                 o.org_name, o.org_type, o.address as org_address,
                 o.contact_number as org_contact, o.logo_path as org_logo_path,
                 rq.priority_number, rq.assigned_to,
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
    redirect('index.php?error=report_not_found');
}

if ($_SESSION['user_role'] === 'Organization Account' && $report['organization_id'] != $_SESSION['organization_id']) {
    redirect('index.php?error=access_denied');
}

if ($_SESSION['user_role'] === 'Organization Member') {
    try {
        $memberStmt = $db->prepare('SELECT id FROM organization_members WHERE user_id = ? AND organization_id = ?');
        $memberStmt->execute([$_SESSION['user_id'], $report['organization_id']]);
        $member = $memberStmt->fetch();
        if (!$member || (int) $report['assigned_to'] !== (int) $member['id']) {
            redirect('index.php?error=access_denied');
        }
    } catch (Exception $e) {
        redirect('index.php?error=access_denied');
    }
}

if (!in_array($report['status'], ['Resolved', 'Closed'], true)) {
    redirect('view.php?id=' . urlencode($report_id) . '&error=not_resolved');
}

$photoStmt = $db->prepare('SELECT * FROM incident_photos WHERE report_id = ? ORDER BY uploaded_at');
$photoStmt->execute([$report_id]);
$photos = $photoStmt->fetchAll();

$witnessStmt = $db->prepare('SELECT * FROM incident_witnesses WHERE report_id = ? ORDER BY created_at');
$witnessStmt->execute([$report_id]);
$victims = $witnessStmt->fetchAll();

$updatesStmt = $db->prepare('
    SELECT iu.*, u.name AS updated_by_name
    FROM incident_updates iu
    LEFT JOIN users u ON iu.updated_by = u.id
    WHERE iu.report_id = ?
    ORDER BY iu.created_at ASC
');
$updatesStmt->execute([$report_id]);
$updates = $updatesStmt->fetchAll();

$resolutionDate = null;
foreach ($updates as $u) {
    if (strpos($u['update_text'], 'Status changed from') !== false &&
        (strpos($u['update_text'], "to 'Resolved'") !== false || strpos($u['update_text'], "to 'Closed'") !== false)) {
        $resolutionDate = $u['created_at'];
        break;
    }
}
if (!$resolutionDate) {
    $resolutionDate = $report['created_at'];
}

$pnp_logo_abs = realpath(__DIR__ . '/../assets/images/pnplogo.png');
$pnp_logo_src = _img_data_uri($pnp_logo_abs);

$org_logo_src = null;
if (!empty($report['org_logo_path'])) {
    $org_logo_abs = realpath(__DIR__ . '/../' . $report['org_logo_path']);
    $org_logo_src = _img_data_uri($org_logo_abs);
}

$orgIdPart = !empty($report['organization_id']) ? str_pad((string) (int) $report['organization_id'], 3, '0', STR_PAD_LEFT) : '000';
$createdTs = !empty($report['created_at']) ? strtotime($report['created_at']) : time();
$irf_entry = $orgIdPart . '-' . date('Ym', $createdTs) . '-' . str_pad((string) (int) $report['id'], 6, '0', STR_PAD_LEFT);

$dt_reported = !empty($report['created_at']) ? date('Y-m-d H:i:s', strtotime($report['created_at'])) : '';
$dt_incident = '';
if (!empty($report['incident_date'])) {
    $dt_incident = $report['incident_date'];
    if (!empty($report['incident_time'])) {
        $dt_incident .= ' ' . $report['incident_time'];
    }
}

$type_label = trim(($report['category'] ?? '') . ' — ' . ($report['title'] ?? ''));

$narrative_body = trim((string) ($report['description'] ?? ''));
$resolv_extra = '';
if (!empty($report['resolution_notes'])) {
    $resolv_extra = "\n\n--- RESOLUTION / ACTION TAKEN ---\n" . trim((string) $report['resolution_notes']);
} elseif (!empty($updates)) {
    $lastText = $updates[count($updates) - 1]['update_text'] ?? '';
    if ($lastText !== '') {
        $resolv_extra = "\n\n--- LAST SYSTEM UPDATE ---\n" . $lastText;
    }
}

$pnp_instruction = 'This Incident Record Form is extracted from the electronic incident management system. '
    . 'For inquiries regarding this entry, contact the station listed at the bottom of page 2. '
    . 'PNP DIDM reference information may be found at www.didm.pnp.gov.ph.';

$irf_pdf_partial = __DIR__ . '/partials/irf_pdf_body.php';

$irf_copy_for = 'PERSONAL COPY';
ob_start();
require $irf_pdf_partial;
$html_personal = ob_get_clean();

$irf_copy_for = 'ORGANIZATION COPY';
ob_start();
require $irf_pdf_partial;
$html_organization = ob_get_clean();

$mpdf = new Mpdf([
    'format' => 'A4',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 10,
    'margin_bottom' => 10,
]);

$mpdf->showWatermarkText = true;
$mpdf->watermarkTextAlpha = 0.09;
$mpdf->watermarkAngle = 33;
$mpdf->watermark_size = 88;

$mpdf->SetTitle('IRF ' . $irf_entry . ' — Personal & Organization copies');

$mpdf->SetWatermarkText('PERSONAL COPY', 0.09);
$mpdf->WriteHTML($html_personal);

$mpdf->AddPage();
$mpdf->SetWatermarkText('ORGANIZATION COPY', 0.09);
$mpdf->WriteHTML($html_organization);

$filename = 'IRF_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $irf_entry) . '_two_copies.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);

exit;
