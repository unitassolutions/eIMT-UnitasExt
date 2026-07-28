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
     * Get the currently installed DB schema version.
     */
    static function get_db_version()
    {
        if (defined('CFG_PLUGIN_UNITAS_EXT_DB_VERSION')) {
            return CFG_PLUGIN_UNITAS_EXT_DB_VERSION;
        }
        return '0.0.0';
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

        return true;
    }

    /**
     * Run migrations for version upgrades.
     */
    static function upgrade()
    {
        self::run_migrations();
        self::patch_core_files();
        self::set_config('CFG_PLUGIN_UNITAS_EXT_DB_VERSION', PLUGIN_UNITAS_EXT_VERSION);
    }

    /**
     * Check if upgrade is needed (plugin version > DB version).
     */
    static function needs_upgrade()
    {
        return version_compare(PLUGIN_UNITAS_EXT_VERSION, self::get_db_version(), '>');
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

    // ── Core File Patching ──────────────────────────────────────────────────

    /**
     * Check if core files have the required patches for custom field types.
     */
    static function core_patches_applied()
    {
        $core_file = 'includes/application_core.php';
        $types_file = 'includes/classes/fields_types.php';

        if (!file_exists($core_file) || !file_exists($types_file)) return false;

        $core_content = file_get_contents($core_file);
        $types_content = file_get_contents($types_file);

        $has_require = (strpos($core_content, 'fieldtype_unitas_geometry') !== false);
        $has_choice = (strpos($types_content, 'fieldtype_unitas_geometry') !== false);

        return ($has_require && $has_choice);
    }

    /**
     * Patch core Rukovoditel files to register the custom field type.
     * Two minimal changes:
     *   1. application_core.php: add require for our field type class
     *   2. fields_types.php: add our type to the Maps dropdown group
     * 
     * Safe to re-run. Checks if patches already exist before applying.
     * Must be re-applied after Rukovoditel core updates.
     */
    static function patch_core_files()
    {
        $results = array('patched' => array(), 'skipped' => array(), 'errors' => array());

        // Patch 1: application_core.php — add require for our field type
        $core_file = 'includes/application_core.php';
        if (file_exists($core_file)) {
            $content = file_get_contents($core_file);
            if (strpos($content, 'fieldtype_unitas_geometry') === false) {
                // Find the last fieldtype require line and add ours after it
                $needle = "require('includes/classes/fieldstypes/fieldtype_google_drive.php');";
                if (strpos($content, $needle) !== false) {
                    $patch = $needle . "\n    require('plugins/unitas_ext/classes/fieldstypes/fieldtype_unitas_geometry.php');";
                    $patched = str_replace($needle, $patch, $content);
                    if (file_put_contents($core_file, $patched) !== false) {
                        $results['patched'][] = 'application_core.php';
                    } else {
                        $results['errors'][] = 'application_core.php (write failed — check file permissions)';
                    }
                } else {
                    $results['errors'][] = 'application_core.php (anchor line not found — manual patch needed)';
                }
            } else {
                $results['skipped'][] = 'application_core.php (already patched)';
            }
        } else {
            $results['errors'][] = 'application_core.php (file not found)';
        }

        // Patch 2: fields_types.php — add to Maps group in get_choices()
        $types_file = 'includes/classes/fields_types.php';
        if (file_exists($types_file)) {
            $content = file_get_contents($types_file);
            if (strpos($content, 'fieldtype_unitas_geometry') === false) {
                // Find the mind_map entry in the Maps group and add ours after it
                $needle = "'fieldtype_mind_map',";
                if (strpos($content, $needle) !== false) {
                    $patch = "'fieldtype_mind_map',\n            'fieldtype_unitas_geometry',";
                    $patched = str_replace($needle, $patch, $content);
                    if (file_put_contents($types_file, $patched) !== false) {
                        $results['patched'][] = 'fields_types.php';
                    } else {
                        $results['errors'][] = 'fields_types.php (write failed — check file permissions)';
                    }
                } else {
                    $results['errors'][] = 'fields_types.php (anchor line not found — manual patch needed)';
                }
            } else {
                $results['skipped'][] = 'fields_types.php (already patched)';
            }
        } else {
            $results['errors'][] = 'fields_types.php (file not found)';
        }

        return $results;
    }
}
