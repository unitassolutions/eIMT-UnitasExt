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

$breadcrumb = array();

$breadcrumb[] = '<li>' . link_to(TEXT_EXT_PIVOT_MAP_REPORT,url_for('unitas_ext/pivot_map_reports/reports')) . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $pivot_map_info['name'] . '</li>';
?>

<ul class="page-breadcrumb breadcrumb">
  <?php echo implode('',$breadcrumb) ?>  
</ul>

<h3 class="page-title"><?php echo TEXT_EXT_ENTITIES ?></h3>

<p><?php echo TEXT_EXT_PIVOT_СALENDAR_ENTITIES_INFO ?></p>

<?php echo button_tag(TEXT_BUTTON_ADD,url_for('unitas_ext/pivot_map_reports/entities_form','reports_id=' . $pivot_map_info['id'])) ?>

<div class="table-scrollable">
<table class="table table-striped table-bordered table-hover">
<thead>
  <tr>
    <th><?php echo TEXT_ACTION ?></th>        
    <th width="100%"><?php echo TEXT_REPORT_ENTITY ?></th>
    <th><?php echo TEXT_FIELD ?></th>        
  </tr>
</thead>
<tbody>
<?php

$reports_query = db_query("select * from app_unitas_pivot_map_reports_entities where reports_id=" . $pivot_map_info['id']. " order by id");

if(db_num_rows($reports_query)==0) echo '<tr><td colspan="9">' . TEXT_NO_RECORDS_FOUND. '</td></tr>'; 

while($reports = db_fetch_array($reports_query)):

$filters_panel_type = 'pivot_map_reports_entity_filters_panel_' . $pivot_map_info['id'] . '_' . $reports['entities_id'];
$filters_panel_id = filters_panels::get_id_by_type($reports['entities_id'], $filters_panel_type);
$filters_panel_link = '<a href="' . url_for('unitas_ext/filters_panels/fields','panels_id=' . $filters_panel_id . '&entities_id=' . $reports['entities_id'] . '&redirect_to=pivot_map_reports_entity' . $pivot_map_info['id'] . '_' . $reports['entities_id']) . '">' . TEXT_FILTERS_PANELS . '</a>';
    
    
$reports_id = pivot_map_reports::get_reports_id_by_map_entity($reports['id'], $reports['entities_id']);

?>
<tr>
  <td style="white-space: nowrap;"><?php echo button_icon_delete(url_for('unitas_ext/pivot_map_reports/entities_delete','id=' . $reports['id'] . '&reports_id=' . $pivot_map_info['id'])) . ' ' . button_icon_edit(url_for('unitas_ext/pivot_map_reports/entities_form','id=' . $reports['id'] . '&reports_id=' . $pivot_map_info['id'])) ?></td>
  
  <td>
  	<?php echo link_to($app_entities_cache[$reports['entities_id']]['name'],url_for('unitas_ext/pivot_map_reports/filters','map_reports_id=' . $pivot_map_info['id'] . '&reports_id=' . $reports_id )) ?>
  	<?php echo tooltip_text(TEXT_FILTERS . ': ' . reports::count_filters_by_reports_id($reports_id) . ' | ' . $filters_panel_link) ?>
  </td>
  <td><?php echo $app_fields_cache[$reports['entities_id']][$reports['fields_id']]['name'] ?></td> 
</tr>  
<?php endwhile ?>
</tbody>
</table>
</div>

<?php echo '<a href="' . url_for('unitas_ext/pivot_map_reports/reports') . '" class="btn btn-default">' . TEXT_BUTTON_BACK. '</a>' ?>
