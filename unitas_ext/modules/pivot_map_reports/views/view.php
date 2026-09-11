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

require_once dirname(__DIR__,3) . '/classes/map/pivot_map_reports.php';

// Modern (v2) layout applies to Google maps only; pre-migration rows and
// every other combination take the classic path unchanged.
$map_type = isset($reports['map_type']) ? $reports['map_type'] : 'google';
$use_v2 = ((isset($reports['layout']) ? $reports['layout'] : 'classic') == 'modern' && $map_type == 'google');

if($use_v2)
{
    // The wrap positions the filter panel as a floating card over the map
    // and receives the dark-mode class from the v2 JS. Filters stay OUTSIDE
    // the reload target so they survive fragment reloads.
    echo '<div class="unitas-pmv2-wrap">';
    // Only emit the floating filter card when the report actually has filter
    // fields configured. render_entity_filters_panel() returns an empty string
    // when there are none, and an empty wrapper still shows its padded card
    // background as a stray bar floating over the top of the map.
    $filters_html = unitas_pivot_map_reports::render_entity_filters_panel($reports);
    if(trim($filters_html) !== '')
    {
        echo '<div class="unitas-pmv2-filters">' . $filters_html . '</div>';
    }
    // v2 draws its own interactive legend inside the map shell
}
else
{
    echo unitas_pivot_map_reports::render_entity_filters_panel($reports);
    echo unitas_pivot_map_reports::render_legend($reports);
}

// load correct map component
switch($map_type)
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

if($use_v2)
{
    echo '</div>';
}
?>
