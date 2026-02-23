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
?>

<h3 class="page-title"><?php echo TEXT_EXT_PIVOT_MAP_REPORT ?></h3>

<p><?php echo TEXT_EXT_PIVOT_MAP_REPORT_INFO ?></p>

<?php echo button_tag(TEXT_BUTTON_CREATE,url_for('unitas_ext/pivot_map_reports/form')) ?>

<div class="table-scrollable">
<table class="table table-striped table-bordered table-hover">
<thead>
  <tr>
    <th><?php echo TEXT_ACTION ?></th>            
    <th width="100%"><?php echo TEXT_NAME ?></th>
    <th><?php echo TEXT_IN_MENU ?></th>            
    <th><?php echo TEXT_USERS_GROUPS ?></th>    
  </tr>
</thead>
<tbody>
<?php

$reports_query = db_query("select * from app_unitas_pivot_map_reports order by name");

if(db_num_rows($reports_query)==0) echo '<tr><td colspan="9">' . TEXT_NO_RECORDS_FOUND. '</td></tr>'; 

while($reports = db_fetch_array($reports_query)):

$count_query = db_query("select count(*) as total from app_unitas_pivot_map_reports_entities where reports_id='" . $reports['id'] . "'");
$count = db_fetch_array($count_query);

$public_url = '';
if($reports['is_public_access'])
{
  $public_url = ' |  <a href="' . url_for('unitas_ext/pivot_map_reports/public','id=' . $reports['id']) . '" target="_blank">' . TEXT_EXT_PUBLIC_URL . '</a>';
}

?>
<tr>
  <td style="white-space: nowrap;"><?php echo button_icon_delete(url_for('unitas_ext/pivot_map_reports/delete','id=' . $reports['id'])) . ' ' . button_icon_edit(url_for('unitas_ext/pivot_map_reports/form','id=' . $reports['id'])) ?></td>  
  <td><?php echo link_to($reports['name'], url_for('unitas_ext/pivot_map_reports/entities','reports_id=' . $reports['id'])) . '<br>' . tooltip_text(TEXT_EXT_ENTITIES . ': ' . $count['total'] . $public_url) ?></td>
  <td><?php echo render_bool_value($reports['in_menu']) ?></td>    
  <td>
      <?php
      if(strlen($reports['users_groups']))
      {	
        $users_groups_list = array();
        foreach(explode(',',$reports['users_groups']) as $users_groups_id)
        {
          $users_groups_list[] = access_groups::get_name_by_id($users_groups_id);
        }
        
        echo implode('<br>',$users_groups_list);
      }
      ?>
   </td> 
</tr>  
<?php endwhile ?>
</tbody>
</table>
</div>
