<?php
/**
 * Entity Buttons - AJAX Handler for Button Retrieval
 */

// Clear output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Get entity ID
$entity_id = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;
if (!$entity_id && isset($_POST['entity_id'])) {
    $entity_id = (int)$_POST['entity_id'];
}

// Validate
if ($entity_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid entity ID']);
    exit();
}

// Query database
$sql = "SELECT * FROM app_ext_unitas_entity_buttons 
        WHERE entity_id = " . $entity_id . " 
        AND is_active = 1 
        ORDER BY sort_order";
        
$result = db_query($sql);
$buttons = [];

while ($row = db_fetch_array($result)) {
    $buttons[] = $row;
}

// Generate buttons HTML - EXACT match to "Add Project" button
$buttons_html = '';

if (count($buttons) > 0) {
    foreach ($buttons as $index => $row) {
        // Get URL
        $url = '';
        if ($row['button_type'] == 'report' && $row['report_id']) {
            $url = 'index.php?module=reports/view&reports_id=' . $row['report_id'];
        } elseif ($row['external_url']) {
            $url = $row['external_url'];
        }
        
        // Format URL properly
        if ($url) {
            // Check if internal URL
            $is_internal = (
                strpos($url, 'index.php') === 0 || 
                strpos($url, '?') === 0 || 
                !preg_match('/^https?:\/\//', $url)
            );
            
            if ($is_internal) {
                // Format internal URL
                if (strpos($url, '?') === 0) {
                    $url = 'index.php' . $url;
                } elseif (!preg_match('/^https?:\/\//', $url) && !preg_match('/^index\.php/', $url)) {
                    $url = 'index.php?' . $url;
                }
                
                // Check if it's a report URL
                if (strpos($url, 'module=reports/view') !== false) {
                    // Add ALL parameters to hide sidebar, header, footer
                    $url .= '&is_modal=1&is_embed=1&is_print=1&hide_menu=1&hide_header=1&hide_footer=1&hide_sidebar=1&hide_toolbar=1&hide_breadcrumb=1';
                }
            }
        }
        
        // Build onclick handler based on URL type
        $onclick = '';
        if ($url) {
            if (strpos($url, 'module=reports/view') !== false && isset($row['report_id']) && $row['report_id']) {
                // Report URL - use clean report modal
                $onclick = 'onclick="unitasOpenCleanReport(\'' . addslashes($url) . '\', \'' . htmlspecialchars($row['button_title']) . '\'); return false;"';
            } elseif (preg_match('/^https?:\/\//', $url)) {
                // External URL - open in new tab
                $onclick = 'onclick="window.open(\'' . addslashes($url) . '\', \'_blank\'); return false;"';
            } else {
                // Internal URL - use open_dialog
                $onclick = 'onclick="open_dialog(\'' . addslashes($url) . '\'); return false;"';
            }
        }
        
        // Build button HTML - EXACT match to Add Project button structure
        $button_html = '<button ' . $onclick . ' class="btn btn-primary" type="button">';
        
        // Add icon if specified
        if (!empty($row['button_icon'])) {
            $button_html .= '<i class="fa ' . $row['button_icon'] . '"></i> ';
        }
        
        $button_html .= htmlspecialchars($row['button_title']) . '</button>';
        
        $buttons_html .= $button_html;
    }
}

// Return JSON response
echo json_encode([
    'success' => true,
    'entity_id' => $entity_id,
    'button_count' => count($buttons),
    'buttons_html' => $buttons_html
]);

exit();