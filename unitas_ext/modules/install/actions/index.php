<?php
/**
 * UNITAS Extension — Install Action
 * 
 * Admin-only. Shows install button on first run, runs installation on submit.
 */

// Admin-only access
if (!isset($app_user['group_id']) || $app_user['group_id'] != 0) {
    redirect_to('dashboard/access_forbidden');
}

$app_title = 'Install UNITAS Extension';

require_once PLUGIN_UNITAS_EXT_PATH . '/install.php';

// Handle install action
if ($app_module_action == 'run') {
    unitas_ext_installer::install();
    $alerts->add('UNITAS Extension v' . PLUGIN_UNITAS_EXT_VERSION . ' installed successfully.', 'success');
    redirect_to('unitas_ext/about/index');
}

// Handle upgrade action
if ($app_module_action == 'upgrade') {
    unitas_ext_installer::upgrade();
    $alerts->add('UNITAS Extension upgraded to v' . PLUGIN_UNITAS_EXT_VERSION . '.', 'success');
    redirect_to('unitas_ext/about/index');
}

// Handle repair action — re-apply the core integration shims after a
// Rukovoditel core update wiped them, without changing the version/schema.
// Surfaces the patch results so a failed anchor is visible instead of silent.
if ($app_module_action == 'repair') {
    $results = unitas_ext_installer::repair();

    if (!empty($results['errors'])) {
        $alerts->add(
            'Core integration repair could not complete: ' . implode('; ', $results['errors'])
            . '. Check that the plugin can write to the Rukovoditel core files, or that this Rukovoditel version still exposes the expected shim anchors.',
            'error'
        );
    } else {
        $applied = count($results['patched']);
        $alerts->add(
            'Core integration repaired' . ($applied > 0 ? ' (' . $applied . ' change(s) applied)' : ' (already in place)')
            . '. Address geocoding is re-enabled. If it still appears off, reset PHP OpCache (restart PHP-FPM).',
            'success'
        );
    }

    redirect_to('unitas_ext/install/index');
}
