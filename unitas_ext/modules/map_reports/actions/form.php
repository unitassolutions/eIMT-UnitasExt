<?php

//Check Access
if($app_user['group_id']>0)
{
	exit();
}

$obj = array();

if(isset($_GET['id']))
{
	$obj = db_find('app_unitas_map_reports',$_GET['id']);
}
else
{
	$obj = db_show_columns('app_unitas_map_reports');
	$obj['zoom'] = 8;
}