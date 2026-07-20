<?php
/**
 * fieldtype_unitas_geometry
 * 
 * Custom Rukovoditel field type for drawing geometry on Google Maps.
 * Stores data as JSON with encoded polyline for Waze Partner Feed compatibility.
 * 
 * Data formats (by drawing mode):
 *   Polyline: {"type":"polyline","encoded_polyline":"...","points":[[lat,lng],...],"distance_m":523}
 *   Polygon:  {"type":"polygon","points":[[lat,lng],...],"area_sqm":12345}
 *   Circle:   {"type":"circle","center":[lat,lng],"radius_m":500}
 * 
 * @package UNITAS Extension
 * @version 1.0.0
 */
class fieldtype_unitas_geometry
{
    public $options;

    function __construct()
    {
        $this->options = array('title' => 'Geometry (Google Map)');
    }

    function get_configuration()
    {
        $cfg = array();

        $cfg[] = array(
            'title' => 'Drawing Mode', 'name' => 'drawing_mode', 'type' => 'dropdown',
            'choices' => array(
                'polyline' => 'Polyline (line along a path)',
                'polygon'  => 'Polygon (enclosed area)',
                'circle'   => 'Circle (radius around a point)',
            ),
            'default' => 'polyline',
            'params' => array('class' => 'form-control input-large')
        );

        $cfg[] = array(
            'title' => 'Map Width', 'name' => 'map_width', 'type' => 'input',
            'default' => '100%', 'tooltip_icon' => 'e.g. 100%, 600px',
            'params' => array('class' => 'form-control input-small')
        );

        $cfg[] = array(
            'title' => 'Map Height', 'name' => 'map_height', 'type' => 'input',
            'default' => '400px', 'tooltip_icon' => 'e.g. 400px, 500px',
            'params' => array('class' => 'form-control input-small')
        );

        $zoom_choices = [];
        for ($i = 3; $i <= 20; $i++) $zoom_choices[$i] = $i;
        $cfg[] = array(
            'title' => 'Default Zoom', 'name' => 'default_zoom', 'type' => 'dropdown',
            'choices' => $zoom_choices, 'default' => 12,
            'params' => array('class' => 'form-control input-small')
        );

        $cfg[] = array(
            'title' => 'Line Color', 'name' => 'stroke_color', 'type' => 'input',
            'default' => '#FF0000', 'tooltip_icon' => 'Hex color (e.g. #FF0000)',
            'params' => array('class' => 'form-control input-small')
        );

        $cfg[] = array(
            'title' => 'Line Weight', 'name' => 'stroke_weight', 'type' => 'input',
            'default' => '4', 'tooltip_icon' => 'Thickness in pixels',
            'params' => array('class' => 'form-control input-small', 'type' => 'number', 'min' => 1, 'max' => 20)
        );

        // ── Waze street-name autofill (optional) ────────────────────────────
        // This method has no entity context, so the dropdowns list text fields
        // across ALL entities (labeled with their entity name). render()
        // validates that a selected target belongs to the same entity before
        // enabling the lookup — wrong-entity picks are silently ignored.
        $field_choices = array('' => '- Disabled -');
        $tf = db_query("SELECT f.id, f.name, e.name AS entity_name FROM app_fields f INNER JOIN app_entities e ON e.id = f.entities_id WHERE f.type = 'fieldtype_input' ORDER BY e.name, f.name");
        while ($tf_row = db_fetch_array($tf)) {
            $field_choices[$tf_row['id']] = $tf_row['entity_name'] . ': ' . $tf_row['name'] . ' (ID ' . $tf_row['id'] . ')';
        }

        $cfg[] = array(
            'title' => 'Waze Autofill: Road Name Field', 'name' => 'waze_target_road_name', 'type' => 'dropdown',
            'choices' => $field_choices, 'default' => '',
            'tooltip_icon' => 'Text field that receives the road name. Must belong to the same entity as this field. Requires a Waze token (UNITAS Extension > Extension Configuration > Waze Integration).',
            'params' => array('class' => 'form-control input-large')
        );

        $cfg[] = array(
            'title' => 'Waze Autofill: Cross Street 1 Field', 'name' => 'waze_target_cross1', 'type' => 'dropdown',
            'choices' => $field_choices, 'default' => '',
            'tooltip_icon' => 'Text field that receives the cross street at the START of the drawn line. Must belong to the same entity as this field.',
            'params' => array('class' => 'form-control input-large')
        );

        $cfg[] = array(
            'title' => 'Waze Autofill: Cross Street 2 Field', 'name' => 'waze_target_cross2', 'type' => 'dropdown',
            'choices' => $field_choices, 'default' => '',
            'tooltip_icon' => 'Text field that receives the cross street at the END of the drawn line. Must belong to the same entity as this field.',
            'params' => array('class' => 'form-control input-large')
        );

        return $cfg;
    }

    function render($field, $obj, $params = array())
    {
        $cfg = new fields_types_cfg($field['configuration']);
        $fid = $field['id'];
        $value = isset($obj['field_' . $fid]) ? $obj['field_' . $fid] : '';
        $map_cfg = self::get_map_config();
        $api_key = $map_cfg['google_map_api_key'];

        if (empty($api_key)) {
            return '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Google Maps API key not configured. Go to UNITAS Extension &gt; Extension Configuration &gt; Google Map.</div>';
        }

        // Resolve Map ID (same light/dark/auto logic as map reports)
        $map_theme = $map_cfg['default_theme'] ?? 'auto';
        if ($map_theme === 'light') {
            $map_id = $map_cfg['map_style_light'] ?? '';
        } elseif ($map_theme === 'dark') {
            $map_id = $map_cfg['map_style_dark'] ?? '';
        } else {
            $hour   = (int)date('H');
            $map_id = ($hour >= 18 || $hour <= 6) ? ($map_cfg['map_style_dark'] ?? '') : ($map_cfg['map_style_light'] ?? '');
        }

        // Pre-build API URL so &map_ids= can be appended cleanly
        $map_ids_param = trim(($map_cfg['map_style_light'] ?? '') . ',' . ($map_cfg['map_style_dark'] ?? ''), ',');
        $render_api_url = 'https://maps.googleapis.com/maps/api/js?key=' . $api_key
                        . '&libraries=geometry'
                        . ($map_ids_param ? '&map_ids=' . $map_ids_param : '')
                        . '&callback=_unitasGeoApiReady';

        $w = $cfg->get('map_width') ?: '100%';
        $h = $cfg->get('map_height') ?: '400px';
        if (!strstr($w, '%') && !strstr($w, 'px')) $w .= 'px';
        if (!strstr($h, '%') && !strstr($h, 'px')) $h .= 'px';

        // ── Waze street-name autofill targets ───────────────────────────────
        // Configured target ids must belong to this entity and still be text
        // fields; deleted, re-typed, or wrong-entity selections are dropped.
        // The token itself never enters $js_cfg or the page HTML — the widget
        // only learns whether the lookup is available.
        $waze_targets = array(
            'road'   => (int)$cfg->get('waze_target_road_name'),
            'cross1' => (int)$cfg->get('waze_target_cross1'),
            'cross2' => (int)$cfg->get('waze_target_cross2'),
        );
        $target_ids = array_filter($waze_targets);
        if (count($target_ids)) {
            $valid_ids = array();
            $vq = db_query("SELECT id FROM app_fields WHERE id IN (" . implode(',', array_map('intval', $target_ids)) . ") AND entities_id = '" . (int)$field['entities_id'] . "' AND type = 'fieldtype_input'");
            while ($v = db_fetch_array($vq)) {
                $valid_ids[] = (int)$v['id'];
            }
            foreach ($waze_targets as $wk => $wid) {
                if ($wid && !in_array($wid, $valid_ids)) {
                    $waze_targets[$wk] = 0;
                }
            }
        }
        $waze_token  = trim($map_cfg['waze_geocoding_token'] ?? '');
        $waze_lookup = ($waze_token !== '' && ($waze_targets['road'] || $waze_targets['cross1'] || $waze_targets['cross2']));

        $js_cfg = json_encode(array(
            'fieldId'      => $fid,
            'drawingMode'  => $cfg->get('drawing_mode') ?: 'polyline',
            'defaultZoom'  => (int)($cfg->get('default_zoom') ?: 12),
            'defaultLat'   => (float)($map_cfg['default_lat'] ?: 35.7596),
            'defaultLng'   => (float)($map_cfg['default_lng'] ?: -79.0193),
            'strokeColor'  => $cfg->get('stroke_color') ?: '#FF0000',
            'strokeWeight' => (int)($cfg->get('stroke_weight') ?: 4),
            'mapId'        => $map_id,
            'existingData' => $value ? json_decode($value, true) : null,
            'wazeTargets'  => $waze_targets,
            'wazeLookup'   => $waze_lookup,
        ));

        $html = '<input type="hidden" name="fields[' . $fid . ']" id="fields_' . $fid . '" '
              . 'value="' . htmlspecialchars($value, ENT_QUOTES) . '" '
              . 'class="' . ($field['is_required'] == 1 ? 'required' : '') . '">';

        $html .= '<div style="display:flex;gap:4px;margin-bottom:4px;">'
               . '<input type="text" id="unitas_geo_addr_' . $fid . '" class="form-control input-sm" placeholder="Search address to navigate map...">'
               . '<button type="button" id="unitas_geo_go_' . $fid . '" class="btn btn-sm btn-default">'
               . '<i class="fa fa-search"></i>&nbsp;Go</button>'
               . '</div>';

        $html .= '<div id="unitas_geo_map_' . $fid . '" style="width:' . $w . ';height:' . $h
               . ';border:1px solid #ddd;border-radius:4px;margin-bottom:0;"></div>';

        $html .= '<div id="unitas_geo_info_' . $fid . '" class="unitas-geo-info" style="display:none;">'
               . '<span id="unitas_geo_distance_' . $fid . '"></span>'
               . '<span id="unitas_geo_points_' . $fid . '"></span>'
               . ' <a href="javascript:void(0)" onclick="unitasGeoWidget[' . $fid . '].clear()" class="btn btn-xs btn-danger" style="margin-left:10px;"><i class="fa fa-trash"></i> Clear</a>'
               . ' <a href="javascript:void(0)" onclick="unitasGeoWidget[' . $fid . '].redraw()" class="btn btn-xs btn-default" style="margin-left:5px;"><i class="fa fa-pencil"></i> Redraw</a>'
               . ($waze_lookup ? ' <a href="javascript:void(0)" onclick="unitasGeoWidget[' . $fid . '].wazeRefresh()" class="btn btn-xs btn-info" style="margin-left:5px;" title="Look up street names again from the drawn geometry"><i class="fa fa-refresh"></i> Street Names</a>' : '')
               . '<span id="unitas_geo_waze_' . $fid . '" style="margin-left:10px;font-size:12px;color:#31708f;"></span>'
               . '</div>';

        // Script: loads widget JS and Google Maps API (both async), then inits this field.
        // Two-flag coordination prevents a race when multiple geometry fields share a page:
        //   _unitasGeoWidgetLoading = script tag has been appended (do not append again)
        //   _unitasGeoWidgetReady   = script has finished loading (UnitasGeoWidget is defined)
        //   _unitasGeoWidgetQueue   = callbacks waiting for widget JS to finish loading
        // _ensureGeoLibs handles three cases for the Maps API:
        //   1. geometry already loaded — init immediately
        //   2. google.maps exists but geometry missing — importLibrary() to add it
        //   3. google.maps not loaded — load script with &libraries=geometry
        $html .= '<script>'
               . 'var unitasGeoConfig_' . $fid . '=' . $js_cfg . ';'
               . 'function _initGeoField_' . $fid . '(){'
               . '  window.unitasGeoWidget=window.unitasGeoWidget||{};'
               . '  window.unitasGeoWidget[' . $fid . ']=new UnitasGeoWidget(unitasGeoConfig_' . $fid . ')'
               . '}'
               . 'function _ensureGeoLibs_' . $fid . '(){'
               // DrawingManager removed in Maps JS API v3.65. Widget uses custom
               // click-to-draw, so only the geometry library is required.
               . '  if(window.google&&google.maps&&google.maps.geometry){'
               // Case 1: geometry already loaded — init immediately
               . '    _initGeoField_' . $fid . '()'
               . '  }else if(window.google&&google.maps&&typeof google.maps.importLibrary==="function"){'
               // Case 2: Maps loaded without geometry — add it dynamically
               . '    google.maps.importLibrary("geometry").then(function(lib){'
               . '      google.maps.geometry=google.maps.geometry||lib;'
               . '      _initGeoField_' . $fid . '()'
               . '    })'
               . '  }else{'
               // Case 3: Maps not loaded yet — queue and load
               . '    window._unitasGeoQueue=window._unitasGeoQueue||[];'
               . '    window._unitasGeoQueue.push(_initGeoField_' . $fid . ');'
               . '    if(!window._unitasGeoApiLoading){'
               . '      window._unitasGeoApiLoading=true;'
               . '      window._unitasGeoApiReady=function(){window._unitasGeoApiLoaded=true;(window._unitasGeoQueue||[]).forEach(function(fn){fn()});window._unitasGeoQueue=[]};'
               . '      var _gs=document.createElement("script");'
               . '      _gs.src="' . htmlspecialchars($render_api_url) . '";'
               . '      _gs.async=true;document.head.appendChild(_gs)'
               . '    }'
               . '  }'
               . '}'
               // Widget JS coordination: wait for the script to actually finish loading
               // before calling _ensureGeoLibs, not just before the tag is appended.
               . 'if(!window._unitasGeoWidgetLoading){'
               // First field on page: start loading the widget script
               . '  window._unitasGeoWidgetLoading=true;'
               . '  var _gw=document.createElement("script");'
               . '  _gw.src="plugins/unitas_ext/js/fieldtype/unitas_geometry.js?v=' . PLUGIN_UNITAS_EXT_VERSION . '";'
               . '  _gw.onload=function(){'
               . '    window._unitasGeoWidgetReady=true;'
               // Flush any other fields that were waiting for the widget to load
               . '    (window._unitasGeoWidgetQueue||[]).forEach(function(fn){fn()});'
               . '    window._unitasGeoWidgetQueue=[];'
               . '    _ensureGeoLibs_' . $fid . '()'
               . '  };'
               . '  document.head.appendChild(_gw)'
               . '}else if(window._unitasGeoWidgetReady){'
               // Widget already loaded: proceed directly
               . '  _ensureGeoLibs_' . $fid . '()'
               . '}else{'
               // Widget is loading but not ready: queue until it finishes
               . '  window._unitasGeoWidgetQueue=window._unitasGeoWidgetQueue||[];'
               . '  window._unitasGeoWidgetQueue.push(function(){_ensureGeoLibs_' . $fid . '()})'
               . '}'
               . '</script>';

        return $html;
    }

    function process($options)
    {
        $value = trim($options['value']);
        if (empty($value)) return '';
        $data = json_decode($value, true);
        if (!$data || !isset($data['type'])) return '';
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    function output($options)
    {
        if (isset($options['is_export'])) {
            $data = json_decode($options['value'], true);
            return ($data && isset($data['encoded_polyline'])) ? $data['encoded_polyline'] : $options['value'];
        }

        if (isset($options['is_listing'])) {
            $data = json_decode($options['value'], true);
            if (!$data || !isset($data['type'])) return '';
            switch ($data['type']) {
                case 'polyline':
                    if (!empty($data['distance_m'])) {
                        $d = $data['distance_m'];
                        return ($d >= 1609) ? round($d / 1609.34, 1) . ' mi' : round($d * 3.28084) . ' ft';
                    }
                    return isset($data['points']) ? count($data['points']) . ' pts' : '';
                case 'polygon':
                    if (!empty($data['area_sqm'])) {
                        $acres = $data['area_sqm'] / 4046.86;
                        return $acres >= 640 ? round($acres / 640, 1) . ' sq mi' : round($acres, 1) . ' ac';
                    }
                    return isset($data['points']) ? 'Polygon (' . count($data['points']) . ' pts)' : 'Polygon';
                case 'circle':
                    if (!empty($data['radius_m'])) {
                        $r = $data['radius_m'];
                        return ($r >= 1609) ? round($r / 1609.34, 1) . ' mi radius' : round($r * 3.28084) . ' ft radius';
                    }
                    return 'Circle';
                default:
                    return '';
            }
        }

        if (empty($options['value'])) return '';
        $data = json_decode($options['value'], true);
        if (!$data || !isset($data['type'])) return '';
        if ($data['type'] === 'circle') {
            if (empty($data['center']) || empty($data['radius_m'])) return '';
        } elseif (empty($data['points']) || count($data['points']) < 2) {
            return '';
        }

        $fid = $options['field']['id'];
        $cfg = new fields_types_cfg($options['field']['configuration']);
        $map_cfg = self::get_map_config();
        $api_key = $map_cfg['google_map_api_key'];
        if (empty($api_key)) return '<em>Map unavailable (no API key)</em>';

        // Resolve Map ID (same light/dark/auto logic as map reports)
        $map_theme = $map_cfg['default_theme'] ?? 'auto';
        if ($map_theme === 'light') {
            $out_map_id = $map_cfg['map_style_light'] ?? '';
        } elseif ($map_theme === 'dark') {
            $out_map_id = $map_cfg['map_style_dark'] ?? '';
        } else {
            $hour       = (int)date('H');
            $out_map_id = ($hour >= 18 || $hour <= 6) ? ($map_cfg['map_style_dark'] ?? '') : ($map_cfg['map_style_light'] ?? '');
        }

        $map_ids_param = trim(($map_cfg['map_style_light'] ?? '') . ',' . ($map_cfg['map_style_dark'] ?? ''), ',');
        // No &libraries=geometry needed for output() — _rGeo_X only uses core Maps shapes
        $output_api_url = 'https://maps.googleapis.com/maps/api/js?key=' . $api_key
                        . ($map_ids_param ? '&map_ids=' . $map_ids_param : '')
                        . '&callback=_unitasGeoApiReady';

        $w = $cfg->get('map_width') ?: '100%';
        $h = $cfg->get('map_height') ?: '400px';
        if (!strstr($w, '%') && !strstr($w, 'px')) $w .= 'px';
        if (!strstr($h, '%') && !strstr($h, 'px')) $h .= 'px';
        $sc = $cfg->get('stroke_color') ?: '#FF0000';
        $sw = (int)($cfg->get('stroke_weight') ?: 4);

        $info_html = '';
        if ($data['type'] === 'polyline' && !empty($data['distance_m'])) {
            $mi = round($data['distance_m'] / 1609.34, 2);
            $ft = number_format(round($data['distance_m'] * 3.28084));
            $info_html = '<div class="unitas-geo-info-output"><i class="fa fa-road"></i> ' . $mi . ' miles (' . $ft . ' ft)</div>';
        } elseif ($data['type'] === 'polygon' && !empty($data['area_sqm'])) {
            $acres = $data['area_sqm'] / 4046.86;
            $area_str = $acres >= 640 ? round($acres / 640, 2) . ' sq mi' : round($acres, 1) . ' acres';
            $info_html = '<div class="unitas-geo-info-output"><i class="fa fa-square-o"></i> ' . $area_str . '</div>';
        } elseif ($data['type'] === 'circle' && !empty($data['radius_m'])) {
            $r_mi = round($data['radius_m'] / 1609.34, 2);
            $r_ft = number_format(round($data['radius_m'] * 3.28084));
            $info_html = '<div class="unitas-geo-info-output"><i class="fa fa-circle-o"></i> ' . $r_mi . ' mi radius (' . $r_ft . ' ft)</div>';
        }

        $data_json = json_encode($data);
        $mid = 'unitas_geo_out_' . $fid;

        $out_map_opts = $out_map_id ? '{"mapId":"' . htmlspecialchars($out_map_id) . '"}' : '{"mapTypeId":"roadmap"}';

        $html  = '<div id="' . $mid . '" style="width:' . $w . ';height:' . $h . ';border:1px solid #ddd;border-radius:4px;margin-bottom:5px"></div>' . $info_html;

        $html .= '<script>'
              . 'function _rGeo_' . $fid . '(){'
              . '  var d=' . $data_json . ',sc="' . $sc . '",sw=' . $sw . ','
              . '    m=new google.maps.Map(document.getElementById("' . $mid . '"),' . $out_map_opts . ');'
              . '  if(d.type==="polyline"||d.type==="polygon"){'
              . '    var path=d.points.map(function(p){return new google.maps.LatLng(p[0],p[1])});'
              . '    var b=new google.maps.LatLngBounds();path.forEach(function(p){b.extend(p)});'
              . '    m.fitBounds(b,40);'
              . '    if(d.type==="polyline"){'
              . '      new google.maps.Polyline({path:path,strokeColor:sc,strokeWeight:sw,strokeOpacity:0.9,map:m})'
              . '    }else{'
              . '      new google.maps.Polygon({paths:path,strokeColor:sc,strokeWeight:sw,strokeOpacity:0.9,fillColor:sc,fillOpacity:0.2,map:m})'
              . '    }'
              . '  }else if(d.type==="circle"){'
              . '    var c=new google.maps.LatLng(d.center[0],d.center[1]);'
              . '    var circ=new google.maps.Circle({center:c,radius:d.radius_m,strokeColor:sc,strokeWeight:sw,strokeOpacity:0.9,fillColor:sc,fillOpacity:0.2,map:m});'
              . '    m.fitBounds(circ.getBounds())'
              . '  }'
              . '}'
              // Defer until window.load so other page scripts (e.g. Rukovoditel Extension Maps load)
              // have settled. If Maps is already available by then, reuse it — no second API load.
              . 'function _initRGeo_' . $fid . '(){'
              . '  if(window.google&&google.maps){'
              . '    _rGeo_' . $fid . '()'
              . '  }else{'
              . '    window._unitasGeoQueue=window._unitasGeoQueue||[];'
              . '    window._unitasGeoQueue.push(_rGeo_' . $fid . ');'
              . '    if(!window._unitasGeoApiLoading){'
              . '      window._unitasGeoApiLoading=true;'
              . '      window._unitasGeoApiReady=function(){window._unitasGeoApiLoaded=true;(window._unitasGeoQueue||[]).forEach(function(fn){fn()});window._unitasGeoQueue=[]};'
              . '      var _s=document.createElement("script");'
              . '      _s.src="' . htmlspecialchars($output_api_url) . '";'
              . '      _s.async=true;document.head.appendChild(_s)'
              . '    }'
              . '  }'
              . '}'
              . 'if(document.readyState==="complete"){'
              . '  _initRGeo_' . $fid . '()'
              . '}else{'
              . '  window.addEventListener("load",_initRGeo_' . $fid . ')'
              . '}'
              . '</script>';

        return $html;
    }

    private static function get_map_config()
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $q = db_query("SELECT * FROM app_unitas_map_reports_config LIMIT 1");
        $cache = db_fetch_array($q);
        if (!$cache) $cache = array('google_map_api_key' => '', 'default_lat' => '35.7596', 'default_lng' => '-79.0193', 'default_zoom' => 8, 'waze_geocoding_token' => '', 'waze_region' => 'na');
        return $cache;
    }
}
