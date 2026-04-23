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


//check access
if($app_user['group_id']>0)
{
	redirect_to('dashboard/access_forbidden');
}

switch($app_module_action)
{
  case 'save':
      if(!defined('CFG_PLUGIN_EXT_LICENSE_KEY'))
      {
        db_perform('app_configuration',array('configuration_value'=>$_POST['product_key'],'configuration_name'=>'CFG_PLUGIN_EXT_LICENSE_KEY'));
      }
          
      redirect_to('ext/license/key');
    break;
  case 'update':
      db_perform('app_configuration',array('configuration_value'=>$_POST['product_key']),'update',"configuration_name = 'CFG_PLUGIN_EXT_LICENSE_KEY'");
          
      redirect_to('ext/license/key');
    break;    
}

