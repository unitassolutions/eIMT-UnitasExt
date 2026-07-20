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
