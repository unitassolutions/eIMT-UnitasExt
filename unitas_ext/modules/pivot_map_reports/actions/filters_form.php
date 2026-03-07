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

$reports_info_query = db_query("select * from app_reports where id='" . db_input((isset($_GET['parent_reports_id']) ? $_GET['parent_reports_id']:$_GET['reports_id'])). "'");
if(!$reports_info = db_fetch_array($reports_info_query))
{
  echo TEXT_REPORT_NOT_FOUND;
  exit();
}

$obj = array();

if(isset($_GET['id']))
{
  $obj = db_find('app_reports_filters',$_GET['id']);  
}
else
{
  $obj = db_show_columns('app_reports_filters');
  $obj['is_active'] = 1;
}