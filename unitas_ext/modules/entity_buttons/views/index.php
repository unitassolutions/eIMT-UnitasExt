<?php
// Entity Buttons - Admin View

echo '<div class="container">';
echo '<h3 class="page-title">' . PLUGIN_UNITAS_ENTITY_BUTTONS . '</h3>';

// Add button link
echo '<div style="margin-bottom: 20px;">';
$add_url = url_for('unitas_ext/entity_buttons/form', 'is_modal=1');
echo '<button type="button" class="btn btn-primary" onclick="openUnitasModal(\'' . $add_url . '\')">';
echo '<i class="fa fa-plus"></i> ' . PLUGIN_UNITAS_ADD_NEW_BUTTON;
echo '</button>';
echo '</div>';

if(count($buttons) > 0)
{
    echo '<div class="table-scrollable">';
    echo '<table class="table table-striped table-bordered table-hover">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Entity</th>';
    echo '<th>Title</th>';
    echo '<th>Type</th>';
    echo '<th>URL</th>';
    echo '<th>Icon</th>';
    echo '<th>Order</th>';
    echo '<th>Active</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach($buttons as $button)
    {
        echo '<tr>';
        echo '<td>' . $button['entity_name'] . ' (ID: ' . $button['entity_id'] . ')</td>';
        echo '<td>' . $button['button_title'] . '</td>';
        echo '<td>' . ucfirst($button['button_type']) . '</td>';
        
        // Show URL
        $url = '';
        if($button['button_type'] == 'report' && $button['report_id']) {
            $url = 'index.php?module=reports/view&reports_id=' . $button['report_id'];
        } elseif($button['external_url']) {
            $url = $button['external_url'];
        }
        echo '<td><small class="text-muted">' . htmlspecialchars(substr($url, 0, 40)) . (strlen($url) > 40 ? '...' : '') . '</small></td>';
        
        // Show icon or empty
        echo '<td>';
        if(!empty($button['button_icon']) && $button['button_icon'] != 'fa-external-link') {
            echo '<i class="fa ' . $button['button_icon'] . '"></i>';
        } else {
            echo '<span class="text-muted">—</span>';
        }
        echo '</td>';
        
        echo '<td>' . $button['sort_order'] . '</td>';
        echo '<td>' . ($button['is_active'] ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>') . '</td>';
        echo '<td nowrap>';
        // Edit button
        $edit_url = url_for('unitas_ext/entity_buttons/form', 'id=' . $button['id'] . '&is_modal=1');
        echo '<button type="button" class="btn btn-default btn-xs" onclick="openUnitasModal(\'' . $edit_url . '\')" title="Edit"><i class="fa fa-edit"></i></button> ';
        // Delete button
        echo '<a href="' . url_for('unitas_ext/entity_buttons/delete', 'id=' . $button['id']) . '" class="btn btn-default btn-xs" title="Delete" onclick="return confirm(\'Are you sure?\')"><i class="fa fa-trash"></i></a>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}
else
{
    echo '<div class="alert alert-info">No buttons found. Click "Add New Button" to create one.</div>';
}

echo '</div>';
?>

<script>
function openUnitasModal(url) {
    open_dialog(url);
}
</script>