<?php
/**
 * UNITAS Extension - Address Autocomplete field loader (admin only).
 *
 * Returns a <select name="fields_id"> of the fieldtype_input fields for the
 * chosen entity, loaded into the rule form when the entity changes.
 * Returns an HTML fragment (no </body>), so the ob_start injection is a no-op.
 *
 * See plan section 7.7.
 */

if (!isset($app_user['group_id']) || $app_user['group_id'] != 0)
{
    exit();
}

$entities_id = (int)($_POST['entities_id'] ?? 0);
$selected    = (int)($_POST['selected'] ?? 0);

if ($entities_id <= 0)
{
    echo select_tag('fields_id', array('' => ''), '', array('class' => 'form-control input-large required'));
    exit();
}

$choices = array('' => '');
$fq = db_query(
    "select id, name from app_fields where entities_id = " . db_input($entities_id) .
    " and type = 'fieldtype_input' order by name"
);
while ($f = db_fetch_array($fq))
{
    $choices[$f['id']] = $f['name'];
}

echo select_tag('fields_id', $choices, $selected ?: '', array('class' => 'form-control input-large required'));
exit();
