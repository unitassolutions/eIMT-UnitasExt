<?php
/**
 * UNITAS Pivot Map Report View
 */

//load report first (MISSING in your version)
$reports_query = db_query("select * from app_unitas_pivot_map_reports where id='" . db_input($_GET['id']) . "'");
if(!$reports = db_fetch_array($reports_query))
{
    echo '<div class="alert alert-warning">Report not found</div>';
    return;
}
?>

<?php if(!isset($_GET['is_modal'])): ?>
<h3 class="page-title"><?php echo $reports['name'] ?></h3>
<?php endif; ?>

<?php

echo pivot_map_reports::render_entity_filters_panel($reports);
echo pivot_map_reports::render_legend($reports);

// load correct map component
$map_type = isset($reports['map_type']) ? $reports['map_type'] : 'google';  switch($map_type)
{
    case 'yandex':
        require(component_path('unitas_ext/pivot_map_reports/view_yandex'));
        break;

    case 'google':
        require(component_path('unitas_ext/pivot_map_reports/view_google'));
        break;

    case 'mapbbcode':
        require(component_path('unitas_ext/pivot_map_reports/view'));
        break;
}
?>
