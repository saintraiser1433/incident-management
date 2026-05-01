<?php
/**
 * Incident Record Form (IRF) — on-screen layout matches PDF export
 */

require_once '../config/config.php';

$report_id = $_GET['id'] ?? 0;

if (!$report_id) {
    redirect('index.php?error=invalid_report');
}

require_login();

function irf_e($s)
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
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
        error_log('Error checking organization member access to resolution report: ' . $e->getMessage());
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

$pnp_logo_url = BASE_URL . 'assets/images/pnplogo.png';
$org_logo_url = !empty($report['org_logo_path']) ? BASE_URL . $report['org_logo_path'] : null;

$page_title = 'Incident Record Form #' . $report['id'] . ' - ' . APP_NAME;
include '../views/header.php';
?>

<div class="container-fluid resolution-report-page">
    <div class="row">
        <main class="col-12 px-md-4 main-content">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200 no-print">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Incident Record Form</h1>
                    <p class="text-sm text-slate-500 mt-1">PDF downloads include <strong>two complete copies</strong>: watermarked <strong>PERSONAL COPY</strong> first, then <strong>ORGANIZATION COPY</strong>.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="view.php?id=<?php echo (int) $report['id']; ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        <i class="fas fa-arrow-left text-slate-400"></i>Back to report
                    </a>
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition" onclick="window.print();">
                        <i class="fas fa-print text-slate-400"></i>Print
                    </button>
                    <a href="resolution_report_pdf.php?id=<?php echo (int) $report['id']; ?>"
                       class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 transition"
                       target="_blank" rel="noopener">
                        <i class="fas fa-file-pdf"></i>PDF
                    </a>
                </div>
            </div>

            <div class="irf-sheet mx-auto bg-white">

                <table class="irf-doc irf-doc-noborder">
                    <tr class="irf-header-logos">
                        <td class="irf-logo-slot">
                            <img src="<?php echo irf_e($pnp_logo_url); ?>" alt="PNP" width="48" height="48">
                        </td>
                        <td class="irf-title-stack">
                            <div class="irf-h-org">Philippine National Police</div>
                            <div class="irf-h-main">INCIDENT RECORD FORM</div>
                            <div class="irf-h-sub"><?php echo irf_e($report['org_name'] ?? ''); ?></div>
                        </td>
                        <td class="irf-logo-slot">
                            <?php if ($org_logo_url): ?>
                                <img src="<?php echo irf_e($org_logo_url); ?>" alt="Organization" width="48" height="48">
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <table class="irf-doc">
                    <tr>
                        <td class="w-34">
                            <div class="irf-l">IRF ENTRY NUMBER</div>
                            <div class="irf-v irf-v-strong"><?php echo irf_e($irf_entry); ?></div>
                        </td>
                        <td class="w-40">
                            <div class="irf-l">TYPE OF INCIDENT</div>
                            <div class="irf-v"><?php echo irf_e($type_label); ?></div>
                        </td>
                        <td class="w-26">
                            <div class="irf-l">COPY FOR</div>
                            <div class="irf-v">ORGANIZATION FILE</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" class="irf-instr"><?php echo irf_e($pnp_instruction); ?></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="irf-l">DATE AND TIME REPORTED</div>
                            <div class="irf-v"><?php echo irf_e($dt_reported); ?></div>
                        </td>
                        <td>
                            <div class="irf-l">DATE AND TIME OF INCIDENT</div>
                            <div class="irf-v"><?php echo irf_e($dt_incident); ?></div>
                        </td>
                        <td>
                            <div class="irf-l">PLACE OF INCIDENT</div>
                            <div class="irf-v"><?php echo irf_e($report['location'] ?? ''); ?></div>
                        </td>
                    </tr>
                </table>

                <table class="irf-doc irf-doc-mt">
                    <tr><td colspan="6" class="irf-section">ITEM &quot;A&quot; — INCIDENT REPORT TITLE / REFERENCE</td></tr>
                    <tr>
                        <td colspan="6">
                            <div class="irf-l">OFFICIAL REPORT NAME / TITLE (SELECTED INCIDENT)</div>
                            <div class="irf-v irf-v-lg"><?php echo irf_e($report['title'] ?? ''); ?></div>
                            <div class="irf-l irf-l-mt">STATUS / PRIORITY</div>
                            <div class="irf-v">
                                <?php echo irf_e($report['status'] ?? ''); ?>
                                <?php if (!empty($report['priority_number'])): ?>
                                    &nbsp;&nbsp;|&nbsp;&nbsp;PRIORITY #<?php echo (int) $report['priority_number']; ?>
                                <?php endif; ?>
                                &nbsp;&nbsp;|&nbsp;&nbsp;SEVERITY: <?php echo irf_e($report['severity_level'] ?? ''); ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <table class="irf-doc irf-doc-mt">
                    <tr><td colspan="6" class="irf-section">ITEM &quot;B&quot; — SUSPECT&apos;S DATA</td></tr>
                    <tr>
                        <td colspan="2"><div class="irf-l">FAMILY NAME</div><div class="irf-v">—</div></td>
                        <td colspan="2"><div class="irf-l">FIRST NAME</div><div class="irf-v">—</div></td>
                        <td colspan="2"><div class="irf-l">MIDDLE NAME / EXTENSION</div><div class="irf-v">—</div></td>
                    </tr>
                    <tr>
                        <td colspan="2"><div class="irf-l">CITIZENSHIP</div><div class="irf-v">—</div></td>
                        <td><div class="irf-l">GENDER</div><div class="irf-v">—</div></td>
                        <td><div class="irf-l">CIVIL STATUS</div><div class="irf-v">—</div></td>
                        <td><div class="irf-l">AGE</div><div class="irf-v">—</div></td>
                        <td><div class="irf-l">DATE OF BIRTH</div><div class="irf-v">—</div></td>
                    </tr>
                    <tr>
                        <td colspan="6"><div class="irf-l">ADDRESS</div><div class="irf-v">Not indicated / No suspect data on file.</div></td>
                    </tr>
                </table>

                <table class="irf-doc irf-doc-mt">
                    <tr><td colspan="6" class="irf-section">ITEM &quot;C&quot; — VICTIM&apos;S DATA</td></tr>
                    <?php if (!empty($victims)): ?>
                        <?php foreach ($victims as $vi => $v): ?>
                            <?php if ($vi > 0): ?>
                                <tr><td colspan="6" class="irf-subsep">ADDITIONAL PERSON <?php echo (int) ($vi + 1); ?></td></tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="2"><div class="irf-l">FAMILY NAME</div><div class="irf-v">—</div></td>
                                <td colspan="2"><div class="irf-l">FIRST NAME</div><div class="irf-v">—</div></td>
                                <td colspan="2"><div class="irf-l">MIDDLE NAME</div><div class="irf-v">—</div></td>
                            </tr>
                            <tr>
                                <td colspan="6"><div class="irf-l">FULL NAME (AS RECORDED)</div><div class="irf-v"><?php echo irf_e($v['witness_name'] ?? ''); ?></div></td>
                            </tr>
                            <tr>
                                <td colspan="2"><div class="irf-l">CONTACT / PHONE NUMBER</div><div class="irf-v"><?php echo irf_e($v['witness_contact'] ?? ''); ?></div></td>
                                <td colspan="2"><div class="irf-l">RECORDED ON</div><div class="irf-v"><?php echo !empty($v['created_at']) ? irf_e(date('Y-m-d H:i', strtotime($v['created_at']))) : ''; ?></div></td>
                                <td colspan="2"><div class="irf-l">RELATION TO INCIDENT</div><div class="irf-v">—</div></td>
                            </tr>
                            <tr>
                                <td colspan="6"><div class="irf-l">ADDRESS</div><div class="irf-v">—</div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6"><div class="irf-v irf-v-muted">No named victim entries on file (witness/victim list empty).</div></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($report['family_contact_name']) || !empty($report['family_contact_number'])): ?>
                        <tr>
                            <td colspan="3"><div class="irf-l">NEXT OF KIN / EMERGENCY CONTACT</div><div class="irf-v"><?php echo irf_e($report['family_contact_name'] ?? ''); ?></div></td>
                            <td colspan="3"><div class="irf-l">CONTACT NUMBER</div><div class="irf-v"><?php echo irf_e($report['family_contact_number'] ?? ''); ?></div></td>
                        </tr>
                    <?php endif; ?>
                </table>

                <table class="irf-doc irf-doc-mt">
                    <tr><td colspan="3" class="irf-section">ITEM &quot;D&quot; — NARRATIVE OF INCIDENT</td></tr>
                    <tr>
                        <td><div class="irf-l">DATE AND TIME REPORTED</div><div class="irf-v"><?php echo irf_e($dt_reported); ?></div></td>
                        <td><div class="irf-l">DATE AND TIME OF INCIDENT</div><div class="irf-v"><?php echo irf_e($dt_incident); ?></div></td>
                        <td><div class="irf-l">PLACE OF INCIDENT</div><div class="irf-v"><?php echo irf_e($report['location'] ?? ''); ?></div></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="irf-banner">
                            THE NARRATIVE OF THE INCIDENT OR EVENT, ANSWERING THE WHO, WHAT, WHEN, WHERE, WHY AND HOW OF REPORTING.
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" class="irf-nbox"><?php echo nl2br(irf_e($narrative_body . $resolv_extra)); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="irf-footer-note">
                            Recorded / extracted electronically — Investigator-on-case / Desk reference:
                            <strong><?php echo irf_e($report['assigned_member_name'] ?? '[NOT ASSIGNED]'); ?></strong>
                            &nbsp;|&nbsp; Resolved on:
                            <?php echo !empty($resolutionDate) ? irf_e(format_datetime($resolutionDate)) : ''; ?>
                        </td>
                    </tr>
                </table>

                <?php if (!empty($photos)): ?>
                    <table class="irf-doc irf-doc-mt">
                        <tr><td colspan="3" class="irf-section">ATTACHED DIGITAL PHOTOS</td></tr>
                        <?php foreach (array_chunk($photos, 3) as $row): ?>
                            <tr class="irf-photo-row">
                                <?php foreach ($row as $photo): ?>
                                    <td class="irf-photo-cell">
                                        <img src="<?php echo irf_e(BASE_URL . $photo['file_path']); ?>" alt="">
                                        <div class="irf-photo-cap"><?php echo !empty($photo['uploaded_at']) ? irf_e(format_datetime($photo['uploaded_at'])) : ''; ?></div>
                                    </td>
                                <?php endforeach; ?>
                                <?php for ($p = count($row); $p < 3; $p++): ?>
                                    <td></td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>

                <div class="irf-page2">

                    <table class="irf-doc irf-doc-mt">
                        <tr>
                            <td class="w-38" rowspan="2">
                                I HEREBY CERTIFY TO THE CORRECTNESS OF THE FOREGOING TO THE BEST OF MY KNOWLEDGE AND BELIEF.
                            </td>
                            <td class="w-34">
                                <div class="irf-l">NAME OF INVESTIGATOR ON CASE</div>
                                <div class="irf-v irf-v-tall"><?php echo irf_e($report['assigned_member_name'] ?? ''); ?></div>
                            </td>
                            <td class="w-28">
                                <div class="irf-l">SIGNATURE</div>
                                <div class="irf-sig-line"></div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="irf-note-small">
                                (Reporting party fields omitted per electronic filing policy — certification by investigator only.)
                            </td>
                        </tr>
                    </table>

                    <table class="irf-doc irf-doc-mt">
                        <tr>
                            <td class="w-38">SUBSCRIBED AND SWORN TO BEFORE ME</td>
                            <td class="w-34">
                                <div class="irf-l">NAME OF ADMINISTERING OFFICER (DUTY OFFICER)</div>
                                <div class="irf-v irf-v-tall">—</div>
                            </td>
                            <td class="w-28">
                                <div class="irf-l">SIGNATURE OF ADMINISTERING OFFICER</div>
                                <div class="irf-sig-line"></div>
                            </td>
                        </tr>
                    </table>

                    <table class="irf-doc irf-doc-mt">
                        <tr>
                            <td class="w-62">
                                <div class="irf-l">RANK, NAME AND DESIGNATION OF POLICE OFFICER (DUTY INVESTIGATOR / IOC / ASSISTING OFFICER)</div>
                                <div class="irf-v irf-v-tall irf-v-strong"><?php echo irf_e($report['assigned_member_name'] ?? ''); ?></div>
                            </td>
                            <td class="w-38">
                                <div class="irf-l">SIGNATURE</div>
                                <div class="irf-sig-line"></div>
                            </td>
                        </tr>
                    </table>

                    <table class="irf-doc irf-doc-mt">
                        <tr>
                            <td class="w-22">INCIDENT RECORDED IN THE BLOTTER BY</td>
                            <td class="w-28">
                                <div class="irf-l">RANK / NAME OF DESK OFFICER</div>
                                <div class="irf-v irf-v-tall">—</div>
                            </td>
                            <td class="w-26">
                                <div class="irf-l">SIGNATURE OF DESK OFFICER</div>
                                <div class="irf-sig-line"></div>
                            </td>
                            <td class="w-24">
                                <div class="irf-l">BLOTTER ENTRY NR</div>
                                <div class="irf-v irf-v-strong"><?php echo irf_e($irf_entry); ?></div>
                            </td>
                        </tr>
                    </table>

                    <table class="irf-doc irf-doc-mt">
                        <tr>
                            <td colspan="4" class="irf-instr">
                                Keep the copy of this Incident Record Form (IRF). An update on the progress of the incident you reported may be provided upon presentation of this IRF.
                                Below are the contact details of the responding organization / station as recorded in the system.
                            </td>
                        </tr>
                        <tr class="irf-grey-head">
                            <td>Name of Station / Organization</td>
                            <td>Detail</td>
                            <td>Telephone / Mobile</td>
                            <td>Detail</td>
                        </tr>
                        <tr>
                            <td class="irf-l">Organization</td>
                            <td class="irf-v"><?php echo irf_e($report['org_name'] ?? ''); ?></td>
                            <td class="irf-l">Contact Number</td>
                            <td class="irf-v"><?php echo irf_e($report['org_contact'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="irf-l">Investigator-on-Case</td>
                            <td class="irf-v"><?php echo irf_e($report['assigned_member_name'] ?? ''); ?></td>
                            <td class="irf-l">Mobile Phone</td>
                            <td class="irf-v">—</td>
                        </tr>
                        <tr>
                            <td class="irf-l">Chief / Head of Office</td>
                            <td class="irf-v">—</td>
                            <td class="irf-l">Mobile Phone</td>
                            <td class="irf-v">—</td>
                        </tr>
                        <?php if (!empty($report['org_address'])): ?>
                            <tr>
                                <td class="irf-l">Address</td>
                                <td colspan="3" class="irf-v"><?php echo irf_e($report['org_address']); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>

                </div>

            </div>
        </main>
    </div>
</div>

<style>
.resolution-report-page { background: #f1f5f9; padding-bottom: 2rem; }

.irf-sheet {
    max-width: 900px;
    padding: 24px 20px 40px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(15,23,42,0.06);
    font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    font-size: 12px;
    color: #000;
}

.irf-doc {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
.irf-doc td, .irf-doc th {
    border: 1px solid #000;
    padding: 6px 8px;
    vertical-align: top;
    word-wrap: break-word;
}
.irf-doc-noborder td { border: none !important; }
.irf-doc-mt { margin-top: 10px; }

.irf-header-logos td { vertical-align: middle !important; padding-bottom: 10px !important; border-bottom: 2px solid #000 !important; }
.irf-logo-slot {
    width: 56px;
    max-width: 56px;
    text-align: center;
    vertical-align: middle !important;
}
.irf-logo-slot img {
    width: 48px !important;
    height: 48px !important;
    max-width: 48px !important;
    max-height: 48px !important;
    object-fit: contain !important;
    display: block;
    margin: 0 auto;
}
.irf-title-stack { text-align: center; padding: 4px 12px !important; }
.irf-h-org { font-size: 13px; font-weight: 600; }
.irf-h-main { font-size: 17px; font-weight: 800; letter-spacing: 0.06em; margin-top: 4px; }
.irf-h-sub { font-size: 12px; color: #374151; margin-top: 4px; line-height: 1.35; }

.irf-l {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #374151;
}
.irf-v { font-size: 12px; margin-top: 3px; line-height: 1.35; }
.irf-v-strong { font-weight: 700; }
.irf-v-lg { font-size: 14px; font-weight: 700; margin-top: 4px; }
.irf-v-muted { color: #64748b; font-style: italic; padding: 8px 0; }
.irf-v-tall { min-height: 36px; }
.irf-l-mt { margin-top: 10px; }

.irf-instr {
    font-size: 11px;
    line-height: 1.35;
    text-align: justify;
    background: #fafafa;
}

.irf-section {
    background: #d9d9d9 !important;
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 7px 8px !important;
}

.irf-subsep {
    background: #f3f4f6;
    font-size: 10px;
    font-weight: 700;
    padding: 4px 8px !important;
}

.irf-banner {
    background: #f0f0f0;
    font-weight: 700;
    font-size: 11px;
    line-height: 1.35;
}

.irf-nbox {
    font-size: 12px;
    line-height: 1.45;
    white-space: pre-wrap;
}

.irf-footer-note {
    font-size: 11px;
    background: #fafafa;
}

.irf-photo-row td { vertical-align: top; }
.irf-photo-cell img {
    width: 100%;
    max-height: 160px;
    object-fit: contain;
    display: block;
    border: 1px solid #ccc;
}
.irf-photo-cap { font-size: 10px; color: #64748b; text-align: center; margin-top: 4px; }

.irf-sig-line {
    border-bottom: 1px solid #000;
    min-height: 36px;
    margin-top: 4px;
}

.irf-grey-head td {
    background: #e8e8e8;
    font-weight: 700;
    font-size: 10px;
}

.irf-note-small { font-size: 10px; color: #6b7280; }

.w-22 { width: 22%; }
.w-26 { width: 26%; }
.w-28 { width: 28%; }
.w-34 { width: 34%; }
.w-38 { width: 38%; }
.w-40 { width: 40%; }
.w-62 { width: 62%; }

.irf-page2 { margin-top: 28px; padding-top: 8px; border-top: 1px dashed #cbd5e1; }

@media print {
    body { background: #fff !important; }
    .navbar, .no-print, .sidebar { display: none !important; }
    .resolution-report-page { background: #fff !important; padding: 0 !important; }
    .main-content { margin: 0 !important; padding: 12px !important; }
    .irf-sheet {
        max-width: 100% !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    .irf-page2 {
        border-top: none;
        padding-top: 0;
    }
}
</style>

<?php include '../views/footer.php'; ?>
