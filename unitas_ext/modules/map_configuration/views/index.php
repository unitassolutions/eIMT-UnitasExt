<?php
require_once dirname(__DIR__) . '/helpers/map_config.php';
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/google/unitas_google_keys.php';
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/google/unitas_google_loader.php';

$cfg = unitas_map_config::get();

$server_source = unitas_google_keys::server_source();
$server_hint   = unitas_google_keys::server_hint();
$health        = unitas_ext_installer::shim_health();

$post_url = url_for('unitas_ext/map_configuration/index');
?>

<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption">
            <span class="caption-subject bold uppercase">Google Map Configuration</span>
        </div>
    </div>

    <div class="portlet-body">

        <form method="post" action="<?php echo $post_url; ?>" class="form-horizontal">

            <h4 class="bold" style="margin-top:0;">Google Maps Platform Keys</h4>

            <div class="form-group">
                <label class="col-md-3 control-label">Browser Key</label>
                <div class="col-md-5">
                    <input type="text" name="google_map_api_key"
                           value="<?php echo htmlspecialchars($cfg['google_map_api_key'] ?? ''); ?>"
                           class="form-control">
                    <span class="help-block">Website-restricted. APIs: Maps JavaScript API, Places API (New), Geocoding API. Public by design.</span>
                    <button type="button" id="unitas_test_browser" class="btn btn-sm btn-default">
                        <i class="fa fa-flask"></i> Test Browser Key
                    </button>
                    <span id="unitas_test_browser_result" style="margin-left:10px;"></span>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Server Key</label>
                <div class="col-md-5">
                    <?php if ($server_source === 'constant'): ?>
                        <p class="form-control-static"><i class="fa fa-lock"></i> Set in <code>config/server.php</code> (read-only)</p>
                    <?php else: ?>
                        <input type="password" name="google_server_api_key" value=""
                               autocomplete="new-password" class="form-control"
                               placeholder="<?php echo $server_source === 'database' ? 'Configured (' . htmlspecialchars($server_hint) . ') — leave blank to keep' : 'Not configured'; ?>">
                        <label class="mt-checkbox mt-checkbox-outline" style="margin-top:6px;">
                            <input type="checkbox" name="google_server_api_key_clear" value="1"> Clear server key
                            <span></span>
                        </label>
                    <?php endif; ?>
                    <span class="help-block">IP-restricted. API: Geocoding API only. Never rendered to the browser, logged, or exported.</span>
                    <button type="button" id="unitas_test_server" class="btn btn-sm btn-default">
                        <i class="fa fa-flask"></i> Test Server Key
                    </button>
                    <span id="unitas_test_server_result" style="margin-left:10px;"></span>
                </div>
            </div>

            <hr>
            <h4 class="bold">Map Appearance</h4>

            <div class="form-group">
                <label class="col-md-3 control-label">Light Map Style ID</label>
                <div class="col-md-4">
                    <input type="text" name="map_style_light"
                           value="<?php echo htmlspecialchars($cfg['map_style_light'] ?? ''); ?>" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Dark Map Style ID</label>
                <div class="col-md-4">
                    <input type="text" name="map_style_dark"
                           value="<?php echo htmlspecialchars($cfg['map_style_dark'] ?? ''); ?>" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Default Theme</label>
                <div class="col-md-2">
                    <select name="default_theme" class="form-control">
                        <option value="auto"  <?php if(($cfg['default_theme'] ?? 'auto')=='auto')  echo 'selected'; ?>>Auto</option>
                        <option value="light" <?php if(($cfg['default_theme'] ?? '')=='light') echo 'selected'; ?>>Light</option>
                        <option value="dark"  <?php if(($cfg['default_theme'] ?? '')=='dark')  echo 'selected'; ?>>Dark</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Default Latitude</label>
                <div class="col-md-2">
                    <input type="text" name="default_lat"
                           value="<?php echo htmlspecialchars($cfg['default_lat'] ?? ''); ?>" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Default Longitude</label>
                <div class="col-md-2">
                    <input type="text" name="default_lng"
                           value="<?php echo htmlspecialchars($cfg['default_lng'] ?? ''); ?>" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Default Zoom</label>
                <div class="col-md-2">
                    <select name="default_zoom" class="form-control">
                    <?php for($i=3;$i<=18;$i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo (($cfg['default_zoom'] ?? 8)==$i?'selected':''); ?>><?php echo $i; ?></option>
                    <?php endfor; ?>
                    </select>
                </div>
            </div>

            <hr>
            <h4 class="bold">Address Lookup</h4>

            <div class="form-group">
                <label class="col-md-3 control-label">Region Codes</label>
                <div class="col-md-3">
                    <input type="text" name="autocomplete_region_codes"
                           value="<?php echo htmlspecialchars($cfg['autocomplete_region_codes'] ?? 'us'); ?>" class="form-control">
                    <span class="help-block">2-letter codes, comma-separated (max 15). Biases autocomplete and geocoding.</span>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Bias Radius (metres)</label>
                <div class="col-md-2">
                    <input type="number" name="autocomplete_bias_radius_m" min="0" max="500000"
                           value="<?php echo (int)($cfg['autocomplete_bias_radius_m'] ?? 50000); ?>" class="form-control">
                    <span class="help-block">0 disables bias.</span>
                </div>
            </div>

            <hr style="margin: 15px 0;">
            <div style="margin-left: 0;">
                <button type="submit" class="btn btn-primary"><?php echo TEXT_SAVE; ?></button>
            </div>

        </form>
    </div>
</div>

<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption"><span class="caption-subject bold uppercase">Status</span></div>
    </div>
    <div class="portlet-body">

        <?php
        require_once PLUGIN_UNITAS_EXT_PATH . '/classes/location/unitas_address_autocomplete_rules.php';
        if (unitas_address_autocomplete_rules::legacy_google_autocomplete_active()): ?>
            <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i>
                <b>The legacy Extension &quot;Google Autocomplete&quot; smart input module is still active.</b>
                It loads Google Maps on every page with its own API key, so Unitas maps, autocomplete,
                and the browser key test all run under <em>that</em> key instead of the browser key above.
                Deactivate it under <b>Extension &gt; Modules &gt; Smart Input</b>, then re-run the tests.
            </div>
        <?php endif; ?>

        <h4 class="bold" style="margin-top:0;">Core Integration</h4>
        <?php if ($health['all_ok']): ?>
            <p><span class="label label-success"><i class="fa fa-check"></i> Healthy</span>
               Field type registration, save hook, and menu shims are all in place.</p>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-triangle"></i> One or more core integration shims are missing.
                Address geocoding may be disabled. Open <b>UNITAS Extension &gt; Install</b> to repair.
                <ul style="margin-top:8px;">
                    <li>Field type registration (S1): <?php echo $health['s1'] ? 'OK' : '<b>MISSING</b>'; ?></li>
                    <li>Save hook (S2): <?php echo $health['s2'] ? 'OK' : '<b>MISSING</b>'; ?></li>
                    <li>Menu placement: <?php echo $health['menu'] ? 'OK' : '<b>MISSING</b>'; ?></li>
                </ul>
            </div>
        <?php endif; ?>

        <h4 class="bold">Last Geocoding Error</h4>
        <?php if (!empty($cfg['geocode_last_error']) || !empty($cfg['geocode_last_status'])): ?>
            <p>
                <span class="label label-warning"><?php echo htmlspecialchars($cfg['geocode_last_status'] ?? ''); ?></span>
                <?php echo htmlspecialchars($cfg['geocode_last_error'] ?? ''); ?>
                <?php if (!empty($cfg['geocode_last_error_at'])): ?>
                    <span class="text-muted">(<?php echo htmlspecialchars($cfg['geocode_last_error_at']); ?>)</span>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="text-muted">None recorded.</p>
        <?php endif; ?>

    </div>
</div>

<?php echo unitas_google_loader::emit(); ?>
<script>
(function () {
    var postUrl = <?php echo json_encode($post_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    function setResult(el, ok, text) {
        el.innerHTML = '<span class="label label-' + (ok ? 'success' : 'danger') + '">' +
            (ok ? 'OK' : 'FAIL') + '</span> ' + text;
    }

    // Test server key: server-side geocode of a fixed address using the stored key.
    var sBtn = document.getElementById('unitas_test_server');
    if (sBtn) sBtn.addEventListener('click', function () {
        var out = document.getElementById('unitas_test_server_result');
        out.textContent = 'Testing...';
        var body = new URLSearchParams(); body.set('unitas_test', 'server_key');
        fetch(postUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var msg = (d.google_status ? d.google_status + ' ' : '') + (d.message || d.status || '');
                setResult(out, !!d.ok, msg || (d.ok ? 'Success' : 'Failed'));
            })
            .catch(function () { setResult(out, false, 'Request failed'); });
    });

    // Test browser key: client-side Places (New) autocomplete for a fixed query.
    var bBtn = document.getElementById('unitas_test_browser');
    if (bBtn) bBtn.addEventListener('click', function () {
        var out = document.getElementById('unitas_test_browser_result');
        out.textContent = 'Testing...';
        if (!window.UnitasGMaps) { setResult(out, false, 'Loader not present (no browser key?)'); return; }
        window.UnitasGMaps.load(['places']).then(function () {
            return google.maps.importLibrary('places');
        }).then(function (places) {
            var req = { input: '1600 Amphitheatre', language: 'en-US' };
            var center = (window.UNITAS_GMAPS && window.UNITAS_GMAPS.defaultCenter) || null;
            var radius = (window.UNITAS_GMAPS && window.UNITAS_GMAPS.biasRadiusM) || 0;
            if (center && radius > 0) req.locationBias = { center: center, radius: radius };
            return places.AutocompleteSuggestion.fetchAutocompleteSuggestions(req);
        }).then(function (res) {
            var n = (res && res.suggestions) ? res.suggestions.length : 0;
            setResult(out, n > 0, n + ' suggestion(s) returned');
        }).catch(function (e) {
            setResult(out, false, (e && e.message) ? e.message : 'Failed');
        });
    });
})();
</script>
