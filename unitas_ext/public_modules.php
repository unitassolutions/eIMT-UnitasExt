<?php
/**
 * UNITAS Extension — Public Module Registration
 *
 * Rukovoditel core loads plugins/{plugin}/public_modules.php just before its
 * login check and compares $_GET['module'] against $allowed_modules. Paths
 * listed here are reachable WITHOUT a login session.
 *
 * Every action listed here must self-guard:
 *   - waze_integration/public — requires the 128-bit feed key (bare 404 otherwise)
 *   - map_reports/public and pivot_map_reports/public — require is_public_access=1
 *     on the report row (the action exits otherwise)
 */

$allowed_modules[] = 'unitas_ext/waze_integration/public';
$allowed_modules[] = 'unitas_ext/map_reports/public';
$allowed_modules[] = 'unitas_ext/pivot_map_reports/public';
