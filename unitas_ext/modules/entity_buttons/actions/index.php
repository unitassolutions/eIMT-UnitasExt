<?php
// Entity Buttons - Main Admin Page

// Check if user is administrator
if($app_user['group_id'] != 0)
{
    redirect_to('dashboard/access_forbidden');
}

// Load language file
require_once(PLUGIN_UNITAS_EXT_PATH . '/languages/en.php');

// Get all buttons from database
$sql = "SELECT b.*, e.name as entity_name 
        FROM app_ext_unitas_entity_buttons b
        LEFT JOIN app_entities e ON e.id = b.entity_id
        ORDER BY b.entity_id, b.sort_order";
        
$buttons_query = db_query($sql);
$buttons = array();

while($button = db_fetch_array($buttons_query))
{
    $buttons[] = $button;
}

// Set page title
$app_title = PLUGIN_UNITAS_EXT_NAME . ' - ' . PLUGIN_UNITAS_ENTITY_BUTTONS;