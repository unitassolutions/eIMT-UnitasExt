<?php
/**
 * UNITAS Extension — Waze CIFS Closure Feed (public, keyed)
 *
 * Live endpoint polled by Waze every few minutes. Emits CIFS JSON:
 *   {"incidents":[{"id":"closure-N","type":"ROAD_CLOSED","street":...,
 *     "polyline":"lat lon lat lon ...","starttime":ISO8601,"endtime":ISO8601,
 *     "direction":...,"subtype":...,"description":...}, ...]}
 *
 * Clearing model: every response sets endtime = now + rolling window, so a
 * record that leaves the feed (reopened / unpushed) expires off Waze within
 * roughly window + one polling cycle. Waze only guarantees removal at
 * endtime — removal on feed disappearance is undocumented, and an omitted
 * endtime defaults to +14 days.
 *
 * Security: 128-bit key required in the URL, compared with hash_equals.
 * Every identity failure (bad method, not installed, disabled, missing or
 * wrong key) returns a bare 404 with an empty body — indistinguishable from
 * a nonexistent page. Success responses carry X-Robots-Tag noindex.
 * Once the key matches, an incomplete mapping or schema drift returns a
 * valid EMPTY feed (200) so Partner Hub validation stays green during setup.
 */

// Remove any output buffers (application_top.php registers an HTML injector)
while (ob_get_level()) {
    ob_end_clean();
}

function unitas_waze_feed_404()
{
    http_response_code(404);
    exit();
}

function unitas_waze_feed_emit($incidents)
{
    $payload = json_encode(
        array('incidents' => array_values($incidents)),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($payload === false) {
        $payload = '{"incidents":[]}';
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo $payload;
    }
    exit();
}

// Parse a stored date value defensively: unix timestamp, then free-form
// string, then the record creation time, then now.
function unitas_waze_parse_time($raw, $fallback)
{
    $raw = trim((string)$raw);
    if ($raw !== '' && is_numeric($raw)) {
        return (int)$raw;
    }
    if ($raw !== '') {
        $ts = strtotime($raw);
        if ($ts !== false) {
            return $ts;
        }
    }
    if (is_numeric($fallback) && (int)$fallback > 0) {
        return (int)$fallback;
    }
    return time();
}

// ── Identity gates: every failure is the same bare 404 ──────────────────────

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', array('GET', 'HEAD'))) {
    unitas_waze_feed_404();
}

require_once PLUGIN_UNITAS_EXT_PATH . '/install.php';
if (!unitas_ext_installer::is_installed()) {
    unitas_waze_feed_404();
}

require_once PLUGIN_UNITAS_EXT_PATH . '/modules/map_configuration/helpers/map_config.php';
$feed_cfg = unitas_map_config::get();

if ((int)($feed_cfg['waze_feed_enabled'] ?? 0) !== 1) {
    unitas_waze_feed_404();
}

$stored_key = trim((string)($feed_cfg['waze_feed_key'] ?? ''));
if (strlen($stored_key) < 32) {
    // Blocks empty-vs-empty acceptance if a key was never generated
    unitas_waze_feed_404();
}
if (!hash_equals($stored_key, (string)($_GET['key'] ?? ''))) {
    unitas_waze_feed_404();
}

// ── Authorized from here on ─────────────────────────────────────────────────

$app_user = array('id' => 0, 'group_id' => 0);

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-cache, must-revalidate');

// Mapping config: incomplete = valid empty feed, never 404
$map = json_decode((string)($feed_cfg['waze_feed_config'] ?? ''), true);
if (!is_array($map)) {
    unitas_waze_feed_emit(array());
}
foreach (array('entities_id', 'geometry_field', 'street_field', 'status_field', 'push_field', 'start_field') as $rk) {
    if ((int)($map[$rk] ?? 0) <= 0) {
        unitas_waze_feed_emit(array());
    }
}
if (trim((string)($map['status_closed_choice'] ?? '')) === '') {
    unitas_waze_feed_emit(array());
}

// Schema-drift guards: entity or fields deleted after the mapping was saved
$eid   = (int)$map['entities_id'];
$table = 'app_entity_' . $eid;

$tq = db_query("SHOW TABLES LIKE '" . db_input($table) . "'");
if (!db_fetch_array($tq)) {
    unitas_waze_feed_emit(array());
}

$existing_columns = array();
$cq = db_query("SHOW COLUMNS FROM `" . $table . "`");
while ($c = db_fetch_array($cq)) {
    $existing_columns[] = $c['Field'];
}

$col = array();
foreach (array('geometry_field', 'street_field', 'status_field', 'push_field', 'start_field', 'reason_field', 'direction_field', 'description_field') as $rk) {
    $col[$rk] = 'field_' . (int)($map[$rk] ?? 0);
}
foreach (array('geometry_field', 'street_field', 'status_field', 'push_field', 'start_field') as $rk) {
    if (!in_array($col[$rk], $existing_columns)) {
        unitas_waze_feed_emit(array());
    }
}
// Optional roles degrade to unconfigured when their column is gone
foreach (array('reason_field', 'direction_field', 'description_field') as $rk) {
    if ((int)($map[$rk] ?? 0) > 0 && !in_array($col[$rk], $existing_columns)) {
        $map[$rk] = 0;
    }
}

// ── Query active pushed closures ────────────────────────────────────────────

$now    = time();
$window = (int)($feed_cfg['waze_feed_window'] ?? 15);
if ($window < 5) {
    $window = 5;
}
if ($window > 120) {
    $window = 120;
}
$end_iso = date('c', $now + $window * 60);

$subtype_map = (isset($map['subtype_map']) && is_array($map['subtype_map'])) ? $map['subtype_map'] : array();
$one_way     = trim((string)($map['direction_one_way_choice'] ?? ''));

// Push filter is type-aware: fieldtype_boolean stores the literal strings
// true / false, while checkbox-style fields store choice ids or 1/0.
$push_type_q   = db_query("select type from app_fields where id = '" . (int)$map['push_field'] . "'");
$push_type_row = db_fetch_array($push_type_q);
$push_type     = is_array($push_type_row) ? (string)($push_type_row['type'] ?? '') : '';
if ($push_type === 'fieldtype_boolean') {
    $push_where = "e." . $col['push_field'] . " = 'true'";
} else {
    $push_where = "length(e." . $col['push_field'] . ") > 0"
                . " and e." . $col['push_field'] . " <> '0'"
                . " and e." . $col['push_field'] . " <> 'false'";
}

// find_in_set handles both single values and comma-separated multi values
$sql = "select e.* from `" . $table . "` e"
     . " where " . $push_where
     . " and find_in_set('" . db_input($map['status_closed_choice']) . "', e." . $col['status_field'] . ")"
     . " and length(e." . $col['geometry_field'] . ") > 0"
     . " order by e.id desc limit 500";

$incidents   = array();
$items_query = db_query($sql);
while ($item = db_fetch_array($items_query)) {

    // Street is required by CIFS
    $street = trim((string)($item[$col['street_field']] ?? ''));
    if ($street === '') {
        continue;
    }

    // Geometry: polyline with at least 2 valid vertices (skip polygon/circle)
    $geo = json_decode((string)($item[$col['geometry_field']] ?? ''), true);
    if (!is_array($geo) || ($geo['type'] ?? '') !== 'polyline') {
        continue;
    }
    if (empty($geo['points']) || !is_array($geo['points']) || count($geo['points']) < 2) {
        continue;
    }
    $pairs = array();
    $bad   = false;
    foreach ($geo['points'] as $pt) {
        if (!is_array($pt) || !isset($pt[0]) || !isset($pt[1]) || !is_numeric($pt[0]) || !is_numeric($pt[1])) {
            $bad = true;
            break;
        }
        $lat = (float)$pt[0];
        $lng = (float)$pt[1];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $bad = true;
            break;
        }
        // %F is locale-independent; 7 decimals exceeds the CIFS minimum of 6
        $pairs[] = sprintf('%.7F %.7F', $lat, $lng);
    }
    if ($bad || count($pairs) < 2) {
        continue;
    }

    // Actual closure start; future (scheduled) closures are not fed
    $start_ts = unitas_waze_parse_time($item[$col['start_field']] ?? '', $item['date_added'] ?? 0);
    if ($start_ts > $now) {
        continue;
    }

    $incident = array(
        'id'        => 'closure-' . (int)$item['id'],
        'type'      => 'ROAD_CLOSED',
        'street'    => $street,
        'polyline'  => implode(' ', $pairs),
        'starttime' => date('c', $start_ts),
        'endtime'   => $end_iso,
    );

    // Direction: ONE_DIRECTION only when the mapped choice matches
    $direction = 'BOTH_DIRECTIONS';
    if ((int)($map['direction_field'] ?? 0) > 0 && $one_way !== '') {
        $dtokens = array_map('trim', explode(',', (string)($item[$col['direction_field']] ?? '')));
        if (in_array($one_way, $dtokens)) {
            $direction = 'ONE_DIRECTION';
        }
    }
    $incident['direction'] = $direction;

    // Subtype + reason label from the mapping captured at save time
    $reason_label = '';
    if ((int)($map['reason_field'] ?? 0) > 0) {
        $rtokens = explode(',', (string)($item[$col['reason_field']] ?? ''));
        $rkey    = trim((string)($rtokens[0] ?? ''));
        if ($rkey !== '' && isset($subtype_map[$rkey]) && is_array($subtype_map[$rkey])) {
            $rsub = trim((string)($subtype_map[$rkey]['subtype'] ?? ''));
            if ($rsub !== '') {
                $incident['subtype'] = $rsub;
            }
            $reason_label = trim((string)($subtype_map[$rkey]['label'] ?? ''));
        }
    }

    $desc_parts = array();
    if ($reason_label !== '') {
        $desc_parts[] = $reason_label;
    }
    if ((int)($map['description_field'] ?? 0) > 0) {
        $dtext = (string)($item[$col['description_field']] ?? '');
        $dtext = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($dtext), ENT_QUOTES, 'UTF-8')));
        if ($dtext !== '') {
            $dtext = function_exists('mb_substr') ? mb_substr($dtext, 0, 300) : substr($dtext, 0, 300);
            $desc_parts[] = $dtext;
        }
    }
    if (count($desc_parts)) {
        $incident['description'] = implode(' - ', $desc_parts);
    }

    $incidents[] = $incident;
    if (count($incidents) >= 500) {
        break;
    }
}

unitas_waze_feed_emit($incidents);
