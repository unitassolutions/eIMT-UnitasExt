<?php
/**
 * UNITAS Extension — Waze Reverse Geocoding Proxy
 *
 * Request:  POST points = JSON array of 1..3 {lat, lng} objects
 * Response: {"success":true,"results":[ <per-point>, ... ]} where each element is
 *   - an array of {names:[...], distance: float} entries sorted by distance (max 5)
 *   - []   when Waze answered but found no streets within its 50 m radius
 *   - null when the upstream call failed for that point
 * or {"success":false,"error":"auth_required|disabled|invalid_request|curl_missing|upstream"}
 *
 * The token never leaves the server: it is read from configuration, sent only
 * to waze.com, and never echoed in responses or error strings. The region
 * path is a server-side whitelist, so no part of the upstream URL is
 * client-controlled.
 */

// Remove any output buffers (application_top.php registers an HTML injector)
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Authenticated users only — regular users create closures, so any group passes
if (!isset($app_user['id']) || (int)$app_user['id'] <= 0) {
    echo json_encode(array('success' => false, 'error' => 'auth_required'));
    exit();
}

require_once PLUGIN_UNITAS_EXT_PATH . '/modules/map_configuration/helpers/map_config.php';
$waze_cfg = unitas_map_config::get();

$token = trim($waze_cfg['waze_geocoding_token'] ?? '');
if ($token === '') {
    echo json_encode(array('success' => false, 'error' => 'disabled'));
    exit();
}

// Validate payload: JSON array of 1..3 {lat,lng} objects with sane ranges
$points_in = json_decode(isset($_POST['points']) ? $_POST['points'] : '', true);

$valid  = is_array($points_in) && count($points_in) >= 1 && count($points_in) <= 3;
$points = array();
if ($valid) {
    foreach ($points_in as $p) {
        if (!is_array($p) || !isset($p['lat']) || !isset($p['lng'])
            || !is_numeric($p['lat']) || !is_numeric($p['lng'])) {
            $valid = false;
            break;
        }
        $lat = (float)$p['lat'];
        $lng = (float)$p['lng'];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $valid = false;
            break;
        }
        $points[] = array('lat' => $lat, 'lng' => $lng);
    }
}
if (!$valid) {
    echo json_encode(array('success' => false, 'error' => 'invalid_request'));
    exit();
}

if (!function_exists('curl_init')) {
    echo json_encode(array('success' => false, 'error' => 'curl_missing'));
    exit();
}

// Region to API path — server-side whitelist, never client-controlled
$region_paths = array(
    'na'  => 'partnerhub-api',
    'row' => 'row-partnerhub-api',
    'il'  => 'il-partnerhub-api',
);
$region   = $waze_cfg['waze_region'] ?? 'na';
$api_path = isset($region_paths[$region]) ? $region_paths[$region] : 'partnerhub-api';

$results   = array();
$hard_fail = false; // connect/resolve/timeout failure — skip remaining points

foreach ($points as $p) {
    if ($hard_fail) {
        $results[] = null;
        continue;
    }

    $url = 'https://www.waze.com/' . $api_path . '/waze-map/streetsInfo'
         . '?lat=' . $p['lat'] . '&lon=' . $p['lng']
         . '&token=' . urlencode($token);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UnitasExtension/' . PLUGIN_UNITAS_EXT_VERSION);

    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        // 6 = resolve, 7 = connect, 28 = timeout — Waze unreachable, stop trying
        if (in_array($errno, array(6, 7, 28))) {
            $hard_fail = true;
        }
        $results[] = null;
        continue;
    }
    if ($code != 200) {
        $results[] = null;
        continue;
    }

    $parsed = json_decode($body, true);
    if (!is_array($parsed) || !isset($parsed['result']) || !is_array($parsed['result'])) {
        // Waze answered but returned no usable street data
        $results[] = array();
        continue;
    }

    $entries = array();
    foreach ($parsed['result'] as $r) {
        if (!is_array($r)) {
            continue;
        }
        // Live API (verified 2026-07-17) returns entries keyed "names";
        // the partner support docs show "streetNames" — accept both.
        $raw_names = array();
        if (!empty($r['names']) && is_array($r['names'])) {
            $raw_names = $r['names'];
        } elseif (!empty($r['streetNames']) && is_array($r['streetNames'])) {
            $raw_names = $r['streetNames'];
        }
        if (count($raw_names) == 0) {
            continue;
        }
        $names = array();
        foreach ($raw_names as $n) {
            $n = trim((string)$n);
            if ($n !== '' && !in_array($n, $names)) {
                $names[] = $n;
            }
        }
        if (count($names) == 0) {
            continue;
        }
        $entries[] = array(
            'names'    => $names,
            'distance' => isset($r['distance']) ? (float)$r['distance'] : 0,
        );
    }

    // Sort by distance ourselves — do not trust upstream ordering
    usort($entries, function ($a, $b) {
        if ($a['distance'] == $b['distance']) return 0;
        return ($a['distance'] < $b['distance']) ? -1 : 1;
    });

    $results[] = array_slice($entries, 0, 5);
}

// Every point failed upstream — report a single upstream error
$all_null = true;
foreach ($results as $r) {
    if ($r !== null) {
        $all_null = false;
        break;
    }
}
if ($all_null) {
    echo json_encode(array('success' => false, 'error' => 'upstream'));
    exit();
}

echo json_encode(array('success' => true, 'results' => $results), JSON_UNESCAPED_UNICODE);
exit();
