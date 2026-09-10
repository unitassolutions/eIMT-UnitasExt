<?php
/**
 * UNITAS Extension - Location value helper.
 *
 * Parses, formats, validates and normalizes the stored value of a
 * fieldtype_unitas_location field. Values are a 4-part, tab-separated
 * superset of the legacy core 3-part Google Map format:
 *
 *     {lat}\t{lng}\t{address}\t{status}
 *
 * - lat / lng : decimal degrees, rounded to 7 places, range validated.
 *               Both empty when there are no coordinates.
 * - address   : normalized plain text (NOT url-encoded, unlike the core field).
 * - status    : one of the STATUSES codes below.
 *
 * This class has no Rukovoditel core dependency and is safe to load in
 * web, cron and REST API contexts.
 *
 * See plan sections 5.3 and 7.4.
 */
class unitas_location_value
{
    /** All valid status codes. */
    const STATUSES = array(
        'autocomplete', // user picked a Places suggestion; coords from the place
        'geocoded',     // server geocoding returned a precise match
        'approximate',  // partial_match or location_type = APPROXIMATE
        'manual',       // user dragged or placed the pin
        'not_found',    // geocoding returned ZERO_RESULTS
        'error',        // lookup failed (quota, denied, network, no server key, ...)
    );

    /** Statuses a browser is permitted to submit through process(). */
    const CLIENT_STATUSES = array('autocomplete', 'manual');

    /** Maximum stored address length (characters). */
    const ADDRESS_MAX = 500;

    /**
     * Normalize address text for both storage and comparison.
     *
     * Strips tags, decodes HTML entities, replaces tabs/newlines with spaces,
     * collapses runs of whitespace, trims, and caps length.
     */
    public static function normalize_address($text)
    {
        $text = (string)$text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Tabs and newlines would corrupt the tab-delimited value; flatten them.
        $text = str_replace(array("\t", "\r", "\n"), ' ', $text);
        // Collapse any whitespace run (including unicode spaces) to a single space.
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        if ($text !== '' && mb_strlen($text, 'UTF-8') > self::ADDRESS_MAX) {
            $text = mb_substr($text, 0, self::ADDRESS_MAX, 'UTF-8');
        }
        return $text;
    }

    /**
     * Range check for a coordinate pair. Both parts must be numeric and in
     * range. Kept identical to fieldtype_unitas_geometry::valid_map_point().
     */
    public static function is_valid_point($lat, $lng)
    {
        if (!is_numeric($lat) || !is_numeric($lng)) return false;
        $lat = (float)$lat;
        $lng = (float)$lng;
        return ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);
    }

    /**
     * Parse a stored value into its parts.
     *
     * Returns array{lat:?float, lng:?float, address:string, status:string}.
     * - A 4-part value uses its own status (unknown status -> '').
     * - A legacy 3-part value with numeric coordinates is treated as 'geocoded'.
     *   Its address is returned verbatim (still url-encoded from the core field);
     *   only the migration tool url-decodes it.
     * - Invalid or out-of-range coordinates yield lat = lng = null.
     */
    public static function parse($raw)
    {
        $raw = (string)$raw;

        $result = array('lat' => null, 'lng' => null, 'address' => '', 'status' => '');
        if ($raw === '') return $result;

        $parts = explode("\t", $raw);

        $lat_raw = isset($parts[0]) ? $parts[0] : '';
        $lng_raw = isset($parts[1]) ? $parts[1] : '';
        $result['address'] = isset($parts[2]) ? $parts[2] : '';

        if (self::is_valid_point($lat_raw, $lng_raw)) {
            $result['lat'] = (float)$lat_raw;
            $result['lng'] = (float)$lng_raw;
        }

        if (count($parts) >= 4) {
            $status = trim($parts[3]);
            $result['status'] = in_array($status, self::STATUSES, true) ? $status : '';
        } elseif ($result['lat'] !== null) {
            // Legacy 3-part value with usable coordinates.
            $result['status'] = 'geocoded';
        }

        return $result;
    }

    /**
     * Build a stored value from parts. Validates and rounds coordinates,
     * normalizes the address, and joins with tabs. Coordinates that fail
     * validation are stored as empty parts. Returns '' when the whole value
     * would be empty.
     */
    public static function format($lat, $lng, $address, $status)
    {
        $lat_str = '';
        $lng_str = '';
        if (self::is_valid_point($lat, $lng)) {
            $lat_str = self::coord_str((float)$lat);
            $lng_str = self::coord_str((float)$lng);
        }

        $address = self::normalize_address($address);
        $status = in_array($status, self::STATUSES, true) ? $status : '';

        if ($lat_str === '' && $address === '' && $status === '') {
            return '';
        }

        return $lat_str . "\t" . $lng_str . "\t" . $address . "\t" . $status;
    }

    /**
     * Format a coordinate rounded to 7 decimal places, without a trailing
     * decimal point or trailing zeros, and without locale/scientific notation.
     */
    private static function coord_str($value)
    {
        $s = sprintf('%.7f', round($value, 7));
        if (strpos($s, '.') !== false) {
            $s = rtrim($s, '0');
            $s = rtrim($s, '.');
        }
        // Normalize a signed zero ("-0") to "0".
        if ($s === '-0') $s = '0';
        return $s;
    }
}
