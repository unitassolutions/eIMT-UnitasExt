<?php
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/location/unitas_location_migration.php';
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/location/unitas_location_value.php';
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/location/unitas_address_autocomplete_rules.php';
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/google/unitas_google_keys.php';

$preflight       = unitas_location_migration::preflight();
$location_fields = unitas_location_migration::location_fields();
$gmap_blocked    = !unitas_ext_installer::core_gmap_fields_present();

$post_url  = url_for('unitas_ext/location_tools/index');
$ajax_url  = url_for('unitas_ext/location_tools/ajax_regeocode', 'action=batch');

// Health tab filters (GET)
$h_statuses = isset($_GET['h_status']) && is_array($_GET['h_status'])
    ? array_values(array_intersect($_GET['h_status'], array('approximate', 'not_found', 'error')))
    : array('approximate', 'not_found', 'error');
$h_entity = (int)($_GET['h_entity'] ?? 0);
$active_tab = isset($_GET['h_status']) || $h_entity ? 'health' : 'migration';

// Collect health rows (capped per field to keep the page bounded).
$health_rows = array();
$health_capped = false;
if ($h_statuses) {
    foreach ($location_fields as $lf) {
        $eid = (int)$lf['entities_id'];
        if ($h_entity && $eid !== $h_entity) continue;
        $fid = (int)$lf['id'];
        $likes = array();
        foreach ($h_statuses as $st) {
            $likes[] = "field_{$fid} like '%\t" . $st . "'";
        }
        $q = db_query("select id, field_{$fid} as v from app_entity_{$eid} where " . implode(' or ', $likes) . " order by id desc limit 200");
        $n = 0;
        while ($r = db_fetch_array($q)) {
            $p = unitas_location_value::parse($r['v']);
            $health_rows[] = array(
                'entity' => $lf['entity_name'], 'field' => $lf['name'],
                'item_id' => (int)$r['id'], 'path' => $eid . '-' . (int)$r['id'],
                'address' => $p['address'], 'status' => $p['status'],
            );
            $n++;
        }
        if ($n >= 200) $health_capped = true;
    }
}
?>

<ul class="nav nav-tabs">
    <li class="<?php echo $active_tab === 'migration' ? 'active' : ''; ?>"><a href="#lt-migration" data-toggle="tab">Migration</a></li>
    <li><a href="#lt-regeocode" data-toggle="tab">Re-geocode</a></li>
    <li class="<?php echo $active_tab === 'health' ? 'active' : ''; ?>"><a href="#lt-health" data-toggle="tab">Location Health</a></li>
</ul>

<div class="tab-content" style="padding-top:15px;">

<!-- ── Migration ─────────────────────────────────────────────────────────── -->
<div class="tab-pane <?php echo $active_tab === 'migration' ? 'active' : ''; ?>" id="lt-migration">

    <?php if (!empty($location_tools_results)): ?>
        <div class="portlet light bordered"><div class="portlet-body">
            <h4 class="bold" style="margin-top:0;">Conversion Results</h4>
            <ul>
            <?php foreach ($location_tools_results as $res): ?>
                <li>
                    <?php echo $res['ok'] ? '<span class="label label-success">OK</span>' : '<span class="label label-danger">FAIL</span>'; ?>
                    <?php echo htmlspecialchars(($res['label'] ?? '') . ': ' . $res['message']); ?>
                    <?php if (!empty($res['rows_rewritten'])) echo ' — ' . (int)$res['rows_rewritten'] . ' value(s) rewritten'; ?>
                </li>
            <?php endforeach; ?>
            </ul>
            <p><b>Next:</b> run <a href="#lt-regeocode" data-toggle="tab">Re-geocode</a> to fill records without coordinates,
               review converted fields in the form designer (they now appear in forms), and check Location Health.</p>
        </div></div>
    <?php endif; ?>

    <?php if (unitas_address_autocomplete_rules::legacy_google_autocomplete_active()): ?>
        <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i>
            The legacy Extension <b>Google Autocomplete</b> smart input module is still active.
            Deactivate it under <b>Extension &gt; Modules &gt; Smart Input</b> as part of the cutover.
        </div>
    <?php endif; ?>

    <div class="portlet light bordered"><div class="portlet-body">
        <h4 class="bold" style="margin-top:0;">Core Google Map Fields</h4>
        <p>
            Core endpoint <code>items/google_map</code>:
            <?php echo $gmap_blocked
                ? '<span class="label label-success">blocked</span> (no core Google map fields remain)'
                : '<span class="label label-default">active</span> (blocks automatically once no core Google map fields remain)'; ?>
        </p>

        <?php if (!$preflight): ?>
            <p class="text-muted">No core Google map fields exist. Nothing to migrate.</p>
        <?php else: ?>
        <form method="post" action="<?php echo $post_url; ?>">
            <input type="hidden" name="form_action" value="convert">
            <table class="table table-striped table-bordered">
                <thead><tr>
                    <th></th><th>Entity</th><th>Field</th><th>Type</th><th>Address Source</th>
                    <th>Records (with coords)</th><th>Column</th><th>Per-field Key</th><th>Smart Input</th><th>Eligible</th>
                </tr></thead>
                <tbody>
                <?php foreach ($preflight as $a): ?>
                    <tr>
                        <td><?php if ($a['eligible']): ?><input type="checkbox" name="convert_fields[]" value="<?php echo (int)$a['id']; ?>"><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($a['entity_name'] ?? ('#' . $a['entities_id'])); ?></td>
                        <td><?php echo htmlspecialchars($a['name']); ?> <span class="text-muted">#<?php echo (int)$a['id']; ?></span></td>
                        <td><code><?php echo htmlspecialchars(str_replace('fieldtype_', '', $a['type'])); ?></code></td>
                        <td><?php echo $a['src_id'] ? htmlspecialchars($a['src_name']) . ' <span class="text-muted">#' . (int)$a['src_id'] . '</span>' : '<span class="text-danger">' . htmlspecialchars($a['address_pattern']) . '</span>'; ?></td>
                        <td><?php echo (int)$a['records']; ?> (<?php echo (int)$a['with_coords']; ?>)</td>
                        <td><?php echo htmlspecialchars($a['column_type']); echo $a['has_index'] ? ' <span class="label label-warning">indexed</span>' : ''; ?></td>
                        <td><?php echo $a['has_key'] ? 'yes' : '<span class="text-muted">no</span>'; ?></td>
                        <td><?php echo $a['smart_input_rule'] ? 'yes' : '<span class="text-muted">no</span>'; ?></td>
                        <td>
                            <?php if ($a['eligible']): ?>
                                <span class="label label-success">yes</span>
                            <?php else: ?>
                                <span class="label label-danger" title="<?php echo htmlspecialchars(implode('; ', $a['reasons'])); ?>">no</span>
                                <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars(implode('; ', $a['reasons'])); ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="alert alert-info" style="margin-top:10px;">
                Conversion keeps field ids and coordinates, moves the address source into the field configuration,
                removes the per-field API key, and widens the column to TEXT (run large tables in a maintenance window).
                Converted fields <b>appear in entry forms</b> at their configured tab position.
            </div>

            <label class="mt-checkbox mt-checkbox-outline" style="display:block;">
                <input type="checkbox" name="confirm_backup" value="1"> I have a verified database backup taken today. <span></span>
            </label>
            <label class="mt-checkbox mt-checkbox-outline" style="display:block;">
                <input type="checkbox" name="confirm_keys" value="1"> The browser and server key tests both pass (UNITAS Extension &gt; Google Map). <span></span>
            </label>

            <button type="submit" class="btn btn-primary" style="margin-top:8px;"
                    onclick="return confirm('Convert the selected fields now?');">
                <i class="fa fa-exchange"></i> Convert Selected Fields
            </button>
        </form>
        <?php endif; ?>
    </div></div>
</div>

<!-- ── Re-geocode ────────────────────────────────────────────────────────── -->
<div class="tab-pane" id="lt-regeocode">
    <div class="portlet light bordered"><div class="portlet-body">
        <h4 class="bold" style="margin-top:0;">Re-geocode Location Fields</h4>
        <p class="text-muted">
            Scans every record of each location field and geocodes the ones that need it
            (no coordinates, a changed address, or a previous error). Records whose stored
            address already matches are skipped without a Google request.
        </p>

        <?php if (!$location_fields): ?>
            <p class="text-muted">No location fields exist yet.</p>
        <?php else: ?>
            <label class="mt-checkbox mt-checkbox-outline" style="display:block;">
                <input type="checkbox" id="rg_include_not_found" value="1"> Also retry records marked <code>not_found</code> <span></span>
            </label>
            <label class="mt-checkbox mt-checkbox-outline" style="display:block;">
                <input type="checkbox" id="rg_include_approximate" value="1"> Also retry records marked <code>approximate</code> <span></span>
            </label>

            <p style="margin-top:8px;">
                <button type="button" id="rg_start" class="btn btn-primary"><i class="fa fa-refresh"></i> Start</button>
                <button type="button" id="rg_stop" class="btn btn-default" disabled>Stop</button>
            </p>

            <div id="rg_progress" class="text-muted"></div>
            <div id="rg_summary" style="margin-top:8px;"></div>
        <?php endif; ?>
    </div></div>
</div>

<!-- ── Location health ───────────────────────────────────────────────────── -->
<div class="tab-pane <?php echo $active_tab === 'health' ? 'active' : ''; ?>" id="lt-health">
    <div class="portlet light bordered"><div class="portlet-body">
        <h4 class="bold" style="margin-top:0;">Location Health</h4>
        <p class="text-muted">Records that will be missing or unreliable on maps. Fix the address or place the pin manually.</p>

        <form method="get" action="index.php" class="form-inline" style="margin-bottom:12px;">
            <input type="hidden" name="module" value="unitas_ext/location_tools/index">
            <?php foreach (array('approximate', 'not_found', 'error') as $st): ?>
                <label class="mt-checkbox mt-checkbox-outline" style="margin-right:12px;">
                    <input type="checkbox" name="h_status[]" value="<?php echo $st; ?>" <?php echo in_array($st, $h_statuses, true) ? 'checked' : ''; ?>>
                    <code><?php echo $st; ?></code> <span></span>
                </label>
            <?php endforeach; ?>
            <select name="h_entity" class="form-control input-small">
                <option value="0">All entities</option>
                <?php
                $seen = array();
                foreach ($location_fields as $lf) {
                    $eid = (int)$lf['entities_id'];
                    if (isset($seen[$eid])) continue;
                    $seen[$eid] = 1;
                    echo '<option value="' . $eid . '"' . ($h_entity === $eid ? ' selected' : '') . '>' . htmlspecialchars($lf['entity_name']) . '</option>';
                }
                ?>
            </select>
            <button type="submit" class="btn btn-default btn-sm">Filter</button>
        </form>

        <?php if (!$health_rows): ?>
            <p><span class="label label-success">All clear</span> No records match the selected statuses.</p>
        <?php else: ?>
            <?php if ($health_capped): ?>
                <div class="alert alert-info">Showing the newest 200 matches per field; narrow the filters for more detail.</div>
            <?php endif; ?>
            <table class="table table-striped table-bordered">
                <thead><tr><th>Entity</th><th>Record</th><th>Address</th><th>Status</th><th>Field</th></tr></thead>
                <tbody>
                <?php foreach ($health_rows as $hr): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($hr['entity']); ?></td>
                        <td><a href="<?php echo url_for('items/info', 'path=' . $hr['path']); ?>">#<?php echo (int)$hr['item_id']; ?></a></td>
                        <td><?php echo htmlspecialchars($hr['address']); ?></td>
                        <td>
                            <?php $cls = ($hr['status'] === 'error') ? 'label-danger' : 'label-warning'; ?>
                            <span class="label <?php echo $cls; ?>"><?php echo htmlspecialchars($hr['status']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($hr['field']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div></div>
</div>

</div>

<script>
(function () {
    var ajaxUrl = <?php echo json_encode($ajax_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var fields = <?php
        $rg = array();
        foreach ($location_fields as $lf) {
            $rg[] = array('id' => (int)$lf['id'], 'label' => $lf['entity_name'] . ' / ' . $lf['name']);
        }
        echo json_encode($rg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    ?>;

    var startBtn = document.getElementById('rg_start');
    var stopBtn = document.getElementById('rg_stop');
    if (!startBtn) return;

    var running = false;
    var totals = {};

    function show(elId, html) { document.getElementById(elId).innerHTML = html; }

    function summary() {
        var parts = [];
        for (var k in totals) parts.push('<code>' + k + '</code>: ' + totals[k]);
        show('rg_summary', parts.length ? '<b>Results:</b> ' + parts.join(' &nbsp; ') : '');
    }

    function batch(idx, lastId) {
        if (!running || idx >= fields.length) {
            running = false;
            startBtn.disabled = false;
            stopBtn.disabled = true;
            show('rg_progress', idx >= fields.length ? 'Done.' : 'Stopped.');
            summary();
            return;
        }
        var f = fields[idx];
        show('rg_progress', 'Processing ' + f.label + ' (from record #' + lastId + ')...');

        var body = new URLSearchParams();
        body.set('fields_id', f.id);
        body.set('last_id', lastId);
        if (document.getElementById('rg_include_not_found').checked) body.set('include_not_found', '1');
        if (document.getElementById('rg_include_approximate').checked) body.set('include_approximate', '1');

        fetch(ajaxUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) throw new Error(d.error || 'failed');
                for (var k in d.statuses) totals[k] = (totals[k] || 0) + d.statuses[k];
                summary();
                // Client-side pacing between batches (plan 8.3).
                setTimeout(function () {
                    if (d.done) batch(idx + 1, 0);
                    else batch(idx, d.last_id);
                }, 300);
            })
            .catch(function (e) {
                running = false;
                startBtn.disabled = false;
                stopBtn.disabled = true;
                show('rg_progress', '<span class="text-danger">Error: ' + (e && e.message ? e.message : 'request failed') + '</span>');
            });
    }

    startBtn.addEventListener('click', function () {
        running = true;
        totals = {};
        startBtn.disabled = true;
        stopBtn.disabled = false;
        show('rg_summary', '');
        batch(0, 0);
    });
    stopBtn.addEventListener('click', function () { running = false; });
})();
</script>
