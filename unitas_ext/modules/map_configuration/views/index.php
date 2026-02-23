<?php
$cfg = unitas_map_config::get();
?>

<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption">
            <span class="caption-subject bold uppercase">
                Google Map Configuration
            </span>
        </div>
    </div>

    <div class="portlet-body">

        <form method="post"
              action="<?php echo url_for('unitas_ext/map_configuration/index'); ?>"
              class="form-horizontal">

            <div class="form-group">
                <label class="col-md-3 control-label">Google Maps API Key</label>
                <div class="col-md-4">
                    <input type="text" name="google_map_api_key"
                           value="<?php echo htmlspecialchars($cfg['google_map_api_key']); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Light Map Style ID</label>
                <div class="col-md-4">
                    <input type="text" name="map_style_light"
                           value="<?php echo htmlspecialchars($cfg['map_style_light']); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Dark Map Style ID</label>
                <div class="col-md-4">
                    <input type="text" name="map_style_dark"
                           value="<?php echo htmlspecialchars($cfg['map_style_dark']); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Default Theme</label>
                <div class="col-md-2">
                    <select name="default_theme" class="form-control">
                        <option value="auto"  <?php if($cfg['default_theme']=='auto') echo 'selected'; ?>>Auto</option>
                        <option value="light" <?php if($cfg['default_theme']=='light') echo 'selected'; ?>>Light</option>
                        <option value="dark"  <?php if($cfg['default_theme']=='dark') echo 'selected'; ?>>Dark</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Default Latitude</label>
                <div class="col-md-2">
                    <input type="text" name="default_lat"
                           value="<?php echo htmlspecialchars($cfg['default_lat']); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Default Longitude</label>
                <div class="col-md-2">
                    <input type="text" name="default_lng"
                           value="<?php echo htmlspecialchars($cfg['default_lng']); ?>"
                           class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Default Zoom</label>
                <div class="col-md-2">
                    <select name="default_zoom" class="form-control">
                    <?php
                    for($i=3;$i<=18;$i++)
                    {
                        echo '<option value="'.$i.'" '.($cfg['default_zoom']==$i?'selected':'').'>'.$i.'</option>';
                    }
                    ?>
                    </select>
                </div>
            </div>

            <div class="form-actions right">
                <button type="submit" class="btn btn-primary">
                    <?php echo TEXT_SAVE; ?>
                </button>
            </div>

        </form>

    </div>
</div>
