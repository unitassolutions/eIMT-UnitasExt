<?php
/**
 * UNITAS Extension - core bootstrap.
 *
 * Loaded by Rukovoditel core (includes/application_core.php) in web, cron and
 * REST API contexts, for every plugin listed in AVAILABLE_PLUGINS. This is the
 * plugin's single entry point outside the HTML request, so it must use
 * __DIR__-relative paths: PLUGIN_UNITAS_EXT_PATH is only defined later, in
 * application_top.php.
 *
 * It loads the core hook targets (shim S1/S2 call into these) and the Unitas
 * field type classes so they are registered and available on every save path.
 *
 * See plan section 6.4.
 */

require_once __DIR__ . '/classes/core_hooks.php';
require_once __DIR__ . '/classes/fieldstypes/fieldtype_unitas_geometry.php';

// The location field type ships in Phase 3; load it only once its file exists
// so this bootstrap never fatals on a partial deployment.
$unitas_location_class = __DIR__ . '/classes/fieldstypes/fieldtype_unitas_location.php';
if (is_file($unitas_location_class)) {
    require_once $unitas_location_class;
}
unset($unitas_location_class);
