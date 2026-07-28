<?php
/**
 * UNITAS Extension - Main Plugin File
 * @package Rukovoditel
 * @subpackage Plugin
 * @version 1.0.2
 *
 * FIX (v1.0.2): Moved all HTML injection from echo (which outputs before
 * <!DOCTYPE html> and triggers browser quirks mode) into a single ob_start()
 * callback that injects before </body>. This resolves body text sizing issues.
 */

// Plugin constants
define('PLUGIN_UNITAS_EXT_VERSION', '1.5.0');
define('PLUGIN_UNITAS_EXT_PATH', __DIR__);

// Load installer
require_once PLUGIN_UNITAS_EXT_PATH . '/install.php';

// Load custom field type (must be available before Rukovoditel tries to instantiate it)
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/fieldstypes/fieldtype_unitas_geometry.php';

// Check if this is an AJAX request — skip all injection for AJAX
$is_ajax_request = (
    (isset($_POST['is_modal']) && $_POST['is_modal'] == 1) ||
    (isset($_GET['module']) && $_GET['module'] == 'unitas_ext/entity_buttons/ajax_get_buttons') ||
    (isset($_GET['module']) && $_GET['module'] == 'unitas_ext/waze_integration/ajax_reverse_geocode') ||
    (isset($_GET['module']) && $_GET['module'] == 'unitas_ext/waze_integration/public')
);

// ── Installation Check ──────────────────────────────────────────────────────
// If plugin is not installed, redirect admin to the install page.
// Non-admins see nothing — the plugin menu is hidden for them anyway.
$current_module = $_GET['module'] ?? '';

if (!unitas_ext_installer::is_installed() && !$is_ajax_request) {
    // Only redirect when admin tries to access a unitas_ext page
    if (strpos($current_module, 'unitas_ext/') === 0) {
        // Allow access to the install page itself
        if (strpos($current_module, 'unitas_ext/install') !== 0) {
            if (isset($app_user['group_id']) && $app_user['group_id'] == 0) {
                redirect_to('unitas_ext/install/index');
            }
        }
    } else {
        // Not a unitas_ext page and not installed — skip all plugin logic
        return;
    }
}

// ── Auto-Upgrade Check ──────────────────────────────────────────────────────
// If installed but DB version is behind plugin version, run migrations silently.
if (unitas_ext_installer::is_installed() && unitas_ext_installer::needs_upgrade() && !$is_ajax_request) {
    unitas_ext_installer::upgrade();
}

// Load entity buttons class (tables are guaranteed to exist after install)
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/EntityButtons.php';

// ── Build injection HTML ────────────────────────────────────────────────────
// All HTML is collected here, then injected before </body> via ob_start().
// NOTHING is echoed — this prevents pre-DOCTYPE content that breaks rendering.

$unitas_inject_html = '';

// Entity Buttons: JS + CSS on listing pages only
if (!$is_ajax_request) {
    $current_module = $_GET['module'] ?? '';
    $current_path = $_GET['path'] ?? '';

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

    if ($is_entity_listing_page) {
        $unitas_inject_html .= "\n<!-- Unitas Extension: Entity Buttons -->\n" .
            '<link rel="stylesheet" href="plugins/unitas_ext/css/unitas_ext.css">' . "\n" .
            '<script src="plugins/unitas_ext/js/load-buttons.js" defer></script>' . "\n";
    }
}

// HEIC Converter: JS + CSS on ALL pages for authenticated users
if (isset($app_user['id']) && $app_user['id'] > 0) {
    $unitas_inject_html .= "\n<!-- Unitas Extension: HEIC Converter -->\n" .
        '<link rel="stylesheet" href="plugins/unitas_ext/css/heic_converter.css">' . "\n" .
        '<script src="plugins/unitas_ext/js/heic/heic_converter.js"></script>' . "\n";
}

// Lightbox Embed Mode: Hide sidebar, header, footer when loaded inside a lightbox iframe.
// This fires on the PAGE INSIDE THE IFRAME, not the parent page.
if (isset($_GET['is_modal']) || isset($_GET['is_embed'])) {
    $unitas_inject_html .= "\n<!-- Unitas Extension: Lightbox Embed Mode -->\n" .
        '<style>
        /* Hide all chrome: sidebar, header, footer, breadcrumbs, title, copyright, chat */
        .page-sidebar-wrapper,
        .page-sidebar,
        .page-header,
        .page-header-inner,
        .page-footer,
        .page-breadcrumb,
        .page-bar,
        .page-toolbar,
        .page-title,
        .footer,
        .app-chat-button,
        #sidebar,
        .navbar,
        .top-menu,
        footer,
        header { display: none !important; }

        /* Expand content area — kill all spacing from hidden header */
        .page-content-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            min-height: auto !important;
        }

        .page-content {
            margin: 0 !important;
            padding: 0 8px 8px 0 !important;
            min-height: auto !important;
        }

        body {
            padding: 0 !important;
            margin: 0 !important;
            background: #fff !important;
            overflow: hidden !important;
        }

        /* Override .page-container 45px margin-top from style.css */
        .page-header-fixed .page-container {
            margin-top: 10px !important;
            margin-left: 10px !important;
        }

        .page-header-fixed .page-content-wrapper,
        .page-header-fixed .page-content {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* Map containers: 100vh - 10px top margin - 45px filter - 8px bottom pad - 12px buffer */
        #goolge_map_container,
        #map_container,
        #openstreetmap_container,
        #yandex_map_container,
        .map-container {
            height: calc(100vh - 75px) !important;
            min-height: 400px !important;
        }

        /* Map with sidebar layout */
        .table-sidebar {
            height: calc(100vh - 75px) !important;
        }
        .table-sidebar .table-sidebar-content,
        .table-sidebar .table-sidebar-body {
            height: calc(100vh - 75px) !important;
            vertical-align: top !important;
        }
        .map-sidebar-list-scroller {
            max-height: calc(100vh - 105px) !important;
            overflow-y: auto !important;
        }
        </style>' . "\n";
}

// ── Single output buffer for all injections ─────────────────────────────────
// Injects collected HTML before </body>. Neutralizes </body> inside HTML
// comments first so str_replace only matches the real closing tag.

if (!empty($unitas_inject_html)) {
    ob_start(function ($buffer) use ($unitas_inject_html) {
        if (stripos($buffer, '</body>') !== false) {
            $safe = preg_replace('/<!--(.*?)<\/body>(.*?)-->/si', '<!--$1&lt;/body&gt;$2-->', $buffer);
            return str_replace('</body>', $unitas_inject_html . '</body>', $safe);
        }
        return $buffer;
    });
}
