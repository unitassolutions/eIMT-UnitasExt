<?php

require_once dirname(__DIR__, 3) . '/modules/map_configuration/helpers/map_config.php';
$map_cfg = unitas_map_config::get();

if(!isset($app_user))
{
    $app_user = ['id'=>0,'group_id'=>0];
}

/* ---------------- REPORT ---------------- */
$reports_query = db_query("select * from app_unitas_map_reports where id='" . db_input(_POST('id')) . "'");
if(!$reports = db_fetch_array($reports_query)) exit();

if(!map_reports::has_access($reports['users_groups'])) exit();

$fiters_reports_id = _POST('fiters_reports_id');
$panel_fiters_reports_id = _POST('panel_fiters_reports_id');

$field_info = db_find('app_fields',$reports['fields_id']);
$cfg = new fields_types_cfg($field_info['configuration']);
$map_reports = new map_reports($reports, $fiters_reports_id, $field_info, $panel_fiters_reports_id);

/* ---------------- MAP STYLE ---------------- */
$MAP_STYLE_LIGHT_ID = $map_cfg['map_style_light'];
$MAP_STYLE_DARK_ID  = $map_cfg['map_style_dark'];

$map_theme = $_POST['map_theme'] ?? $map_cfg['default_theme'] ?? 'auto';

if($map_theme === 'light')
    $google_map_id = $MAP_STYLE_LIGHT_ID;
elseif($map_theme === 'dark')
    $google_map_id = $MAP_STYLE_DARK_ID;
else
{
    $hour = (int)date('H');
    $google_map_id = ($hour >= 18 || $hour <= 6)
        ? $MAP_STYLE_DARK_ID
        : $MAP_STYLE_LIGHT_ID;
}

/* ---------------- SETTINGS MODE ---------------- */
$use_form_settings = ($reports['use_form_settings'] == 1);

/* ---------------- ZOOM ---------------- */
if($use_form_settings && (int)$reports['zoom'] > 0)
{
    $zoom = (int)$reports['zoom'];
}
else
{
    $zoom = (int)$map_cfg['default_zoom'];
}

/* ---------------- CENTER LOGIC ---------------- */
$form_center_valid = false;

if($use_form_settings && strlen(trim($reports['latlng'])) > 0)
{
    $latlng = explode(',', $reports['latlng']);

    if(count($latlng) == 2 && is_numeric(trim($latlng[0])) && is_numeric(trim($latlng[1])))
    {
        $lat = trim($latlng[0]);
        $lng = trim($latlng[1]);
        $form_center_valid = true;
    }
}

/* ===== CASE 1: Checkbox ON ===== */
if($use_form_settings)
{
    if($form_center_valid)
    {
        // Use form center
        $center_map_js = "
            var myLatlng = new google.maps.LatLng($lat, $lng);
            map.setCenter(myLatlng);
        ";
    }
    else
    {
        // Fallback to marker bounds
        $center_map_js = "
            if(markers.length > 0 || shapes.length > 0)
            {
                var bounds = new google.maps.LatLngBounds();
                markers.forEach(function(marker){
                    bounds.extend(marker.getPosition());
                });
                shapes.forEach(function(shape){
                    if(typeof shape.getPath === 'function'){ shape.getPath().forEach(function(p){ bounds.extend(p); }); }
                    else if(typeof shape.getBounds === 'function'){ bounds.union(shape.getBounds()); }
                });
                map.fitBounds(bounds);
            }
        ";
    }
}

/* ===== CASE 2: Checkbox OFF ===== */
else
{
    // Always use map configuration center
    $lat = $map_cfg['default_lat'];
    $lng = $map_cfg['default_lng'];

    $center_map_js = "
        var myLatlng = new google.maps.LatLng($lat, $lng);
        map.setCenter(myLatlng);
    ";
}

/* ---------------- HTML ---------------- */
$html='
<script>
var map;

function addThemeControls(map)
{
    const controlDiv=document.createElement("div");
    controlDiv.style.display="flex";
    controlDiv.style.alignItems="center";
    controlDiv.style.height="40px";
    controlDiv.style.marginLeft="8px";
    controlDiv.style.marginTop="10px";

    function makeBtn(icon,title,theme)
    {
        const ui=document.createElement("div");
        ui.style.background="#fff";
        ui.style.borderRadius="2px";
        ui.style.boxShadow="0 2px 6px rgba(0,0,0,.3)";
        ui.style.cursor="pointer";
        ui.style.height="40px";
        ui.style.width="40px";
        ui.style.display="flex";
        ui.style.alignItems="center";
        ui.style.justifyContent="center";
        ui.style.marginRight="4px";
        ui.title=title;

        const ic=document.createElement("span");
        ic.className="material-icons";
        ic.innerHTML=icon;
        ic.style.fontSize="20px";
        ic.style.color="#5f6368";
        ui.appendChild(ic);

        ui.addEventListener("click",function(){
            loadMapTheme(theme);
        });

        return ui;
    }

    controlDiv.appendChild(makeBtn("light_mode","Light Map","light"));
    controlDiv.appendChild(makeBtn("dark_mode","Dark Map","dark"));
    controlDiv.appendChild(makeBtn("brightness_auto","Auto Theme","auto"));

    map.controls[google.maps.ControlPosition.TOP_CENTER].push(controlDiv);
}

$(function(){

    var mapOptions={
        zoom: '.$zoom.',
        mapId: "'.$google_map_id.'"
    };

    map=new google.maps.Map(document.getElementById("goolge_map_container"),mapOptions);

    addThemeControls(map);

    let markers=[];
    let shapes=[];
    '.$map_reports->render_google_js().'
    '.$center_map_js.'

    if(markers.length>0)
    {
        new markerClusterer.MarkerClusterer({ map, markers });
    }

    /* IMPORTANT: SIDEBAR CLICK NAVIGATION */
    $(".map-sidebar-item").click(function(){
        map.setCenter(new google.maps.LatLng($(this).attr("lat"),$(this).attr("lng")));
        map.setZoom(13);
    });

});
</script>
';

/* ---------------- CONTAINER ---------------- */
if($reports['display_sidebar']==1)
{
    $portlets = new portlets('map_sidebar_' . $reports['id']);

    $html .= '
    <table class="table-sidebar">
        <tr>
            <td class="table-sidebar-content">
                <div id="goolge_map_container" style="width:100%; height:75vh;"></div>
            </td>
            <td class="table-sidebar-body" width="' . pivot_map_reports::get_sidebar_width($reports) . '" ' . $portlets->render_body(). '>
                <div class="map-sidebar-list-scroller">' . $map_reports->get_sidebar() . '</div>
            </td>
            <td class="table-sidebar-action right ' . $portlets->button_css() . '"></td>
        </tr>
    </table>';
}
else
{
    $html .= '<div id="goolge_map_container" style="width:100%; height:600px;"></div>';
}

echo ((count($map_reports->markers) || count($map_reports->shapes)) ? $html : '<div class="alert alert-warning">' . TEXT_NO_RECORDS_FOUND . '</div>');
