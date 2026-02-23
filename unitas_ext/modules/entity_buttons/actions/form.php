<?php
// Entity Buttons - Add/Edit Form

// Check if user is administrator
if($app_user['group_id'] != 0)
{
    redirect_to('dashboard/access_forbidden');
}

// Load language file
require_once(PLUGIN_UNITAS_EXT_PATH . '/languages/en.php');

// Get button ID if editing
$id = isset($_GET['id']) ? $_GET['id'] : 0;
$button_data = array();

if($id > 0)
{
    // Get existing button data
    $sql = "SELECT * FROM app_ext_unitas_entity_buttons WHERE id = " . (int)$id;
    $result = db_query($sql);
    
    if($button = db_fetch_array($result))
    {
        $button_data = $button;
    }
}

// Get entities for dropdown
$sql_entities = "SELECT id, name FROM app_entities ORDER BY name";
$result_entities = db_query($sql_entities);
$entities = array();
while($row = db_fetch_array($result_entities))
{
    $entities[$row['id']] = $row['name'];
}

// Set page title
$app_title = ($id > 0 ? PLUGIN_UNITAS_EDIT_BUTTON : PLUGIN_UNITAS_ADD_NEW_BUTTON);