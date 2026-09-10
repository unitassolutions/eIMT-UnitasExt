<?php
/**
 * UNITAS Extension - Google Maps Platform key resolver.
 *
 * The single source of truth for the two Google keys (plan section 4):
 *   - browser key : website-restricted, public by design, printed into HTML.
 *   - server key  : IP-restricted, kept secret, NEVER rendered into HTML/JS,
 *                   NEVER logged, NEVER exported.
 *
 * Each key can be supplied by a constant in config/server.php (preferred for
 * stricter deployments) or by the Unitas configuration row. Constants win.
 *
 * Constants use a UNITAS_ prefix (not CFG_) so they cannot collide with
 * Rukovoditel app_configuration constants.
 *
 * See plan sections 4.1, 4.2, 7.1.
 */

// unitas_map_config lives in a module helper that is not auto-loaded in cron
// and REST API contexts, so pull it in by a __DIR__-relative path.
require_once __DIR__ . '/../../modules/map_configuration/helpers/map_config.php';

class unitas_google_keys
{
    /**
     * Website-restricted browser key. Safe to print into HTML.
     * Source order: UNITAS_GOOGLE_BROWSER_KEY constant, then the config row.
     */
    public static function browser()
    {
        if (defined('UNITAS_GOOGLE_BROWSER_KEY') && UNITAS_GOOGLE_BROWSER_KEY !== '') {
            return (string)UNITAS_GOOGLE_BROWSER_KEY;
        }
        $cfg = unitas_map_config::get();
        return (string)($cfg['google_map_api_key'] ?? '');
    }

    /**
     * IP-restricted server key. Server-side only.
     *
     * Only unitas_geocoder and the config-screen server test may call this.
     * It must never reach a browser, a log line, an error message or an export.
     *
     * Source order: UNITAS_GOOGLE_SERVER_KEY constant, then the config row.
     */
    public static function server()
    {
        if (defined('UNITAS_GOOGLE_SERVER_KEY') && UNITAS_GOOGLE_SERVER_KEY !== '') {
            return (string)UNITAS_GOOGLE_SERVER_KEY;
        }
        $cfg = unitas_map_config::get();
        return (string)($cfg['google_server_api_key'] ?? '');
    }

    /**
     * Where the server key comes from: 'constant', 'database' or 'none'.
     * Used by the configuration screen to describe the current state.
     */
    public static function server_source()
    {
        if (defined('UNITAS_GOOGLE_SERVER_KEY') && UNITAS_GOOGLE_SERVER_KEY !== '') {
            return 'constant';
        }
        $cfg = unitas_map_config::get();
        return ((string)($cfg['google_server_api_key'] ?? '') !== '') ? 'database' : 'none';
    }

    /**
     * A safe-to-display hint for the configured server key: the last 4
     * characters only, or '' when no key is configured. Never the full key.
     */
    public static function server_hint()
    {
        $key = self::server();
        if ($key === '') return '';
        return "\xE2\x80\xA6" . substr($key, -4); // horizontal ellipsis + last 4
    }
}
