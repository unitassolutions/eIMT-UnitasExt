<?php
/**
 * UNITAS Extension — Pivot Map Report v2 (Modern layout) component
 *
 * Emits the map libraries, the v2 assets, the .load() container and the
 * contract functions. load_pivot_map_report{id}() MUST keep that exact
 * name — the filter panel refetch callback generated in
 * unitas_pivot_map_reports::render_entity_filters_panel() calls it.
 * All other names are report-id scoped (no global loadMapTheme here).
 */

require_once dirname(__DIR__,2) . '/map_configuration/helpers/map_config.php';
$map_cfg = unitas_map_config::get();

/* ENTITY CONFIG */
$reports_entities_query = db_query("select * from app_unitas_pivot_map_reports_entities where reports_id=" . (int)$reports['id'] . " order by id limit 1");

if(!$reports_entities = db_fetch_array($reports_entities_query))
{
    echo '<div class="alert alert-warning">Pivot map entity configuration missing</div>';
    return;
}

/* GOOGLE MAP SCRIPT */
$google_maps_js = 'https://maps.googleapis.com/maps/api/js'
    . '?key=' . urlencode($map_cfg['google_map_api_key'])
    . '&v=weekly'
    . '&map_ids=' . urlencode($map_cfg['map_style_light'] . ',' . $map_cfg['map_style_dark']);
?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="plugins/unitas_ext/css/pivot_map_v2.css?v=<?php echo PLUGIN_UNITAS_EXT_VERSION ?>">

<script src="<?php echo $google_maps_js; ?>"></script>
<script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
<script src="plugins/unitas_ext/js/pivot-map-v2.js?v=<?php echo PLUGIN_UNITAS_EXT_VERSION ?>"></script>

<div id="unitas_pmv2_<?php echo (int)$reports['id'] ?>" class="unitas-pmv2-shell"></div>

<script>
// CONTRACT: exact name — called by refetch_pivot_map_reports_entity{entity_id}()
function load_pivot_map_report<?php echo (int)$reports['id'] ?>()
{
    unitasPmv2Reload_<?php echo (int)$reports['id'] ?>(window._unitasPmv2Theme_<?php echo (int)$reports['id'] ?> || "<?php echo $map_cfg['default_theme'] ?: 'auto'; ?>");
}

function unitasPmv2Reload_<?php echo (int)$reports['id'] ?>(theme)
{
    window._unitasPmv2Theme_<?php echo (int)$reports['id'] ?> = theme;
    $("#unitas_pmv2_<?php echo (int)$reports['id'] ?>").load(
        "<?php echo url_for('unitas_ext/pivot_map_reports/view_google_v2&id=' . (int)$reports['id']) ?>",
        { id: <?php echo (int)$reports['id'] ?>, map_theme: theme }
    );
}

$(function(){
    unitasPmv2Reload_<?php echo (int)$reports['id'] ?>("<?php echo $map_cfg['default_theme'] ?: 'auto'; ?>");
});
</script>
