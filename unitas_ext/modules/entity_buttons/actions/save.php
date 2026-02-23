<?php
// Entity Buttons - Save Action
// This must output ONLY JSON for modal requests

// Turn off output buffering
while(ob_get_level()) {
    ob_end_clean();
}

// Check if user is administrator
if($app_user['group_id'] != 0)
{
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get form data
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$entity_id = isset($_POST['entity_id']) ? (int)$_POST['entity_id'] : 0;
$button_title = isset($_POST['button_title']) ? db_prepare_input($_POST['button_title']) : '';
$button_type = isset($_POST['button_type']) ? $_POST['button_type'] : 'url';
$url = isset($_POST['url']) ? trim(db_prepare_input($_POST['url'])) : '';
$button_icon = isset($_POST['button_icon']) ? trim(db_prepare_input($_POST['button_icon'])) : ''; // Empty by default
$button_color = 'btn-primary'; // Always blue
$sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
$is_active = isset($_POST['is_active']) ? 1 : 0;
$is_modal = isset($_POST['is_modal']) ? 1 : 0;

// Set JSON header FIRST
header('Content-Type: application/json');

// Validate required fields
if(empty($entity_id) || empty($button_title) || empty($url))
{
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit();
}

// Parse URL
$report_id = 0;
$external_url = $url;

// Try to extract report_id from URL if it's a report
if($button_type == 'report' && preg_match('/reports_id=(\d+)/', $url, $matches)) {
    $report_id = (int)$matches[1];
}

// Prepare SQL
if($id > 0)
{
    // Update
    $sql = "UPDATE app_ext_unitas_entity_buttons SET
            entity_id = '" . $entity_id . "',
            button_title = '" . $button_title . "',
            button_type = '" . $button_type . "',
            report_id = " . ($report_id ?: 'NULL') . ",
            external_url = '" . db_input($external_url) . "',
            button_icon = '" . db_input($button_icon) . "',
            button_color = '" . $button_color . "',
            sort_order = " . $sort_order . ",
            is_active = " . $is_active . "
            WHERE id = " . $id;
}
else
{
    // Insert
    $sql = "INSERT INTO app_ext_unitas_entity_buttons 
            (entity_id, button_title, button_type, report_id, external_url, button_icon, button_color, sort_order, is_active)
            VALUES (
            '" . $entity_id . "',
            '" . $button_title . "',
            '" . $button_type . "',
            " . ($report_id ?: 'NULL') . ",
            '" . db_input($external_url) . "',
            '" . db_input($button_icon) . "',
            '" . $button_color . "',
            " . $sort_order . ",
            " . $is_active . ")";
}

// Execute query
if(db_query($sql))
{
    if($is_modal) {
        echo json_encode(['success' => true, 'message' => 'Record saved successfully']);
        exit();
    } else {
        $alerts->add('Record saved', 'success');
        echo '<script>window.location.href = "' . url_for('unitas_ext/entity_buttons/index') . '";</script>';
        exit();
    }
}
else
{
    if($is_modal) {
        echo json_encode(['success' => false, 'message' => 'Error saving record to database']);
        exit();
    } else {
        $alerts->add('Error saving record', 'error');
        echo '<script>window.location.href = "' . url_for('unitas_ext/entity_buttons/form', ($id > 0 ? 'id=' . $id : '')) . '";</script>';
        exit();
    }
}