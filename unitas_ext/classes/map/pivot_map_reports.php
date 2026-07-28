<?php

class unitas_pivot_map_reports
{

    public $markers;
    public $shapes;
    private $polyline;
    private $polygon;
    private $reports_id;
    private $sidebar;
    private $display_sidebar;
    public $latlng;
    
    private $fields_id;
    private $entities_id;
    private $background;
    private $fields_in_popup;
    private $fields_in_sidebar = '';
    private $marker_color;
    private $marker_icon;
    private $field_info;
    private $is_modern;

    function __construct($reports)
    {
        $this->reports_id = $reports['id'];
        $this->is_modern = ((isset($reports['layout']) ? $reports['layout'] : '') == 'modern');
        $this->markers = array();
        $this->shapes = array();
        $this->polyline = array();
        $this->polygon = array();
        $this->latlng = false;
        $this->sidebar = [];
        $this->display_sidebar = $reports['display_sidebar'];

        //set default coordinates
        if(strlen($reports['latlng']))
        {
            $this->latlng = $reports['latlng'];
        }

        $this->get_coordinates();
    }

    function get_coordinates()
    {
        global $sql_query_having, $app_choices_cache, $app_fields_cache, $app_global_choices_cache;

        $text_pattern = new fieldtype_text_pattern;

        $map_entities_query = db_query("select * from app_unitas_pivot_map_reports_entities where reports_id=" . $this->reports_id . " order by id");
        while($map_entities = db_fetch_array($map_entities_query))
        {

            $this->fields_id = $map_entities['fields_id'];
            $this->entities_id = $map_entities['entities_id'];
            $this->background = $map_entities['background'];
            $this->fields_in_popup = $map_entities['fields_in_popup'];
            $this->marker_color = $map_entities['marker_color'];
            $this->marker_icon = $map_entities['marker_icon'];

            $this->field_info = $app_fields_cache[$this->entities_id][$this->fields_id];

            $listing_sql_query = '';
            $select_sql_query = '';
            $listing_sql_query_having = '';
            $sql_query_having = array();

            //add filters query
            $reports_info_query = db_query("select * from app_reports where entities_id='" . db_input($map_entities['entities_id']) . "' and reports_type='pivot_map" . $map_entities['id'] . "'");
            if($reports_info = db_fetch_array($reports_info_query))
            {
                $listing_sql_query = reports::add_filters_query($reports_info['id'], $listing_sql_query);
            }
            
            //extra filters
            if($fiters_panel_reports_id = reports::get_reports_id_by_type($map_entities['entities_id'],'pivot_map_reports_entity_filters_panel_' . $this->reports_id . '_' . $map_entities['entities_id'],true))
            {
               $listing_sql_query = reports::add_filters_query($fiters_panel_reports_id,$listing_sql_query); 
            }

            //add filter by map
            $listing_sql_query .= " and length(e.field_" . $this->fields_id . ")>0";

            //add access query
            $listing_sql_query = items::add_access_query($this->entities_id, $listing_sql_query);


            //prepare fields sum for formulas
            $sql_query_select = fieldtype_formula::prepare_query_select($this->entities_id, '');

            //prepare having query for formula fields
            if(isset($sql_query_having[$this->entities_id]))
            {
                $listing_sql_query .= reports::prepare_filters_having_query($sql_query_having[$this->entities_id]);
            }

            //prepare parent item query
            if(isset($_GET['path']))
            {
                $path_info = items::parse_path($_GET['path']);
                if($path_info['parent_entity_item_id'] > 0)
                {
                    $listing_sql_query .= " and e.parent_item_id='" . $path_info['parent_entity_item_id'] . "'";
                }
            }

            $listing_sql = "select e.* " . $sql_query_select . " from app_entity_" . $this->entities_id . " e  where e.id>0 " . $listing_sql_query;


            $items_query = db_query($listing_sql);
            while($items = db_fetch_array($items_query))
            {
                // Geometry fields hold one JSON object per record. The value
                // normalization below (semicolon split, comma-space squeeze,
                // parenthesis truncation) is mapbbcode-specific and
                // would corrupt it, so this type reads the raw column value.
                if($this->field_info['type'] === 'fieldtype_unitas_geometry')
                {
                    $this->add_geometry($items, $map_entities);
                    continue;
                }

                foreach(explode(';', $items['field_' . $this->fields_id]) as $obj_count => $value)
                {
                    //prepare value
                    $value = str_replace(array('[map]', '[/map]', ', '), ['', '', ','], trim($value));

                    if(strstr($value, '('))
                    {
                        $value = explode('(', $value);
                        $value = $value[0];
                    }

                    //get color
                    $color = $this->get_background_color($items);

                    switch($this->field_info['type'])
                    {
                        case 'fieldtype_mapbbcode':
                            //set latlng
                            $this->set_latlng($value);

                            //set data
                            if(strstr($value, ' '))
                            {
                                if($this->is_poligon($value))
                                {
                                    $this->polygon[] = ['coordinates' => $value, 'color' => $color, 'popup' => $this->get_popup($items)];
                                }
                                else
                                {
                                    $this->polyline[] = ['coordinates' => $value, 'color' => $color, 'popup' => $this->get_popup($items)];
                                }
                            }
                            else
                            {
                                $this->markers[] = ['coordinates' => $value, 'color' => $color, 'popup' => $this->get_popup($items)];
                            }

                            if($this->display_sidebar and $obj_count == 0)
                            {
                                //set sidebar
                                if(strlen($this->fields_in_sidebar))
                                {
                                    $name = $text_pattern->output_singe_text($this->fields_in_sidebar, $this->entities_id, $items);
                                }
                                else
                                {
                                    $name = items::get_heading_field($this->entities_id, $items['id'], $items);
                                }

                                $v = explode(' ', $value);
                                $v = explode(',', $v[0]);
                                $lat = $v[0];
                                $lng = $v[1];

                                $this->sidebar[$this->entities_id][] = [
                                    'lat' => $lat,
                                    'lng' => $lng,
                                    'color' => $color,
                                    'name' => $name,
                                ];
                            }
                            break;
                        case 'fieldtype_google_map_directions':
                            $address_array = preg_split("/\\r\\n|\\r|\\n/", $value);

                            foreach($address_array as $address_key => $address)
                            {
                                $value = explode("\t", $address);

                                $lat = $value[0];
                                $lng = $value[1];

                                $this->markers[] = ['id' => $items['id'] . '_' . $map_entities['id'] . '_' . $address_key, 'lat' => $lat, 'lng' => $lng, 'color' => $color, 'icon' => $this->marker_icon, 'entities_id' => $this->entities_id, 'entity_row' => $map_entities['id'], 'name' => $this->get_item_name($map_entities, $items), 'popup' => $this->get_popup($items, $value[2])];
                            }
                            break;
                        default:
                            $value = explode("\t", $value);

                            if (isset($value[0]) and is_numeric($value[0]) and isset($value[1]) and is_numeric($value[1]))
                            {
                                $lat = $value[0];
                                $lng = $value[1];

                                $name = $this->get_item_name($map_entities, $items);

                                $this->markers[] = ['id' => $items['id'] . '_' . $map_entities['id'], 'lat' => $lat, 'lng' => $lng, 'color' => $color, 'icon' => $this->marker_icon, 'entities_id' => $this->entities_id, 'entity_row' => $map_entities['id'], 'name' => $name, 'popup' => $this->get_popup($items)];

                                if($this->display_sidebar)
                                {
                                    //set sidebar
                                    $this->sidebar[$this->entities_id][] = [
                                        'lat' => $lat,
                                        'lng' => $lng,
                                        'color' => $color,
                                        'name' => $name,
                                    ];
                                }
                            }

                            break;
                    }
                }
            }
        }

        //echo '<pre>';
        //print_r($this->markers);
        //print_r($this->polygon);
        //print_r($this->polyline);
        //echo '</pre>';
    }

    /**
     * Resolve a record color: the per-entity marker color, overridden by
     * the background field choice color when configured. Extracted from
     * get_coordinates() so the geometry path reuses it.
     */
    function get_background_color($items)
    {
        global $app_choices_cache, $app_fields_cache, $app_global_choices_cache;

        $color = (strlen($this->marker_color) ? $this->marker_color : '');

        if($this->background)
        {
            if(strlen($items['field_' . $this->background]))
            {
                if(isset($app_fields_cache[$this->entities_id][$this->background]))
                {
                    $cfg = new fields_types_cfg($app_fields_cache[$this->entities_id][$this->background]['configuration']);

                    if($cfg->get('use_global_list') > 0)
                    {
                        if(isset($app_global_choices_cache[$items['field_' . $this->background]]['bg_color']))
                        {
                            $color = $app_global_choices_cache[$items['field_' . $this->background]]['bg_color'];
                        }
                    }
                    else
                    {
                        if(isset($app_choices_cache[$items['field_' . $this->background]]['bg_color']))
                        {
                            $color = $app_choices_cache[$items['field_' . $this->background]]['bg_color'];
                        }
                    }
                }
            }
        }

        return $color;
    }

    /**
     * Turn a fieldtype_unitas_geometry value into a drawable shape plus a
     * companion marker at its representative point. The marker keeps
     * clustering, the sidebar, bounds fitting and the empty-state guard
     * working exactly as they do for ordinary point fields.
     */
    function add_geometry($items, $map_entities)
    {
        $shape = fieldtype_unitas_geometry::parse_for_map($items['field_' . $this->fields_id]);

        if(!$shape)
        {
            return;
        }

        $cfg = new fields_types_cfg($this->field_info['configuration']);

        // Status color first, then the field default, then red
        $color = $this->get_background_color($items);
        if(!strlen($color)) $color = $cfg->get('stroke_color');
        if(!strlen($color)) $color = '#FF0000';

        $weight = (int)$cfg->get('stroke_weight');
        if($weight < 1) $weight = 4;

        $id    = $items['id'] . '_' . $map_entities['id'];
        $popup = $this->get_popup($items);
        $name  = $this->get_item_name($map_entities, $items);

        $this->shapes[] = array(
            'id'          => $id,
            'kind'        => $shape['kind'],
            'points'      => $shape['points'],
            'center'      => $shape['center'],
            'radius_m'    => $shape['radius_m'],
            'color'       => $color,
            'weight'      => $weight,
            'entities_id' => $this->entities_id,
            'entity_row'  => $map_entities['id'],
            'popup'       => $popup,
        );

        $this->markers[] = array(
            'id'          => $id,
            'lat'         => $shape['lat'],
            'lng'         => $shape['lng'],
            'color'       => $color,
            'icon'        => $this->marker_icon,
            'entities_id' => $this->entities_id,
            'entity_row'  => $map_entities['id'],
            'name'        => $name,
            'popup'       => $popup,
        );

        if(!$this->latlng)
        {
            $this->latlng = $shape['lat'] . ',' . $shape['lng'];
        }

        if($this->display_sidebar)
        {
            $this->sidebar[$this->entities_id][] = [
                'lat'   => $shape['lat'],
                'lng'   => $shape['lng'],
                'color' => $color,
                'name'  => $name,
            ];
        }
    }

    /**
     * Display name for a record: the sidebar heading template when set,
     * else the entity heading field. Computed only when a consumer exists
     * (classic sidebar or the v2 layout) to avoid extra work per record.
     */
    private function get_item_name($map_entities, $items)
    {
        if(!$this->display_sidebar && !$this->is_modern)
        {
            return '';
        }

        if(strlen($map_entities['fields_in_sidebar']))
        {
            $text_pattern = new fieldtype_text_pattern;
            return $text_pattern->output_singe_text($map_entities['fields_in_sidebar'], $this->entities_id, $items);
        }

        return items::get_heading_field($this->entities_id, $items['id'], $items);
    }

    /**
     * Structured legend/layer rows for the v2 renderer. Unlike render_legend()
     * this returns EVERY configured entity row (the v2 legend is also the
     * layer visibility control), with the swatch resolved by the classic
     * precedence: icon wins, then marker color, then geometry stroke color.
     */
    static function get_legend_data($reports)
    {
        $rows = array();

        $items_query = db_query("select ce.*, e.name as entity_name, f.type as field_type, f.configuration as field_configuration from app_unitas_pivot_map_reports_entities ce inner join app_entities e on e.id=ce.entities_id left join app_fields f on f.id=ce.fields_id where ce.reports_id='" . (int)$reports['id'] . "' order by ce.id");
        while($items = db_fetch_array($items_query))
        {
            $is_geometry = ($items['field_type'] === 'fieldtype_unitas_geometry');

            $color = strlen($items['marker_color'] ?? '') ? $items['marker_color'] : null;
            if($is_geometry && $color === null)
            {
                $geo_cfg = new fields_types_cfg($items['field_configuration']);
                $color = $geo_cfg->get('stroke_color');
                if(!strlen($color)) $color = '#FF0000';
            }

            $label = (strlen(trim($items['legend_label'] ?? '')) > 0) ? $items['legend_label'] : $items['entity_name'];

            $rows[] = array(
                'key'         => (int)$items['id'],
                'entities_id' => (int)$items['entities_id'],
                'entity_name' => $items['entity_name'],
                'label'       => $label,
                'kind'        => $is_geometry ? 'geometry' : 'marker',
                'icon'        => strlen($items['marker_icon'] ?? '') ? $items['marker_icon'] : null,
                'color'       => $color,
                'count'       => 0,
            );
        }

        return $rows;
    }

    /**
     * Everything the v2 (modern layout) renderer needs, as one array the
     * action json_encodes. Popup HTML is converted to display form here:
     * get_popup() returns addslashes()-escaped single-line HTML meant for
     * direct embedding in classic script strings — the JSON path reverses
     * that and applies the same urldecode + nl2br display transform.
     */
    function get_v2_payload($reports, $resolved)
    {
        $layers = self::get_legend_data($reports);

        // counts by layer key (every geometry item also has a companion marker)
        $counts = array();
        foreach($this->markers as $m)
        {
            $row = (int)($m['entity_row'] ?? 0);
            $counts[$row] = ($counts[$row] ?? 0) + 1;
        }
        foreach($layers as $k => $layer)
        {
            $layers[$k]['count'] = $counts[$layer['key']] ?? 0;
        }

        $markers = array();
        foreach($this->markers as $m)
        {
            $markers[] = array(
                'id'    => (string)$m['id'],
                'layer' => (int)($m['entity_row'] ?? 0),
                'lat'   => (float)$m['lat'],
                'lng'   => (float)$m['lng'],
                'color' => (string)($m['color'] ?? ''),
                'icon'  => (string)($m['icon'] ?? ''),
                'name'  => (string)($m['name'] ?? ''),
                'popup' => $this->v2_popup($m['popup'] ?? ''),
            );
        }

        $shapes = array();
        foreach($this->shapes as $s)
        {
            $shapes[] = array(
                'id'       => (string)$s['id'],
                'layer'    => (int)($s['entity_row'] ?? 0),
                'kind'     => $s['kind'],
                'points'   => $s['points'],
                'center'   => $s['center'],
                'radius_m' => (float)$s['radius_m'],
                'color'    => (string)$s['color'],
                'weight'   => (int)$s['weight'],
                'popup'    => $this->v2_popup($s['popup'] ?? ''),
            );
        }

        return array(
            'report' => array(
                'id'              => (int)$reports['id'],
                'container'       => 'unitas_pmv2_' . (int)$reports['id'],
                'theme'           => $resolved['theme'],
                'theme_choice'    => $resolved['theme_choice'],
                'map_id'          => $resolved['map_id'],
                'zoom'            => (int)$resolved['zoom'],
                'center'          => $resolved['center'],
                'display_sidebar' => (int)$reports['display_sidebar'],
                'display_legend'  => (int)$reports['display_legend'],
                'sidebar_width'   => (strlen($reports['sidebar_width'] ?? '') ? (int)$reports['sidebar_width'] : 250),
                'reload_fn'       => 'unitasPmv2Reload_' . (int)$reports['id'],
            ),
            'layers'  => array_values($layers),
            'markers' => $markers,
            'shapes'  => $shapes,
            'i18n'    => array(
                'search'     => defined('TEXT_SEARCH') ? TEXT_SEARCH : 'Search',
                'no_records' => defined('TEXT_NO_RECORDS_FOUND') ? TEXT_NO_RECORDS_FOUND : 'No records found',
                'layers'     => 'Layers',
                'objects'    => 'Objects',
            ),
        );
    }

    private function v2_popup($popup)
    {
        return nl2br(urldecode(stripslashes((string)$popup)));
    }

    function set_latlng($value)
    {
        if(!$this->latlng)
        {
            $value = explode(' ', $value);

            $this->latlng = $value[0];
        }
    }

    function is_poligon($value)
    {
        $value_array = explode(' ', $value);

        if(count($value_array) != count(array_unique($value_array)))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function get_popup($items, $address = '')
    {
        $html = '';

        $html .= '<h5 class="heading"><a href="' . url_for('items/info', 'path=' . $this->entities_id . '-' . $items['id']) . '" target="_new">' . items::get_heading_field($this->entities_id, $items['id'], $items) . '</a></h5>';


        if(strlen($address))
        {
            $html .= '<p>' . $address . '</p>';
        }

        if(strlen($this->fields_in_popup))
        {
            $html .= '
					<table class="table">
						<tbody>';


            foreach(explode(',', $this->fields_in_popup) as $fields_id)
            {
                $field_query = db_query("select * from app_fields where id='" . $fields_id . "'");
                if($field = db_fetch_array($field_query))
                {
                    //prepare field value
                    $value = items::prepare_field_value_by_type($field, $items);

                    $output_options = array('class' => $field['type'],
                        'value' => $value,
                        'field' => $field,
                        'item' => $items,
                        'is_export' => true,
                        'is_print' => true,
                        'path' => $field['entities_id']);

                    // Geometry returns the encoded polyline for is_export —
                    // popups want the human summary (e.g. 0.24 mi) instead.
                    if($field['type'] === 'fieldtype_unitas_geometry')
                    {
                        unset($output_options['is_export'], $output_options['is_print']);
                        $output_options['is_listing'] = true;
                    }

                    $value = trim(fields_types::output($output_options));

                    if(strlen(strip_tags($value)) > 255 and in_array($field['type'], ['fieldtype_textarea_wysiwyg', 'fieldtype_textarea']))
                        $value = substr(strip_tags($value), 0, 255) . '...';

                    if(strlen($value))
                    {
                        $html .= '
							<tr>
								<td valign="top" style="padding-right: 7px;">' . fields_types::get_option($field['type'], 'name', $field['name']) . ':</td>
								<td valign="top">' . $value . '</td>
							</tr>';
                    }
                }
            }

            $html .= '
						</tbody>
					</table>
					';
        }

        return addslashes(str_replace(array("\n", "\r", "\n\r"), '', $html));
    }

    function render_yandex_js()
    {
        $html = '';

        foreach($this->markers as $v)
        {
            $options = 'preset:"islands#dotIcon"';

            if(strlen($v['icon']))
            {
                $options .= ', iconLayout: "default#image", iconImageHref:"' . $v['icon'] . '"';
            }
            elseif(strlen($v['color']))
            {
                $options .= ', iconColor:"' . $v['color'] . '"';
            }
            
            $html .= '                   
                clusterer.add(new ymaps.Placemark([' . $v['lat'] . ', ' . $v['lng'] . '],{balloonContentBody:"' . nl2br(urldecode($v['popup'])) . '"},{' . $options . '}));';
                        
        }

        return $html;
    }

    function render_google_js()
    {
        $html = '';

        foreach($this->markers as $v)
        {
            $html .= '
                            var myLatlng = new google.maps.LatLng(' . $v['lat'] . ',' . $v['lng'] . ');
					
                            var marker' . $v['id'] . ' = new google.maps.Marker({
                                map: map,
                                position: myLatlng,
                                ' . (strlen($v['icon']) ? 'icon: \'' . $v['icon'] . '\',' : '') . '    
                            });	
                            
                            markers.push(marker' . $v['id'] . ')
							
                            var infowindow = new google.maps.InfoWindow();
						  		
                            google.maps.event.addListener(marker' . $v['id'] . ', "click", function() {
                              infowindow.close();//hide the infowindow
                              infowindow.setContent(\'<div id="content">' . str_replace(array("\n", "\r", "\n\r"), ' ', nl2br(urldecode($v['popup']))) . '</div>\');
                              infowindow.open(map,marker' . $v['id'] . ');
                            });	
				';
        }

        $html .= fieldtype_unitas_geometry::render_map_shapes_js($this->shapes);

        return $html;
    }

    function render_js()
    {
        $html = '';

        foreach($this->markers as $v)
        {
            $html .= '
					L.marker([' . $v['coordinates'] . '],{
					  icon: L.divIcon({
					    className: \'custom-map-marker-icon\',
					    iconSize: new L.Point(25, 41),    
					    html: \'<div class="marker-bg"></div><i class="fa fa-map-marker" ' . (strlen($v['color']) ? 'style="color: ' . $v['color'] . '"' : '') . '></i>\'
					})}
					).addTo(map)
					.bindPopup(\'' . $v['popup'] . '\');
					';
        }

        foreach($this->polygon as $v)
        {
            $html .= '
				L.polygon([[' . str_replace(' ', '],[', $v['coordinates']) . ']]' . (strlen($v['color']) ? ', {color: \'' . $v['color'] . '\'}' : '') . ').addTo(map).bindPopup(\'' . $v['popup'] . '\');';
        }

        foreach($this->polyline as $v)
        {
            $html .= '
				L.polyline([[' . str_replace(' ', '],[', $v['coordinates']) . ']]' . (strlen($v['color']) ? ', {color: \'' . $v['color'] . '\'}' : '') . ').addTo(map).bindPopup(\'' . $v['popup'] . '\');';
        }

        return $html;
    }

    static function has_access($users_groups)
    {
        global $app_user;

        if(in_array($app_user['group_id'], explode(',', $users_groups)) or $app_user['group_id'] == 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    static function get_reports_id_by_map_entity($id, $entiteis_id)
    {
        $reports_info_query = db_query("select * from app_reports where entities_id='" . db_input($entiteis_id) . "' and reports_type='pivot_map" . $id . "'");
        $reports_info = db_fetch_array($reports_info_query);

        return $reports_info['id'];
    }

    static function get_map_type($reports_id)
    {
        global $app_fields_cache;

        $reports_entities_query = db_query("select * from app_unitas_pivot_map_reports_entities where reports_id=" . $reports_id . " order by id limit 1");
        if($reports_entities = db_fetch_array($reports_entities_query))
        {
            switch($app_fields_cache[$reports_entities['entities_id']][$reports_entities['fields_id']]['type'])
            {
                case 'fieldtype_yandex_map':
                    return 'yandex';
                    break;
                case 'fieldtype_google_map':
                case 'fieldtype_google_map_directions':
                case 'fieldtype_unitas_geometry':
                    return "google";
                    break;
                case 'fieldtype_mapbbcode':
                    return "mapbbcode";
                    break;
            }
        }
        else
        {
            return false;
        }
    }

    static function render_legend($reports)
    {
        $html = '';

        if($reports['display_legend'] == 1)
        {
            $html .= '<ul class="list-inline">';

            // Geometry layers are included even without a marker color or icon:
            // they draw shapes, so their legend swatch comes from the shape color.
            $items_query = db_query("select ce.*, e.name, f.type as field_type, f.configuration as field_configuration from app_unitas_pivot_map_reports_entities ce inner join app_entities e on e.id=ce.entities_id left join app_fields f on f.id=ce.fields_id where ce.reports_id='" . $reports['id'] . "' and (length(ce.marker_color)>0 or length(ce.marker_icon)>0 or f.type='fieldtype_unitas_geometry') order by ce.id");
            while($items = db_fetch_array($items_query))
            {
                // Use legend_label if set, otherwise fall back to entity name
                $label = (strlen(trim($items['legend_label'])) > 0) ? $items['legend_label'] : $items['name'];

                // Geometry layers use the marker icon when one is set, so they
                // match the other icon legend items. Without an icon they fall
                // back to a line swatch in the shape color. Only the swatch is
                // colored - the label keeps the default legend text color.
                if($items['field_type'] === 'fieldtype_unitas_geometry')
                {
                    if(strlen($items['marker_icon']))
                    {
                        $html .= '<li><img src="' . $items['marker_icon'] . '"> ' . htmlspecialchars($label) . '</li>';
                    }
                    else
                    {
                        $legend_color = $items['marker_color'];

                        if(!strlen($legend_color))
                        {
                            $geo_cfg = new fields_types_cfg($items['field_configuration']);
                            $legend_color = $geo_cfg->get('stroke_color');
                        }

                        if(!strlen($legend_color)) $legend_color = '#FF0000';

                        $html .= '<li><i class="fa fa-minus" aria-hidden="true" style="color: ' . $legend_color . '; font-weight: bold;"></i> ' . htmlspecialchars($label) . '</li>';
                    }

                    continue;
                }

                if(strlen($items['marker_color']))
                    $html .= '<li style="color: ' . $items['marker_color'] . '"><i class="fa fa-map-marker" aria-hidden="true"></i> ' . htmlspecialchars($label) . '</li>';

                if(strlen($items['marker_icon']))
                    $html .= '<li><img src="' . $items['marker_icon'] . '"> ' . htmlspecialchars($label) . '</li>';
            }

            $html .= '</ul>';
        }

        return $html;
    }

    static function get_sidebar_width($reports)
    {
        return strlen($reports['sidebar_width']) ? (int) $reports['sidebar_width'] : 250;
    }

    function get_sidebar()
    {
        global $app_entities_cache;

        if(!count($this->sidebar))
            return '';

        //print_rr($this->sidebar);   

        $html = '
                <div class="list-group map-sidebar-list ">
                    <div class="list-group-item map-sidebar-item-search">
                        <div class="input-group">
                            ' . input_tag('map_sidebar_search', '', ['class' => 'form-control', 'placeholder' => TEXT_SEARCH]) . '
                            <span class="input-group-btn">
                                <button class="btn btn-default map-sidebar-item-search-reset"><i class="fa fa-times" aria-hidden="true"></i></button>
                            </spa>    
                        </div>
                    </div>
                    
                    <a href="#collapse_geliossoft_objects" class="list-group-item accordion-toggle arrow" data-toggle="collapse"><h4 id="geliossoft_objects_heading" class="list-group-item-heading"></h4></a>
                    <div id="collapse_geliossoft_objects" class="map-sidebar-geliossoft-objects in"></div>';

        foreach($this->sidebar as $entity_id => $items)
        {
            $html .= '
                    <a href="#collapse_' . $entity_id . '" class="list-group-item accordion-toggle arrow" data-toggle="collapse"><h4 class="list-group-item-heading">' . $app_entities_cache[$entity_id]['name'] . ' (' . count($items) . ')</h4></a>
                     <div id="collapse_' . $entity_id . '" class="in">';

            foreach($items as $item)
            {
                $html .= '
                    <a href="#" class="list-group-item map-sidebar-item" lat="' . $item['lat'] . '" lng="' . $item['lng'] . '">' . $item['name'] . '</a>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
    
    static function render_entity_filters_panel($reports)
    {
        $html = '';
        $rendered_entities = array(); // Track rendered entities to avoid duplicates
        
        $report_entities_query = db_query("select * from app_unitas_pivot_map_reports_entities where reports_id={$reports['id']}");
        while($report_entities = db_fetch_array($report_entities_query))
        {
            // Skip if we already rendered filters for this entity
            // (same entity may appear multiple times with different marker styles)
            if(in_array($report_entities['entities_id'], $rendered_entities))
            {
                continue;
            }
            $rendered_entities[] = $report_entities['entities_id'];

            $filters_panel_type = 'pivot_map_reports_entity_filters_panel_' . $reports['id'] . '_' . $report_entities['entities_id'];
            $filters_panel_id = filters_panels::get_id_by_type($report_entities['entities_id'], $filters_panel_type);

            $count_query = db_query("select count(*) as total from app_filters_panels_fields where panels_id='{$filters_panel_id}'");
            $count = db_fetch_array($count_query);

                if($count['total']>0)
                {                 
                    $fiters_reports_id = reports::auto_create_report_by_type($report_entities['entities_id'],$filters_panel_type,true);

                    $filters_panels = new filters_panels($report_entities['entities_id'],$fiters_reports_id,'',0);
                    $filters_panels->set_type($filters_panel_type);
                    $filters_panels->set_items_listing_funciton_name('refetch_pivot_map_reports_entity' . $report_entities['entities_id']);
                    $html .= '
                        <div class="' . $filters_panel_type . '">' . $filters_panels->render_horizontal() . '</div>
                        <script>
                            function refetch_pivot_map_reports_entity' . $report_entities['entities_id'] . '()
                            {                                                               
                                load_pivot_map_report' . $reports['id'] . '()                                 
                            }
                        </script>
                        ';
                }
        }
        
        return $html;
    }

}
