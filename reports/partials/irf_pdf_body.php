<?php
/**
 * IRF PDF HTML body (included twice for Personal / Organization copies).
 *
 * Required variables (from resolution_report_pdf.php): $report, $victims, $photos,
 * $pnp_logo_src, $org_logo_src, $irf_entry, $dt_reported, $dt_incident, $type_label,
 * $narrative_body, $resolv_extra, $pnp_instruction, $resolutionDate, $irf_copy_for
 */
if (!isset($irf_copy_for)) {
    $irf_copy_for = 'ORGANIZATION COPY';
}
$irf_partial_root = dirname(__DIR__);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>IRF <?php echo irf_h($irf_entry); ?> — <?php echo irf_h($irf_copy_for); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: dejavusanscondensed, dejavusans, sans-serif;
            font-size: 7.5pt;
            color: #000000;
            margin: 0;
            padding: 0;
        }
        .t { width: 100%; border-collapse: collapse; }
        .t td, .t th {
            border: 0.35pt solid #000000;
            vertical-align: top;
            padding: 2.5pt 3pt;
        }
        .bd-none td { border: none !important; padding: 0 !important; }
        .logo-cell {
            width: 18mm !important;
            max-width: 18mm !important;
            text-align: center;
            vertical-align: middle !important;
            overflow: hidden !important;
            padding: 2mm !important;
        }
        .logo-cell img {
            width: 14mm !important;
            height: 14mm !important;
            max-width: 14mm !important;
            max-height: 14mm !important;
            object-fit: contain !important;
            display: block !important;
            margin: 0 auto !important;
        }
        .ctr { text-align: center; }
        .lbl {
            font-size: 6.3pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .val { font-size: 7.5pt; margin-top: 1pt; line-height: 1.2; }
        .hdr-main { font-size: 9pt; font-weight: bold; }
        .hdr-sub { font-size: 7pt; margin-top: 1pt; }
        .section {
            background-color: #d9d9d9;
            font-weight: bold;
            font-size: 7pt;
            text-transform: uppercase;
            padding: 3pt 4pt !important;
        }
        .instr {
            font-size: 6.3pt;
            line-height: 1.25;
            text-align: justify;
            padding: 4pt !important;
        }
        .nbox {
            font-size: 7.5pt;
            line-height: 1.35;
            padding: 5pt !important;
        }
        .grey-head { background-color: #e8e8e8; font-weight: bold; font-size: 6.5pt; }
        .photo-grid td { width: 33.33%; padding: 2pt; vertical-align: top; border: 0.35pt solid #000; }
        .photo-grid img { width: 100%; height: 42mm; max-height: 42mm; object-fit: contain; display: block; }
        .photo-cap { font-size: 6pt; color: #333; text-align: center; margin-top: 1pt; }
        .sig-line { border-bottom: 0.5pt solid #000; height: 26pt; margin-top: 4pt; }
        .irf-sig-block { page-break-inside: avoid; }
    </style>
</head>
<body>

<table class="t bd-none" style="margin-bottom:4pt;">
    <tr>
        <td class="logo-cell">
            <?php if ($pnp_logo_src): ?>
                <img src="<?php echo $pnp_logo_src; ?>" alt="" width="53" height="53">
            <?php endif; ?>
        </td>
        <td class="ctr" style="vertical-align:middle;">
            <div class="hdr-main">Philippine National Police</div>
            <div style="font-size:12pt;font-weight:bold;margin-top:2pt;letter-spacing:0.5pt;">INCIDENT RECORD FORM</div>
            <div class="hdr-sub"><?php echo irf_h($report['org_name'] ?? ''); ?></div>
        </td>
        <td class="logo-cell">
            <?php if ($org_logo_src): ?>
                <img src="<?php echo $org_logo_src; ?>" alt="" width="53" height="53">
            <?php endif; ?>
        </td>
    </tr>
</table>

<table class="t">
    <tr>
        <td width="34%">
            <div class="lbl">IRF ENTRY NUMBER</div>
            <div class="val"><strong><?php echo irf_h($irf_entry); ?></strong></div>
        </td>
        <td width="40%">
            <div class="lbl">TYPE OF INCIDENT</div>
            <div class="val"><?php echo irf_h($type_label); ?></div>
        </td>
        <td width="26%">
            <div class="lbl">COPY FOR</div>
            <div class="val"><?php echo irf_h($irf_copy_for); ?></div>
        </td>
    </tr>
    <tr>
        <td colspan="3" class="instr"><?php echo irf_h($pnp_instruction); ?></td>
    </tr>
    <tr>
        <td>
            <div class="lbl">DATE AND TIME REPORTED</div>
            <div class="val"><?php echo irf_h($dt_reported); ?></div>
        </td>
        <td>
            <div class="lbl">DATE AND TIME OF INCIDENT</div>
            <div class="val"><?php echo irf_h($dt_incident); ?></div>
        </td>
        <td>
            <div class="lbl">PLACE OF INCIDENT</div>
            <div class="val"><?php echo irf_h($report['location'] ?? ''); ?></div>
        </td>
    </tr>
</table>

<table class="t" style="margin-top:5pt;">
    <tr><td colspan="6" class="section">ITEM &quot;A&quot; — INCIDENT REPORT TITLE / REFERENCE</td></tr>
    <tr>
        <td colspan="6">
            <div class="lbl">OFFICIAL REPORT NAME / TITLE (SELECTED INCIDENT)</div>
            <div class="val" style="font-size:9pt;font-weight:bold;margin-top:3pt;"><?php echo irf_h($report['title'] ?? ''); ?></div>
            <div class="lbl" style="margin-top:5pt;">STATUS / PRIORITY</div>
            <div class="val">
                <?php echo irf_h($report['status'] ?? ''); ?>
                <?php if (!empty($report['priority_number'])): ?>
                    &nbsp;&nbsp;|&nbsp;&nbsp;PRIORITY #<?php echo (int) $report['priority_number']; ?>
                <?php endif; ?>
                &nbsp;&nbsp;|&nbsp;&nbsp;SEVERITY: <?php echo irf_h($report['severity_level'] ?? ''); ?>
            </div>
        </td>
    </tr>
</table>

<table class="t" style="margin-top:5pt;">
    <tr><td colspan="6" class="section">ITEM &quot;B&quot; — SUSPECT&apos;S DATA</td></tr>
    <tr>
        <td colspan="2"><div class="lbl">FAMILY NAME</div><div class="val">—</div></td>
        <td colspan="2"><div class="lbl">FIRST NAME</div><div class="val">—</div></td>
        <td colspan="2"><div class="lbl">MIDDLE NAME / EXTENSION</div><div class="val">—</div></td>
    </tr>
    <tr>
        <td colspan="2"><div class="lbl">CITIZENSHIP</div><div class="val">—</div></td>
        <td><div class="lbl">GENDER</div><div class="val">—</div></td>
        <td><div class="lbl">CIVIL STATUS</div><div class="val">—</div></td>
        <td><div class="lbl">AGE</div><div class="val">—</div></td>
        <td><div class="lbl">DATE OF BIRTH</div><div class="val">—</div></td>
    </tr>
    <tr>
        <td colspan="6"><div class="lbl">ADDRESS</div><div class="val">Not indicated / No suspect data on file.</div></td>
    </tr>
</table>

<table class="t" style="margin-top:5pt;">
    <tr><td colspan="6" class="section">ITEM &quot;C&quot; — VICTIM&apos;S DATA</td></tr>
    <?php if (!empty($victims)): ?>
        <?php foreach ($victims as $vi => $v): ?>
            <?php if ($vi > 0): ?>
                <tr><td colspan="6" style="background:#f5f5f5;font-size:6.5pt;font-weight:bold;">ADDITIONAL PERSON <?php echo (int) ($vi + 1); ?></td></tr>
            <?php endif; ?>
            <tr>
                <td colspan="2"><div class="lbl">FAMILY NAME</div><div class="val">—</div></td>
                <td colspan="2"><div class="lbl">FIRST NAME</div><div class="val">—</div></td>
                <td colspan="2"><div class="lbl">MIDDLE NAME</div><div class="val">—</div></td>
            </tr>
            <tr>
                <td colspan="6"><div class="lbl">FULL NAME (AS RECORDED)</div><div class="val"><?php echo irf_h($v['witness_name'] ?? ''); ?></div></td>
            </tr>
            <tr>
                <td colspan="2"><div class="lbl">CONTACT / PHONE NUMBER</div><div class="val"><?php echo irf_h($v['witness_contact'] ?? ''); ?></div></td>
                <td colspan="2"><div class="lbl">RECORDED ON</div><div class="val"><?php echo !empty($v['created_at']) ? irf_h(date('Y-m-d H:i', strtotime($v['created_at']))) : ''; ?></div></td>
                <td colspan="2"><div class="lbl">RELATION TO INCIDENT</div><div class="val">—</div></td>
            </tr>
            <tr>
                <td colspan="6"><div class="lbl">ADDRESS</div><div class="val">—</div></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="6"><div class="val" style="padding:6pt;">No named victim entries on file (witness/victim list empty).</div></td>
        </tr>
    <?php endif; ?>
    <?php if (!empty($report['family_contact_name']) || !empty($report['family_contact_number'])): ?>
        <tr>
            <td colspan="3"><div class="lbl">NEXT OF KIN / EMERGENCY CONTACT</div><div class="val"><?php echo irf_h($report['family_contact_name'] ?? ''); ?></div></td>
            <td colspan="3"><div class="lbl">CONTACT NUMBER</div><div class="val"><?php echo irf_h($report['family_contact_number'] ?? ''); ?></div></td>
        </tr>
    <?php endif; ?>
</table>

<table class="t" style="margin-top:5pt;">
    <tr><td colspan="3" class="section">ITEM &quot;D&quot; — NARRATIVE OF INCIDENT</td></tr>
    <tr>
        <td>
            <div class="lbl">DATE AND TIME REPORTED</div>
            <div class="val"><?php echo irf_h($dt_reported); ?></div>
        </td>
        <td>
            <div class="lbl">DATE AND TIME OF INCIDENT</div>
            <div class="val"><?php echo irf_h($dt_incident); ?></div>
        </td>
        <td>
            <div class="lbl">PLACE OF INCIDENT</div>
            <div class="val"><?php echo irf_h($report['location'] ?? ''); ?></div>
        </td>
    </tr>
    <tr>
        <td colspan="3" class="instr" style="background:#f0f0f0;font-weight:bold;">
            THE NARRATIVE OF THE INCIDENT OR EVENT, ANSWERING THE WHO, WHAT, WHEN, WHERE, WHY AND HOW OF REPORTING.
        </td>
    </tr>
    <tr>
        <td colspan="3" class="nbox"><?php echo nl2br(irf_h($narrative_body . $resolv_extra)); ?></td>
    </tr>
    <tr>
        <td colspan="3" style="font-size:6.8pt;padding:5pt;">
            Recorded / extracted electronically — Investigator-on-case / Desk reference:
            <strong><?php echo irf_h($report['assigned_member_name'] ?? '[NOT ASSIGNED]'); ?></strong>
            &nbsp;|&nbsp; Resolved on:
            <?php echo !empty($resolutionDate) ? irf_h(format_datetime($resolutionDate)) : ''; ?>
        </td>
    </tr>
</table>

<?php if (!empty($photos)): ?>
<table class="t" style="margin-top:5pt;">
    <tr><td colspan="3" class="section">ATTACHED DIGITAL PHOTOS</td></tr>
    <?php
    $rows = array_chunk($photos, 3);
    foreach ($rows as $row):
    ?>
    <tr class="photo-grid">
        <?php foreach ($row as $photo): ?>
            <td>
                <?php
                $absPhoto = realpath($irf_partial_root . '/../' . $photo['file_path']);
                $photoSrc = _img_data_uri($absPhoto);
                ?>
                <?php if ($photoSrc): ?>
                    <img src="<?php echo $photoSrc; ?>" alt="">
                    <div class="photo-cap"><?php echo !empty($photo['uploaded_at']) ? irf_h(format_datetime($photo['uploaded_at'])) : ''; ?></div>
                <?php endif; ?>
            </td>
        <?php endforeach; ?>
        <?php for ($p = count($row); $p < 3; $p++): ?>
            <td></td>
        <?php endfor; ?>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<table class="t irf-sig-block">
    <tr>
        <td width="38%" rowspan="2" style="font-size:6.8pt;line-height:1.35;">
            I HEREBY CERTIFY TO THE CORRECTNESS OF THE FOREGOING TO THE BEST OF MY KNOWLEDGE AND BELIEF.
        </td>
        <td width="34%">
            <div class="lbl">NAME OF INVESTIGATOR ON CASE</div>
            <div class="val" style="min-height:22pt;"><?php echo irf_h($report['assigned_member_name'] ?? ''); ?></div>
        </td>
        <td width="28%">
            <div class="lbl">SIGNATURE</div>
            <div class="sig-line"></div>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="font-size:6.5pt;color:#444;">
            (Reporting party fields omitted per electronic filing policy — certification by investigator only.)
        </td>
    </tr>
</table>

<table class="t irf-sig-block" style="margin-top:5pt;">
    <tr>
        <td width="38%" style="font-size:6.8pt;line-height:1.35;vertical-align:middle;">
            SUBSCRIBED AND SWORN TO BEFORE ME
        </td>
        <td width="34%">
            <div class="lbl">NAME OF ADMINISTERING OFFICER (DUTY OFFICER)</div>
            <div class="val" style="min-height:22pt;">—</div>
        </td>
        <td width="28%">
            <div class="lbl">SIGNATURE OF ADMINISTERING OFFICER</div>
            <div class="sig-line"></div>
        </td>
    </tr>
</table>

<table class="t irf-sig-block" style="margin-top:5pt;">
    <tr>
        <td width="62%">
            <div class="lbl">RANK, NAME AND DESIGNATION OF POLICE OFFICER (DUTY INVESTIGATOR / IOC / ASSISTING OFFICER)</div>
            <div class="val" style="min-height:28pt;font-weight:bold;"><?php echo irf_h($report['assigned_member_name'] ?? ''); ?></div>
        </td>
        <td width="38%">
            <div class="lbl">SIGNATURE</div>
            <div class="sig-line"></div>
        </td>
    </tr>
</table>

<table class="t irf-sig-block" style="margin-top:5pt;">
    <tr>
        <td width="22%" style="font-size:6.8pt;vertical-align:middle;">INCIDENT RECORDED IN THE BLOTTER BY</td>
        <td width="28%">
            <div class="lbl">RANK / NAME OF DESK OFFICER</div>
            <div class="val" style="min-height:22pt;">—</div>
        </td>
        <td width="26%">
            <div class="lbl">SIGNATURE OF DESK OFFICER</div>
            <div class="sig-line"></div>
        </td>
        <td width="24%">
            <div class="lbl">BLOTTER ENTRY NR</div>
            <div class="val"><strong><?php echo irf_h($irf_entry); ?></strong></div>
        </td>
    </tr>
</table>

<table class="t irf-sig-block" style="margin-top:8pt;">
    <tr>
        <td colspan="4" class="instr">
            Keep the copy of this Incident Record Form (IRF). An update on the progress of the incident you reported may be provided upon presentation of this IRF.
            Below are the contact details of the responding organization / station as recorded in the system.
        </td>
    </tr>
    <tr class="grey-head">
        <td width="28%">Name of Station / Organization</td>
        <td width="27%">Detail</td>
        <td width="22%">Telephone / Mobile</td>
        <td width="23%">Detail</td>
    </tr>
    <tr>
        <td class="lbl">Organization</td>
        <td class="val"><?php echo irf_h($report['org_name'] ?? ''); ?></td>
        <td class="lbl">Contact Number</td>
        <td class="val"><?php echo irf_h($report['org_contact'] ?? ''); ?></td>
    </tr>
    <tr>
        <td class="lbl">Investigator-on-Case</td>
        <td class="val"><?php echo irf_h($report['assigned_member_name'] ?? ''); ?></td>
        <td class="lbl">Mobile Phone</td>
        <td class="val">—</td>
    </tr>
    <tr>
        <td class="lbl">Chief / Head of Office</td>
        <td class="val">—</td>
        <td class="lbl">Mobile Phone</td>
        <td class="val">—</td>
    </tr>
    <?php if (!empty($report['org_address'])): ?>
    <tr>
        <td class="lbl">Address</td>
        <td colspan="3" class="val"><?php echo irf_h($report['org_address']); ?></td>
    </tr>
    <?php endif; ?>
</table>

</body>
</html>
