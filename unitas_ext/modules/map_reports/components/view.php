<?php

if($reports['is_public_access'] == 0 or $app_layout == 'layout.php')
{
    require(component_path('unitas_ext/map_reports/view_filters'));
}
else
{
    $fiters_reports_id = default_filters::get_reports_id($reports['entities_id'], 'public_map' . $reports['id']);
    $panel_fiters_reports_id = 0;
}

$html = '           
    <div id="map_rpeort_' . $reports['id'] . '"></div>
        
    <script>
        function load_map_report' . $reports['id'] . '()
        {
            $("#map_rpeort_' . $reports['id'] . '").load("' . url_for('unitas_ext/map_reports/view_openstreetmap&id=' . $reports['id']) . '",{id: ' . $reports['id'] . ', fiters_reports_id: ' . $fiters_reports_id . ', panel_fiters_reports_id: ' . $panel_fiters_reports_id . '},function(){
                App.initMapSidebar();
            })
        }
        
        $(function(){
            load_map_report' . $reports['id'] . '();
        })    
    </script>
    ';

echo $html;
