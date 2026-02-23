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

$reports_query = db_query("select * from app_unitas_pivot_map_reports where id='" . db_input(_POST('id')) . "'");
if(!$reports = db_fetch_array($reports_query))
{
    exit();
}

//check access
if(!pivot_map_reports::has_access($reports['users_groups']))
{
    exit();
}

$map_reports = new pivot_map_reports($reports);

if(!$map_reports->latlng)
{
    $map_reports->latlng = '45.26329,34.10156';
}
?>

<link rel="stylesheet" href="js/leaflet/src/leaflet.css" />
<script src="js/leaflet/src/leaflet.js"></script>

<script src="js/mapbbcode-master/src/controls/Leaflet.Search.js"></script>

<?php
$html = '';

if($reports['display_sidebar'] == 1)
{
    $portlets = new portlets('pivo_map_sidebar_' . $reports['id']);

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

echo $html;
?>

<script>

    resize_map()

    var map = L.map('map');

    if (L.Control.Search)
        map.addControl(new L.Control.Search({title: '<?php echo TEXT_SEARCH ?>'}));

    map.setView([<?php echo $map_reports->latlng ?>], <?php echo $reports['zoom'] ?>);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

<?php echo $map_reports->render_js() ?>

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
            height = $(window).height() - $('.page-title').height() - 150;

            if ($('.navbar-items').length)
            {
                height = height - $('.navbar-items').height() - 50;
            }
        }
        else
        {
            height = $(window).height()
        }

        $('#map').css('height', height)
    }

</script>	
