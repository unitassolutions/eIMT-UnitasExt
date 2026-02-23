<?php
// Entity Buttons - Delete Action

// Check if user is administrator
if($app_user['group_id'] != 0)
{
    redirect_to('dashboard/access_forbidden');
}

// Get button ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id > 0)
{
    // Delete button
    $sql = "DELETE FROM app_ext_unitas_entity_buttons WHERE id = " . $id;
    if(db_query($sql)) {
        $alerts->add('Record deleted', 'success');
    } else {
        $alerts->add('Error deleting record', 'error');
    }
}

// Redirect back to list with refresh parameter
redirect_to(url_for('unitas_ext/entity_buttons/index', 'refresh=1'));