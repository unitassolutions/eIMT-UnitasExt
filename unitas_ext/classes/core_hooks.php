<?php
/**
 * UNITAS Extension - core hook targets.
 *
 * These functions are the plugin-side targets of the two installer-managed
 * shims in Rukovoditel core (plan section 6). All logic lives here so that a
 * core update can only ever require re-applying the one-line shims, never a
 * plugin change.
 *
 * Loaded by plugins/unitas_ext/application_core.php in web, cron and REST API
 * contexts. Both functions are guarded so core keeps working (no fatals) if the
 * plugin is removed from AVAILABLE_PLUGINS and no Unitas field types exist.
 */

if (!function_exists('unitas_ext_core_field_types')) {
    /**
     * Register Unitas field types in the core "Maps" field type group.
     * Called from shim S1, immediately before fields_types::get_choices()
     * loops $fieldtypes.
     *
     * Each class is guarded by class_exists() so a type whose class has not
     * shipped yet (e.g. fieldtype_unitas_location before Phase 3) is simply
     * skipped, and the in_array() guard prevents a duplicate while the legacy
     * hardcoded geometry entry may still be present mid-upgrade.
     *
     * @param array $fieldtypes core field type groups, by reference
     */
    function unitas_ext_core_field_types(array &$fieldtypes)
    {
        if (!defined('TEXT_MAPS')) return;

        $group = TEXT_MAPS;
        if (!isset($fieldtypes[$group]) || !is_array($fieldtypes[$group])) {
            $fieldtypes[$group] = array();
        }

        foreach (array('fieldtype_unitas_geometry', 'fieldtype_unitas_location') as $class) {
            if (class_exists($class) && !in_array($class, $fieldtypes[$group], true)) {
                $fieldtypes[$group][] = $class;
            }
        }
    }
}

if (!function_exists('unitas_ext_core_update_items_fields')) {
    /**
     * Run Unitas post-save field updates for every save path (item form, REST
     * API, imports, recurring tasks, processes, bulk updates). Called from shim
     * S2, immediately after core geocodes its own map fields.
     *
     * Guarded by class_exists() so it is a no-op until the location field type
     * ships (Phase 3). fieldtype_unitas_location::update_items_fields() itself
     * returns immediately when the entity has no fields of its type.
     */
    function unitas_ext_core_update_items_fields($entity_id, $item_id, $item_info)
    {
        if (class_exists('fieldtype_unitas_location')
            && method_exists('fieldtype_unitas_location', 'update_items_fields')) {
            fieldtype_unitas_location::update_items_fields((int)$entity_id, (int)$item_id, $item_info);
        }
    }
}
