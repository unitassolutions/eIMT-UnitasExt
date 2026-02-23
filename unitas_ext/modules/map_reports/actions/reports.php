<?php

//Check Access
if ($app_user['group_id'] > 0)
{
    redirect_to('dashboard/access_forbidden');
}

switch ($app_module_action)
{
    case 'save':

        $sql_data = array(
            'name' => $_POST['name'],
            'entities_id' => $_POST['entities_id'],
            'users_groups' => (isset($_POST['access']) ? json_encode($_POST['access']) : ''),
            'fields_id' => $_POST['fields_id'],
            'in_menu' => (isset($_POST['in_menu']) ? $_POST['in_menu'] : 0),
            'users_groups' => (isset($_POST['users_groups']) ? implode(',', $_POST['users_groups']) : ''),
            'fields_in_popup' => (isset($_POST['fields_in_popup']) ? implode(',', $_POST['fields_in_popup']) : ''),
            'background' => $_POST['background'],
            'zoom' => $_POST['zoom'],
            'latlng' => trim(preg_replace('/ +/', ',', $_POST['latlng'])),
            'is_public_access' => $_POST['is_public_access'] ?? 0,
            'display_sidebar' => $_POST['display_sidebar'],
            'fields_in_sidebar' => $_POST['fields_in_sidebar'],
            'sidebar_width' => $_POST['sidebar_width'],
            'use_form_settings' => $_POST['use_form_settings'] ?? 0,
        );

        if (isset($_GET['id']))
        {
            db_perform('app_unitas_map_reports', $sql_data, 'update', "id='" . db_input($_GET['id']) . "'");
        }
        else
        {
            db_perform('app_unitas_map_reports', $sql_data);
        }

        redirect_to('unitas_ext/map_reports/reports');

        break;

    case 'delete':
        $obj = db_find('app_unitas_map_reports', $_GET['id']);

        db_delete_row('app_unitas_map_reports', $_GET['id']);
        
        $id = _GET('id');
        
        $reports_types = [
            'panel_map_reports' . $id,
            'map_reports' . $id,
            'default_map_reports' . $id,
            'public_map' . $id,
        ];
        
        $report_info_query = db_query("select id from app_reports where reports_type in (" . db_input_in($reports_types). ")");
        while($report_info = db_fetch_array($report_info_query))
        {
            reports::delete_reports_by_id($report_info['id']);
        } 

        $alerts->add(sprintf(TEXT_WARN_DELETE_SUCCESS, $obj['name']), 'success');

        redirect_to('unitas_ext/map_reports/reports');
        break;


    case 'get_entities_fields':

        $entities_id = $_POST['entities_id'];
        $entities_info = db_find('app_entities', $entities_id);

        $obj = array();

        if (isset($_POST['id']))
        {
            $obj = db_find('app_unitas_map_reports', $_POST['id']);
        }
        else
        {
            $obj = db_show_columns('app_unitas_map_reports');
        }

        $html = '';

        $fields_type_by_id_js = '';

        $choices = array();
        $fields_query = db_query("select * from app_fields where type in ('fieldtype_mapbbcode','fieldtype_yandex_map','fieldtype_google_map','fieldtype_google_map_directions') and entities_id='" . db_input($entities_id) . "'");
        while ($fields = db_fetch_array($fields_query))
        {
            $choices[$fields['id']] = $fields['name'];

            $fields_type_by_id_js .= 'fields_type_by_id[' . $fields['id'] . ']="' . $fields['type'] . '"; ' . "\n";
        }

        $html .= '
         <div class="form-group">
          	<label class="col-md-4 control-label" for="allowed_groups">' . TEXT_FIELD . '</label>
            <div class="col-md-8">	
          	   ' . select_tag('fields_id', $choices, $obj['fields_id'], array('class' => 'form-control input-large required', 'onChange' => 'check_field_type()')) . '
               ' . tooltip_text(TEXT_AVAILABLE_FIELS . ': ' . TEXT_FIELDTYPE_MAPBBCODE_TITLE . ', ' . TEXT_FIELDTYPE_GOOGLE_MAP_TITLE) . '
            </div>			
          </div>
          <script>
           var fields_type_by_id = [];
           ' . $fields_type_by_id_js . '    		
          </script>
        ';

        $exclude_types = array("'fieldtype_image_ajax'","'fieldtype_image'", "'fieldtype_attachments'", "'fieldtype_action'", "'fieldtype_parent_item_id'", "'fieldtype_related_records'", "'fieldtype_mapbbcode'", "'fieldtype_section'", "'fieldtype_attachments'");
        $choices = array();
        $fields_query = db_query("select * from app_fields where type not in (" . implode(",", $exclude_types) . ") and entities_id='" . db_input($entities_id) . "'");
        while ($fields = db_fetch_array($fields_query))
        {
            $choices[$fields['id']] = $fields['name'];
        }

        $html .= '
         <div class="form-group">
          	<label class="col-md-4 control-label" for="allowed_groups">' . TEXT_FIELDS_IN_POPUP . '</label>
            <div class="col-md-8">
          	   ' . select_tag('fields_in_popup[]', $choices, $obj['fields_in_popup'], array('class' => 'form-control input-xlarge chosen-select', 'multiple' => 'multiple')) . '
            </div>
          </div>
        ';

        $choices = array('' => '');
        $fields_query = db_query("select * from app_fields where type in ('fieldtype_dropdown','fieldtype_radioboxes','fieldtype_autostatus') and entities_id='" . db_input($entities_id) . "'");
        while ($fields = db_fetch_array($fields_query))
        {
            $choices[$fields['id']] = $fields['name'];
        }

        $html .= '
         <div class="form-group from-group-background">
          	<label class="col-md-4 control-label" for="allowed_groups">' . tooltip_icon(TEXT_EXT_MAP_REPORTS_BACKGROUND_COLOR_INFO) . TEXT_BACKGROUND_COLOR . '</label>
            <div class="col-md-8">
          	   ' . select_tag('background', $choices, $obj['background'], array('class' => 'form-control input-large')) . '               
            </div>
          </div>
        ';
        
        $html .= '
            <div class="form-group">
                <label class="col-md-4 control-label" for="display_sidebar">' . tooltip_icon(TEXT_EXT_MAP_SIDEBAR_TIP) . TEXT_EXT_DISPLAY_OBJECT_LIST . '</label>
                <div class="col-md-8">	
                    <div class="checkbox-list">' . select_tag('display_sidebar', ['0'=>TEXT_NO,'1'=>TEXT_YES],$obj['display_sidebar'], array('class' => 'form-control input-small')) . '</div>
                </div>			
            </div> 

            <div class="form-group" form_display_rules="display_sidebar:1">
                <label class="col-md-4 control-label" for="sidebar_width">' . TEXT_SIDEBAR_WIDTH . '</label>
                <div class="col-md-8">
                    <div class="input-group input-small">
                        ' . input_tag('sidebar_width', $obj['sidebar_width'], array('class' => 'form-control input-small')) . '
                        <span class="input-group-addon">px</span>
                    </div>
                </div>			
            </div>
            
            <div class="form-group" form_display_rules="display_sidebar:1">
                <label class="col-md-4 control-label" for="allowed_groups">' . TEXT_SIDEBAR . ' (' . TEXT_HEADING . ')' . fields::get_available_fields_helper($entities_id, 'fields_in_sidebar') . '</label>
                <div class="col-md-8">
                       ' . textarea_tag('fields_in_sidebar', $obj['fields_in_sidebar'], array('class' => 'form-control input-xlarge code')) . tooltip_text(TEXT_HEADING_TEMPLATE_INFO) . '
                </div>
            </div>
            ';

        echo $html;

        exit();
        break;
}
