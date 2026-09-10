<?php
/**
 * UNITAS Extension - Address Autocomplete rules (admin only).
 *
 * CRUD for standalone rules that attach the Places (New) autocomplete widget
 * to a plain text field. One rule per field (unique fields_id).
 *
 * See plan section 7.7.
 */

if ($app_user['group_id'] != 0)
{
    redirect_to('dashboard/');
}

require_once PLUGIN_UNITAS_EXT_PATH . '/classes/location/unitas_address_autocomplete_rules.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $form_action = $_POST['form_action'] ?? '';

    if ($form_action === 'delete_rule')
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0)
        {
            db_query("delete from app_unitas_address_autocomplete_rules where id = " . $id);
        }
        redirect_to('unitas_ext/address_autocomplete/index');
    }

    if ($form_action === 'save_rule')
    {
        $id          = (int)($_POST['id'] ?? 0);
        $entities_id = (int)($_POST['entities_id'] ?? 0);
        $fields_id   = (int)($_POST['fields_id'] ?? 0);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $notes       = trim($_POST['notes'] ?? '');

        // The field must belong to the chosen entity and be a plain text input.
        $ok = false;
        if ($entities_id > 0 && $fields_id > 0)
        {
            $fq = db_query(
                "select id from app_fields where id = " . $fields_id .
                " and entities_id = " . $entities_id . " and type = 'fieldtype_input'"
            );
            $ok = (db_num_rows($fq) > 0);
        }

        if ($ok)
        {
            $data = array(
                'entities_id' => $entities_id,
                'fields_id'   => $fields_id,
                'is_active'   => $is_active,
                'notes'       => $notes,
            );

            // Enforce one rule per field: reuse the existing rule for this field
            // if the caller did not name a specific rule id.
            if ($id <= 0)
            {
                $eq = db_query("select id from app_unitas_address_autocomplete_rules where fields_id = " . $fields_id);
                if ($er = db_fetch_array($eq)) $id = (int)$er['id'];
            }

            if ($id > 0)
            {
                db_perform('app_unitas_address_autocomplete_rules', $data, 'update', "id = " . $id);
            }
            else
            {
                db_perform('app_unitas_address_autocomplete_rules', $data, 'insert');
            }
        }

        redirect_to('unitas_ext/address_autocomplete/index');
    }

    redirect_to('unitas_ext/address_autocomplete/index');
}

$app_title = 'Address Autocomplete';
