<?php
/**
 * UNITAS Extension — About Page Action
 */

if (!isset($app_user['group_id']) || $app_user['group_id'] != 0) {
    redirect_to('dashboard/access_forbidden');
}

$app_title = 'About UNITAS Extension';
