<?php
/**
 * CSV export for reports (Admin and Organization views)
 */

require_once '../config/config.php';
require_login();

$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['Admin', 'Organization Account'])) {
    http_response_code(403);
    exit('Forbidden');
}

$database = new Database();
$db = $database->getConnection();

// Collect filters from query string
$status = $_GET['status'] ?? '';
$severity = $_GET['severity'] ?? '';
$category = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$organization = $_GET['organization'] ?? '';

$query = "SELECT ir.id, ir.title, ir.description, ir.category, ir.severity_level, ir.status, ir.location,
                 ir.incident_date, ir.incident_time, ir.created_at,
                 o.org_name, u.name AS reporter_name, rq.priority_number
          FROM incident_reports ir
          LEFT JOIN organizations o ON ir.organization_id = o.id
          LEFT JOIN users u ON ir.reported_by = u.id
          LEFT JOIN report_queue rq ON rq.report_id = ir.id
          WHERE 1=1";
$params = [];

if ($role === 'Organization Account') {
    $query .= " AND ir.organization_id = ?";
    $params[] = $_SESSION['organization_id'];
} else if ($organization) {
    $query .= " AND ir.organization_id = ?";
    $params[] = $organization;
}

if ($status) { $query .= " AND ir.status = ?"; $params[] = $status; }
if ($severity) { $query .= " AND ir.severity_level = ?"; $params[] = $severity; }
if ($category) { $query .= " AND ir.category = ?"; $params[] = $category; }
if ($date_from) { $query .= " AND ir.incident_date >= ?"; $params[] = $date_from; }
if ($date_to) { $query .= " AND ir.incident_date <= ?"; $params[] = $date_to; }

$query .= " ORDER BY ir.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Output CSV headers
header('Content-Type: text/csv; charset=utf-8');
$filename = ($role === 'Organization Account' ? 'organization_reports_' : 'incident_reports_') . date('Y-m-d') . '.csv';
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fputcsv($output, [
    'ID','Title','Description','Category','Severity','Status','Priority Number','Organization','Reporter','Location','Incident Date','Incident Time','Created At'
]);

foreach ($rows as $r) {
    fputcsv($output, [
        $r['id'],
        $r['title'],
        $r['description'],
        $r['category'],
        $r['severity_level'],
        $r['status'],
        $r['priority_number'] ? (int)$r['priority_number'] : '',
        $r['org_name'],
        $r['reporter_name'],
        $r['location'],
        $r['incident_date'],
        $r['incident_time'],
        $r['created_at'],
    ]);
}

fclose($output);
exit;


