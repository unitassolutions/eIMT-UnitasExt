<?php
/**
 * UNITAS Extension - shared Google Maps JS loader (server side).
 *
 * Emits, at most once per PHP request, the browser configuration and the small
 * loader script (js/google/unitas_gmaps_loader.js) that every Unitas map
 * consumer uses to load Maps JS exactly once with the browser key. Only the
 * public browser key is ever printed; the server key never appears here.
 *
 * See plan section 7.5.
 */

require_once __DIR__ . '/unitas_google_keys.php';

class unitas_google_loader
{
    /** True once emit() has produced its output this request. */
    private static $emitted = false;

    /**
     * Return the loader HTML (config + script tag), or '' if it has already
     * been emitted this request. When no browser key is configured it returns
     * an admin-only warning instead (once), and nothing for other users.
     *
     * Setting the core $is_google_map_script flag prevents any remaining core
     * map field from loading a second copy of Maps JS on the same page.
     */
    public static function emit()
    {
        if (self::$emitted) return '';
        self::$emitted = true;

        global $is_google_map_script, $app_user;
        $is_google_map_script = true;

        $key = unitas_google_keys::browser();
        if ($key === '') {
            $is_admin = (isset($app_user['group_id']) && (int)$app_user['group_id'] === 0);
            if ($is_admin) {
                return '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> '
                     . 'Google Maps browser key not configured. Go to UNITAS Extension &gt; Extension Configuration &gt; Google Map.'
                     . '</div>';
            }
            return '';
        }

        $version = defined('PLUGIN_UNITAS_EXT_VERSION') ? PLUGIN_UNITAS_EXT_VERSION : '1.6.0';
        $config_json = json_encode(
            self::build_config($key),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        $html  = '<script>window.UNITAS_GMAPS = window.UNITAS_GMAPS || ' . $config_json . ';</script>';
        $html .= '<script src="plugins/unitas_ext/js/google/unitas_gmaps_loader.js?v=' . rawurlencode($version) . '"></script>';

        return $html;
    }

    /** Build the browser config object. Contains the browser key only. */
    private static function build_config($key)
    {
        $cfg = unitas_map_config::get();

        // Unique, non-empty map IDs for the dynamic bootstrap loader.
        $map_ids = array();
        foreach (array($cfg['map_style_light'] ?? '', $cfg['map_style_dark'] ?? '') as $mid) {
            $mid = trim((string)$mid);
            if ($mid !== '' && !in_array($mid, $map_ids, true)) $map_ids[] = $mid;
        }

        // Region codes for autocomplete/geocoding bias.
        $regions = array();
        foreach (explode(',', (string)($cfg['autocomplete_region_codes'] ?? 'us')) as $c) {
            $c = strtolower(trim($c));
            if (preg_match('/^[a-z]{2}$/', $c)) $regions[] = $c;
        }

        $center = null;
        if (is_numeric($cfg['default_lat'] ?? null) && is_numeric($cfg['default_lng'] ?? null)) {
            $center = array('lat' => (float)$cfg['default_lat'], 'lng' => (float)$cfg['default_lng']);
        }

        $pin_url = function_exists('url_for') ? url_for('unitas_ext/location/pin') : '';

        return array(
            'browserKey'   => $key,
            'mapIds'       => $map_ids,
            'mapId'        => array(
                'light' => trim((string)($cfg['map_style_light'] ?? '')),
                'dark'  => trim((string)($cfg['map_style_dark'] ?? '')),
            ),
            'defaultTheme' => in_array(($cfg['default_theme'] ?? 'auto'), array('auto', 'light', 'dark'), true)
                                ? $cfg['default_theme'] : 'auto',
            'defaultCenter'=> $center,
            'regionCodes'  => $regions,
            'biasRadiusM'  => (int)($cfg['autocomplete_bias_radius_m'] ?? 50000),
            'pinUrl'       => $pin_url,
            'strings'      => array(
                'loadError' => 'Map could not be loaded.',
            ),
        );
    }
}
