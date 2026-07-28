<?php

//Check access
if($app_user['group_id']>0)
{
  redirect_to('dashboard/access_forbidden');
}

switch($app_module_action)
{
  case 'save':
  
      $sql_data = array(
      	'name'=>$_POST['name'],
        'users_groups'=>(isset($_POST['access']) ? json_encode($_POST['access']):''),
        'in_menu'=>(isset($_POST['in_menu']) ? $_POST['in_menu']:0),
        'users_groups'=>(isset($_POST['users_groups']) ? implode(',',$_POST['users_groups']):''),
        'is_public_access' => $_POST['is_public_access'] ?? 0,
        'zoom'=>$_POST['zoom'] ?? '',
        'latlng'=>trim(preg_replace('/ +/',',',$_POST['latlng'] ?? '')),
        'display_legend'=>$_POST['display_legend'] ?? 0,
        'display_sidebar'=>$_POST['display_sidebar'] ?? 0,
        'sidebar_width'=>$_POST['sidebar_width'] ?? '',
        'layout'=>(in_array($_POST['layout'] ?? 'classic', array('classic','modern')) ? ($_POST['layout'] ?? 'classic') : 'classic'),
        'use_form_map_settings' => $_POST['use_form_map_settings'] ?? 0
      );
                                                                                    
      if(isset($_GET['id']))
      {        
        db_perform('app_unitas_pivot_map_reports',$sql_data,'update',"id='" . db_input($_GET['id']) . "'");       
      }
      else
      {                               
        db_perform('app_unitas_pivot_map_reports',$sql_data);                    
      }
                                          
      redirect_to('unitas_ext/pivot_map_reports/reports');
      
    break;
    
    case 'delete':
      $obj = db_find('app_unitas_pivot_map_reports',$_GET['id']);
      
      db_delete_row('app_unitas_pivot_map_reports',$_GET['id']);
      
      $entities_query = db_query("select id from app_unitas_pivot_map_reports_entities where reports_id='" . $_GET['id'] . "'");
      while($entities = db_fetch_array($entities_query))
      {
          reports::delete_reports_by_type('pivot_map' . $entities['id']);
      }
      
      db_delete_row('app_unitas_pivot_map_reports_entities',$_GET['id'],'reports_id');
                                     
      $alerts->add(sprintf(TEXT_WARN_DELETE_SUCCESS,$obj['name']),'success');
      
      redirect_to('unitas_ext/pivot_map_reports/reports');
    break;      
}