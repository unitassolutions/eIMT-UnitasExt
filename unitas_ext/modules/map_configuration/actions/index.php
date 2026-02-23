<?php

if ($app_user['group_id'] != 0)
{
    redirect_to('dashboard/');
}

require_once dirname(__DIR__) . '/helpers/map_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $sql_data = [
        'google_map_api_key' => trim($_POST['google_map_api_key'] ?? ''),
        'map_style_light'    => trim($_POST['map_style_light'] ?? ''),
        'map_style_dark'     => trim($_POST['map_style_dark'] ?? ''),
        'default_theme'      => $_POST['default_theme'] ?? 'auto',
        'default_lat'        => trim($_POST['default_lat'] ?? ''),
        'default_lng'        => trim($_POST['default_lng'] ?? ''),
        'default_zoom'       => (int)($_POST['default_zoom'] ?? 8),
    ];

    db_perform(
        'app_unitas_map_reports_config',
        $sql_data,
        'update',
        "id = 1"
    );

    redirect_to('unitas_ext/map_configuration/index');
}
