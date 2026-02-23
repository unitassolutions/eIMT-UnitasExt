<?php

require_once dirname(__DIR__,2) . '/map_configuration/helpers/map_config.php';
$map_cfg = unitas_map_config::get();

/* ENTITY CONFIG */
$reports_entities_query = db_query("
    select * 
    from app_unitas_pivot_map_reports_entities 
    where reports_id=" . (int)$reports['id'] . " 
    order by id 
    limit 1
");

if(!$reports_entities = db_fetch_array($reports_entities_query))
{
    echo '<div class="alert alert-warning">Pivot map entity configuration missing</div>';
    return;
}

$cfg = new fields_types_cfg(
    $app_fields_cache[$reports_entities['entities_id']]
    [$reports_entities['fields_id']]['configuration']
);

/* GOOGLE MAP SCRIPT */
$google_maps_js = 'https://maps.googleapis.com/maps/api/js'
    . '?key=' . urlencode($map_cfg['google_map_api_key'])
    . '&v=weekly'
    . '&map_ids=' . urlencode(
        $map_cfg['map_style_light'] . ',' . $map_cfg['map_style_dark']
    );
?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<script src="<?php echo $google_maps_js; ?>"></script>
<script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
<script src="js/geliossoft/geliossoft_objects.js?v=<?php echo PROJECT_VERSION ?>"></script>

<div id="map_rpeort_<?php echo $reports['id'] ?>"></div>

<script>

function loadMapTheme(theme)
{
    $("#map_rpeort_<?php echo $reports['id'] ?>").load(
        "<?php echo url_for('unitas_ext/pivot_map_reports/view_google&id=' . $reports['id']) ?>",
        {
            id: <?php echo (int)$reports['id'] ?>,
            map_theme: theme
        },
        function(){
            if(typeof App !== 'undefined')
                App.initMapSidebar();
        }
    );
}

$(function(){
    loadMapTheme("<?php echo $map_cfg['default_theme'] ?: 'auto'; ?>");
});

</script>
