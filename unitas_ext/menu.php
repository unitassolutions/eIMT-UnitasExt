<?php

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
                    )
                )
            )
        )
    );
}
