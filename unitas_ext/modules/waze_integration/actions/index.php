<?php
/**
 * UNITAS Extension — Waze Integration Settings
 *
 * Stores the Waze reverse-geocoding token and API region.
 * Settings live in app_unitas_map_reports_config (single row, id = 1),
 * alongside the Google Map configuration.
 */

if ($app_user['group_id'] != 0)
{
    redirect_to('dashboard/');
}

require_once PLUGIN_UNITAS_EXT_PATH . '/modules/map_configuration/helpers/map_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $form_action = $_POST['form_action'] ?? 'save_lookup';

    if ($form_action === 'regenerate_key')
    {
        // New 128-bit key: the old feed URL stops working immediately
        db_perform(
            'app_unitas_map_reports_config',
            ['waze_feed_key' => bin2hex(random_bytes(16))],
            'update',
            "id = 1"
        );
    }
    elseif ($form_action === 'save_feed')
    {
        // Field mapping: ints only; keys mirror the waze_feed_config JSON
        $post_to_cfg = array(
            'feed_entities_id'       => 'entities_id',
            'feed_geometry_field'    => 'geometry_field',
            'feed_street_field'      => 'street_field',
            'feed_status_field'      => 'status_field',
            'feed_push_field'        => 'push_field',
            'feed_start_field'       => 'start_field',
            'feed_end_field'         => 'end_field',
            'feed_reason_field'      => 'reason_field',
            'feed_direction_field'   => 'direction_field',
            'feed_description_field' => 'description_field',
        );
        $map = array();
        foreach ($post_to_cfg as $post_key => $cfg_key)
        {
            $map[$cfg_key] = (int)($_POST[$post_key] ?? 0);
        }
        $map['status_closed_choice']     = trim($_POST['feed_status_closed_choice'] ?? '');
        $map['direction_one_way_choice'] = trim($_POST['feed_direction_one_way_choice'] ?? '');

        // Subtype map: accept only real choice ids of the selected reason
        // field; labels are captured now so the feed never queries choices.
        $map['subtype_map'] = array();
        if ($map['reason_field'] > 0 && isset($_POST['feed_subtype_map']) && is_array($_POST['feed_subtype_map']))
        {
            $labels = array();
            $rfield = db_find('app_fields', $map['reason_field']);
            if (isset($rfield['id']))
            {
                $rcfg = new fields_types_cfg($rfield['configuration']);
                if ((int)$rcfg->get('use_global_list') > 0)
                {
                    $chq = db_query("select id, name from app_global_lists_choices where lists_id = '" . db_input($rcfg->get('use_global_list')) . "'");
                }
                else
                {
                    $chq = db_query("select id, name from app_fields_choices where fields_id = '" . (int)$map['reason_field'] . "'");
                }
                while ($ch = db_fetch_array($chq))
                {
                    $labels[(string)$ch['id']] = $ch['name'];
                }
            }
            $subtype_whitelist = array('', 'ROAD_CLOSED_HAZARD', 'ROAD_CLOSED_CONSTRUCTION', 'ROAD_CLOSED_EVENT');
            foreach ($_POST['feed_subtype_map'] as $cid => $sub)
            {
                $cid = (string)(int)$cid;
                if (isset($labels[$cid]) && in_array($sub, $subtype_whitelist, true))
                {
                    $map['subtype_map'][$cid] = array('subtype' => $sub, 'label' => $labels[$cid]);
                }
            }
        }

        $window = (int)($_POST['waze_feed_window'] ?? 15);
        if ($window < 5)   { $window = 5; }
        if ($window > 120) { $window = 120; }

        $sql_data = [
            'waze_feed_enabled' => isset($_POST['waze_feed_enabled']) ? 1 : 0,
            'waze_feed_window'  => $window,
            'waze_feed_config'  => json_encode($map, JSON_UNESCAPED_UNICODE),
        ];

        // First enable mints the URL key so the feed never runs keyless
        $current = unitas_map_config::get();
        if ($sql_data['waze_feed_enabled'] == 1 && trim((string)($current['waze_feed_key'] ?? '')) === '')
        {
            $sql_data['waze_feed_key'] = bin2hex(random_bytes(16));
        }

        db_perform(
            'app_unitas_map_reports_config',
            $sql_data,
            'update',
            "id = 1"
        );
    }
    else
    {
        // save_lookup: reverse-geocoding token + region (Phase 1)
        $region = $_POST['waze_region'] ?? 'na';
        if (!in_array($region, array('na', 'row', 'il')))
        {
            $region = 'na';
        }

        $sql_data = [
            'waze_geocoding_token' => trim($_POST['waze_geocoding_token'] ?? ''),
            'waze_region'          => $region,
        ];

        db_perform(
            'app_unitas_map_reports_config',
            $sql_data,
            'update',
            "id = 1"
        );
    }

    redirect_to('unitas_ext/waze_integration/index');
}

$app_title = 'Waze Integration';
