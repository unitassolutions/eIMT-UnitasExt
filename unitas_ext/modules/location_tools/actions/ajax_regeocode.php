<?php
/**
 * UNITAS Extension - Re-geocode batch endpoint (admin only, JSON).
 *
 * Processes up to 25 records of one location field per request, reusing the
 * same per-record decision as the save hook (process_record), so the tool and
 * the hook can never disagree. Records whose stored address matches the source
 * with a settled status are skipped without a Geocoding call; the geocoder
 * memoizes repeated addresses within the batch. Plan section 8.3.
 *
 * POST: fields_id, last_id, include_not_found, include_approximate.
 * Reached with action=batch so the global CSRF token check applies.
 */

header('Content-Type: application/json');

if (!isset($app_user['group_id']) || $app_user['group_id'] != 0)
{
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Admin only'));
    exit();
}

$fields_id = (int)($_POST['fields_id'] ?? 0);
$last_id   = (int)($_POST['last_id'] ?? 0);

$field = db_find('app_fields', $fields_id);
if (!isset($field['id']) || $field['type'] !== 'fieldtype_unitas_location')
{
    echo json_encode(array('ok' => false, 'error' => 'Not a location field'));
    exit();
}

$eid = (int)$field['entities_id'];

$retry = array();
if (!empty($_POST['include_not_found']))   $retry[] = 'not_found';
if (!empty($_POST['include_approximate'])) $retry[] = 'approximate';

$rows = array();
$q = db_query("select * from app_entity_{$eid} where id > " . $last_id . " order by id limit 25");
while ($r = db_fetch_array($q)) $rows[] = $r;

$statuses = array();
foreach ($rows as $row)
{
    $last_id = (int)$row['id'];
    $status = fieldtype_unitas_location::process_record($eid, (int)$row['id'], $field, $row, $retry);
    $key = ($status === null) ? 'empty' : $status;
    $statuses[$key] = isset($statuses[$key]) ? $statuses[$key] + 1 : 1;
}

echo json_encode(array(
    'ok'        => true,
    'processed' => count($rows),
    'statuses'  => $statuses,
    'last_id'   => $last_id,
    'done'      => (count($rows) < 25),
));
exit();
