<?php
/** 
 * Version:       1.1.0 (Production) 
 * Author:        Darshan Patel
 * Company:       UNITAS Solution
 * Contact:       darshan.patel@unitas.solutions 
 * -----------------------------------------------
 * (c) 2025 UNITAS Solution. All rights reserved.
 * -----------------------------------------------
 */

//check access
if($app_user['group_id']>0 and !in_array($app_module_path,['unitas_ext/pivot_map_reports/view','unitas_ext/pivot_map_reports/view_openstreetmap','unitas_ext/pivot_map_reports/view_google','unitas_ext/pivot_map_reports/view_google_v2','unitas_ext/pivot_map_reports/view_yandex']))
{
  redirect_to('dashboard/access_forbidden');
}
