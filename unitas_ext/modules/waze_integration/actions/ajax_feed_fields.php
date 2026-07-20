<?php
/**
 * UNITAS Extension — Waze Feed Mapping AJAX (admin only)
 *
 * Returns HTML fragments loaded into the Closure Feed settings portlet via
 * jQuery .load() (same pattern as map_reports get_entities_fields):
 *   action=get_fields  — role selects for the chosen entity
 *   action=get_choices — choice selects for status / reason / direction fields
 */

if (!isset($app_user['group_id']) || $app_user['group_id'] != 0) {
    exit();
}

require_once PLUGIN_UNITAS_EXT_PATH . '/modules/map_configuration/helpers/map_config.php';
$feed_cfg_row = unitas_map_config::get();
$feed_saved   = json_decode((string)($feed_cfg_row['waze_feed_config'] ?? ''), true);
if (!is_array($feed_saved)) {
    $feed_saved = array();
}

switch ($app_module_action) {

    case 'get_fields':

        $entities_id = (int)($_POST['entities_id'] ?? 0);
        if ($entities_id <= 0) {
            exit();
        }

        // Preselect saved values only when configuring the same entity
        $use_saved = ((int)($feed_saved['entities_id'] ?? 0) === $entities_id);

        // role select name => [label, allowed types, required, saved config key, tooltip]
        $roles = array(
            'feed_geometry_field' => array(
                'Closure Geometry Field', array('fieldtype_unitas_geometry'), true, 'geometry_field',
                'The Unitas Geometry field holding the drawn closure polyline.'
            ),
            'feed_street_field' => array(
                'Road Name Field', array('fieldtype_input'), true, 'street_field',
                'Sent to Waze as the closed street name.'
            ),
            'feed_status_field' => array(
                'Road Status Field', array('fieldtype_dropdown', 'fieldtype_radioboxes', 'fieldtype_autostatus'), true, 'status_field',
                'Only records with the selected Closed status are fed.'
            ),
            'feed_push_field' => array(
                'Push To Waze Field', array('fieldtype_boolean', 'fieldtype_checkbox', 'fieldtype_checkboxes'), true, 'push_field',
                'Checkbox that opts a record into the feed.'
            ),
            'feed_start_field' => array(
                'Date/Time Closed Field', array('fieldtype_input_date', 'fieldtype_input_datetime', 'fieldtype_date_added'), true, 'start_field',
                'Sent to Waze as the closure start time.'
            ),
            'feed_end_field' => array(
                'Est. Reopen Field (optional)', array('fieldtype_input_date', 'fieldtype_input_datetime'), false, 'end_field',
                'Stored for reference only — the feed always sends a rolling end time.'
            ),
            'feed_reason_field' => array(
                'Reason Field (optional)', array('fieldtype_dropdown', 'fieldtype_radioboxes'), false, 'reason_field',
                'Each reason choice can map to a Waze closure subtype below.'
            ),
            'feed_direction_field' => array(
                'Direction Field (optional)', array('fieldtype_dropdown', 'fieldtype_radioboxes'), false, 'direction_field',
                'Choice meaning One Direction is selected below; everything else feeds as both directions.'
            ),
            'feed_description_field' => array(
                'Details Field (optional)', array('fieldtype_text', 'fieldtype_textarea_wysiwyg', 'fieldtype_input'), false, 'description_field',
                'Free text appended to the closure description.'
            ),
        );

        $all_fields = array();
        $fq = db_query("select id, name, type from app_fields where entities_id = '" . db_input($entities_id) . "' order by name");
        while ($f = db_fetch_array($fq)) {
            $all_fields[] = $f;
        }

        $html = '';
        foreach ($roles as $select_name => $def) {
            $choices = array();
            if (!$def[2]) {
                $choices[''] = '- Not used -';
            } else {
                $choices[''] = '';
            }
            foreach ($all_fields as $f) {
                if (in_array($f['type'], $def[1])) {
                    $choices[$f['id']] = $f['name'];
                }
            }
            // Fallback: field type names are core-defined — if the filter
            // matched nothing, offer every field with its type visible.
            if (count($choices) <= 1) {
                foreach ($all_fields as $f) {
                    $choices[$f['id']] = $f['name'] . ' [' . $f['type'] . ']';
                }
            }

            $selected = $use_saved ? (string)($feed_saved[$def[3]] ?? '') : '';
            if ($selected === '0') {
                $selected = '';
            }

            $attrs = array('class' => 'form-control input-large' . ($def[2] ? ' required' : ''));
            if ($select_name === 'feed_status_field') {
                $attrs['onChange'] = 'unitasFeedLoadChoices("status", this.value)';
            }
            if ($select_name === 'feed_reason_field') {
                $attrs['onChange'] = 'unitasFeedLoadChoices("reason", this.value)';
            }
            if ($select_name === 'feed_direction_field') {
                $attrs['onChange'] = 'unitasFeedLoadChoices("direction", this.value)';
            }

            $html .= '
              <div class="form-group">
                <label class="col-md-4 control-label">' . $def[0] . '</label>
                <div class="col-md-8">
                  ' . select_tag($select_name, $choices, $selected, $attrs) . '
                  ' . ($def[4] !== '' ? tooltip_text($def[4]) : '') . '
                </div>
              </div>';

            if ($select_name === 'feed_status_field') {
                $html .= '<div id="feed_status_choices"></div>';
            }
            if ($select_name === 'feed_reason_field') {
                $html .= '<div id="feed_reason_choices"></div>';
            }
            if ($select_name === 'feed_direction_field') {
                $html .= '<div id="feed_direction_choices"></div>';
            }
        }

        // Load choice selects for any preselected fields
        $html .= '
          <script>
            if ($("#feed_status_field").val() > 0) unitasFeedLoadChoices("status", $("#feed_status_field").val());
            if ($("#feed_reason_field").val() > 0) unitasFeedLoadChoices("reason", $("#feed_reason_field").val());
            if ($("#feed_direction_field").val() > 0) unitasFeedLoadChoices("direction", $("#feed_direction_field").val());
          </script>';

        echo $html;
        exit();
        break;

    case 'get_choices':

        $fields_id = (int)($_POST['fields_id'] ?? 0);
        $kind      = (string)($_POST['kind'] ?? '');
        if ($fields_id <= 0 || !in_array($kind, array('status', 'reason', 'direction'))) {
            exit();
        }

        $field = db_find('app_fields', $fields_id);
        if (!isset($field['id'])) {
            exit();
        }

        $fcfg = new fields_types_cfg($field['configuration']);
        $choice_rows = array();
        if ((int)$fcfg->get('use_global_list') > 0) {
            $chq = db_query("select id, name from app_global_lists_choices where lists_id = '" . db_input($fcfg->get('use_global_list')) . "'");
        } else {
            $chq = db_query("select id, name from app_fields_choices where fields_id = '" . (int)$fields_id . "'");
        }
        while ($ch = db_fetch_array($chq)) {
            $choice_rows[$ch['id']] = $ch['name'];
        }

        if ($kind === 'status') {
            $saved_choice = ((int)($feed_saved['status_field'] ?? 0) === $fields_id)
                ? (string)($feed_saved['status_closed_choice'] ?? '') : '';
            echo '
              <div class="form-group">
                <label class="col-md-4 control-label">Choice Meaning Closed</label>
                <div class="col-md-8">
                  ' . select_tag('feed_status_closed_choice', array('' => '') + $choice_rows, $saved_choice, array('class' => 'form-control input-large required')) . '
                  ' . tooltip_text('Only records with this status value are pushed to Waze.') . '
                </div>
              </div>';
        } elseif ($kind === 'direction') {
            $saved_choice = ((int)($feed_saved['direction_field'] ?? 0) === $fields_id)
                ? (string)($feed_saved['direction_one_way_choice'] ?? '') : '';
            echo '
              <div class="form-group">
                <label class="col-md-4 control-label">Choice Meaning One Direction</label>
                <div class="col-md-8">
                  ' . select_tag('feed_direction_one_way_choice', array('' => '- Not used (always both directions) -') + $choice_rows, $saved_choice, array('class' => 'form-control input-large')) . '
                </div>
              </div>';
        } else {
            $saved_map = ((int)($feed_saved['reason_field'] ?? 0) === $fields_id && isset($feed_saved['subtype_map']) && is_array($feed_saved['subtype_map']))
                ? $feed_saved['subtype_map'] : array();
            $subtypes = array(
                ''                         => 'ROAD_CLOSED (generic)',
                'ROAD_CLOSED_HAZARD'       => 'ROAD_CLOSED_HAZARD',
                'ROAD_CLOSED_CONSTRUCTION' => 'ROAD_CLOSED_CONSTRUCTION',
                'ROAD_CLOSED_EVENT'        => 'ROAD_CLOSED_EVENT',
            );
            $html = '
              <div class="form-group">
                <label class="col-md-4 control-label" style="font-weight:bold;">Reason to Waze Subtype</label>
                <div class="col-md-8"><p class="help-block" style="margin:7px 0 0;">Each reason maps to a Waze closure subtype in the feed.</p></div>
              </div>';
            foreach ($choice_rows as $cid => $cname) {
                $selected = (isset($saved_map[$cid]) && is_array($saved_map[$cid])) ? (string)($saved_map[$cid]['subtype'] ?? '') : '';
                $html .= '
                  <div class="form-group">
                    <label class="col-md-4 control-label" style="font-weight:normal;">' . htmlspecialchars($cname) . '</label>
                    <div class="col-md-8">
                      ' . select_tag('feed_subtype_map[' . (int)$cid . ']', $subtypes, $selected, array('class' => 'form-control input-large')) . '
                    </div>
                  </div>';
            }
            echo $html;
        }

        exit();
        break;
}

exit();
