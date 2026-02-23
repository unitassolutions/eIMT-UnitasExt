<?php
/**
 * Этот файл является частью программы "CRM Руководитель" - конструктор CRM систем для бизнеса
 * https://www.rukovoditel.net.ru/
 * 
 * CRM Руководитель - это свободное программное обеспечение, 
 * распространяемое на условиях GNU GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Автор и правообладатель программы: Харчишина Ольга Александровна (RU), Харчишин Сергей Васильевич (RU).
 * Государственная регистрация программы для ЭВМ: 2023664624
 * https://fips.ru/EGD/3b18c104-1db7-4f2d-83fb-2d38e1474ca3
 */

if(!isset($app_user))
{
    $app_user = [
        'id' => 0,
        'group_id' => 0
    ];
}

//check if report exist
$reports_query = db_query("select * from app_ext_map_reports where id='" . db_input(_POST('id')) . "'");
if(!$reports = db_fetch_array($reports_query))
{
    exit();
}

//check access
if(!map_reports::has_access($reports['users_groups']))
{
    exit();
}

$fiters_reports_id = _POST('fiters_reports_id');
$panel_fiters_reports_id = _POST('panel_fiters_reports_id');

$field_info = db_find('app_fields',$reports['fields_id']);

$cfg = new fields_types_cfg($field_info['configuration']);

$map_reports = new map_reports($reports, $fiters_reports_id, $field_info, $panel_fiters_reports_id);

if(!$map_reports->latlng)
{
    $map_reports->latlng = '45.26329,34.10156';
}

$html = '';

if($reports['display_sidebar'] == 1)
{
    $portlets = new portlets('map_sidebar_' . $reports['id']);

    $html .= '
        <table class="table-sidebar">
            <tr>
                <td class="table-sidebar-content">
                    <div id="map" style="height: 600px"></div>
                </td>
                <td class="table-sidebar-body" width="' . pivot_map_reports::get_sidebar_width($reports) . '" ' . $portlets->render_body() . '>
                    <div class="map-sidebar-list-scroller">' . $map_reports->get_sidebar() . '</div>
                </td>
                <td class="table-sidebar-action resize-omap right ' . $portlets->button_css() . '"></td>
            </tr>    
        </table>         
        ';
}
else
{
    $html .= '<div id="map" style="height: 600px"></div>';
}


$html .= '
<script>

    resize_map()

    var map = L.map(\'map\');

    if (L.Control.Search)
        map.addControl(new L.Control.Search({title: "'. TEXT_SEARCH  . '"}));

    map.setView([' . $map_reports->latlng . '], ' . $reports['zoom'] . ');

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a>"
    }).addTo(map);

   ' . $map_reports->render_js() . '

    $(function ()
    {
        $(window).resize(function ()
        {
            resize_map()
        });
        
        $(".map-sidebar-item").click(function(){
            lat = $(this).attr("lat")
            lng = $(this).attr("lng")

            map.setView([lat,lng], 12);
        })
    })

    function resize_map()
    {
        if ($(".page-title").length)
        {
            height = $(window).height() - $(".page-title").height() - $(".portlet-filters-preview").height() - 150;

            if ($(".navbar-items").length)
            {
                height = height - $(".navbar-items").height() - 50;
            }
        }
        else
        {
            height = $(window).height()
        }

        $("#map").css("height", height)
    }

</script>';	

echo $html;