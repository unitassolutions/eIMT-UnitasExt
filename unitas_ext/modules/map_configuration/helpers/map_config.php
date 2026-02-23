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
            $cfg = [
                'google_map_api_key' => '',
                'map_style_light'    => '',
                'map_style_dark'     => '',
                'default_theme'      => 'auto',
                'default_lat'        => '',
                'default_lng'        => '',
                'default_zoom'       => 8,
            ];
        }

        return $cfg;
    }
}
