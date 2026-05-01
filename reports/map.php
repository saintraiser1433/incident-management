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
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>

<div class="container-fluid">
    <div class="row g-0">
        <?php include '../views/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 mb-6 border-b border-slate-200">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Incident Map</h1>
                    <p class="text-sm text-slate-500 mt-1">Geographic distribution of reported incidents.</p>
                </div>
                <a href="../reports/index.php" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    <i class="fas fa-list text-slate-400"></i>List View
                </a>
            </div>

            <div class="card mb-4">
                <div class="card-header flex items-center gap-2">
                    <i class="fas fa-filter text-slate-400"></i>
                    <span>Filters</span>
                </div>
                <div class="card-body">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <?php if ($is_admin): ?>
                            <div>
                                <label for="organization_id" class="form-label">Organization</label>
                                <select name="organization_id" id="organization_id" class="form-select">
                                    <option value="">All Organizations</option>
                                    <?php foreach ($organizations as $org): ?>
                                        <option value="<?php echo $org['id']; ?>"
                                            <?php echo ($selected_org_id && (int) $selected_org_id === (int) $org['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($org['org_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div>
                                <label class="form-label">Organization</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['organization_name'] ?? ''); ?>" disabled>
                            </div>
                        <?php endif; ?>

                        <div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 transition">
                                <i class="fas fa-search"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-map text-slate-400"></i>
                        <span>Incident Locations</span>
                    </div>
                    <span class="badge bg-secondary"><?php echo count($incidents); ?> incident(s)</span>
                </div>
                <div class="card-body p-0">
                    <div id="incident-map-view" class="incident-map-gl" style="height: 550px; width: 100%; border-radius: 0 0 0.875rem 0.875rem; overflow: hidden;"></div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('incident-map-view');
    if (!mapElement || typeof maplibregl === 'undefined') {
        return;
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    const defaultCenter = <?php echo json_encode($defaultCenter); ?>;
    const incidents = <?php echo json_encode($incidents); ?>;

    function getStatusColor(status) {
        switch (status) {
            case 'Pending':
                return '#ca8a04';
            case 'In Progress':
                return '#0284c7';
            case 'Resolved':
                return '#15803d';
            case 'Closed':
                return '#475569';
            default:
                return '#475569';
        }
    }

    // Vector style with buildings + readable labels; tilt for 3D-style perspective
    var map = new maplibregl.Map({
        container: 'incident-map-view',
        style: 'https://tiles.openfreemap.org/styles/liberty',
        center: [defaultCenter.lng, defaultCenter.lat],
        zoom: defaultCenter.zoom,
        pitch: 58,
        bearing: -28,
        maxPitch: 85,
        antialias: true,
        attributionControl: true
    });

    map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');

    map.on('load', function() {
        /* optional: subtle fog adds depth on pitched maps */
        try {
            if (typeof map.setFog === 'function') {
                map.setFog({
                    color: 'rgb(186, 210, 235)',
                    'high-color': 'rgb(36, 92, 223)',
                    'horizon-blend': 0.02,
                    'space-color': 'rgb(11, 11, 25)',
                    'star-intensity': 0.6
                });
            }
        } catch (err) {}

        const lngLats = [];

        incidents.forEach(function(incident) {
            if (!incident.latitude || !incident.longitude) return;
            const lat = parseFloat(incident.latitude);
            const lng = parseFloat(incident.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            lngLats.push([lng, lat]);

            const color = getStatusColor(incident.status);
            const el = document.createElement('div');
            el.className = 'incident-marker-gl';
            el.style.backgroundColor = color;
            el.style.width = '16px';
            el.style.height = '16px';
            el.style.borderRadius = '50%';
            el.style.border = '3px solid #ffffff';
            el.style.boxShadow = '0 2px 8px rgba(15, 23, 42, 0.35)';
            el.style.cursor = 'pointer';

            const prio = incident.priority_number ? '<span style="display:inline-block;margin-left:6px;padding:2px 8px;border-radius:9999px;background:#dcfce7;color:#166534;font-size:11px;font-weight:600;">#' + escapeHtml(incident.priority_number) + '</span>' : '';

            const popupHtml =
                '<div class="incident-map-popup-inner">' +
                '<div class="imp-title">' + escapeHtml(incident.title) + '</div>' +
                '<div class="imp-badges">' +
                '<span class="imp-badge imp-badge-status">' + escapeHtml(incident.status) + '</span>' +
                '<span class="imp-badge imp-badge-sev">' + escapeHtml(incident.severity_level) + '</span>' +
                prio +
                '</div>' +
                '<div class="imp-row"><span class="imp-k">Type</span><span class="imp-v">' + escapeHtml(incident.category || 'N/A') + '</span></div>' +
                '<div class="imp-row"><span class="imp-k">Reported by</span><span class="imp-v">' + escapeHtml(incident.reported_by || 'Unknown') + '</span></div>' +
                '<div class="imp-meta">' + escapeHtml([incident.org_name, incident.location].filter(Boolean).join(' • ')) + '</div>' +
                '<div class="imp-meta">' + escapeHtml(String(incident.incident_date || '') + ' ' + String(incident.incident_time || '')) + '</div>' +
                '<a class="imp-btn" href="view.php?id=' + encodeURIComponent(incident.id) + '">View incident</a>' +
                '</div>';

            const popup = new maplibregl.Popup({ offset: 24, maxWidth: '320px' }).setHTML(popupHtml);

            new maplibregl.Marker({ element: el, anchor: 'center' })
                .setLngLat([lng, lat])
                .setPopup(popup)
                .addTo(map);
        });

        if (lngLats.length > 0) {
            const b = new maplibregl.LngLatBounds(lngLats[0], lngLats[0]);
            lngLats.forEach(function(ll) { b.extend(ll); });
            map.fitBounds(b, { padding: 72, maxZoom: 16, duration: 900 });
        }
    });
});
</script>

<?php include '../views/footer.php'; ?>

