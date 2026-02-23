<?php
/**
 * UNITAS Extension - Custom Report Viewer for Modal
 * Shows ONLY report content without page wrappers
 */

// Start session and include Rukovoditel core
$current_directory = dirname(__FILE__);
$root_path = '';

// Find the Rukovoditel root path
for ($i = 0; $i < 10; $i++) {
    if (file_exists($current_directory . '/../../../../includes/functions/database.php')) {
        $root_path = $current_directory . '/../../../../';
        break;
    }
    $current_directory = dirname($current_directory);
}

if (empty($root_path)) {
    die('Cannot find Rukovoditel installation');
}

// Change to Rukovoditel root directory
chdir($root_path);

// Define Rukovoditel constant
define('IS_RUKOVODITEL', true);

// Include Rukovoditel configuration
require_once $root_path . 'includes/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_name('rukovoditel_' . CFG_APP_SHORT_NAME);
    session_start();
}

// Include required files
require_once $root_path . 'includes/functions/database.php';
require_once $root_path . 'includes/functions/sessions.php';
require_once $root_path . 'includes/classes/check_view.php';

// Initialize database connection
db_connect();

// Check if user is logged in
$app_user = array();
if (isset($_SESSION['app_logged_users_id'])) {
    $user_query = db_query("SELECT * FROM app_entity_1 WHERE id=" . (int)$_SESSION['app_logged_users_id']);
    if ($user = db_fetch_array($user_query)) {
        $app_user = array(
            'id' => $user['id'],
            'group_id' => $user['field_6'],
            'name' => $user['field_7'] . ' ' . $user['field_8']
        );
    }
}

// Check access
if (empty($app_user) || $app_user['id'] == 0) {
    die('<div class="alert alert-danger">Access denied. Please login first.</div>');
}

// Get report ID
$reports_id = isset($_GET['reports_id']) ? (int)$_GET['reports_id'] : 0;
if (!$reports_id) {
    die('<div class="alert alert-danger">Report ID is required</div>');
}

// Check if report exists
$report_info_query = db_query("SELECT * FROM app_reports WHERE id=" . $reports_id);
if (!$report_info = db_fetch_array($report_info_query)) {
    die('<div class="alert alert-danger">Report not found</div>');
}

// Check if user has access to this entity
$check_view = new check_view($report_info['entities_id']);
if (!$check_view->has_access()) {
    die('<div class="alert alert-danger">You don\'t have access to this report</div>');
}

// Include reports class
require_once $root_path . 'includes/classes/reports.php';

// Set flag to indicate this is a modal view
define('IS_MODAL', true);

// Disable headers and footers
$disable_header_footer = true;

// Start output buffering
ob_start();

// Initialize report
$reports = new reports($reports_id);

// Get report HTML
$report_html = $reports->get_html();

// Clean the HTML - remove headers, footers, and other unwanted elements
$patterns_to_remove = array(
    // Remove page title block
    '/<div class="page-title-block"[^>]*>.*?<\/div>/is',
    '/<h3 class="page-title"[^>]*>.*?<\/h3>/is',
    
    // Remove page toolbar and action buttons
    '/<div class="page-toolbar"[^>]*>.*?<\/div>/is',
    '/<div class="entitly-listing-buttons-left"[^>]*>.*?<\/div>/is',
    '/<div class="btn-toolbar"[^>]*>.*?<\/div>/is',
    
    // Remove filters and search forms
    '/<form[^>]*name="listing_filters_count_form"[^>]*>.*?<\/form>/is',
    '/<div class="row filters-container"[^>]*>.*?<\/div>/is',
    
    // Remove breadcrumbs
    '/<ul class="page-breadcrumb"[^>]*>.*?<\/ul>/is',
    
    // Remove alerts
    '/<div class="alert alert-[^"]*"[^>]*>.*?<\/div>/is',
    
    // Remove any script tags
    '/<script[^>]*>.*?<\/script>/is',
    
    // Remove any style tags (we'll add our own)
    '/<style[^>]*>.*?<\/style>/is',
);

foreach ($patterns_to_remove as $pattern) {
    $report_html = preg_replace($pattern, '', $report_html);
}

// Extract just the table content
$clean_html = '';

// Try to find the table scrollable div
if (preg_match('/<div class="table-scrollable"[^>]*>(.*?)<\/div>/is', $report_html, $matches)) {
    $clean_html = '<div class="table-scrollable">' . $matches[1] . '</div>';
} 
// Try table-responsive
elseif (preg_match('/<div class="table-responsive"[^>]*>(.*?)<\/div>/is', $report_html, $matches)) {
    $clean_html = '<div class="table-scrollable">' . $matches[1] . '</div>';
} 
// Try direct table
elseif (preg_match('/<table[^>]*class="table[^>]*>.*?<\/table>/is', $report_html, $matches)) {
    $clean_html = '<div class="table-scrollable">' . $matches[0] . '</div>';
}
// Fallback to cleaned HTML
else {
    $clean_html = $report_html;
}

// Add pagination if exists
if (preg_match('/<div class="row"[^>]*>.*?<div class="col-md-6"[^>]*>(.*?)<\/div>.*?<\/div>/is', $report_html, $matches)) {
    $pagination_html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $matches[1]);
    $clean_html .= '<div class="pagination-container">' . $pagination_html . '</div>';
}

// Output clean HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($report_info['name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
            background: #fff;
            padding: 20px;
        }
        
        .table-scrollable {
            width: 100%;
            overflow-x: auto;
            margin: 0;
            border: none;
            min-height: 200px;
        }
        
        .table {
            width: 100%;
            max-width: 100%;
            margin-bottom: 20px;
            background-color: transparent;
            border-collapse: collapse;
            border-spacing: 0;
        }
        
        .table > thead > tr > th {
            vertical-align: bottom;
            border-bottom: 2px solid #ddd;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            background: #f8f9fa;
            color: #495057;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table > tbody > tr > td {
            padding: 10px 8px;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        
        .table > tbody > tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        
        .table > tbody > tr:hover {
            background-color: #f5f5f5;
        }
        
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        
        .pagination-container {
            margin-top: 20px;
            padding: 15px;
            border-top: 1px solid #eee;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 4px;
            margin: 0;
            flex-wrap: wrap;
        }
        
        .pagination > li {
            display: inline;
            margin: 2px;
        }
        
        .pagination > li > a,
        .pagination > li > span {
            display: block;
            padding: 8px 12px;
            line-height: 1.5;
            color: #337ab7;
            text-decoration: none;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .pagination > li > a:hover,
        .pagination > li > span:hover {
            color: #23527c;
            background-color: #eee;
            border-color: #ddd;
        }
        
        .pagination > .active > a,
        .pagination > .active > span {
            color: #fff;
            background-color: #337ab7;
            border-color: #337ab7;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .table > thead > tr > th,
            .table > tbody > tr > td {
                padding: 8px 6px;
                font-size: 13px;
            }
            
            .pagination > li > a,
            .pagination > li > span {
                padding: 6px 10px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <?php 
    if (empty($clean_html)) {
        echo '<div class="alert alert-info">No data found in report.</div>';
    } else {
        echo $clean_html;
    }
    ?>
    
    <script>
    // Simple script to handle pagination links within the modal
    document.addEventListener('DOMContentLoaded', function() {
        // Make pagination links work within modal
        var paginationLinks = document.querySelectorAll('.pagination a');
        paginationLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get the URL from the link
                var url = this.getAttribute('href');
                if (!url) return;
                
                // Send message to parent window to reload content
                if (window.parent && window.parent.unitasReloadModalContent) {
                    window.parent.unitasReloadModalContent(url);
                }
            });
        });
        
        // Make table rows clickable if they have onclick attributes
        var tableRows = document.querySelectorAll('table tbody tr[onclick]');
        tableRows.forEach(function(row) {
            row.addEventListener('click', function(e) {
                var onclickAttr = this.getAttribute('onclick');
                if (onclickAttr && onclickAttr.includes('open_dialog')) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Extract URL from onclick
                    var urlMatch = onclickAttr.match(/open_dialog\('([^']+)'/);
                    if (urlMatch && urlMatch[1]) {
                        if (window.parent && window.parent.open_dialog) {
                            window.parent.open_dialog(urlMatch[1]);
                            if (window.parent.unitasCloseModal) {
                                window.parent.unitasCloseModal();
                            }
                        }
                    }
                }
            });
        });
    });
    </script>
</body>
</html>
<?php
// End output
ob_end_flush();
exit();