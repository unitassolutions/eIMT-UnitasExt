<?php
// Entity Buttons - Form View

// Check if we're in a modal
$is_modal = isset($_GET['is_modal']);

// If in modal, use modal classes
if($is_modal) {
    echo '<div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
            <h4 class="modal-title">' . $app_title . '</h4>
          </div>
          <div class="modal-body">';
} else {
    echo '<div class="container">
          <h3 class="page-title">' . $app_title . '</h3>';
}

// Start form
echo '<form action="' . url_for('unitas_ext/entity_buttons/save') . '" method="post" class="form-horizontal" id="unitas-form">';
echo '<input type="hidden" name="id" value="' . (isset($button_data['id']) ? $button_data['id'] : 0) . '">';
// Hidden field for button color - always blue
echo '<input type="hidden" name="button_color" value="btn-primary">';
if($is_modal) {
    echo '<input type="hidden" name="is_modal" value="1">';
}

// Entity selection
echo '<div class="form-group">';
echo '<label class="col-md-3 control-label">' . PLUGIN_UNITAS_SELECT_ENTITY . ' <span class="required-label">*</span></label>';
echo '<div class="col-md-9">';
echo '<select name="entity_id" class="form-control" required>';
echo '<option value="">-- ' . PLUGIN_UNITAS_SELECT_ENTITY . ' --</option>';

foreach($entities as $entity_id => $entity_name)
{
    $selected = (isset($button_data['entity_id']) && $button_data['entity_id'] == $entity_id) ? 'selected' : '';
    echo '<option value="' . $entity_id . '" ' . $selected . '>' . $entity_name . '</option>';
}

echo '</select>';
echo '</div>';
echo '</div>';

// Button title
echo '<div class="form-group">';
echo '<label class="col-md-3 control-label">' . PLUGIN_UNITAS_BUTTON_TITLE . ' <span class="required-label">*</span></label>';
echo '<div class="col-md-9">';
echo '<input type="text" name="button_title" value="' . (isset($button_data['button_title']) ? htmlspecialchars($button_data['button_title']) : '') . '" class="form-control" required>';
echo '</div>';
echo '</div>';

// Button type
echo '<div class="form-group">';
echo '<label class="col-md-3 control-label">' . PLUGIN_UNITAS_BUTTON_TYPE . '</label>';
echo '<div class="col-md-9">';
echo '<select name="button_type" class="form-control" id="button_type">';
echo '<option value="url" ' . ((!isset($button_data['button_type']) || $button_data['button_type'] == 'external' || $button_data['button_type'] == 'url') ? 'selected' : '') . '>URL/Link</option>';
echo '<option value="report" ' . (isset($button_data['button_type']) && $button_data['button_type'] == 'report' ? 'selected' : '') . '>Report</option>';
echo '</select>';
echo '</div>';
echo '</div>';

// URL field
echo '<div class="form-group">';
echo '<label class="col-md-3 control-label">URL <span class="required-label">*</span></label>';
echo '<div class="col-md-9">';

// Determine what to show in URL field
$url_value = '';
if(isset($button_data['button_type'])) {
    if($button_data['button_type'] == 'report' && !empty($button_data['report_id'])) {
        $url_value = 'index.php?module=reports/view&reports_id=' . $button_data['report_id'];
    } elseif(($button_data['button_type'] == 'external' || $button_data['button_type'] == 'url') && !empty($button_data['external_url'])) {
        $url_value = $button_data['external_url'];
    }
}

echo '<input type="text" name="url" id="url_field" value="' . htmlspecialchars($url_value) . '" class="form-control" required placeholder="index.php?module=... or https://...">';
echo '<div class="help-block"><small>';
echo '<strong>Examples:</strong><br>';
echo '- Report: <code>index.php?module=reports/view&reports_id=123</code><br>';
echo '- Form: <code>index.php?module=items/form&path=21</code><br>';
echo '- External: <code>https://www.example.com</code>';
echo '</small></div>';
echo '</div>';
echo '</div>';

// Button icon - optional
echo '<div class="form-group">';
echo '<label class="col-md-3 control-label">Button Icon</label>';
echo '<div class="col-md-9">';
echo '<input type="text" name="button_icon" value="' . (isset($button_data['button_icon']) && $button_data['button_icon'] != 'fa-external-link' ? htmlspecialchars($button_data['button_icon']) : '') . '" class="form-control" placeholder="fa-map, fa-chart, fa-file (leave blank for no icon)">';
echo '<small class="help-block">FontAwesome icon class.</small>';
echo '</div>';
echo '</div>';

// Sort order
echo '<div class="form-group">';
echo '<label class="col-md-3 control-label">' . PLUGIN_UNITAS_SORT_ORDER . '</label>';
echo '<div class="col-md-9">';
echo '<input type="number" name="sort_order" value="' . (isset($button_data['sort_order']) ? $button_data['sort_order'] : 0) . '" class="form-control" style="width: 100px;">';
echo '</div>';
echo '</div>';

// Active status
echo '<div class="form-group">';
echo '<label class="col-md-3 control-label">' . PLUGIN_UNITAS_ACTIVE . '</label>';
echo '<div class="col-md-9">';
echo '<div class="checkbox">';
echo '<label>';
echo '<input type="checkbox" name="is_active" value="1" ' . (isset($button_data['is_active']) && $button_data['is_active'] ? 'checked' : '') . '> Yes';
echo '</label>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</form>';

if($is_modal) {
    echo '</div>'; // Close modal-body
    echo '<div class="modal-footer">';
    echo '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>';
    echo '<button type="button" class="btn btn-primary" id="save-button">Save</button>';
    echo '</div>';
} else {
    echo '<div class="form-group">
            <div class="col-md-9 col-md-offset-3">
                <button type="submit" class="btn btn-primary" onclick="$(\'#unitas-form\').submit()">Save</button>
                <a href="' . url_for('unitas_ext/entity_buttons/index') . '" class="btn btn-default">Cancel</a>
            </div>
          </div>';
    echo '</div>'; // Close container
}
?>

<script>
$(document).ready(function() {
    // Update URL placeholder based on type
    $('#button_type').change(function() {
        if($(this).val() === 'report') {
            $('#url_field').attr('placeholder', 'index.php?module=reports/view&reports_id=...');
        } else {
            $('#url_field').attr('placeholder', 'index.php?module=... or https://...');
        }
    });
    
    <?php if($is_modal): ?>
    // Handle modal form submission with AJAX
    $('#save-button').click(function(e) {
        e.preventDefault();
        
        // Validate form
        if(!$('#unitas-form')[0].checkValidity()) {
            $('#unitas-form')[0].reportValidity();
            return;
        }
        
        // Show loading state
        var $button = $(this);
        var originalText = $button.html();
        $button.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
        
        // Get form data
        var formData = $('#unitas-form').serialize();
        
        // Submit via AJAX
        $.ajax({
            url: $('#unitas-form').attr('action'),
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if(response && response.success) {
                    parent.location.reload();
                    parent.$.fn.modalGlue.close_modal();
                } else {
                    var errorMsg = response && response.message ? response.message : 'Unknown error occurred';
                    alert('Error: ' + errorMsg);
                    $button.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                var responseText = xhr.responseText;
                var errorMsg = 'Server error: ' + error;
                
                if(responseText && responseText.trim().startsWith('<')) {
                    errorMsg = 'Server returned HTML instead of JSON. Please check the save action.';
                    console.error('HTML response:', responseText.substring(0, 200));
                }
                
                alert(errorMsg);
                $button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Handle Enter key in form
    $('#unitas-form').on('keypress', function(e) {
        if(e.which === 13) {
            e.preventDefault();
            $('#save-button').click();
        }
    });
    <?php endif; ?>
});
</script>