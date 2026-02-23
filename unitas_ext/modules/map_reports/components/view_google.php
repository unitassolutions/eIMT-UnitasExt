<?php

require_once dirname(__DIR__, 3) . '/modules/map_configuration/helpers/map_config.php';
$map_cfg = unitas_map_config::get();

$MAP_STYLE_LIGHT_ID = $map_cfg['map_style_light'];
$MAP_STYLE_DARK_ID  = $map_cfg['map_style_dark'];

if($reports['is_public_access']==0 or $app_layout=='layout.php')
{
    require(component_path('unitas_ext/map_reports/view_filters'));
}
else
{
    $fiters_reports_id = default_filters::get_reports_id($reports['entities_id'], 'public_map' . $reports['id']);
    $panel_fiters_reports_id = 0;
}

$cfg = new fields_types_cfg($field_info['configuration']);

/* ---- SAFE SCRIPT URL BUILDING (CRITICAL FIX) ---- */
$google_maps_js = 'https://maps.googleapis.com/maps/api/js'
    . '?key=' . urlencode($map_cfg['google_map_api_key'])
    . '&v=weekly'
    . '&map_ids=' . urlencode($MAP_STYLE_LIGHT_ID . ',' . $MAP_STYLE_DARK_ID);

?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<script src="<?php echo $google_maps_js; ?>"></script>
<script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
<script src="js/geliossoft/geliossoft_objects.js?v=<?php echo PROJECT_VERSION; ?>"></script>

<div id="map_rpeort_<?php echo $reports['id']; ?>"></div>

<script>
function loadMapTheme(theme)
{
    $("#map_rpeort_<?php echo $reports['id']; ?>").load(
        "<?php echo url_for('unitas_ext/map_reports/view_google&id=' . $reports['id']); ?>",
        {
            id: <?php echo (int)$reports['id']; ?>,
            fiters_reports_id: <?php echo (int)$fiters_reports_id; ?>,
            panel_fiters_reports_id: <?php echo (int)$panel_fiters_reports_id; ?>,
            map_theme: theme
        },
        function(){
            App.initMapSidebar();
        }
    );
}

$(function(){
    loadMapTheme("<?php echo $map_cfg['default_theme'] ?: 'auto'; ?>");
});
</script>
