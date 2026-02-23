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



$html = '';

$html .= '
        
    <div id="map_rpeort_' . $reports['id'] . '"></div>
        
    <script>
        function load_pivot_map_report' . $reports['id'] . '()
        {
            $("#map_rpeort_' . $reports['id'] . '").load("' . url_for('unitas_ext/pivot_map_reports/view_openstreetmap&id=' . $reports['id']) . '",{id: ' . $reports['id'] . '},function(){
                App.initMapSidebar();
            })
        }
        
        $(function(){
            load_pivot_map_report' . $reports['id'] . '();
        })        
    </script>
        ';

echo $html;
