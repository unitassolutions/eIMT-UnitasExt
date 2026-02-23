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

/*
 * api
 * https://yandex.ru/dev/maps/jsapi/doc/2.1/ref/reference/Placemark.html
 */

$reports_entities_query = db_query("select * from app_ext_pivot_map_reports_entities where reports_id=" . $reports['id']. " order by id limit 1");
$reports_entities = db_fetch_array($reports_entities_query);

$cfg = new fields_types_cfg($app_fields_cache[$reports_entities['entities_id']][$reports_entities['fields_id']]['configuration']);

$map_reports = new pivot_map_reports($reports);

$html = '';

$html .= '
    <script src="https://api-maps.yandex.ru/2.1/?apikey=' . $cfg->get('api_key') . '&lang=' . $cfg->get('lang') . '" type="text/javascript"></script>
    <script src="js/geliossoft/geliossoft_objects.js?v=' . PROJECT_VERSION . '" type="text/javascript"></script>
    ';

$html .= '
<div id="pivot_map_rpeort_' . $reports['id'] . '"></div>
        
<script>

    function load_pivot_map_report' . $reports['id'] . '()
    {
        $("#pivot_map_rpeort_' . $reports['id'] . '").load("' . url_for('ext/pivot_map_reports/view_yandex&id=' . $reports['id']) . '",{id: ' . $reports['id'] . '},function(){
            App.initMapSidebar();
        })
    }

    $(function(){
        load_pivot_map_report' . $reports['id'] . '();
    })        
</script>	   
';	

echo $html;
