<?php

if ($app_user['group_id'] != 0)
{
    redirect_to('dashboard/');
}

require_once dirname(__DIR__) . '/helpers/map_config.php';
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/google/unitas_google_keys.php';
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/google/unitas_geocoder.php';

// ── AJAX: Test server key ────────────────────────────────────────────────
// Server-side geocode of a fixed, well-known address using the stored,
// IP-restricted server key. The key itself is never returned. CSRF is covered
// by the &token in the action URL (checked globally in application_top).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['unitas_test'] ?? '') === 'server_key')
{
    header('Content-Type: application/json');

    if (unitas_google_keys::server_source() === 'none')
    {
        echo json_encode(array('ok' => false, 'message' => 'No server key configured. Save a server key first.'));
        exit;
    }

    $res = unitas_geocoder::lookup('1600 Amphitheatre Parkway, Mountain View, CA');
    echo json_encode(array(
        'ok'            => in_array($res['status'], array('geocoded', 'approximate'), true),
        'status'        => $res['status'],
        'google_status' => $res['google_status'],
        'message'       => $res['message'],
    ));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    // ── Input hardening (plan section 7.2) ───────────────────────────────
    $theme = $_POST['default_theme'] ?? 'auto';
    if (!in_array($theme, array('auto', 'light', 'dark'), true)) $theme = 'auto';

    // Coordinates: numeric and in range, or empty.
    $lat = trim($_POST['default_lat'] ?? '');
    $lng = trim($_POST['default_lng'] ?? '');
    if ($lat !== '' && (!is_numeric($lat) || $lat < -90  || $lat > 90))  $lat = '';
    if ($lng !== '' && (!is_numeric($lng) || $lng < -180 || $lng > 180)) $lng = '';

    // Region codes: 2-letter CLDR codes, comma-separated, de-duplicated, max 15.
    $region_codes = array();
    foreach (explode(',', strtolower($_POST['autocomplete_region_codes'] ?? '')) as $c)
    {
        $c = trim($c);
        if (preg_match('/^[a-z]{2}$/', $c) && !in_array($c, $region_codes, true))
        {
            $region_codes[] = $c;
        }
        if (count($region_codes) >= 15) break;
    }
    $region_codes = $region_codes ? implode(',', $region_codes) : 'us';

    // Bias radius: 0..500000 metres.
    $bias = (int)($_POST['autocomplete_bias_radius_m'] ?? 50000);
    if ($bias < 0)      $bias = 0;
    if ($bias > 500000) $bias = 500000;

    $sql_data = array(
        'google_map_api_key'         => trim($_POST['google_map_api_key'] ?? ''),
        'map_style_light'            => trim($_POST['map_style_light'] ?? ''),
        'map_style_dark'             => trim($_POST['map_style_dark'] ?? ''),
        'default_theme'              => $theme,
        'default_lat'                => $lat,
        'default_lng'                => $lng,
        'default_zoom'               => (int)($_POST['default_zoom'] ?? 8),
        'autocomplete_region_codes'  => $region_codes,
        'autocomplete_bias_radius_m' => $bias,
    );

    // ── Server key: write-only (plan section 7.2, P7) ────────────────────
    // - When set by a config/server.php constant, the DB value is never used;
    //   do not touch it here.
    // - A "clear" checkbox empties it.
    // - A blank submission keeps the stored key (never overwrite with '').
    if (unitas_google_keys::server_source() !== 'constant')
    {
        if (!empty($_POST['google_server_api_key_clear']))
        {
            $sql_data['google_server_api_key'] = '';
        }
        else
        {
            $posted_server = trim($_POST['google_server_api_key'] ?? '');
            if ($posted_server !== '')
            {
                $sql_data['google_server_api_key'] = $posted_server;
            }
            // blank + not cleared => omit column, preserving the stored key
        }
    }

    db_perform('app_unitas_map_reports_config', $sql_data, 'update', 'id = 1');

    redirect_to('unitas_ext/map_configuration/index');
}
