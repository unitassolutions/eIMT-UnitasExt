<?php
/**
 * UNITAS Extension — Installer
 * 
 * Handles fresh installs and version migrations.
 * Pattern matches the Rukovoditel Extension installer:
 *   - CFG_PLUGIN_UNITAS_EXT_INSTALLED in app_configuration tracks install state
 *   - CFG_PLUGIN_UNITAS_EXT_DB_VERSION in app_configuration tracks schema version
 *   - All tables use CREATE TABLE IF NOT EXISTS (safe to re-run)
 *   - Migrations use column existence checks before ALTER TABLE
 */

class unitas_ext_installer
{
    /**
     * Check if the plugin is installed.
     * Rukovoditel core loads app_configuration values as PHP constants.
     */
    static function is_installed()
    {
        return defined('CFG_PLUGIN_UNITAS_EXT_INSTALLED');
    }

    /**
     * Get the currently installed plugin version.
     */
    static function get_db_version()
    {
        if (defined('CFG_PLUGIN_UNITAS_EXT_DB_VERSION')) {
            return CFG_PLUGIN_UNITAS_EXT_DB_VERSION;
        }
        return '0.0.0';
    }

    /**
     * Schema version the code expects (integer, bumped per migration batch).
     * Independent of the plugin's semantic version so that several commits
     * sharing one plugin version each still trigger their own migrations.
     */
    static function schema_version()
    {
        return defined('PLUGIN_UNITAS_EXT_SCHEMA_VERSION') ? (int)PLUGIN_UNITAS_EXT_SCHEMA_VERSION : 0;
    }

    /**
     * Schema version currently recorded in the database.
     */
    static function get_db_schema_version()
    {
        if (defined('CFG_PLUGIN_UNITAS_EXT_SCHEMA_VERSION')) {
            return (int)CFG_PLUGIN_UNITAS_EXT_SCHEMA_VERSION;
        }
        return 0;
    }

    /**
     * Run full installation: create all tables + set config flags.
     */
    static function install()
    {
        // Create all tables
        self::create_tables();

        // Run all migrations
        self::run_migrations();

        // Patch core files to register custom field type
        self::patch_core_files();

        // Mark as installed
        self::set_config('CFG_PLUGIN_UNITAS_EXT_INSTALLED', '1');
        self::set_config('CFG_PLUGIN_UNITAS_EXT_DB_VERSION', PLUGIN_UNITAS_EXT_VERSION);
        self::set_config('CFG_PLUGIN_UNITAS_EXT_SCHEMA_VERSION', (string)self::schema_version());

        return true;
    }

    /**
     * Run migrations for version and/or schema upgrades. run_migrations() is
     * fully idempotent, so re-running it is safe.
     */
    static function upgrade()
    {
        self::run_migrations();
        self::patch_core_files();
        self::set_config('CFG_PLUGIN_UNITAS_EXT_DB_VERSION', PLUGIN_UNITAS_EXT_VERSION);
        self::set_config('CFG_PLUGIN_UNITAS_EXT_SCHEMA_VERSION', (string)self::schema_version());
    }

    /**
     * Re-apply the core integration shims without a version/schema change.
     *
     * A Rukovoditel core update overwrites the patched core files and silently
     * removes our shims, but leaves the plugin and DB versions untouched — so
     * needs_upgrade() stays false and neither install() nor upgrade() re-run.
     * This is the repair path the admin banner and install page point at.
     *
     * Returns the patch_core_files() results (patched / skipped / errors) so the
     * caller can surface exactly what happened, including a core version whose
     * anchors no longer match. It also resets OpCache when available, so the
     * freshly re-written core files take effect on the next request rather than
     * waiting for a manual PHP-FPM restart.
     *
     * @return array{patched:string[], skipped:string[], errors:string[]}
     */
    static function repair()
    {
        $results = self::patch_core_files();

        // Keep the cached core-Google-map-fields flag in step with reality.
        self::refresh_core_gmap_flag();

        // Make the rewritten core files live immediately where the host allows it.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return $results;
    }

    /**
     * Upgrade is needed when the plugin version advanced OR the schema version
     * advanced. The schema check catches new migrations added under an
     * unchanged plugin version.
     */
    static function needs_upgrade()
    {
        return version_compare(PLUGIN_UNITAS_EXT_VERSION, self::get_db_version(), '>')
            || (self::schema_version() > self::get_db_schema_version());
    }

    /**
     * Create all plugin tables. Uses IF NOT EXISTS so safe to re-run.
     */
    private static function create_tables()
    {
        $sql = "
CREATE TABLE IF NOT EXISTS `app_ext_unitas_entity_buttons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_id` int(11) NOT NULL,
  `button_title` varchar(255) NOT NULL,
  `button_type` varchar(20) NOT NULL DEFAULT 'url',
  `report_id` int(11) DEFAULT NULL,
  `external_url` text DEFAULT NULL,
  `button_icon` varchar(50) DEFAULT NULL,
  `button_color` varchar(50) NOT NULL DEFAULT 'btn-primary',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entity_id` (`entity_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_unitas_map_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entities_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `fields_id` int(11) NOT NULL,
  `users_groups` text NOT NULL,
  `in_menu` tinyint(1) NOT NULL,
  `background` int(11) NOT NULL,
  `fields_in_popup` text NOT NULL,
  `display_sidebar` tinyint(1) NOT NULL,
  `fields_in_sidebar` text NOT NULL,
  `sidebar_width` varchar(16) NOT NULL,
  `zoom` tinyint(1) NOT NULL,
  `latlng` varchar(16) NOT NULL,
  `is_public_access` tinyint(1) NOT NULL,
  `use_form_map_settings` tinyint(1) NOT NULL DEFAULT 0,
  `use_form_settings` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_entities_id` (`entities_id`),
  KEY `idx_fields_id` (`fields_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_unitas_map_reports_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_map_api_key` varchar(255) NOT NULL,
  `map_style_light` varchar(64) NOT NULL,
  `map_style_dark` varchar(64) NOT NULL,
  `default_theme` varchar(10) DEFAULT 'auto',
  `default_lat` varchar(32) DEFAULT NULL,
  `default_lng` varchar(32) DEFAULT NULL,
  `default_zoom` int(11) DEFAULT 8,
  `waze_geocoding_token` varchar(255) NOT NULL DEFAULT '',
  `waze_region` varchar(8) NOT NULL DEFAULT 'na',
  `waze_feed_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `waze_feed_key` varchar(64) NOT NULL DEFAULT '',
  `waze_feed_window` int(11) NOT NULL DEFAULT 15,
  `waze_feed_config` text NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_unitas_pivot_map_reports` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `users_groups` text NOT NULL,
  `is_public_access` tinyint(1) NOT NULL DEFAULT 0,
  `in_menu` tinyint(1) NOT NULL,
  `zoom` tinyint(1) NOT NULL,
  `latlng` varchar(16) NOT NULL,
  `display_legend` tinyint(1) NOT NULL,
  `display_sidebar` tinyint(1) NOT NULL,
  `sidebar_width` varchar(16) NOT NULL,
  `map_type` varchar(20) NOT NULL DEFAULT 'google',
  `layout` varchar(16) NOT NULL DEFAULT 'classic',
  `use_form_map_settings` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `app_unitas_pivot_map_reports_entities` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reports_id` int(11) NOT NULL,
  `entities_id` int(11) NOT NULL,
  `fields_id` int(11) NOT NULL,
  `background` int(11) NOT NULL,
  `fields_in_popup` text NOT NULL,
  `fields_in_sidebar` text NOT NULL,
  `marker_color` varchar(16) NOT NULL,
  `marker_icon` varchar(255) NOT NULL,
  `legend_label` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_entities_id` (`entities_id`),
  KEY `idx_fields_id` (`fields_id`),
  KEY `idx_reports_id` (`reports_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

        foreach (explode(';', $sql) as $query) {
            $query = trim($query);
            if (strlen($query) > 0) {
                db_query($query);
            }
        }

        // Insert default map config if table is empty
        $check = db_query("SELECT COUNT(*) as total FROM app_unitas_map_reports_config");
        $row = db_fetch_array($check);
        if ($row['total'] == 0) {
            db_query("INSERT INTO app_unitas_map_reports_config (id, google_map_api_key, map_style_light, map_style_dark, default_theme, default_lat, default_lng, default_zoom, waze_geocoding_token, waze_region, waze_feed_enabled, waze_feed_key, waze_feed_window, waze_feed_config) VALUES (1, '', '', '', 'auto', '35.7596', '-79.0193', 8, '', 'na', 0, '', 15, '')");
        }
    }

    /**
     * Run version-specific migrations. Each migration checks column/table
     * existence before running, so they are safe to re-run.
     */
    private static function run_migrations()
    {
        // v1.0.3: Add legend_label column to pivot map report entities
        if (!self::column_exists('app_unitas_pivot_map_reports_entities', 'legend_label')) {
            db_query("ALTER TABLE app_unitas_pivot_map_reports_entities ADD COLUMN legend_label varchar(255) NOT NULL DEFAULT '' AFTER marker_icon");
        }

        // v1.2.0: Waze Integration — reverse geocoding token + API region
        if (!self::column_exists('app_unitas_map_reports_config', 'waze_geocoding_token')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN waze_geocoding_token varchar(255) NOT NULL DEFAULT '' AFTER default_zoom");
        }
        if (!self::column_exists('app_unitas_map_reports_config', 'waze_region')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN waze_region varchar(8) NOT NULL DEFAULT 'na' AFTER waze_geocoding_token");
        }

        // v1.3.0: Waze CIFS closure feed — enabled flag, URL key, rolling window, field mapping
        if (!self::column_exists('app_unitas_map_reports_config', 'waze_feed_enabled')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN waze_feed_enabled tinyint(1) NOT NULL DEFAULT 0 AFTER waze_region");
        }
        if (!self::column_exists('app_unitas_map_reports_config', 'waze_feed_key')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN waze_feed_key varchar(64) NOT NULL DEFAULT '' AFTER waze_feed_enabled");
        }
        if (!self::column_exists('app_unitas_map_reports_config', 'waze_feed_window')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN waze_feed_window int(11) NOT NULL DEFAULT 15 AFTER waze_feed_key");
        }
        if (!self::column_exists('app_unitas_map_reports_config', 'waze_feed_config')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN waze_feed_config text NULL AFTER waze_feed_window");
        }

        // v1.5.0: Pivot map v2 layout — opt-in modern renderer per report
        if (!self::column_exists('app_unitas_pivot_map_reports', 'layout')) {
            db_query("ALTER TABLE app_unitas_pivot_map_reports ADD COLUMN layout varchar(16) NOT NULL DEFAULT 'classic' AFTER map_type");
        }

        // v1.6.0: Google key lockdown — separate server key, autocomplete
        // settings, and last-geocode-error tracking on the shared config row.
        if (!self::column_exists('app_unitas_map_reports_config', 'google_server_api_key')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN google_server_api_key varchar(255) NOT NULL DEFAULT '' AFTER google_map_api_key");
        }
        if (!self::column_exists('app_unitas_map_reports_config', 'autocomplete_region_codes')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN autocomplete_region_codes varchar(64) NOT NULL DEFAULT 'us' AFTER waze_feed_config");
        }
        if (!self::column_exists('app_unitas_map_reports_config', 'autocomplete_bias_radius_m')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN autocomplete_bias_radius_m int(11) NOT NULL DEFAULT 50000 AFTER autocomplete_region_codes");
        }
        if (!self::column_exists('app_unitas_map_reports_config', 'geocode_last_status')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN geocode_last_status varchar(32) NOT NULL DEFAULT '' AFTER autocomplete_bias_radius_m");
        }
        if (!self::column_exists('app_unitas_map_reports_config', 'geocode_last_error')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN geocode_last_error varchar(255) NOT NULL DEFAULT '' AFTER geocode_last_status");
        }
        if (!self::column_exists('app_unitas_map_reports_config', 'geocode_last_error_at')) {
            db_query("ALTER TABLE app_unitas_map_reports_config ADD COLUMN geocode_last_error_at datetime NULL DEFAULT NULL AFTER geocode_last_error");
        }

        // v1.6.0: standalone address autocomplete rules (attach Places autocomplete
        // to a plain text field). Safe/idempotent via IF NOT EXISTS.
        db_query("
CREATE TABLE IF NOT EXISTS `app_unitas_address_autocomplete_rules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entities_id` int(10) UNSIGNED NOT NULL,
  `fields_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fields_id` (`fields_id`),
  KEY `idx_entities_id` (`entities_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // v1.6.7 (schema 2): log of core Google map field conversions (plan 8.2).
        db_query("
CREATE TABLE IF NOT EXISTS `app_unitas_location_migration_log` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fields_id` int(10) UNSIGNED NOT NULL,
  `entities_id` int(10) UNSIGNED NOT NULL,
  `converted_at` datetime NOT NULL,
  `converted_by` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rows_rewritten` int(11) NOT NULL DEFAULT 0,
  `notes` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fields_id` (`fields_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Check if a column exists in a table.
     */
    private static function column_exists($table, $column)
    {
        $result = db_query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return (db_num_rows($result) > 0);
    }

    /**
     * Set a configuration value in app_configuration (Rukovoditel config table).
     * Creates the row if it does not exist, updates if it does.
     */
    private static function set_config($name, $value)
    {
        $check = db_query("SELECT id FROM app_configuration WHERE configuration_name = '" . db_input($name) . "'");
        if (db_num_rows($check) > 0) {
            db_query("UPDATE app_configuration SET configuration_value = '" . db_input($value) . "' WHERE configuration_name = '" . db_input($name) . "'");
        } else {
            db_perform('app_configuration', array(
                'configuration_name'  => $name,
                'configuration_value' => $value
            ));
        }
    }

    /**
     * Whether any core Google map fields still exist (P12). Cached in
     * app_configuration so the check runs once, not on every request; the
     * cached constant is loaded by core at bootstrap on subsequent requests.
     * The migration tool and location tools page call refresh_core_gmap_flag()
     * to update it.
     */
    static function core_gmap_fields_present()
    {
        if (defined('CFG_UNITAS_CORE_GMAP_FIELDS_PRESENT')) {
            return CFG_UNITAS_CORE_GMAP_FIELDS_PRESENT === '1';
        }
        // Not cached yet: compute now, store for next request, use this value now.
        return self::refresh_core_gmap_flag();
    }

    /**
     * Recompute and store the core-Google-map-fields presence flag.
     * @return bool present
     */
    static function refresh_core_gmap_flag()
    {
        $q = db_query("select count(*) as n from app_fields where type in ('fieldtype_google_map','fieldtype_google_map_directions','fieldtype_google_map_nested')");
        $r = db_fetch_array($q);
        $present = ($r && (int)$r['n'] > 0);
        self::set_config('CFG_UNITAS_CORE_GMAP_FIELDS_PRESENT', $present ? '1' : '0');
        return $present;
    }

    // ── Core File Patching ──────────────────────────────────────────────────

    /**
     * Check if core files have the required patches for custom field types.
     */
    static function core_patches_applied()
    {
        $h = self::shim_health();
        return $h['all_ok'];
    }

    /**
     * Per-shim health check (plan section 6.6). Reports which core integration
     * shims are currently present, so the install/config screens and the admin
     * banner can flag a core update that silently removed one.
     *
     * @return array{s1:bool, s2:bool, menu:bool, all_ok:bool}
     *   s1   = field type registration shim in fields_types::get_choices()
     *   s2   = save hook shim in fields_types::update_items_fields()
     *   menu = entities_menu.php map report menu shims
     */
    static function shim_health()
    {
        $types_file = 'includes/classes/fields_types.php';
        $menu_file  = 'includes/classes/model/entities_menu.php';

        $s1 = $s2 = $menu = false;

        if (file_exists($types_file)) {
            $types_content = file_get_contents($types_file);
            $s1 = (strpos($types_content, 'UNITAS_EXT_SHIM:field_types') !== false);
            $s2 = (strpos($types_content, 'UNITAS_EXT_SHIM:update_items_fields') !== false);
        }
        if (file_exists($menu_file)) {
            $menu_content = file_get_contents($menu_file);
            $menu = (strpos($menu_content, 'unitas_ext_menu_build_item($reports_list') !== false);
        }

        return array(
            's1'     => $s1,
            's2'     => $s2,
            'menu'   => $menu,
            'all_ok' => ($s1 && $s2 && $menu),
        );
    }

    /**
     * Patch core Rukovoditel files with the plugin integration shims.
     *
     * Two generic, marker-delimited shims (plan section 6) replace the old
     * per-type edits:
     *   S1  fields_types::get_choices()        -> unitas_ext_core_field_types()
     *   S2  fields_types::update_items_fields() -> unitas_ext_core_update_items_fields()
     * A third edit (unchanged) lets Unitas map reports appear in the entity
     * menu configuration.
     *
     * Each shim is idempotent, refuses to patch unless its anchor occurs
     * exactly once, and calls a plugin function so all logic stays in the
     * plugin. Legacy pre-1.6.0 edits are retired here too.
     *
     * Safe to re-run. Must be re-applied after Rukovoditel core updates.
     */
    static function patch_core_files()
    {
        $results = array('patched' => array(), 'skipped' => array(), 'errors' => array());

        $types_file = 'includes/classes/fields_types.php';

        // Shim S1: register Unitas field types in the Maps group. Inserted
        // immediately BEFORE the unique foreach in get_choices(). %ANCHOR% in
        // the template is replaced by the anchor itself.
        self::install_shim(
            $types_file,
            'S1',
            'UNITAS_EXT_SHIM:field_types',
            'foreach ($fieldtypes as $group => $fields)',
            "// UNITAS_EXT_SHIM:field_types (managed by plugins/unitas_ext/install.php, do not edit)\n"
                . "        if (function_exists('unitas_ext_core_field_types')) unitas_ext_core_field_types(\$fieldtypes);\n\n"
                . "        %ANCHOR%",
            $results
        );

        // Shim S2: run Unitas post-save updates on every save path. Inserted
        // immediately AFTER the unique core google_map save call in
        // update_items_fields().
        self::install_shim(
            $types_file,
            'S2',
            'UNITAS_EXT_SHIM:update_items_fields',
            'fieldtype_google_map::update_items_fields($current_entity_id, $item_id, $item_info);',
            "%ANCHOR%\n"
                . "        // UNITAS_EXT_SHIM:update_items_fields (managed by plugins/unitas_ext/install.php, do not edit)\n"
                . "        if (function_exists('unitas_ext_core_update_items_fields')) unitas_ext_core_update_items_fields(\$current_entity_id, \$item_id, \$item_info);",
            $results
        );

        // Retire the pre-1.6.0 per-type edits (plan section 6.5). The plugin
        // application_core.php now loads the field type classes and shim S1
        // registers them, so the old edits are removed. Their absence is
        // recorded as already-removed, not an error.
        self::retire_legacy_patches($results);

        // Patch 3: entities_menu.php — let Unitas map reports be placed in the
        // main menu (Application Structure > Entities > Menu). Core has no hook
        // for this, so two one-line shims are injected that call functions
        // defined in application_top.php. All logic stays in the plugin, so
        // only a core update (not a plugin change) can require re-patching.
        $menu_file = 'includes/classes/model/entities_menu.php';
        if (file_exists($menu_file)) {
            $content = file_get_contents($menu_file);

            // Current shim signature. v1.5.1 injected switch cases instead,
            // which depended on matching the right switch in the file; the
            // block below migrates that older form automatically.
            if (strpos($content, 'unitas_ext_menu_build_item($reports_list') === false) {

                // Remove the v1.5.1 case-based shim if it is present
                $content = preg_replace(
                    '/\s*case\s+strstr\(\$reports_type,\s*\'unitaspivotmap\'\)\s*:\s*case\s+strstr\(\$reports_type,\s*\'unitasmap\'\)\s*:\s*if\(function_exists\(\'unitas_ext_menu_build_item\'\)\)[^\n]*\n\s*break;\s*/s',
                    "\n                ",
                    $content
                );

                // 3a: contribute our reports to the menu configuration dropdown,
                // injected just before get_reports_choices() returns
                if (strpos($content, 'unitas_ext_menu_reports_choices') === false) {
                    $choices_shim = 'if(function_exists(\'unitas_ext_menu_reports_choices\')) $choices = unitas_ext_menu_reports_choices($choices);';
                    $content = preg_replace(
                        '/(return\s+\$choices;\s*\}\s*static\s+function\s+get_reports_types)/',
                        $choices_shim . "\n\n        \$1",
                        $content,
                        1
                    );
                }

                // 3b: hand the whole saved list to the plugin at the TOP of
                // build_menu, before core loops it. Anchoring on the function
                // signature avoids any dependence on the switch contents or on
                // core matching our prefixes with strstr / str_replace.
                $build_shim = 'if(function_exists(\'unitas_ext_menu_build_item\')) $sub_menu = unitas_ext_menu_build_item($reports_list, $sub_menu);';
                $content = preg_replace(
                    '/(static\s+function\s+build_menu\s*\(\s*\$reports_list\s*,\s*\$sub_menu\s*\)\s*\{\s*global\s+\$app_user;)/',
                    "\$1\n\n        " . $build_shim,
                    $content,
                    1
                );

                if ($content !== null
                    && strpos($content, 'unitas_ext_menu_build_item($reports_list') !== false
                    && strpos($content, 'unitas_ext_menu_reports_choices') !== false) {
                    if (file_put_contents($menu_file, $content) !== false) {
                        $results['patched'][] = 'entities_menu.php';
                    } else {
                        $results['errors'][] = 'entities_menu.php (write failed — check file permissions)';
                    }
                } else {
                    $results['errors'][] = 'entities_menu.php (anchor not found — manual patch needed)';
                }
            } else {
                $results['skipped'][] = 'entities_menu.php (already patched)';
            }
        } else {
            $results['errors'][] = 'entities_menu.php (file not found)';
        }

        return $results;
    }

    /**
     * Idempotently insert a marker-delimited shim into a core file relative to a
     * unique anchor (plan section 6.1). Refuses to patch — and changes nothing —
     * unless the anchor occurs exactly once, so a core rewrite that duplicated
     * or removed the anchor is reported instead of mis-patched. Safe to re-run.
     *
     * @param string $file     core file path
     * @param string $label    short shim id for messages (S1/S2)
     * @param string $marker   unique substring proving the shim is present
     * @param string $anchor   unique anchor text to insert relative to
     * @param string $template replacement text; %ANCHOR% is replaced by $anchor
     * @param array  $results  results accumulator, by reference
     */
    private static function install_shim($file, $label, $marker, $anchor, $template, &$results)
    {
        if (!file_exists($file)) {
            $results['errors'][] = "{$file} ({$label}: file not found)";
            return;
        }

        $content = file_get_contents($file);

        if (strpos($content, $marker) !== false) {
            $results['skipped'][] = "{$file} ({$label}: already applied)";
            return;
        }

        $count = substr_count($content, $anchor);
        if ($count !== 1) {
            $results['errors'][] = "{$file} ({$label}: anchor found {$count} times, expected exactly 1 — not patched)";
            return;
        }

        // %ANCHOR% carries the anchor into the replacement. str_replace does not
        // re-scan inserted text, so a template that repeats the anchor is safe.
        $replacement = str_replace('%ANCHOR%', $anchor, $template);
        $patched = str_replace($anchor, $replacement, $content);

        if (file_put_contents($file, $patched) !== false) {
            $results['patched'][] = "{$file} ({$label})";
        } else {
            $results['errors'][] = "{$file} ({$label}: write failed — check file permissions)";
        }
    }

    /**
     * Remove the pre-1.6.0 core edits (plan section 6.5):
     *   - the geometry require injected into core includes/application_core.php
     *   - the geometry entry injected into BOTH get_types_excluded_in_email()
     *     and get_choices() in fields_types.php (the old str_replace that also
     *     wrongly excluded geometry from notification emails)
     *
     * Matches only the inserted lines. Not finding them is recorded as
     * already-removed, not an error. Idempotent.
     */
    private static function retire_legacy_patches(&$results)
    {
        // 1. core application_core.php geometry require line
        $core_file = 'includes/application_core.php';
        if (file_exists($core_file)) {
            $content = file_get_contents($core_file);
            $new = preg_replace(
                "/\n[ \t]*require\('plugins\/unitas_ext\/classes\/fieldstypes\/fieldtype_unitas_geometry\.php'\);/",
                '',
                $content
            );
            if ($new !== null && $new !== $content) {
                if (file_put_contents($core_file, $new) !== false) {
                    $results['patched'][] = 'application_core.php (legacy require removed)';
                } else {
                    $results['errors'][] = 'application_core.php (legacy require removal write failed)';
                }
            } else {
                $results['skipped'][] = 'application_core.php (no legacy require)';
            }
        }

        // 2. fields_types.php geometry array entries (both functions)
        $types_file = 'includes/classes/fields_types.php';
        if (file_exists($types_file)) {
            $content = file_get_contents($types_file);
            $new = preg_replace(
                "/\n[ \t]*'fieldtype_unitas_geometry',/",
                '',
                $content
            );
            if ($new !== null && $new !== $content) {
                if (file_put_contents($types_file, $new) !== false) {
                    $results['patched'][] = 'fields_types.php (legacy geometry entries removed)';
                } else {
                    $results['errors'][] = 'fields_types.php (legacy geometry removal write failed)';
                }
            } else {
                $results['skipped'][] = 'fields_types.php (no legacy geometry entries)';
            }
        }
    }
}
