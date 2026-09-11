<?php
/**
 * UNITAS Extension - location pin endpoint (POST, JSON).
 *
 * Sets a location field to a manually placed pin. Unlike the core
 * items/google_map endpoint it enforces UPDATE access, field type, field-level
 * access, coordinate validation and a non-empty address before writing.
 *
 * Reached as module=unitas_ext/location/pin&action=save so the global
 * csrf_protect::check() validates the token in application_top.
 *
 * Input (POST): path (e.g. "42-1234"), field_id, lat, lng.
 *
 * See plan section 7.9.
 */

require_once PLUGIN_UNITAS_EXT_PATH . '/classes/location/unitas_location_value.php';

function unitas_pin_fail($code, $msg)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => $msg));
    exit();
}

// 1. Authenticated user.
if (empty($app_user['id']) || (int)$app_user['id'] <= 0) {
    unitas_pin_fail(401, 'Not authenticated');
}

// 3. Parse path into entity + item ids (mirrors items/module_top.php).
$path     = (string)($_POST['path'] ?? '');
$field_id = (int)($_POST['field_id'] ?? 0);
$lat      = isset($_POST['lat']) ? $_POST['lat'] : null;
$lng      = isset($_POST['lng']) ? $_POST['lng'] : null;

$last_segment = trim($path);
if (strpos($last_segment, '/') !== false) {
    $seg = explode('/', $last_segment);
    $last_segment = end($seg);
}
$ids  = explode('-', $last_segment);
$eid  = (int)($ids[0] ?? 0);
$item = (int)($ids[1] ?? 0);

if ($eid <= 0 || $item <= 0 || $field_id <= 0) {
    unitas_pin_fail(400, 'Bad request');
}

// Entity exists.
if (!isset($app_entities_cache[$eid])) {
    unitas_pin_fail(404, 'Unknown entity');
}

// 4. Item is visible to this user (record visibility + parent-entity access).
$vq = db_query(
    "select e.* from app_entity_" . $eid . " e where e.id = '" . db_input($item) . "' "
    . records_visibility::add_access_query($eid) . " "
    . items::add_access_query_for_parent_entities($eid),
    false
);
$item_info = $vq ? db_fetch_array($vq) : false;
if (!$item_info) {
    unitas_pin_fail(404, 'Not found');
}

// 5. Field exists, belongs to this entity, and is our type.
$field = db_find('app_fields', $field_id);
if (!isset($field['id']) || (int)$field['entities_id'] !== $eid || $field['type'] !== 'fieldtype_unitas_location') {
    unitas_pin_fail(400, 'Invalid field');
}

// 6. Update access to the record (admins pass automatically).
$current_access_schema = users::get_entities_access_schema($eid, $app_user['group_id']);
$access_rules = new access_rules($eid, $item_info);
$schema = $access_rules->get_access_schema();
if (!users::has_access('update', $schema)) {
    unitas_pin_fail(403, 'No update access');
}

// 7. Field is not view-only or hidden for this group. Presence in either map
//    means restricted (this mirrors the core save loop, which skips such fields).
if ((int)$app_user['group_id'] !== 0) {
    $fields_access = users::get_fields_access_schema($eid, $app_user['group_id']);
    $view_only     = $access_rules->get_fields_view_only_access();
    if (isset($fields_access[$field_id]) || isset($view_only[$field_id])) {
        unitas_pin_fail(403, 'Field not editable');
    }
}

// 8. Coordinates valid.
if (!unitas_location_value::is_valid_point($lat, $lng)) {
    unitas_pin_fail(400, 'Invalid coordinates');
}

// 9. Current source address is non-empty (P13: a manual pin needs an address).
$cfg    = new fields_types_cfg($field['configuration']);
$src_id = (int)$cfg->get('source_field_id');
$source = unitas_location_value::normalize_address(
    ($src_id > 0 && isset($item_info['field_' . $src_id])) ? $item_info['field_' . $src_id] : ''
);
if ($source === '') {
    unitas_pin_fail(400, 'Address is empty');
}

// Write the manual value.
$value = unitas_location_value::format((float)$lat, (float)$lng, $source, 'manual');
db_query("update app_entity_" . $eid . " set field_" . $field_id . " = '" . db_input($value) . "' where id = '" . db_input($item) . "'");

header('Content-Type: application/json');
echo json_encode(array('ok' => true, 'status' => 'manual'));
exit();
