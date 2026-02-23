<?php

switch ($app_module_action)
{
    case 'getGeliosObjects':
        
        if(!isset($_POST['field_id']))
        {
            exit();            
        }
        
        $field_query = db_query("select * from app_fields where id='" . _POST('field_id') . "'");
        if(!$field = db_fetch_array($field_query))
        {
            exit();
        }
        
        $cfg = new fields_types_cfg($field['configuration']);
        
        $url = 'https://admin.geliospro.com/sdk/?login=' . $cfg->get('username') . '&pass=' . $cfg->get('password') . '&svc=get_units&params={}';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);			
        $result = curl_exec($ch);
        curl_close($ch);
        
        echo $result;
        
        exit();
        break;
}

$reports_query = db_query("select * from app_unitas_map_reports where is_public_access=1 and id='" . _GET('id') . "'");
if(!$reports = db_fetch_array($reports_query))
{
    exit();
}

$app_user = [];
$app_user['id'] = 0;
$app_user['group_id'] = 0;
        
$app_title = $reports['name'];
$app_layout = 'public_map_layout.php';
