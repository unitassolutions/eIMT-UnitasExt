<?php
/**
 * UNITAS Extension - Location Tools (admin only).
 *
 * Migration (preflight + convert core Google map fields), Re-geocode driver,
 * and Location health triage. Plan section 8.
 */

if ($app_user['group_id'] != 0)
{
    redirect_to('dashboard/');
}

require_once PLUGIN_UNITAS_EXT_PATH . '/classes/location/unitas_location_migration.php';
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/google/unitas_google_keys.php';

// Keep the P12 endpoint-block flag current whenever this page is visited
// (plan section 7.13).
unitas_ext_installer::refresh_core_gmap_flag();

$location_tools_results = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'convert')
{
    // Preconditions (plan 8.2): explicit confirmations and both keys present.
    if (empty($_POST['confirm_backup']) || empty($_POST['confirm_keys']))
    {
        $location_tools_results[] = array('ok' => false, 'label' => 'Preconditions',
            'message' => 'Confirm the database backup and the key tests before converting.');
    }
    elseif (unitas_google_keys::browser() === '' || unitas_google_keys::server_source() === 'none')
    {
        $location_tools_results[] = array('ok' => false, 'label' => 'Preconditions',
            'message' => 'Both the browser key and the server key must be configured (UNITAS Extension > Google Map).');
    }
    else
    {
        $selected = isset($_POST['convert_fields']) && is_array($_POST['convert_fields'])
            ? array_map('intval', $_POST['convert_fields']) : array();

        if (!$selected)
        {
            $location_tools_results[] = array('ok' => false, 'label' => 'Convert',
                'message' => 'No fields selected.');
        }

        foreach ($selected as $fid)
        {
            $field = db_find('app_fields', $fid);
            $label = isset($field['name']) ? $field['name'] . ' (#' . $fid . ')' : '#' . $fid;
            $res = unitas_location_migration::convert_field($fid, (int)$app_user['id']);
            $res['label'] = $label;
            $location_tools_results[] = $res;
        }

        // Conversion may have removed the last core map field.
        unitas_ext_installer::refresh_core_gmap_flag();
    }
}

$app_title = 'Location Tools';
