<?php
/**
 * Этот файл является частью программы "CRM Руководитель" - конструктор CRM систем для бизнеса
 * https://www.rukovoditel.net.ru/
 * 
 * CRM Руководитель - это свободное программное обеспечение, 
 * распространяемое на условиях GNU GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Автор и правообладатель программы: Харчишина Ольга Александровна (RU), Харчишин Сергей Васильевич (RU).
 * Государственная регистрация программы для ЭВМ: 2023664624
 * https://fips.ru/EGD/3b18c104-1db7-4f2d-83fb-2d38e1474ca3
 */

class map_reports
{

    private $entities_id;
    private $fiters_reports_id;
    private $fields_id;
    public $markers;
    private $polyline;
    private $polygon;
    private $fields_in_popup;
    private $background;
    public $latlng;
    private $sidebar;
    private $display_sidebar;
    private $fields_in_sidebar;
    private $panel_fiters_reports_id;
    private $field_info;
    private $is_public_access;

    function __construct($reports, $fiters_reports_id, $field_info, $panel_fiters_reports_id=false)
    {
        $this->entities_id = $reports['entities_id'];
        $this->fields_id = $reports['fields_id'];
        $this->fields_in_popup = $reports['fields_in_popup'];
        $this->field_info = $field_info;
        $this->background = $reports['background'];
        $this->fiters_reports_id = $fiters_reports_id;
        $this->is_public_access = $reports['is_public_access'];
        $this->markers = array();
        $this->polyline = array();
        $this->polygon = array();
        $this->latlng = false;
        $this->sidebar = [];
        $this->display_sidebar = $reports['display_sidebar'];
        $this->fields_in_sidebar = $reports['fields_in_sidebar'];
        $this->panel_fiters_reports_id = $panel_fiters_reports_id;

        //set default coordinates
        if (strlen($reports['latlng']))
        {
            $this->latlng = $reports['latlng'];
        }

        $this->get_coordinates();
    }
    


    function get_coordinates()
    {
        global $sql_query_having, $app_choices_cache, $app_fields_cache, $app_global_choices_cache;
        
        $text_pattern = new fieldtype_text_pattern;

        $listing_sql_query = '';
        $select_sql_query = '';
        $listing_sql_query_having = '';
        $sql_query_having = array();

        //add filters query
        $listing_sql_query = reports::add_filters_query($this->fiters_reports_id, $listing_sql_query);
                        
        //add palen filters
        if($this->panel_fiters_reports_id)
        {            
            $listing_sql_query = reports::add_filters_query($this->panel_fiters_reports_id, $listing_sql_query);
        }

        //add filter by map
        $listing_sql_query .= " and length(e.field_" . $this->fields_id . ")>0";

        //add access query
        $listing_sql_query = items::add_access_query($this->entities_id, $listing_sql_query);


        //prepare fields sum for formulas
        $sql_query_select = fieldtype_formula::prepare_query_select($this->entities_id, '');

        //prepare having query for formula fields
        if (isset($sql_query_having[$this->entities_id]))
        {
            $listing_sql_query .= reports::prepare_filters_having_query($sql_query_having[$this->entities_id]);
        }

        //prepare parent item query
        if (isset($_GET['path']))
        {
            $path_info = items::parse_path($_GET['path']);
            if ($path_info['parent_entity_item_id'] > 0)
            {
                $listing_sql_query .= " and e.parent_item_id='" . $path_info['parent_entity_item_id'] . "'";
            }
        }

        $listing_sql = "select e.* " . $sql_query_select . " from app_entity_" . $this->entities_id . " e  where e.id>0 " . $listing_sql_query;


        $items_query = db_query($listing_sql);
        while ($items = db_fetch_array($items_query))
        {
            foreach (explode(';', $items['field_' . $this->fields_id]) as $obj_count=>$value)
            {
                //prepare value
                $value = str_replace(array('[map]', '[/map]', ', '), ['', '', ','], trim($value));

                if (strstr($value, '('))
                {
                    $value = explode('(', $value);
                    $value = $value[0];
                }

                $color = '';

                //get color
                if ($this->background)
                {
                    if (strlen($items['field_' . $this->background]))
                    {
                        if (isset($app_fields_cache[$this->entities_id][$this->background]))
                        {
                            $cfg = new fields_types_cfg($app_fields_cache[$this->entities_id][$this->background]['configuration']);

                            if ($cfg->get('use_global_list') > 0)
                            {
                                if (isset($app_global_choices_cache[$items['field_' . $this->background]]['bg_color']))
                                {
                                    $color = $app_global_choices_cache[$items['field_' . $this->background]]['bg_color'];
                                }
                            }
                            else
                            {
                                if (isset($app_choices_cache[$items['field_' . $this->background]]['bg_color']))
                                {
                                    $color = $app_choices_cache[$items['field_' . $this->background]]['bg_color'];
                                }
                            }
                        }
                    }
                }

                switch ($this->field_info['type'])
                {
                    case 'fieldtype_mapbbcode':
                        //set latlng
                        $this->set_latlng($value);
                        
                        
                        //set data
                        if (strstr($value, ' '))
                        {
                            if ($this->is_poligon($value))
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
                        
                        if($this->display_sidebar and $obj_count==0)
                        {                                
                            //set sidebar
                            if(strlen($this->fields_in_sidebar))
                            {
                                $name = $text_pattern->output_singe_text($this->fields_in_sidebar, $this->entities_id,$items);
                            }
                            else
                            {
                                $name = items::get_heading_field($this->entities_id, $items['id'], $items);
                            }
                            
                            $v = explode(' ', $value);
                            $v = explode(',',$v[0]);
                            $lat = $v[0];
                            $lng = $v[1];

                            $this->sidebar[$this->entities_id][] = [
                                'lat'=>$lat,
                                'lng'=>$lng,
                                'color'=>$color,
                                'name' => $name,
                            ];
                        }
                            
                        break;
                    case 'fieldtype_google_map_directions':
                        $address_array = preg_split("/\\r\\n|\\r|\\n/", $value);

                        foreach ($address_array as $address_key => $address)
                        {
                            $value = explode("\t", $address);

                            $lat = $value[0];
                            $lng = $value[1];

                            $this->markers[] = ['id' => $items['id'] . '_' . $address_key, 'lat' => $lat, 'lng' => $lng, 'color' => $color, 'popup' => $this->get_popup($items, $value[2])];
                        }
                        break;
                    default:
                        $value = explode("\t", $value);

                        if (isset($value[0]) and is_numeric($value[0]) and isset($value[1]) and is_numeric($value[1]))
                        {
                            $lat = $value[0];
                            $lng = $value[1];

                            $this->markers[] = ['id' => $items['id'], 'lat' => $lat, 'lng' => $lng, 'color' => $color, 'popup' => $this->get_popup($items)];
                            
                            if($this->display_sidebar)
                            {                                
                                //set sidebar
                                if(strlen($this->fields_in_sidebar))
                                {
                                    $name = $text_pattern->output_singe_text($this->fields_in_sidebar, $this->entities_id,$items);
                                }
                                else
                                {
                                    $name = items::get_heading_field($this->entities_id, $items['id'], $items);
                                }

                                $this->sidebar[$this->entities_id][] = [
                                    'lat'=>$lat,
                                    'lng'=>$lng,
                                    'color'=>$color,
                                    'name' => $name,
                                ];
                            }
                        }
                        break;
                }
            }
        }

        //echo '<pre>';
        //print_r($this->markers);
        //print_r($this->polygon);
        //print_r($this->polyline);
        //echo '</pre>';
    }

    function set_latlng($value)
    {
        if (!$this->latlng)
        {
            $value = explode(' ', $value);

            $this->latlng = $value[0];
        }
    }

    function is_poligon($value)
    {
        $value_array = explode(' ', $value);

        if (count($value_array) != count(array_unique($value_array)))
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
        global $app_layout;
        
        $html = '';

        if($app_layout == 'public_map_layout.php')
        {
            $html .= '<h5 class="heading">' . items::get_heading_field($this->entities_id, $items['id'], $items) . '</h5>';  
        }
        else
        {                    
            $html .= '<h5 class="heading"><a href="' . url_for('items/info', 'path=' . $this->entities_id . '-' . $items['id']) . '" target="_new">' . items::get_heading_field($this->entities_id, $items['id'], $items) . '</a></h5>';
        }


        if (strlen($address))
        {
            $html .= '<p>' . $address . '</p>';
        }

        if (strlen($this->fields_in_popup))
        {
            $html .= '
					<table class="table">
						<tbody>';


            foreach (explode(',', $this->fields_in_popup) as $fields_id)
            {
                $field_query = db_query("select * from app_fields where id='" . $fields_id . "'");
                if ($field = db_fetch_array($field_query))
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

                    $value = trim(fields_types::output($output_options));

                    if (strlen(strip_tags($value)) > 255 and in_array($field['type'], ['fieldtype_textarea_wysiwyg', 'fieldtype_textarea']))
                        $value = substr(strip_tags($value), 0, 255) . '...';

                    if (strlen($value))
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

        foreach ($this->markers as $v)
        {
            $options = 'preset:"islands#dotIcon"';
                
            if(strlen($v['color']))
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

        foreach ($this->markers as $v)
        {
            $html .= '
		var myLatlng = new google.maps.LatLng(' . $v['lat'] . ',' . $v['lng'] . ');
					
                var marker' . $v['id'] . ' = new google.maps.Marker({
                    map: map,
                    position: myLatlng,									
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

        return $html;
    }

    function render_js()
    {
        $html = '';

        foreach ($this->markers as $v)
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

        foreach ($this->polygon as $v)
        {
            $html .= '
				L.polygon([[' . str_replace(' ', '],[', $v['coordinates']) . ']]' . (strlen($v['color']) ? ', {color: \'' . $v['color'] . '\'}' : '') . ').addTo(map).bindPopup(\'' . $v['popup'] . '\');';
        }

        foreach ($this->polyline as $v)
        {
            $html .= '
				L.polyline([[' . str_replace(' ', '],[', $v['coordinates']) . ']]' . (strlen($v['color']) ? ', {color: \'' . $v['color'] . '\'}' : '') . ').addTo(map).bindPopup(\'' . $v['popup'] . '\');';
        }

        return $html;
    }

    static function has_access($users_groups)
    {
        global $app_user;

        if (in_array($app_user['group_id'], explode(',', $users_groups??'')) or $app_user['group_id'] == 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    
        function get_sidebar()
        {
            global $app_entities_cache;
            
            if(!count($this->sidebar)) return '';
            
            //print_rr($this->sidebar);   
            
            $html = '
                <div class="list-group map-sidebar-list ">
                    <div class="list-group-item map-sidebar-item-search">
                        <div class="input-group">
                            ' . input_tag('map_sidebar_search','',['class'=>'form-control','placeholder'=>TEXT_SEARCH]). '
                            <span class="input-group-btn">
                                <button class="btn btn-default map-sidebar-item-search-reset"><i class="fa fa-times" aria-hidden="true"></i></button>
                            </spa>    
                        </div>
                    </div>
                    
                    <a href="#collapse_geliossoft_objects" class="list-group-item accordion-toggle arrow" data-toggle="collapse"><h4 id="geliossoft_objects_heading" class="list-group-item-heading"></h4></a>
                    <div id="collapse_geliossoft_objects" class="map-sidebar-geliossoft-objects in"></div>';
            
            foreach($this->sidebar as $entity_id=>$items)
            {
                $html .= '
                    <a href="#collapse_' . $entity_id . '" class="list-group-item accordion-toggle arrow" data-toggle="collapse"><h4 class="list-group-item-heading">' . $app_entities_cache[$entity_id]['name'] . ' (' . count($items) . ')</h4></a>
                     <div id="collapse_' . $entity_id . '" class="in">';
                
                foreach($items as $item)
                {
                    $html .= '
                    <a href="#" class="list-group-item map-sidebar-item" lat="' . $item['lat'] . '" lng="' . $item['lng']. '">' . $item['name'] . '</a>';
                }
                
                $html .='</div>';
            }
                        
            $html .= '</div>';
            
            return $html;
        }    

}
