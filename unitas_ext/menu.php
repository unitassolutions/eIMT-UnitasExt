<?php

require_once PLUGIN_UNITAS_EXT_PATH . '/install.php';

if (unitas_ext_installer::is_installed()) {
    // Full menu — only shown when plugin is installed
    require(component_path('unitas_ext/map_reports/menu'));
    require(component_path('unitas_ext/pivot_map_reports/menu'));

    if($app_user['group_id'] == 0)
    {
        $app_plugin_menu['menu'][] = array(
            'title' => 'UNITAS Extension',
            'url'   => url_for('unitas_ext/entity_buttons/index'),
            'class' => 'fa-puzzle-piece',
            'submenu' => array(
                array(
                    'title' => 'Entity Buttons',
                    'url'   => url_for('unitas_ext/entity_buttons/index')
                ),
                array(
                    'title' => 'Map Report',
                    'url'   => url_for('unitas_ext/map_reports/reports')
                ),
                array(
                    'title' => 'Pivot Map Report',
                    'url'   => url_for('unitas_ext/pivot_map_reports/reports')
                ),

                array(
                    'title' => 'Extension Configuration',
                    'url'   => '#',
                    'submenu' => array(
                        array(
                            'title' => 'Google Map',
                            'url'   => url_for('unitas_ext/map_configuration/index')
                        ),
                        array(
                            'title' => 'HEIC Converter',
                            'url'   => url_for('unitas_ext/heic_converter/index')
                        ),
                        array(
                            'title' => 'Waze Integration',
                            'url'   => url_for('unitas_ext/waze_integration/index')
                        )
                    )
                ),
                array(
                    'title' => 'About',
                    'url'   => url_for('unitas_ext/about/index')
                )
            )
        );
    }
} else {
    // Not installed — show only the install link for admins
    if($app_user['group_id'] == 0)
    {
        $app_plugin_menu['menu'][] = array(
            'title' => 'UNITAS Extension',
            'url'   => url_for('unitas_ext/install/index'),
            'class' => 'fa-puzzle-piece',
            'submenu' => array(
                array(
                    'title' => 'Install Extension',
                    'url'   => url_for('unitas_ext/install/index')
                )
            )
        );
    }
}
