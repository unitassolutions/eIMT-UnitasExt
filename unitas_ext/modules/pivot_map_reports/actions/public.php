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

//check if report exist
$reports_query = db_query("select * from app_unitas_pivot_map_reports where is_public_access=1 and id='" . _GET('id') . "'");
if(!$reports = db_fetch_array($reports_query))
{
	exit();
}

app_set_title($reports['name']);

$app_user = [];
$app_user['id'] = 0;
$app_user['group_id'] = 0;
        
$app_layout = 'public_map_layout.php';