<?php
/**
 * UNITAS Extension — Install View
 */

require_once PLUGIN_UNITAS_EXT_PATH . '/install.php';

$is_installed = unitas_ext_installer::is_installed();
$needs_upgrade = $is_installed && unitas_ext_installer::needs_upgrade();
$db_version = unitas_ext_installer::get_db_version();
?>

<h3 class="page-title">UNITAS Extension — Installation</h3>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-<?php echo $is_installed ? ($needs_upgrade ? 'warning' : 'success') : 'info'; ?>">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-<?php echo $is_installed ? ($needs_upgrade ? 'arrow-up' : 'check-circle') : 'download'; ?>"></i>
                    <?php
                    if (!$is_installed) {
                        echo 'Install UNITAS Extension';
                    } elseif ($needs_upgrade) {
                        echo 'Upgrade Available';
                    } else {
                        echo 'Installation Complete';
                    }
                    ?>
                </h3>
            </div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <tr>
                        <td style="width:40%"><strong>Plugin Version</strong></td>
                        <td><?php echo PLUGIN_UNITAS_EXT_VERSION; ?></td>
                    </tr>
                    <?php if ($is_installed): ?>
                    <tr>
                        <td><strong>Installed DB Version</strong></td>
                        <td><?php echo $db_version; ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Status</strong></td>
                        <td>
                            <?php if (!$is_installed): ?>
                                <span class="label label-warning">Not Installed</span>
                            <?php elseif ($needs_upgrade): ?>
                                <span class="label label-warning">Upgrade Needed</span>
                            <?php else: ?>
                                <span class="label label-success">Installed &amp; Up to Date</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php if (!$is_installed): ?>
                    <p>This will create the required database tables and configuration for the UNITAS Extension.</p>
                    <a href="<?php echo url_for('unitas_ext/install/index', 'action=run'); ?>" class="btn btn-primary btn-lg">
                        <i class="fa fa-download"></i> Install UNITAS Extension
                    </a>
                <?php elseif ($needs_upgrade): ?>
                    <p>A database upgrade is available. This will run any pending migrations to update your schema from v<?php echo $db_version; ?> to v<?php echo PLUGIN_UNITAS_EXT_VERSION; ?>.</p>
                    <a href="<?php echo url_for('unitas_ext/install/index', 'action=upgrade'); ?>" class="btn btn-warning btn-lg">
                        <i class="fa fa-arrow-up"></i> Upgrade to v<?php echo PLUGIN_UNITAS_EXT_VERSION; ?>
                    </a>
                <?php else: ?>
                    <p>The UNITAS Extension is installed and up to date. No action needed.</p>
                    <a href="<?php echo url_for('unitas_ext/about/index'); ?>" class="btn btn-default">
                        <i class="fa fa-info-circle"></i> Go to About Page
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-database"></i> Database Tables</h3>
            </div>
            <div class="panel-body">
                <p>The following tables will be created (or verified):</p>
                <table class="table table-condensed">
                    <tr><td><code>app_ext_unitas_entity_buttons</code></td><td>Entity listing buttons</td></tr>
                    <tr><td><code>app_unitas_map_reports</code></td><td>Map report definitions</td></tr>
                    <tr><td><code>app_unitas_map_reports_config</code></td><td>Map configuration (API keys, defaults)</td></tr>
                    <tr><td><code>app_unitas_pivot_map_reports</code></td><td>Pivot map report definitions</td></tr>
                    <tr><td><code>app_unitas_pivot_map_reports_entities</code></td><td>Pivot map entity configurations</td></tr>
                </table>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-wrench"></i> Core File Patches</h3>
            </div>
            <div class="panel-body">
                <p>Two small changes to Rukovoditel core files are needed to register the custom Geometry field type.
                   These are applied automatically during install/upgrade and need to be re-applied after Rukovoditel core updates.</p>
                <table class="table table-condensed">
                    <tr>
                        <td><code>includes/application_core.php</code></td>
                        <td>Adds <code>require</code> for the Geometry field type class</td>
                        <td>
                            <?php if (unitas_ext_installer::core_patches_applied()): ?>
                                <span class="label label-success">Applied</span>
                            <?php else: ?>
                                <span class="label label-warning">Not Applied</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><code>includes/classes/fields_types.php</code></td>
                        <td>Adds Geometry to the Maps field type dropdown</td>
                        <td>
                            <?php if (unitas_ext_installer::core_patches_applied()): ?>
                                <span class="label label-success">Applied</span>
                            <?php else: ?>
                                <span class="label label-warning">Not Applied</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
