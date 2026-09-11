<?php
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/location/unitas_address_autocomplete_rules.php';

$edit_id   = (int)($_GET['edit'] ?? 0);
$edit_rule = $edit_id > 0 ? db_find('app_unitas_address_autocomplete_rules', $edit_id) : null;
if (!isset($edit_rule['id'])) $edit_rule = null;

$rules = unitas_address_autocomplete_rules::all_rules();

$entity_choices = array('' => '');
$eq = db_query("select id, name from app_entities order by name");
while ($e = db_fetch_array($eq)) $entity_choices[$e['id']] = $e['name'];

$sel_entity = $edit_rule ? (int)$edit_rule['entities_id'] : 0;
$sel_field  = $edit_rule ? (int)$edit_rule['fields_id']   : 0;

// When editing, pre-render the field select for the rule's entity so the form
// shows the current field without waiting for the AJAX round-trip.
$field_choices = array('' => '');
if ($sel_entity > 0) {
    $fq = db_query("select id, name from app_fields where entities_id = " . $sel_entity . " and type = 'fieldtype_input' order by name");
    while ($f = db_fetch_array($fq)) $field_choices[$f['id']] = $f['name'];
}

$post_url  = url_for('unitas_ext/address_autocomplete/index');
$ajax_url  = url_for('unitas_ext/address_autocomplete/ajax_fields');
$index_url = url_for('unitas_ext/address_autocomplete/index');

// Location-bound fields (Phase 3) shown read-only so admins see the full picture.
$location_bound = unitas_address_autocomplete_rules::location_bound_field_ids();
?>

<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption"><span class="caption-subject bold uppercase">Address Autocomplete Rules</span></div>
    </div>
    <div class="portlet-body">
        <p class="text-muted">Attach Google Places (New) address suggestions to a plain text field. Requires a browser key configured under Google Map.</p>

        <?php if (unitas_address_autocomplete_rules::legacy_google_autocomplete_active()): ?>
            <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i>
                <b>The legacy Extension &quot;Google Autocomplete&quot; smart input module is still active.</b>
                It loads Google Maps with its own API key on every page, which blocks the Unitas autocomplete
                from using the configured browser key. Deactivate it under
                <b>Extension &gt; Modules &gt; Smart Input</b> before testing these rules.
            </div>
        <?php endif; ?>

        <table class="table table-striped table-bordered">
            <thead>
                <tr><th>Entity</th><th>Field</th><th>Active</th><th>Notes</th><th style="width:140px;">Actions</th></tr>
            </thead>
            <tbody>
            <?php if (!$rules): ?>
                <tr><td colspan="5" class="text-muted">No rules yet.</td></tr>
            <?php else: foreach ($rules as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['entity_name'] !== null ? $r['entity_name'] : '(deleted entity #' . (int)$r['entities_id'] . ')'); ?></td>
                    <td>
                        <?php echo htmlspecialchars($r['field_name'] !== null ? $r['field_name'] : '(deleted field #' . (int)$r['fields_id'] . ')'); ?>
                        <?php if ($r['field_type'] !== null && $r['field_type'] !== 'fieldtype_input'): ?>
                            <span class="label label-warning" title="No longer a text field">not text</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $r['is_active'] ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>'; ?></td>
                    <td><?php echo htmlspecialchars($r['notes']); ?></td>
                    <td>
                        <a href="<?php echo $index_url . '&edit=' . (int)$r['id']; ?>" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i> Edit</a>
                        <form method="post" action="<?php echo $post_url; ?>" style="display:inline;" onsubmit="return confirm('Delete this rule?');">
                            <input type="hidden" name="form_action" value="delete_rule">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($location_bound): ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> The following field id(s) also receive autocomplete from Location fields and cannot be edited here:
                <?php echo htmlspecialchars(implode(', ', array_map('intval', $location_bound))); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="portlet light bordered">
    <div class="portlet-title">
        <div class="caption"><span class="caption-subject bold uppercase"><?php echo $edit_rule ? 'Edit Rule' : 'Add Rule'; ?></span></div>
    </div>
    <div class="portlet-body">
        <form method="post" action="<?php echo $post_url; ?>" class="form-horizontal">
            <input type="hidden" name="form_action" value="save_rule">
            <input type="hidden" name="id" value="<?php echo $edit_rule ? (int)$edit_rule['id'] : 0; ?>">

            <div class="form-group">
                <label class="col-md-3 control-label">Entity</label>
                <div class="col-md-4">
                    <?php echo select_tag('entities_id', $entity_choices, $sel_entity ?: '', array('class' => 'form-control input-large required', 'id' => 'ac_entity')); ?>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Text Field</label>
                <div class="col-md-4" id="ac_fields_container">
                    <?php echo select_tag('fields_id', $field_choices, $sel_field ?: '', array('class' => 'form-control input-large required')); ?>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Active</label>
                <div class="col-md-4">
                    <label class="mt-checkbox mt-checkbox-outline">
                        <input type="checkbox" name="is_active" value="1" <?php echo (!$edit_rule || $edit_rule['is_active']) ? 'checked' : ''; ?>>
                        <span></span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-3 control-label">Notes</label>
                <div class="col-md-6">
                    <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($edit_rule ? $edit_rule['notes'] : ''); ?></textarea>
                </div>
            </div>

            <hr style="margin:15px 0;">
            <div>
                <button type="submit" class="btn btn-primary"><?php echo TEXT_SAVE; ?></button>
                <?php if ($edit_rule): ?>
                    <a href="<?php echo $index_url; ?>" class="btn btn-default">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var ajaxUrl = <?php echo json_encode($ajax_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var entitySel = document.getElementById('ac_entity');
    if (!entitySel) return;
    entitySel.addEventListener('change', function () {
        var eid = this.value;
        var container = document.getElementById('ac_fields_container');
        container.innerHTML = 'Loading...';
        var body = new URLSearchParams();
        body.set('entities_id', eid);
        fetch(ajaxUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.text(); })
            .then(function (html) { container.innerHTML = html; })
            .catch(function () { container.innerHTML = '<span class="text-danger">Could not load fields.</span>'; });
    });
})();
</script>
