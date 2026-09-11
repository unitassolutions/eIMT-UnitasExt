<?php
/**
 * UNITAS Extension - migration of core Google map fields to
 * fieldtype_unitas_location (plan section 8).
 *
 * Preflight assesses every core Google map field; convert_field() rewrites an
 * eligible field in place: type + configuration swap (dropping the per-field
 * api_key), column widened to TEXT, and stored 3-part values rewritten to the
 * 4-part format with the address url-decoded. Field ids are preserved, so
 * reports and filters keep working.
 */

require_once __DIR__ . '/unitas_location_value.php';

class unitas_location_migration
{
    /** Core Google map field types. Only plain google_map is convertible. */
    const CORE_TYPES = array('fieldtype_google_map', 'fieldtype_google_map_directions', 'fieldtype_google_map_nested');

    /** All core Google map fields, joined to their entity name. */
    public static function core_map_fields()
    {
        $rows = array();
        $q = db_query(
            "select f.*, e.name as entity_name from app_fields f " .
            "left join app_entities e on e.id = f.entities_id " .
            "where f.type in ('" . implode("','", self::CORE_TYPES) . "') " .
            "order by e.name, f.name"
        );
        while ($r = db_fetch_array($q)) $rows[] = $r;
        return $rows;
    }

    /** Preflight assessment for every core Google map field. */
    public static function preflight()
    {
        $out = array();
        foreach (self::core_map_fields() as $field) {
            $out[] = self::assess($field);
        }
        return $out;
    }

    /**
     * Assess one field. Returns the field row plus:
     *   src_id, src_name, has_key, geliossoft, records, with_coords,
     *   column_type, has_index, smart_input_rule, placement,
     *   eligible (bool), reasons (string[])
     */
    public static function assess($field)
    {
        $eid = (int)$field['entities_id'];
        $fid = (int)$field['id'];
        $cfg = new fields_types_cfg($field['configuration']);

        $a = $field;
        $a['reasons'] = array();
        $a['src_id'] = 0;
        $a['src_name'] = '';

        // Type: directions / nested are not supported (D6) and stay untouched.
        if ($field['type'] !== 'fieldtype_google_map') {
            $a['reasons'][] = 'Type ' . $field['type'] . ' is not supported for conversion (left untouched)';
        }

        // Address pattern must be a single [N] reference to a text field of
        // the same entity.
        $pattern = trim((string)$cfg->get('address_pattern'));
        $a['address_pattern'] = $pattern;
        if (preg_match('/^\[(\d+)\]$/', $pattern, $m)) {
            $src = db_find('app_fields', (int)$m[1]);
            if (isset($src['id']) && (int)$src['entities_id'] === $eid && $src['type'] === 'fieldtype_input') {
                $a['src_id'] = (int)$src['id'];
                $a['src_name'] = $src['name'];
            } else {
                $a['reasons'][] = 'Address pattern references a field that is missing, in another entity, or not a text input';
            }
        } else {
            $a['reasons'][] = 'Address pattern is not a single [field] reference';
        }

        // GeliosSoft integration is not supported.
        $a['geliossoft'] = ((string)$cfg->get('is_geliossoft') === '1');
        if ($a['geliossoft']) {
            $a['reasons'][] = 'GeliosSoft is enabled on this field';
        }

        $a['has_key'] = (trim((string)$cfg->get('api_key')) !== '');

        // Record counts (with_coords: first tab-part starts numeric).
        $a['records'] = 0;
        $a['with_coords'] = 0;
        $cq = db_query(
            "select count(*) as total, " .
            "sum(case when field_{$fid} <> '' and substring_index(field_{$fid}, '\t', 1) regexp '^-?[0-9]' then 1 else 0 end) as with_coords " .
            "from app_entity_{$eid}"
        );
        if ($c = db_fetch_array($cq)) {
            $a['records'] = (int)$c['total'];
            $a['with_coords'] = (int)$c['with_coords'];
        }

        // Column type and indexes: an index blocks the TEXT conversion.
        $a['column_type'] = '';
        $a['has_index'] = false;
        $colq = db_query("show columns from app_entity_{$eid} like 'field_{$fid}'");
        if ($col = db_fetch_array($colq)) {
            $a['column_type'] = isset($col['Type']) ? $col['Type'] : '';
        }
        $ixq = db_query("show index from app_entity_{$eid}");
        while ($ix = db_fetch_array($ixq)) {
            if (isset($ix['Column_name']) && $ix['Column_name'] === 'field_' . $fid) {
                $a['has_index'] = true;
            }
        }
        if ($a['has_index']) {
            $a['reasons'][] = 'An index exists on the column; drop or prefix it before converting to TEXT';
        }

        // Legacy smart input Google Autocomplete rule on the source field.
        $a['smart_input_rule'] = false;
        if ($a['src_id'] > 0 && function_exists('is_ext_installed') && is_ext_installed()) {
            $sq = db_query(
                "select r.id from app_ext_smart_input_rules r, app_ext_modules m " .
                "where r.modules_id = m.id and m.module = 'google_autocomplete' and r.fields_id = " . (int)$a['src_id'] . " limit 1"
            );
            if (db_fetch_array($sq)) $a['smart_input_rule'] = true;
        }

        // Form placement: core excluded this field from forms; after conversion
        // it appears at this position.
        $a['placement'] = (isset($field['forms_tabs_id']) ? 'tab ' . $field['forms_tabs_id'] : '');

        $a['eligible'] = (count($a['reasons']) === 0);
        return $a;
    }

    /**
     * Convert one eligible field in place. Returns
     * array{ok:bool, message:string, rows_rewritten:int}.
     */
    public static function convert_field($fields_id, $user_id)
    {
        $field = db_find('app_fields', (int)$fields_id);
        if (!isset($field['id'])) {
            return array('ok' => false, 'message' => 'Field not found', 'rows_rewritten' => 0);
        }
        if ($field['type'] === 'fieldtype_unitas_location') {
            return array('ok' => true, 'message' => 'Already converted; skipped', 'rows_rewritten' => 0);
        }

        $a = self::assess($field);
        if (!$a['eligible']) {
            return array('ok' => false, 'message' => 'Not eligible: ' . implode('; ', $a['reasons']), 'rows_rewritten' => 0);
        }

        $eid = (int)$field['entities_id'];
        $fid = (int)$field['id'];
        $cfg = new fields_types_cfg($field['configuration']);

        // 1. Swap type + configuration. Building the configuration from scratch
        //    drops api_key, address_pattern and every GeliosSoft key.
        $new_cfg = json_encode(array(
            'source_field_id'     => (string)$a['src_id'],
            'enable_autocomplete' => 'yes',
            'form_map_preview'    => 'yes',
            'map_width'           => ((string)$cfg->get('map_width') !== '' ? (string)$cfg->get('map_width') : '470px'),
            'map_height'          => ((string)$cfg->get('map_height') !== '' ? (string)$cfg->get('map_height') : '470px'),
            'zoom'                => ((string)$cfg->get('zoom') !== '' ? (string)$cfg->get('zoom') : '11'),
        ));
        db_query(
            "update app_fields set type = 'fieldtype_unitas_location', configuration = '" . db_input($new_cfg) . "' " .
            "where id = " . $fid
        );

        // 2. Widen the column to the type new Unitas fields get (P8).
        db_query("alter table app_entity_{$eid} modify field_{$fid} TEXT NOT NULL");

        // 3. Rewrite stored legacy values to the 4-part format.
        $rewritten = self::rewrite_values($eid, $fid);

        // 4. Migration log.
        db_perform('app_unitas_location_migration_log', array(
            'fields_id'      => $fid,
            'entities_id'    => $eid,
            'converted_at'   => date('Y-m-d H:i:s'),
            'converted_by'   => (int)$user_id,
            'rows_rewritten' => $rewritten,
            'notes'          => 'source_field_id=' . $a['src_id'],
        ));

        // 5. Refresh the P12 endpoint-block flag.
        if (class_exists('unitas_ext_installer')) {
            unitas_ext_installer::refresh_core_gmap_flag();
        }

        return array('ok' => true, 'message' => 'Converted', 'rows_rewritten' => $rewritten);
    }

    /**
     * Rewrite legacy 3-part values (url-encoded address) to the 4-part format
     * in batches. Values already carrying a valid 4-part status are left
     * untouched, so a re-run changes nothing.
     */
    public static function rewrite_values($entities_id, $fields_id)
    {
        $eid = (int)$entities_id;
        $fid = (int)$fields_id;
        $count = 0;
        $last = 0;

        while (true) {
            $q = db_query("select id, field_{$fid} as v from app_entity_{$eid} where id > " . $last . " order by id limit 500");
            $rows = array();
            while ($r = db_fetch_array($q)) $rows[] = $r;
            if (!$rows) break;

            foreach ($rows as $r) {
                $last = (int)$r['id'];
                $raw = (string)$r['v'];
                if ($raw === '') continue;

                $parts = explode("\t", $raw);
                if (count($parts) >= 4 && in_array(trim($parts[3]), unitas_location_value::STATUSES, true)) {
                    continue; // already 4-part
                }

                $lat = isset($parts[0]) ? $parts[0] : '';
                $lng = isset($parts[1]) ? $parts[1] : '';
                // Core stored the address url-encoded; ours is plain text.
                $addr = unitas_location_value::normalize_address(urldecode(isset($parts[2]) ? $parts[2] : ''));

                if (unitas_location_value::is_valid_point($lat, $lng)) {
                    $new = unitas_location_value::format((float)$lat, (float)$lng, $addr, 'geocoded');
                } else {
                    // No usable coordinates: leave empty so Re-geocode finds it.
                    $new = '';
                }

                if ($new !== $raw) {
                    db_query("update app_entity_{$eid} set field_{$fid} = '" . db_input($new) . "' where id = " . (int)$r['id']);
                    $count++;
                }
            }

            if (count($rows) < 500) break;
        }

        return $count;
    }

    /** All converted/native location fields, joined to entity names. */
    public static function location_fields()
    {
        $rows = array();
        $q = db_query(
            "select f.*, e.name as entity_name from app_fields f " .
            "left join app_entities e on e.id = f.entities_id " .
            "where f.type = 'fieldtype_unitas_location' order by e.name, f.name"
        );
        while ($r = db_fetch_array($q)) $rows[] = $r;
        return $rows;
    }
}
