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
require_once dirname(__DIR__,3) . '/classes/map/pivot_map_reports.php';

// Modern (v2) layout applies to Google maps only and draws its own legend
$public_map_type = unitas_pivot_map_reports::get_map_type($reports['id']);
$use_v2 = ((isset($reports['layout']) ? $reports['layout'] : 'classic') == 'modern' && $public_map_type == 'google');

if(!$use_v2)
{
    echo unitas_pivot_map_reports::render_legend($reports);
}

switch($public_map_type)
{
    case 'yandex':
        require(component_path('unitas_ext/pivot_map_reports/view_yandex'));
        break;
    case 'google':
        require(component_path($use_v2 ? 'unitas_ext/pivot_map_reports/view_google_v2' : 'unitas_ext/pivot_map_reports/view_google'));
        break;
    case 'mapbbcode':
        require(component_path('unitas_ext/pivot_map_reports/view'));
        break;
}