<?php

//Check if report exist
$reports_query = db_query("select * from app_unitas_map_reports where id='" . db_input(_get::int('id')) . "'");
if(!$reports = db_fetch_array($reports_query))
{
	redirect_to('dashboard/page_not_found');
}

app_set_title($reports['name']);

//Check Access
if(!map_reports::has_access($reports['users_groups']))
{
	redirect_to('dashboard/access_forbidden');
}