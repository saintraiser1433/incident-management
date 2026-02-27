<?php
/**
 * Incident Map View
 * Shows incidents on a map for Admins and Organization Accounts
 */

require_once '../config/config.php';

// Only Admin and Organization Account can access
require_role(['Admin', 'Organization Account']);

$database = new Database();
$db = $database->getConnection();

// Determine filters
$selected_org_id = null;
$is_admin = $_SESSION['user_role'] === 'Admin';

if ($is_admin) {
    $selected_org_id = isset($_GET['organization_id']) && $_GET['organization_id'] !== ''
        ? (int) $_GET['organization_id']
        : null;
} else {
    // Organization accounts are locked to their own org
    $selected_org_id = $_SESSION['organization_id'] ?? null;
}

// Fetch organizations (for filter + coordinates)
$orgQuery = "SELECT id, org_name, latitude, longitude FROM organizations ORDER BY org_name";
$orgStmt = $db->prepare($orgQuery);
$orgStmt->execute();
$organizations = $orgStmt->fetchAll();

// Build incident query
$where = [];
$params = [];

if ($selected_org_id) {
    $where[] = "ir.organization_id = ?";
    $params[] = $selected_org_id;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$incidentQuery = "
    SELECT 
        ir.id,
        ir.title,
        ir.location,
        ir.latitude,
        ir.longitude,
        ir.status,
        ir.severity_level,
        ir.incident_date,
        ir.incident_time,
        ir.category,
        ir.reported_by,
        rq.priority_number,
        o.org_name
    FROM incident_reports ir
    LEFT JOIN organizations o ON ir.organization_id = o.id
    LEFT JOIN report_queue rq ON rq.report_id = ir.id
    {$whereSql}
    ORDER BY ir.created_at DESC
";

$incidentStmt = $db->prepare($incidentQuery);
$incidentStmt->execute($params);
$incidents = $incidentStmt->fetchAll();

// Determine default map center
$defaultCenter = [
    'lat' => 6.0523,
    'lng' => 125.2896,
    'zoom' => 12,
];

// If organization account and org has coordinates, use them as default
if (!$is_admin && isset($_SESSION['organization_id'])) {
    foreach ($organizations as $org) {
        if ((int) $org['id'] === (int) $_SESSION['organization_id'] && $org['latitude'] !== null && $org['longitude'] !== null) {
            $defaultCenter['lat'] = (float) $org['latitude'];
            $defaultCenter['lng'] = (float) $org['longitude'];
            $defaultCenter['zoom'] = 14;
            break;
        }
    }
}

$page_title = 'Incident Map View - ' . APP_NAME;
include '../views/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-map-marked-alt me-2"></i>Incident Map
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="../reports/index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>List View
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0 d-flex align-items-center">
                        <i class="fas fa-filter me-2"></i>Filters
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <?php if ($is_admin): ?>
                            <div class="col-md-4">
                                <label for="organization_id" class="form-label">Organization</label>
                                <select name="organization_id" id="organization_id" class="form-select">
                                    <option value="">All Organizations</option>
                                    <?php foreach ($organizations as $org): ?>
                                        <option
                                            value="<?php echo $org['id']; ?>"
                                            <?php echo ($selected_org_id && (int) $selected_org_id === (int) $org['id']) ? 'selected' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars($org['org_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="col-md-4">
                                <label class="form-label">Organization</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($_SESSION['organization_name'] ?? ''); ?>"
                                    disabled
                                >
                            </div>
                        <?php endif; ?>

                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-map me-2"></i>Incident Locations
                    </h5>
                    <span class="badge bg-secondary">
                        <?php echo count($incidents); ?> incident(s)
                    </span>
                </div>
                <div class="card-body p-0">
                    <div id="incident-map-view" style="height: 550px; width: 100%; border-radius: 0 0 0.5rem 0.5rem; overflow: hidden;"></div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('incident-map-view');
    if (!mapElement || typeof L === 'undefined') {
        return;
    }

    const defaultCenter = <?php echo json_encode($defaultCenter); ?>;
    const incidents = <?php echo json_encode($incidents); ?>;

    const map = L.map('incident-map-view').setView([defaultCenter.lat, defaultCenter.lng], defaultCenter.zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    function getStatusColor(status) {
        switch (status) {
            case 'Pending':
                return '#FFC107'; // yellow
            case 'In Progress':
                return '#0DCAF0'; // cyan
            case 'Resolved':
                return '#198754'; // green
            case 'Closed':
                return '#6C757D'; // gray
            default:
                return '#6C757D';
        }
    }

    const markers = [];
    incidents.forEach(function(incident) {
        // Fallback: skip if no coordinates
        if (!incident.latitude || !incident.longitude) {
            return;
        }

        const lat = parseFloat(incident.latitude);
        const lng = parseFloat(incident.longitude);
        if (isNaN(lat) || isNaN(lng)) {
            return;
        }

        const color = getStatusColor(incident.status);
        const marker = L.circleMarker([lat, lng], {
            radius: 8,
            fillColor: color,
            color: '#ffffff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9
        }).addTo(map);

        const popupHtml = `
            <div style="min-width: 240px;">
                <div class="fw-bold mb-1">${incident.title}</div>
                <div class="mb-1">
                    <span class="badge bg-secondary me-1">${incident.status}</span>
                    <span class="badge bg-dark me-1">${incident.severity_level}</span>
                    ${incident.priority_number ? `<span class="badge bg-success">#${incident.priority_number}</span>` : ''}
                </div>
                <div class="small mb-1">
                    <strong>Type:</strong> ${incident.category || 'N/A'}
                </div>
                <div class="small mb-1">
                    <strong>Reported by:</strong> ${incident.reported_by || 'Unknown'}
                </div>
                <div class="small text-muted mb-1">
                    ${incident.org_name ? incident.org_name + ' • ' : ''}${incident.location || ''}
                </div>
                <div class="small text-muted mb-2">
                    ${incident.incident_date} ${incident.incident_time}
                </div>
                <a
                    href="../reports/view.php?id=${incident.id}"
                    class="btn btn-sm btn-primary"
                >
                    <i class="fas fa-eye me-1"></i>View Incident
                </a>
            </div>
        `;

        marker.bindPopup(popupHtml);
        markers.push(marker);
    });

    if (markers.length > 0) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.2));
    }
});
</script>

<?php include '../views/footer.php'; ?>

