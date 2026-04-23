/**
 * UNITAS Extension - Button Loader
 * Injects entity buttons onto listing pages
 * ZERO CSS IMPACT - Uses only Rukovoditel's native modal system
 */

(function() {
    'use strict';
    
    // Prevent multiple loads
    if (window.unitasLoaded) {
        return;
    }
    window.unitasLoaded = true;
    
    // ============================================
    // 1. GLOBAL MODAL FUNCTION - Uses Rukovoditel's open_dialog
    // ============================================
    window.unitasOpenCleanReport = function(url, title) {
        // Simply use Rukovoditel's built-in open_dialog function
        // Add parameters to hide sidebar/header
        if (typeof open_dialog === 'function') {
            open_dialog(url);
        } else {
            // Fallback - should never happen
            window.location.href = url;
        }
    };
    
    // ============================================
    // 2. INITIALIZATION
    // ============================================
    function init() {
        if (typeof jQuery === 'undefined') {
            setTimeout(init, 100);
            return;
        }
        
        jQuery(document).ready(function($) {
            var entityId = getCurrentEntityId();
            
            if (entityId && entityId > 0) {
                setTimeout(function() {
                    loadButtons(entityId, $);
                }, 500);
            }
        });
    }
    
    // ============================================
    // 3. GET CURRENT ENTITY ID
    // ============================================
    function getCurrentEntityId() {
        var urlParams = new URLSearchParams(window.location.search);
        var path = urlParams.get('path');
        var module = urlParams.get('module');
        var entitiesId = urlParams.get('entities_id');
        
        if (entitiesId) {
            return parseInt(entitiesId, 10);
        }
        
        if (path && module === 'items/items') {
            if (path.includes('/')) {
                var parts = path.split('/');
                var subEntityPart = parts[parts.length - 1];
                
                if (subEntityPart.includes('-')) {
                    return parseInt(subEntityPart.split('-')[0], 10);
                }
                return parseInt(subEntityPart, 10);
            }
            
            if (path.includes('-')) {
                return parseInt(path.split('-')[0], 10);
            }
            
            return parseInt(path, 10);
        }
        
        return 0;
    }
    
    // ============================================
    // 4. LOAD BUTTONS FROM SERVER
    // ============================================
    function loadButtons(entityId, $) {
        var url = 'index.php?module=unitas_ext/entity_buttons/ajax_get_buttons&entity_id=' + entityId;
        
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.buttons_html && response.button_count > 0) {
                    // Insert buttons as plain HTML - NO wrapper div/span
                    var $buttons = $(response.buttons_html);
                    
                    // Find button area
                    var $buttonArea = $('.entitly-listing-buttons-left');
                    
                    if ($buttonArea.length) {
                        var $addButton = $buttonArea.find('button:first');
                        if ($addButton.length) {
                            $addButton.after($buttons);
                        } else {
                            $buttonArea.append($buttons);
                        }
                    } else {
                        // Alternative: find any button toolbar
                        $buttonArea = $('.btn-toolbar').first();
                        if ($buttonArea.length) {
                            $buttonArea.prepend($buttons);
                        }
                    }
                }
            },
            error: function() {
                // Silent fail - don't break the page
            }
        });
    }
    
    // Start initialization
    init();
    
})();