/**
 * UNITAS Extension - Button Loader
 * Injects entity buttons onto listing pages
 */

(function() {
    'use strict';
    
    // Prevent multiple loads
    if (window.unitasLoaded) {
        return;
    }
    window.unitasLoaded = true;
    
    // ============================================
    // 1. GLOBAL MODAL FUNCTION - CLEAN REPORT VIEW
    // ============================================
    window.unitasOpenCleanReport = function(url, title) {
        // Create unique modal ID
        var modalId = 'unitas-clean-report-' + Date.now();
        
        // Create overlay (dark background)
        var $overlay = $('<div class="unitas-clean-overlay"></div>').css({
            'position': 'fixed',
            'top': '0',
            'left': '0',
            'width': '100%',
            'height': '100%',
            'background': 'rgba(0,0,0,0.95)',
            'z-index': '99999',
            'display': 'flex',
            'align-items': 'center',
            'justify-content': 'center'
        });
        
        // Create clean modal (NO HEADER, just iframe)
        var $modal = $(
            '<div id="' + modalId + '" class="unitas-clean-modal">' +
            '  <div class="unitas-clean-close" title="Close (ESC)">&times;</div>' +
            '  <iframe src="' + url + '" frameborder="0"></iframe>' +
            '</div>'
        ).css({
            'background': '#fff',
            'width': '99%',
            'height': '98%',
            'position': 'relative',
            'box-shadow': '0 15px 60px rgba(0,0,0,0.7)',
            'border-radius': '6px',
            'overflow': 'hidden',
            'border': '1px solid #444'
        });
        
        // Style close button
        $modal.find('.unitas-clean-close').css({
            'position': 'absolute',
            'top': '20px',
            'right': '25px',
            'font-size': '42px',
            'line-height': '1',
            'color': '#fff',
            'cursor': 'pointer',
            'z-index': '1000',
            'text-shadow': '0 3px 15px rgba(0,0,0,0.8)',
            'background': 'rgba(0,0,0,0.5)',
            'width': '55px',
            'height': '55px',
            'border-radius': '50%',
            'display': 'flex',
            'align-items': 'center',
            'justify-content': 'center',
            'transition': 'all 0.3s',
            'font-weight': '300',
            'opacity': '0.9'
        }).hover(
            function() { 
                $(this).css({
                    'background': 'rgba(0,0,0,0.8)',
                    'transform': 'scale(1.15)',
                    'opacity': '1'
                }); 
            },
            function() { 
                $(this).css({
                    'background': 'rgba(0,0,0,0.5)',
                    'transform': 'scale(1)',
                    'opacity': '0.9'
                }); 
            }
        );
        
        // Style iframe
        $modal.find('iframe').css({
            'width': '100%',
            'height': '100%',
            'border': 'none',
            'display': 'block'
        });
        
        // Add to page
        $overlay.append($modal);
        $('body').append($overlay).addClass('unitas-no-scroll');
        
        // Prevent body scroll
        $('body').css({
            'overflow': 'hidden',
            'padding-right': '0'
        });
        
        // ============================================
        // CLOSE FUNCTIONALITY
        // ============================================
        function closeModal() {
            $overlay.fadeOut(200, function() {
                $(this).remove();
                $('body').css({
                    'overflow': '',
                    'padding-right': ''
                }).removeClass('unitas-no-scroll');
            });
        }
        
        // Close on X click
        $modal.find('.unitas-clean-close').click(closeModal);
        
        // Close on overlay click (outside modal)
        $overlay.click(function(e) {
            if ($(e.target).hasClass('unitas-clean-overlay')) {
                closeModal();
            }
        });
        
        // Close on ESC key
        $(document).on('keydown.unitasCleanModal', function(e) {
            if (e.keyCode === 27) { // ESC key
                closeModal();
            }
        });
        
        // Remove key listener when modal closes
        $overlay.on('remove', function() {
            $(document).off('keydown.unitasCleanModal');
        });
        
        // ============================================
        // CLEAN IFRAME CONTENT (Remove sidebar/header)
        // ============================================
        $modal.find('iframe').on('load', function() {
            var iframe = this;
            
            // Wait for iframe to fully load
            setTimeout(function() {
                try {
                    var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    var iframeWindow = iframe.contentWindow;
                    
                    // Remove sidebar completely
                    $(iframeDoc).find('#sidebar, .sidebar, .page-sidebar, .main-sidebar').remove();
                    
                    // Remove header completely
                    $(iframeDoc).find('#header, .page-header, .navbar-header, .main-header').remove();
                    
                    // Remove page title area
                    $(iframeDoc).find('.page-title, .page-title-block, .content-header').remove();
                    
                    // Remove button toolbars
                    $(iframeDoc).find('.btn-toolbar, .entitly-listing-buttons-left, .page-toolbar').remove();
                    
                    // Remove footer
                    $(iframeDoc).find('#footer, .page-footer, .main-footer').remove();
                    
                    // Remove breadcrumbs
                    $(iframeDoc).find('.breadcrumb').remove();
                    
                    // Remove navigation
                    $(iframeDoc).find('.navbar, .nav-tabs').remove();
                    
                    // Make content full width
                    $(iframeDoc).find('.container, .page-content, .content-wrapper, .content').css({
                        'width': '100%',
                        'max-width': '100%',
                        'padding': '20px',
                        'margin': '0',
                        'margin-top': '0 !important',
                        'margin-left': '0 !important',
                        'margin-right': '0 !important'
                    });
                    
                    // Remove body padding/margin
                    $(iframeDoc).find('body').css({
                        'padding': '0',
                        'margin': '0',
                        'background': '#fff',
                        'overflow-x': 'hidden'
                    });
                    
                    // Remove any left margin/padding from main content
                    $(iframeDoc).find('#content, .content-area, .wrapper').css({
                        'margin-left': '0',
                        'padding-left': '0'
                    });
                    
                    // Adjust iframe height to fit content
                    setTimeout(function() {
                        try {
                            var height = Math.max(
                                iframeDoc.body.scrollHeight,
                                iframeDoc.body.offsetHeight,
                                iframeDoc.documentElement.scrollHeight,
                                iframeDoc.documentElement.offsetHeight
                            );
                            
                            // Add some padding
                            $(iframe).height(height + 40 + 'px');
                            
                        } catch (e) {
                            // Height adjustment failed
                        }
                    }, 300);
                    
                } catch (e) {
                    // Cross-origin restriction - can't modify iframe
                    console.log('UNITAS: Using iframe as-is (security restrictions)');
                }
            }, 800); // Wait 800ms for full page load
        });
        
        // Return modal reference
        return $modal;
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
            // Get current entity ID
            var entityId = getCurrentEntityId();
            
            if (entityId && entityId > 0) {
                // Wait a bit for page to fully load
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
        
        // Priority 1: entities_id parameter
        if (entitiesId) {
            return parseInt(entitiesId, 10);
        }
        
        // Priority 2: Parse from path
        if (path && module === 'items/items') {
            // Check for sub-entity format: "21-1/22"
            if (path.includes('/')) {
                var parts = path.split('/');
                var subEntityPart = parts[parts.length - 1];
                
                if (subEntityPart.includes('-')) {
                    return parseInt(subEntityPart.split('-')[0], 10);
                }
                return parseInt(subEntityPart, 10);
            }
            
            // Main entity format: "21" or "21-1"
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
                    // Create button container
                    var $container = $('<div class="unitas-buttons"></div>');
                    $container.html(response.buttons_html);
                    
                    // Style buttons
                    $container.find('button').addClass('btn btn-default btn-sm');
                    
                    // Find button area
                    var $buttonArea = $('.entitly-listing-buttons-left');
                    
                    if ($buttonArea.length) {
                        // Insert after "Add" button
                        var $addButton = $buttonArea.find('button:first');
                        if ($addButton.length) {
                            $addButton.after($container);
                        } else {
                            $buttonArea.prepend($container);
                        }
                    } else {
                        // Alternative button area
                        $buttonArea = $('.page-toolbar .btn-toolbar:first');
                        if ($buttonArea.length) {
                            $buttonArea.prepend($container);
                        }
                    }
                }
            },
            error: function() {
                console.warn('UNITAS: Failed to load buttons');
            }
        });
    }
    
    // Start initialization
    init();
    
})();