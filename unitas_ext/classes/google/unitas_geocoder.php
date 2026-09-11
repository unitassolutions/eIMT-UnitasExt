<?php
/**
 * UNITAS Extension - server-side Geocoding.
 *
 * Turns a normalized address into coordinates using the IP-restricted server
 * key. This is the ONLY component (besides the config-screen server test) that
 * reads the server key. The key never appears in a response, a log line, an
 * exception or an error message.
 *
 * Runs synchronously inside the save hook, so timeouts are short.
 *
 * See plan section 7.3.
 */

require_once __DIR__ . '/unitas_google_keys.php';
require_once __DIR__ . '/../location/unitas_location_value.php';

class unitas_geocoder
{
    /** Geocoding web service endpoint (canonical host, not maps.google.com). */
    const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

    /** Per-request memo of results, keyed by normalized address. */
    private static $memo = array();

    /** Last error written to the config row this process: {status,message,at}. */
    private static $last_error_recorded = null;

    /**
     * Look up an address.
     *
     * @param string $normalized_address address already passed through
     *        unitas_location_value::normalize_address()
     * @return array{status:string, lat:?float, lng:?float, google_status:string, message:string}
     *         status is one of: geocoded | approximate | not_found | error
     */
    public static function lookup($normalized_address)
    {
        $address = (string)$normalized_address;

        if ($address === '') {
            // Callers should not pass empty addresses; no HTTP call.
            return self::result('error', null, null, '', 'Empty address');
        }

        if (isset(self::$memo[$address])) {
            return self::$memo[$address];
        }

        $key = unitas_google_keys::server();
        if ($key === '') {
            $result = self::result('error', null, null, '', 'Server key not configured');
            self::record_error($result);
            return self::$memo[$address] = $result;
        }

        $cfg = unitas_map_config::get();
        $regions = self::region_codes($cfg);

        $params = array(
            'address' => rawurlencode($address),
            'key'     => rawurlencode($key),
        );
        if (!empty($regions[0])) {
            $params['region'] = rawurlencode($regions[0]);
            // Bias hard to one country only when exactly one region is configured.
            if (count($regions) === 1) {
                $params['components'] = rawurlencode('country:' . strtoupper($regions[0]));
            }
        }

        $query = array();
        foreach ($params as $k => $v) $query[] = $k . '=' . $v;
        $url = self::ENDPOINT . '?' . implode('&', $query);

        $result = self::request($url);

        // Only the address (never the key) is memoized inside $result.
        if ($result['status'] === 'error') {
            self::record_error($result);
        }
        return self::$memo[$address] = $result;
    }

    /**
     * Perform the HTTP request and map the response.
     * The URL (which contains the key) is never surfaced in the result.
     */
    private static function request($url)
    {
        if (!function_exists('curl_init')) {
            return self::result('error', null, null, '', 'HTTP client unavailable');
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 6,
        ));
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            // curl_strerror describes the transport error and cannot contain the key.
            return self::result('error', null, null, '', 'Network error: ' . curl_strerror($errno));
        }
        if ($http !== 200) {
            return self::result('error', null, null, '', 'HTTP status ' . $http);
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['status'])) {
            return self::result('error', null, null, '', 'Invalid response');
        }

        $google_status = (string)$data['status'];
        // Google's error_message is safe to surface (it never echoes the key).
        $message = isset($data['error_message']) ? (string)$data['error_message'] : '';

        switch ($google_status) {
            case 'OK':
                return self::map_ok($data, $google_status, $message);

            case 'ZERO_RESULTS':
                return self::result('not_found', null, null, $google_status, $message);

            case 'OVER_DAILY_LIMIT':
            case 'OVER_QUERY_LIMIT':
            case 'REQUEST_DENIED':
            case 'INVALID_REQUEST':
            case 'UNKNOWN_ERROR':
            default:
                return self::result('error', null, null, $google_status,
                    $message !== '' ? $message : $google_status);
        }
    }

    /** Map an OK response, distinguishing precise from approximate matches. */
    private static function map_ok($data, $google_status, $message)
    {
        if (empty($data['results'][0])) {
            return self::result('error', null, null, $google_status, 'No results in OK response');
        }
        $first = $data['results'][0];

        $lat = isset($first['geometry']['location']['lat']) ? $first['geometry']['location']['lat'] : null;
        $lng = isset($first['geometry']['location']['lng']) ? $first['geometry']['location']['lng'] : null;
        if (!unitas_location_value::is_valid_point($lat, $lng)) {
            return self::result('error', null, null, $google_status, 'Missing coordinates');
        }

        $partial = !empty($first['partial_match']);
        $loc_type = isset($first['geometry']['location_type']) ? $first['geometry']['location_type'] : '';
        // Q5 default: only partial_match or APPROXIMATE is "approximate".
        // GEOMETRIC_CENTER, RANGE_INTERPOLATED and ROOFTOP stay "geocoded".
        $status = ($partial || $loc_type === 'APPROXIMATE') ? 'approximate' : 'geocoded';

        return self::result($status, (float)$lat, (float)$lng, $google_status, $message);
    }

    /** Split and clean the configured comma-separated CLDR region codes. */
    private static function region_codes($cfg)
    {
        $raw = isset($cfg['autocomplete_region_codes']) ? (string)$cfg['autocomplete_region_codes'] : 'us';
        $codes = array();
        foreach (explode(',', $raw) as $c) {
            $c = strtolower(trim($c));
            if (preg_match('/^[a-z]{2}$/', $c)) $codes[] = $c;
        }
        return $codes;
    }

    /** Build a normalized result array. */
    private static function result($status, $lat, $lng, $google_status, $message)
    {
        return array(
            'status'        => $status,
            'lat'           => $lat,
            'lng'           => $lng,
            'google_status' => $google_status,
            'message'       => $message,
        );
    }

    /**
     * Record the last geocoding error to the config row, throttled: write only
     * when the status or message differs from what was last recorded, or at
     * least 5 minutes have passed. This avoids a DB write on every failing save
     * during a batch import.
     */
    private static function record_error($result)
    {
        $status = $result['google_status'] !== '' ? $result['google_status'] : 'error';
        $message = $result['message'];
        $now = time();

        // Seed the baseline from the (request-cached) config row on first use.
        if (self::$last_error_recorded === null) {
            $cfg = unitas_map_config::get();
            $prev_at = 0;
            if (!empty($cfg['geocode_last_error_at'])) {
                $prev_at = @strtotime((string)$cfg['geocode_last_error_at']);
                if ($prev_at === false) $prev_at = 0;
            }
            self::$last_error_recorded = array(
                'status'  => isset($cfg['geocode_last_status']) ? (string)$cfg['geocode_last_status'] : '',
                'message' => isset($cfg['geocode_last_error']) ? (string)$cfg['geocode_last_error'] : '',
                'at'      => $prev_at,
            );
        }

        $prev = self::$last_error_recorded;
        $unchanged = ($prev['status'] === $status && $prev['message'] === $message);
        if ($unchanged && ($now - $prev['at']) < 300) {
            return; // same error seen recently; skip the write
        }

        // Truncate the message defensively to the column width; it never holds the key.
        $message_stored = mb_substr($message, 0, 255, 'UTF-8');

        if (function_exists('db_query') && function_exists('db_input')) {
            db_query(
                "UPDATE app_unitas_map_reports_config SET " .
                "geocode_last_status = '" . db_input(mb_substr($status, 0, 32, 'UTF-8')) . "', " .
                "geocode_last_error = '" . db_input($message_stored) . "', " .
                "geocode_last_error_at = '" . db_input(date('Y-m-d H:i:s', $now)) . "' " .
                "WHERE id = 1"
            );
        }

        self::$last_error_recorded = array('status' => $status, 'message' => $message, 'at' => $now);
    }
}
