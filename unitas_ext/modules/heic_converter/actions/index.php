<?php
/**
 * HEIC Converter — Settings Action (Unitas Extension)
 */

// Admin-only access
if (!isset($app_user['group_id']) || $app_user['group_id'] != 0) {
    redirect_to('dashboard/page/index');
}

$app_title = 'HEIC Converter Settings';
