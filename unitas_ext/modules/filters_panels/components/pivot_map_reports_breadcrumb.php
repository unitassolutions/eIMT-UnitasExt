<?php

$data = explode('_',str_replace('pivot_map_reports_entity','',$app_redirect_to));
$reports_query = db_query("select * from app_unitas_pivot_map_reports where id='" . (int)$data[0]. "'");
$reports = db_fetch_array($reports_query);

$breadcrumb = array();

$breadcrumb[] = '<li>' . link_to(TEXT_EXT_PIVOT_MAP_REPORT,url_for('unitas_ext/pivot_map_reports/entities','reports_id=' . (int)$data[0])) . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $reports['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . $app_entities_cache[$data[1]]['name'] . '<i class="fa fa-angle-right"></i></li>';

$breadcrumb[] = '<li>' . TEXT_QUICK_FILTERS_PANELS . '</li>';

?>

<ul class="page-breadcrumb breadcrumb">
  <?php echo implode('',$breadcrumb) ?>  
</ul>