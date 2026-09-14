<?php
/**
 * UNITAS Extension — Install View
 */

require_once PLUGIN_UNITAS_EXT_PATH . '/install.php';

$is_installed = unitas_ext_installer::is_installed();
$needs_upgrade = $is_installed && unitas_ext_installer::needs_upgrade();
$db_version = unitas_ext_installer::get_db_version();

// Core integration health. A Rukovoditel core update can wipe our shims
// without changing the plugin/DB version, so the install page must offer a
// version-independent repair path whenever a shim is missing.
$health = unitas_ext_installer::shim_health();
$core_ok = !empty($health['all_ok']);
$needs_repair = $is_installed && !$needs_upgrade && !$core_ok;
?>

<h3 class="page-title">UNITAS Extension — Installation</h3>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-<?php echo !$is_installed ? 'info' : ($needs_upgrade ? 'warning' : ($needs_repair ? 'danger' : 'success')); ?>">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-<?php echo !$is_installed ? 'download' : ($needs_upgrade ? 'arrow-up' : ($needs_repair ? 'exclamation-triangle' : 'check-circle')); ?>"></i>
                    <?php
                    if (!$is_installed) {
                        echo 'Install UNITAS Extension';
                    } elseif ($needs_upgrade) {
                        echo 'Upgrade Available';
                    } elseif ($needs_repair) {
                        echo 'Core Integration Repair Needed';
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
                            <?php elseif ($needs_repair): ?>
                                <span class="label label-danger">Repair Needed</span>
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
                    <p>A database upgrade is available. This will run any pending migrations to update your schema from v<?php echo $db_version; ?> to v<?php echo PLUGIN_UNITAS_EXT_VERSION; ?>, and re-apply the core integration shims.</p>
                    <a href="<?php echo url_for('unitas_ext/install/index', 'action=upgrade'); ?>" class="btn btn-warning btn-lg">
                        <i class="fa fa-arrow-up"></i> Upgrade to v<?php echo PLUGIN_UNITAS_EXT_VERSION; ?>
                    </a>
                <?php elseif ($needs_repair): ?>
                    <p>One or more core integration shims are missing — this normally happens after a Rukovoditel core update overwrites the patched core files.
                       <strong>Address geocoding stays disabled until the shims are re-applied.</strong>
                       Repairing re-injects the shims without changing your version or data, and is safe to run at any time.</p>
                    <a href="<?php echo url_for('unitas_ext/install/index', 'action=repair'); ?>" class="btn btn-danger btn-lg">
                        <i class="fa fa-wrench"></i> Repair Core Integration
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

        <div class="panel panel-<?php echo $core_ok ? 'default' : 'danger'; ?>">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-wrench"></i> Core Integration Shims</h3>
            </div>
            <div class="panel-body">
                <p>The UNITAS Extension injects small marker-delimited shims into Rukovoditel core files so custom field types, address
                   geocoding, and the entity menu integration keep working. These are applied automatically during install/upgrade and
                   <strong>must be re-applied after a Rukovoditel core update</strong>, which overwrites the patched files.</p>
                <table class="table table-condensed">
                    <?php
                    $shim_rows = array(
                        array($health['s1'],   'includes/classes/fields_types.php',        'Registers the Unitas field types in the Maps field type dropdown'),
                        array($health['s2'],   'includes/classes/fields_types.php',        'Runs address geocoding on every record save'),
                        array($health['menu'], 'includes/classes/model/entities_menu.php', 'Adds Unitas map reports to the entity Menu configuration'),
                    );
                    foreach ($shim_rows as $row):
                        list($ok, $file, $desc) = $row;
                    ?>
                    <tr>
                        <td><code><?php echo $file; ?></code></td>
                        <td><?php echo $desc; ?></td>
                        <td class="text-right">
                            <?php if ($ok): ?>
                                <span class="label label-success">Applied</span>
                            <?php else: ?>
                                <span class="label label-danger">Missing</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (!$core_ok): ?>
                    <a href="<?php echo url_for('unitas_ext/install/index', 'action=repair'); ?>" class="btn btn-danger">
                        <i class="fa fa-wrench"></i> Repair Core Integration
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
