<?php

class unitas_map_config
{
    public static function get()
    {
        static $cfg = null;

        if ($cfg !== null)
        {
            return $cfg;
        }

        $q = db_query("SELECT * FROM app_unitas_map_reports_config WHERE id = 1");

        if ($row = db_fetch_array($q))
        {
            $cfg = $row;
        }
        else
        {
            // Fallback used only when the config row is missing (fresh or broken
            // install). It is the single source of defaults for every Unitas map
            // consumer, including the geometry field (which previously kept its
            // own private copy). See plan section 7.1.
            $cfg = [
                'google_map_api_key'        => '',
                'google_server_api_key'     => '',
                'map_style_light'           => '',
                'map_style_dark'            => '',
                'default_theme'             => 'auto',
                'default_lat'               => '35.7596',
                'default_lng'               => '-79.0193',
                'default_zoom'              => 8,
                'waze_geocoding_token'      => '',
                'waze_region'               => 'na',
                'waze_feed_enabled'         => 0,
                'waze_feed_key'             => '',
                'waze_feed_window'          => 15,
                'waze_feed_config'          => '',
                'autocomplete_region_codes' => 'us',
                'autocomplete_bias_radius_m'=> 50000,
                'geocode_last_status'       => '',
                'geocode_last_error'        => '',
                'geocode_last_error_at'     => null,
            ];
        }

        return $cfg;
    }
}
