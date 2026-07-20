<?php
$cfg = unitas_map_config::get();

// Test-lookup point: the configured default map center (Chatham County fallback)
$test_lat = is_numeric($cfg['default_lat'] ?? '') ? (float)$cfg['default_lat'] : 35.7596;
$test_lng = is_numeric($cfg['default_lng'] ?? '') ? (float)$cfg['default_lng'] : -79.0193;

// ── Closure Feed (CIFS) settings ────────────────────────────────────────────
$feed_map = json_decode((string)($cfg['waze_feed_config'] ?? ''), true);
if (!is_array($feed_map)) {
    $feed_map = array();
}
$feed_enabled = (int)($cfg['waze_feed_enabled'] ?? 0);
$feed_key     = trim((string)($cfg['waze_feed_key'] ?? ''));
$feed_window  = (int)($cfg['waze_feed_window'] ?? 15);
if ($feed_window < 5)   { $feed_window = 5; }
if ($feed_window > 120) { $feed_window = 120; }

$feed_complete = true;
foreach (array('entities_id', 'geometry_field', 'street_field', 'status_field', 'push_field', 'start_field') as $feed_rk) {
    if ((int)($feed_map[$feed_rk] ?? 0) <= 0) {
        $feed_complete = false;
        break;
    }
}
if (trim((string)($feed_map['status_closed_choice'] ?? '')) === '') {
    $feed_complete = false;
}

// Absolute feed URL for display (rendered on this admin page only)
$feed_url = '';
if ($feed_key !== '') {
    $feed_scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443)) ? 'https' : 'http';
    $feed_base   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    $feed_url    = $feed_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $feed_base . '/index.php?module=unitas_ext/waze_integration/public&key=' . urlencode($feed_key);
}

// Entity choices for the mapping form
$feed_entities = array('' => '');
$feed_eq = db_query("select id, name from app_entities order by name");
while ($feed_e = db_fetch_array($feed_eq)) {
    $feed_entities[$feed_e['id']] = $feed_e['name'];
}
?>

<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption">
            <span class="caption-subject bold uppercase">
                Waze Integration
            </span>
        </div>
    </div>

    <div class="portlet-body">

        <p style="max-width: 700px;">
            The reverse-geocoding token enables automatic street-name lookup when a road
            closure is drawn on a Geometry (Google Map) field. Obtain the token in the
            <strong>Waze Partner Hub</strong> under Toolbox &gt; Partner feed &gt;
            Reverse Geocoding Token. Leaving the token blank disables the lookup —
            manual entry of street names continues to work as before.
        </p>

        <form method="post"
              action="<?php echo url_for('unitas_ext/waze_integration/index'); ?>"
              class="form-horizontal">
            <input type="hidden" name="form_action" value="save_lookup">

            <div class="form-group">
                <label class="col-md-3 control-label">Reverse Geocoding Token</label>
                <div class="col-md-4">
                    <input type="text" name="waze_geocoding_token"
                           value="<?php echo htmlspecialchars($cfg['waze_geocoding_token'] ?? ''); ?>"
                           class="form-control" autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">API Region</label>
                <div class="col-md-2">
                    <select name="waze_region" class="form-control">
                        <option value="na"  <?php if(($cfg['waze_region'] ?? 'na')=='na')  echo 'selected'; ?>>North America</option>
                        <option value="row" <?php if(($cfg['waze_region'] ?? 'na')=='row') echo 'selected'; ?>>Rest of World</option>
                        <option value="il"  <?php if(($cfg['waze_region'] ?? 'na')=='il')  echo 'selected'; ?>>Israel</option>
                    </select>
                </div>
            </div>

            <hr style="margin: 15px 0;">

            <div style="margin-left: 0;">
                <button type="submit" class="btn btn-primary">
                    <?php echo TEXT_SAVE; ?>
                </button>
                <button type="button" class="btn btn-default" id="waze_test_btn" style="margin-left: 8px;">
                    <i class="fa fa-map-signs"></i> Test Lookup
                </button>
                <span id="waze_test_result" style="margin-left: 10px; font-size: 13px;"></span>
            </div>

            <p class="help-block" style="margin-top: 10px;">
                Test Lookup uses the <em>saved</em> token against the default map center
                (<?php echo $test_lat; ?>, <?php echo $test_lng; ?>). Save the settings first,
                then test.
            </p>

        </form>

    </div>
</div>

<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption">
            <span class="caption-subject bold uppercase">
                Closure Feed (CIFS)
            </span>
        </div>
    </div>

    <div class="portlet-body">

        <?php if ($feed_enabled && !$feed_complete): ?>
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i>
            The feed is enabled but the field mapping is incomplete — the feed URL currently
            returns an empty incident list. Complete and save the mapping below.
        </div>
        <?php endif; ?>

        <p style="max-width: 700px;">
            Publishes active road closures (Push to Waze checked, status Closed, polyline drawn)
            to the Waze app. Waze reads the feed URL every few minutes. Every response marks each
            closure to expire after the rolling window below, so a record that is reopened or
            unpushed clears from Waze within roughly the window plus one polling cycle.
        </p>

        <?php if ($feed_url !== ''): ?>
        <div style="margin-bottom: 15px;">
            <label class="control-label" style="font-weight: bold;">Feed URL — secret, paste only into the Waze Partner Hub</label>
            <input type="text" readonly id="waze_feed_url" class="form-control"
                   value="<?php echo htmlspecialchars($feed_url); ?>" onclick="this.select();">
            <p class="help-block">
                Treat this URL like a password: requests without the exact key receive a blank 404.
                Do not link it anywhere and do not add it to robots.txt (listing it there would
                advertise the path).
                <a href="<?php echo htmlspecialchars($feed_url); ?>" target="_blank" rel="noopener">Open feed</a>
            </p>
        </div>
        <?php endif; ?>

        <form method="post" action="<?php echo url_for('unitas_ext/waze_integration/index'); ?>"
              style="margin-bottom: 15px;"
              onsubmit="return confirm('Waze will stop accepting the current URL immediately. Update the Waze Partner Hub with the new URL after regenerating. Continue?');">
            <input type="hidden" name="form_action" value="regenerate_key">
            <button type="submit" class="btn btn-default btn-sm">
                <i class="fa fa-key"></i>
                <?php echo $feed_key === '' ? 'Generate Feed Key' : 'Regenerate Feed Key'; ?>
            </button>
        </form>

        <hr style="margin: 15px 0;">

        <form method="post" action="<?php echo url_for('unitas_ext/waze_integration/index'); ?>"
              class="form-horizontal">
            <input type="hidden" name="form_action" value="save_feed">

            <div class="form-group">
                <label class="col-md-4 control-label">Enable Feed</label>
                <div class="col-md-8">
                    <input type="checkbox" name="waze_feed_enabled" value="1" <?php if ($feed_enabled) echo 'checked'; ?>>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-4 control-label">Expiry Window (minutes)</label>
                <div class="col-md-2">
                    <input type="number" name="waze_feed_window" min="5" max="120"
                           value="<?php echo $feed_window; ?>" class="form-control">
                </div>
                <div class="col-md-6">
                    <p class="help-block" style="margin-top: 7px;">
                        Each response marks closures to expire this many minutes ahead. While a
                        closure stays in the feed the window keeps rolling forward, so it never
                        actually expires — anything removed from the feed clears within the window.
                    </p>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-4 control-label">Road Closures Entity</label>
                <div class="col-md-8">
                    <?php echo select_tag('feed_entities_id', $feed_entities, (string)($feed_map['entities_id'] ?? ''), array('class' => 'form-control input-large', 'onChange' => 'unitasFeedLoadFields(this.value)')); ?>
                </div>
            </div>

            <div id="waze_feed_fields"></div>

            <hr style="margin: 15px 0;">

            <div style="margin-left: 0;">
                <button type="submit" class="btn btn-primary">
                    <?php echo TEXT_SAVE; ?>
                </button>
            </div>

        </form>

    </div>
</div>

<script>
function unitasFeedLoadFields(entitiesId) {
    if (!entitiesId || entitiesId < 1) {
        $('#waze_feed_fields').html('');
        return;
    }
    $('#waze_feed_fields').html('<div class="ajax-loading"></div>');
    $('#waze_feed_fields').load('<?php echo url_for('unitas_ext/waze_integration/ajax_feed_fields', 'action=get_fields'); ?>', { entities_id: entitiesId }, function (response, status, xhr) {
        if (status == 'error') {
            $(this).html('<div class="alert alert-danger">Error loading fields: ' + xhr.status + ' ' + xhr.statusText + '</div>');
        } else if (typeof appHandleUniform === 'function') {
            appHandleUniform();
        }
    });
}

function unitasFeedLoadChoices(kind, fieldsId) {
    var target = '#feed_' + kind + '_choices';
    if (!fieldsId || fieldsId < 1) {
        $(target).html('');
        return;
    }
    $(target).load('<?php echo url_for('unitas_ext/waze_integration/ajax_feed_fields', 'action=get_choices'); ?>', { fields_id: fieldsId, kind: kind }, function (response, status) {
        if (status == 'error') {
            $(target).html('');
        } else if (typeof appHandleUniform === 'function') {
            appHandleUniform();
        }
    });
}

$(function () {
    var unitasFeedSavedEntity = <?php echo (int)($feed_map['entities_id'] ?? 0); ?>;
    if (unitasFeedSavedEntity > 0) {
        unitasFeedLoadFields(unitasFeedSavedEntity);
    }
});
</script>

<script>
$(function() {
    $('#waze_test_btn').click(function() {
        var $out = $('#waze_test_result');
        $out.css('color', '#666').text('Testing...');

        $.ajax({
            url: 'index.php?module=unitas_ext/waze_integration/ajax_reverse_geocode',
            type: 'POST',
            dataType: 'json',
            timeout: 15000,
            data: { points: JSON.stringify([{ lat: <?php echo $test_lat; ?>, lng: <?php echo $test_lng; ?> }]) },
            success: function(r) {
                if (r && r.success && r.results && r.results[0] && r.results[0].length > 0) {
                    var e = r.results[0][0];
                    $out.css('color', '#3c763d').text('Success — nearest street: ' + e.names[0] + ' (' + Math.round(e.distance) + ' m away)');
                } else if (r && r.success) {
                    $out.css('color', '#8a6d3b').text('Token accepted, but no streets found within 50 m of the default map center.');
                } else if (r && r.error === 'disabled') {
                    $out.css('color', '#a94442').text('No token saved yet — save the settings first.');
                } else if (r && r.error === 'upstream') {
                    $out.css('color', '#a94442').text('Lookup failed — the token may be invalid or Waze is unreachable.');
                } else {
                    $out.css('color', '#a94442').text('Lookup failed (' + (r && r.error ? r.error : 'unknown') + ').');
                }
            },
            error: function() {
                $out.css('color', '#a94442').text('Request failed — check the server logs.');
            }
        });
    });
});
</script>
