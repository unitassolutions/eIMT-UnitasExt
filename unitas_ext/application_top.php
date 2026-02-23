<?php
/**
 * UNITAS Extension - Main Plugin File
 * @package Rukovoditel
 * @subpackage Plugin
 * @version 1.0.0
 */

// Prevent direct access
//if (!defined('IS_RUKOVODITEL')) {
//    die('Direct access not permitted');
//}

// Plugin constants
define('PLUGIN_UNITAS_EXT_VERSION', '1.0.0');
define('PLUGIN_UNITAS_EXT_PATH', __DIR__);

// Load main class
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/EntityButtons.php';

// Check if this is an AJAX request to avoid outputting JavaScript
$is_ajax_request = (
    (isset($_POST['is_modal']) && $_POST['is_modal'] == 1) ||
    (isset($_GET['module']) && $_GET['module'] == 'unitas_ext/entity_buttons/ajax_get_buttons')
);

if (!$is_ajax_request) {
    // Auto-install on first access
    PluginUnitasExtEntityButtons::install();
    
    // Get current module and path
    $current_module = $_GET['module'] ?? '';
    $current_path = $_GET['path'] ?? '';
    
    // Check if this is an entity listing page
    $is_entity_listing_page = false;
    
    // Pattern 1: Standard entity listing (main or sub-entity)
    if ($current_module === 'items/items' && !empty($current_path)) {
        $is_entity_listing_page = true;
    }
    
    // Pattern 2: Entity listing with specific action
    if (strpos($current_module, 'items/listing') === 0) {
        $is_entity_listing_page = true;
    }
    
    // Pattern 3: Reports with entity context
    if ($current_module === 'reports/view' && isset($_GET['reports_id'])) {
        $is_entity_listing_page = true;
    }
    
    // Load JavaScript ONLY on entity listing pages
    if ($is_entity_listing_page) {
        echo '<script src="plugins/unitas_ext/js/load-buttons.js" defer></script>';
        
        // Add CSS for modal styling
        echo '<style>
        /* Clean report modal - NO HEADER/SIDEBAR */
        .unitas-clean-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: rgba(0,0,0,0.95) !important;
            z-index: 99999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .unitas-clean-modal {
            background: #fff !important;
            width: 99% !important;
            height: 98% !important;
            position: relative !important;
            box-shadow: 0 15px 60px rgba(0,0,0,0.7) !important;
            border-radius: 6px !important;
            overflow: hidden !important;
            border: 1px solid #444 !important;
        }

        .unitas-clean-close {
            position: absolute !important;
            top: 20px !important;
            right: 25px !important;
            font-size: 42px !important;
            line-height: 1 !important;
            color: #fff !important;
            cursor: pointer !important;
            z-index: 1000 !important;
            text-shadow: 0 3px 15px rgba(0,0,0,0.8) !important;
            background: rgba(0,0,0,0.5) !important;
            width: 55px !important;
            height: 55px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.3s !important;
            font-weight: 300 !important;
            opacity: 0.9 !important;
        }

        .unitas-clean-close:hover {
            background: rgba(0,0,0,0.8) !important;
            transform: scale(1.15) !important;
            opacity: 1 !important;
        }

        .unitas-clean-modal iframe {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
            display: block !important;
        }

        /* Hide scrollbars when modal is open */
        body.unitas-no-scroll {
            overflow: hidden !important;
            padding-right: 0 !important;
        }

        /* Hide elements inside iframe */
        .unitas-clean-modal iframe body {
            padding: 0 !important;
            margin: 0 !important;
            background: #fff !important;
        }

        /* UNITAS button styling */
        .unitas-buttons {
            display: inline-block !important;
            margin-right: 10px !important;
            vertical-align: middle !important;
        }

        .unitas-buttons button {
            margin-right: 5px !important;
            margin-bottom: 5px !important;
            font-family: inherit !important;
            font-size: inherit !important;
        }
        </style>';
    }
}