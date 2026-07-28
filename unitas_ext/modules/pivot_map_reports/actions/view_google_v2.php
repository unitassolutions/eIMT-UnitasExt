<?php
/**
 * UNITAS Extension — Pivot Map Report v2 (Modern layout) fragment
 *
 * Loaded via jQuery .load() from components/view_google_v2.php with
 * POST {id, map_theme}. Emits the v2 stage markup plus ONE json_encode
 * payload consumed by js/pivot-map-v2.js — no string-concatenated JS.
 */

require_once dirname(__DIR__,2) . '/map_configuration/helpers/map_config.php';
$map_cfg = unitas_map_config::get();

/* ---------------- REPORT ---------------- */
$reports_query = db_query("select * from app_unitas_pivot_map_reports where id='" . db_input(_POST('id')) . "'");
if(!$reports = db_fetch_array($reports_query)) exit();

/* ---------------- ENTITY CONFIG ---------------- */
$reports_entities_query = db_query("select * from app_unitas_pivot_map_reports_entities where reports_id=" . (int)$reports['id'] . " order by id limit 1");
if(!$reports_entities = db_fetch_array($reports_entities_query)) exit();

require_once dirname(__DIR__,3) . '/classes/map/pivot_map_reports.php';
$map_reports = new unitas_pivot_map_reports($reports);

/* ---------------- THEME (classic resolution rules) ---------------- */
$map_theme = $_POST['map_theme'] ?? $map_cfg['default_theme'] ?? 'auto';

if($map_theme === 'light')
{
    $resolved_theme = 'light';
}
elseif($map_theme === 'dark')
{
    $resolved_theme = 'dark';
}
else
{
    $hour = (int)date('H');
    $resolved_theme = ($hour >= 18 || $hour <= 6) ? 'dark' : 'light';
}

$google_map_id = ($resolved_theme === 'dark') ? $map_cfg['map_style_dark'] : $map_cfg['map_style_light'];

/* ---------------- ZOOM / CENTER (classic rules, as data) ---------------- */
$use_form = ($reports['use_form_map_settings'] == 1);

$zoom = ($use_form && strlen($reports['zoom'] ?? '')) ? (int)$reports['zoom'] : (int)$map_cfg['default_zoom'];

if($use_form)
{
    $latlng = explode(',', trim($reports['latlng'] ?? ''));
    if(count($latlng) == 2 && is_numeric(trim($latlng[0])) && is_numeric(trim($latlng[1])))
    {
        $center = array('mode' => 'fixed', 'lat' => (float)trim($latlng[0]), 'lng' => (float)trim($latlng[1]));
    }
    else
    {
        $center = array('mode' => 'fit');
    }
}
else
{
    $center = array('mode' => 'fixed', 'lat' => (float)$map_cfg['default_lat'], 'lng' => (float)$map_cfg['default_lng']);
}

/* ---------------- EMPTY STATE (same gate and text as classic) ---------------- */
if(!count($map_reports->markers) && !count($map_reports->shapes))
{
    echo '<div class="alert alert-warning">' . TEXT_NO_RECORDS_FOUND . '</div>';
    return;
}

/* ---------------- PAYLOAD + STAGE ---------------- */
$resolved = array(
    'theme'        => $resolved_theme,
    'theme_choice' => $map_theme,
    'map_id'       => $google_map_id,
    'zoom'         => $zoom,
    'center'       => $center,
);

$payload = $map_reports->get_v2_payload($reports, $resolved);

// HEX flags keep popup HTML from ever closing the script block
$json_flags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<div class="unitas-pmv2-stage<?php echo $resolved_theme === 'dark' ? ' unitas-pmv2-dark' : ''; ?>" id="unitas_pmv2_stage_<?php echo (int)$reports['id'] ?>">
    <div id="unitas_pmv2_map_<?php echo (int)$reports['id'] ?>" class="unitas-pmv2-map"></div>
</div>
<script>
UnitasPivotMapV2.init(<?php echo json_encode($payload, $json_flags); ?>);
</script>
