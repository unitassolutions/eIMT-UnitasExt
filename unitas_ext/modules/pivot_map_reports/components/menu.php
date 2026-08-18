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

require_once PLUGIN_UNITAS_EXT_PATH . '/classes/map/pivot_map_reports.php';

$reports_query = db_query("select * from app_unitas_pivot_map_reports order by name");
while($reports = db_fetch_array($reports_query))
{
	if(unitas_pivot_map_reports::has_access($reports['users_groups']))
	{
		// Skip when the report is already placed in the main menu via
		// Application Structure > Entities > Menu (prefix set by application_top.php)
		$check_query = db_query("select id from app_entities_menu where find_in_set('unitaspivotmap" . $reports['id']. "',reports_list)");
		if(!$check = db_fetch_array($check_query))
		{
			if($reports['in_menu'])
			{
				$app_plugin_menu['menu'][] = array('title'=>$reports['name'],'url'=>url_for('unitas_ext/pivot_map_reports/view','id=' . $reports['id']),'class'=>'fa-map-marker');
			}
			else
			{
				$app_plugin_menu['reports'][] = array('title'=>$reports['name'],'url'=>url_for('unitas_ext/pivot_map_reports/view','id=' . $reports['id']));
			}
		}
	}
}
