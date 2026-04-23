<?php
// Entity Buttons - Admin View
?>

<div class="col-md-12">

    <script>
        $(function(){
            // Any custom jQuery initialization can go here
        })  
    </script>       
        
    <h3 class="page-title"><?php echo PLUGIN_UNITAS_ENTITY_BUTTONS; ?></h3>

    <p>This module allows you to create custom buttons that appear on entity item pages. <a href="#" target="_blank">Read more</a>.</p>

    <?php $add_url = url_for('unitas_ext/entity_buttons/form', 'is_modal=1'); ?>
    <button onclick="open_dialog('<?php echo $add_url; ?>'); return false;" class="btn btn-primary" type="button">Create</button>
    
    <div class="table-scrollable">
        <table class="table table-striped table-bordered table-hover" style="width: auto; min-width: 100%;">
            <thead>
                <tr>
                    <th style="white-space: nowrap; width: 1%;">Action</th>
                    <th style="white-space: nowrap;">Entity</th>
                    <th style="white-space: nowrap;">Title</th>
                    <th style="white-space: nowrap;">Type</th>
                    <th style="width: 100%;">URL</th>
                    <th style="white-space: nowrap; text-align: center;">Active</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($buttons) > 0): ?>
                    <?php foreach($buttons as $button): ?>
                    <tr>
                        <td style="white-space: nowrap; text-align: center;">
                            <?php 
                            $edit_url = url_for('unitas_ext/entity_buttons/form', 'id=' . $button['id'] . '&is_modal=1');
                            ?>
                            <button type="button" class="btn btn-default btn-xs" onclick="open_dialog('<?php echo $edit_url; ?>')" title="Edit">
                                <i class="fa fa-edit"></i>
                            </button>
                            <a href="<?php echo url_for('unitas_ext/entity_buttons/delete', 'id=' . $button['id']); ?>" 
                               class="btn btn-default btn-xs" 
                               title="Delete" 
                               onclick="return confirm('Are you sure?')">
                                <i class="fa fa-trash"></i>
                            </a>
                        </td>
                        <td style="white-space: nowrap;"><?php echo htmlspecialchars($button['entity_name']); ?></td>
                        <td style="white-space: nowrap;"><?php echo htmlspecialchars($button['button_title']); ?></td>
                        <td style="white-space: nowrap;"><?php echo ucfirst($button['button_type']); ?></td>
                        <td style="word-break: break-all;">
                            <?php 
                            $url = '';
                            if($button['button_type'] == 'report' && $button['report_id']) {
                                $url = 'index.php?module=reports/view&reports_id=' . $button['report_id'];
                            } elseif($button['external_url']) {
                                $url = $button['external_url'];
                            }
                            echo htmlspecialchars($url);
                            ?>
                        </td>
                        <td style="white-space: nowrap; text-align: center;">
                            <?php if($button['is_active']): ?>
                                <span class="label label-success">Yes</span>
                            <?php else: ?>
                                <span class="label label-default">No</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No Records Found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
</div>