<?php
/**
 * UNITAS Extension - address autocomplete rule map.
 *
 * Resolves the set of text-field ids that should receive the Places (New)
 * address autocomplete widget: the union of active standalone rules
 * (app_unitas_address_autocomplete_rules) and the source fields of location
 * fields that have autocomplete enabled (Phase 3). Only fields that still
 * exist and are still fieldtype_input are returned.
 *
 * See plan section 7.7.
 */
class unitas_address_autocomplete_rules
{
    /** Per-request cache of the resolved, validated field id list. */
    private static $ids_cache = null;

    /** Per-request cache of whether the rules table exists. */
    private static $table_ok = null;

    /**
     * Whether the rules table exists. Guards every query against a missing
     * table (e.g. a migration that has not yet run) so a plugin sub-feature
     * can never take down the whole application. Cached per request.
     */
    private static function table_ready()
    {
        if (self::$table_ok !== null) return self::$table_ok;
        $r = db_query("SHOW TABLES LIKE 'app_unitas_address_autocomplete_rules'");
        self::$table_ok = ($r && db_num_rows($r) > 0);
        return self::$table_ok;
    }

    /**
     * Field ids that should get the autocomplete widget.
     * @return int[]
     */
    public static function field_ids()
    {
        if (self::$ids_cache !== null) return self::$ids_cache;

        $candidates = array();

        // 1. Active standalone rules (skipped if the table is not present yet).
        if (self::table_ready()) {
            $q = db_query("select fields_id from app_unitas_address_autocomplete_rules where is_active = 1");
            while ($r = db_fetch_array($q)) {
                $candidates[(int)$r['fields_id']] = true;
            }
        }

        // 2. Location fields with autocomplete enabled -> their source text field.
        foreach (self::location_bound_field_ids() as $fid) {
            $candidates[(int)$fid] = true;
        }

        if (!$candidates) {
            return self::$ids_cache = array();
        }

        // Keep only ids that still exist and are still plain text inputs.
        $in = implode(',', array_map('intval', array_keys($candidates)));
        $valid = array();
        $vq = db_query("select id from app_fields where id in ($in) and type = 'fieldtype_input'");
        while ($v = db_fetch_array($vq)) {
            $valid[] = (int)$v['id'];
        }

        return self::$ids_cache = $valid;
    }

    /**
     * Source field ids contributed by location fields (fieldtype_unitas_location)
     * that have enable_autocomplete on. Empty until the location field ships
     * (Phase 3). Not validated here; field_ids() validates the union.
     * @return int[]
     */
    public static function location_bound_field_ids()
    {
        $ids = array();
        if (!class_exists('fields_types_cfg')) return $ids;

        $q = db_query("select configuration from app_fields where type = 'fieldtype_unitas_location'");
        while ($r = db_fetch_array($q)) {
            $cfg = new fields_types_cfg($r['configuration']);
            // Default on: only an explicit 'no' disables autocomplete.
            if ((string)$cfg->get('enable_autocomplete') === 'no') continue;
            $src = (int)$cfg->get('source_field_id');
            if ($src > 0) $ids[] = $src;
        }
        return $ids;
    }

    /**
     * All standalone rules joined to field/entity names, for the admin list.
     * Rules whose field or entity no longer exists are still listed (flagged),
     * so the admin can clean them up.
     * @return array[]
     */
    public static function all_rules()
    {
        if (!self::table_ready()) return array();

        $rows = array();
        $q = db_query(
            "select r.*, f.name as field_name, f.type as field_type, e.name as entity_name " .
            "from app_unitas_address_autocomplete_rules r " .
            "left join app_fields f on f.id = r.fields_id " .
            "left join app_entities e on e.id = r.entities_id " .
            "order by e.name, f.name"
        );
        while ($row = db_fetch_array($q)) {
            $rows[] = $row;
        }
        return $rows;
    }
}
