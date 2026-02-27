<?php
/**
 * Simple reverse geocoding proxy using OpenStreetMap Nominatim.
 * Called from the browser (same origin) to avoid CORS issues.
 */

require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

if ($lat === null || $lng === null) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid coordinates'
    ]);
    exit;
}

// Basic bounds check
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode([
        'success' => false,
        'error' => 'Coordinates out of range'
    ]);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' .
    urlencode($lat) .
    '&lon=' .
    urlencode($lng);

// Nominatim requires a proper User-Agent identifying the application
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: MDRRMO-GLAN-IMS/1.0 (reverse_geocode.php)\r\n" .
                    "Accept: application/json\r\n",
        'timeout' => 3,
    ],
];

$context = stream_context_create($opts);

$raw = @file_get_contents($url, false, $context);

if ($raw === false) {
    echo json_encode([
        'success' => false,
        'error' => 'Reverse geocoding request failed',
    ]);
    exit;
}

$data = json_decode($raw, true);

if (!is_array($data) || empty($data['display_name'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No address found',
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'display_name' => $data['display_name'],
]);

