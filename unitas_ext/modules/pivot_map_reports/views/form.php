<?php

?>

<?php echo ajax_modal_template_header(TEXT_INFO) ?>

<?php echo form_tag('configuration_form', url_for('unitas_ext/pivot_map_reports/reports', 'action=save' . (isset($_GET['id']) ? '&id=' . $_GET['id'] : '')), array('class' => 'form-horizontal')) ?>
<div class="modal-body">
    <div class="form-body">

        <div class="form-group">
            <label class="col-md-4 control-label"><?php echo TEXT_NAME ?></label>
            <div class="col-md-8">	
                <?php echo input_tag('name', $obj['name'], array('class' => 'form-control input-large required')) ?>        
            </div>			
        </div>

        <div class="form-group">
            <label class="col-md-4 control-label"><?php echo TEXT_IN_MENU ?></label>
            <div class="col-md-8">
                <div class="checkbox-list">
                    <label class="checkbox-inline">
                        <?php echo input_checkbox_tag('in_menu', '1', array('checked' => $obj['in_menu'])) ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="col-md-4 control-label">Layout</label>
            <div class="col-md-8">
                <?php echo select_tag('layout', array('classic' => 'Classic', 'modern' => 'Modern (v2)'), ($obj['layout'] ?? 'classic'), array('class' => 'form-control input-medium')) ?>
                <?php echo tooltip_text('Modern layout applies to Google maps only. Other map types always use the classic layout.') ?>
            </div>
        </div>

        <?php
        $choices = [];
        for($i = 3; $i <= 18; $i++)
        {
            $choices[$i] = $i;
        }
        ?>

        <div class="form-group">
            <label class="col-md-4 control-label">Override Default Zoom / Position</label>
            <div class="col-md-8">	
                <div class="checkbox-list">
                    <label class="checkbox-inline">
                        <?php echo input_checkbox_tag('use_form_map_settings','1',
                            array('checked'=>$obj['use_form_map_settings'],'id'=>'use_form_map_settings')) ?>
                    </label>
                </div>
            </div>			
        </div>
        
        <div class="form-group" id="zoom_block">
            <label class="col-md-4 control-label">Override Zoom</label>
            <div class="col-md-8">	
                <?php echo select_tag('zoom', $choices, $obj['zoom'], array('class' => 'form-control input-small')) ?>
            </div>			
        </div>

        <div class="form-group" id="latlng_block">
            <label class="col-md-4 control-label">Override Default Position</label>
            <div class="col-md-8">	
                <?php echo input_tag('latlng', $obj['latlng'], array('class' => 'form-control input-medium')) ?>
                <?php echo tooltip_text(TEXT_DEFAULT_POSITION_TIP) ?>        
            </div>			
        </div>
        
        <div class="form-group">
            <label class="col-md-4 control-label"><?php echo tooltip_icon(TEXT_EXT_MAP_SIDEBAR_TIP) . TEXT_EXT_DISPLAY_OBJECT_LIST ?></label>
            <div class="col-md-8">	
                <div class="checkbox-list"><?php echo select_tag('display_sidebar', ['0'=>TEXT_NO,'1'=>TEXT_YES],$obj['display_sidebar'], array('class' => 'form-control input-small')) ?></div>
            </div>			
        </div> 
        
        <div class="form-group" form_display_rules="display_sidebar:1">
            <label class="col-md-4 control-label"><?php echo TEXT_SIDEBAR_WIDTH ?></label>
            <div class="col-md-8">
                <div class="input-group input-small">
                    <?php echo input_tag('sidebar_width', $obj['sidebar_width'], array('class' => 'form-control input-small')) ?>
                    <span class="input-group-addon">px</span>
                </div>
            </div>			
        </div>

        <div class="form-group">
            <label class="col-md-4 control-label"><?php echo tooltip_icon(TEXT_EXT_ENTITIES_DISPLAY_LEGEND_TIP) . TEXT_EXT_DISPLAY_LEGEND ?></label>
            <div class="col-md-8">	
                <div class="checkbox-list">
                    <label class="checkbox-inline">
                        <?php echo input_checkbox_tag('display_legend', '1', array('checked' => $obj['display_legend'])) ?>
                    </label>
                </div>
            </div>			
        </div> 
        
        <p class="form-section"><?= TEXT_ACCESS ?></p>

        <div class="form-group">
            <label class="col-md-4 control-label"><?php echo tooltip_icon(TEXT_EXT_USERS_GROUPS_INFO) . TEXT_EXT_USERS_GROUPS ?></label>
            <div class="col-md-8">	
                <?php echo select_tag('users_groups[]', access_groups::get_choices(false), $obj['users_groups'], array('class' => 'form-control input-xlarge chosen-select', 'multiple' => 'multiple')) ?>
            </div>			
        </div>
        
        <div class="form-group">
            <label class="col-md-4 control-label"><?php echo tooltip_icon(TEXT_EXT_PUBLIC_ACCESS_REPORT_INFO) . TEXT_EXT_PUBLIC_ACCESS ?></label>
            <div class="col-md-8">	
                <div class="checkbox-list">
                    <label class="checkbox-inline">
                        <?php echo input_checkbox_tag('is_public_access', '1', array('checked' => $obj['is_public_access'])) ?>
                    </label>
                </div>
            </div>			
        </div> 

    </div>
</div> 

<?php echo ajax_modal_template_footer() ?>
</form>

<script>
$(function(){
    $('#configuration_form').validate({
        submitHandler: function (form){
            app_prepare_modal_action_loading(form)
            form.submit();
        }
    });

    function toggle_fields(){
        if($('#use_form_map_settings').is(':checked')){
            $('#zoom_block').show();
            $('#latlng_block').show();
        }else{
            $('#zoom_block').hide();
            $('#latlng_block').hide();
        }
    }

    toggle_fields();

    $(document).on('change','#use_form_map_settings',function(){
        toggle_fields();
    });
});
</script>