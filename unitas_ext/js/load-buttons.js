/**
 * UNITAS Extension - Button Loader & Report Lightbox
 * v1.0.2 — Proper lightbox using custom overlay+iframe
 *
 * FIX (v1.0.2): Previous version called open_dialog() which opens a standard
 * Bootstrap modal (~600px wide). Now creates a custom full-screen overlay with
 * iframe matching the .unitas-clean-* CSS classes. Closes via:
 *   - Close button (× icon, top-right)
 *   - Click on dark backdrop (outside modal)
 *   - Escape key
 */
(function () {
    'use strict';

    // Prevent multiple loads
    if (window.unitasLoaded) return;
    window.unitasLoaded = true;

    // ════════════════════════════════════════════════════════════════════════
    // 1. REPORT LIGHTBOX
    // ════════════════════════════════════════════════════════════════════════

    var activeOverlay = null; // Track the currently open overlay

    /**
     * Open a report URL in a full-screen lightbox overlay.
     * Creates: overlay (dark backdrop) → modal (white container) → close button + iframe
     */
    window.unitasOpenCleanReport = function (url, title) {
        // Close any existing overlay first
        if (activeOverlay) {
            closeModal();
        }

        // ── Build DOM ──

        // Overlay (dark backdrop — click to close)
        var overlay = document.createElement('div');
        overlay.className = 'unitas-clean-overlay';

        // Modal container (white box with rounded corners)
        var modal = document.createElement('div');
        modal.className = 'unitas-clean-modal';

        // Close button (× icon, top-right corner)
        var closeBtn = document.createElement('button');
        closeBtn.className = 'unitas-clean-close';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('title', 'Close (Esc)');
        closeBtn.setAttribute('aria-label', 'Close report');

        // Iframe for report content
        var iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.setAttribute('title', title || 'Report');
        iframe.setAttribute('allowfullscreen', 'true');

        // ── Assemble ──
        modal.appendChild(closeBtn);
        modal.appendChild(iframe);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        document.body.classList.add('unitas-no-scroll');
        activeOverlay = overlay;

        // ── Close handlers ──

        // Close button click
        closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            closeModal();
        });

        // Click on dark backdrop (outside the modal)
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeModal();
            }
        });

        // Escape key
        document.addEventListener('keydown', handleEscape);

        // Prevent modal click from bubbling to overlay
        modal.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    };

    /**
     * Close the active lightbox and clean up.
     */
    function closeModal() {
        if (!activeOverlay) return;

        document.removeEventListener('keydown', handleEscape);
        document.body.classList.remove('unitas-no-scroll');

        // Fade out then remove
        activeOverlay.style.opacity = '0';
        activeOverlay.style.transition = 'opacity 0.15s ease-out';
        var overlay = activeOverlay;
        activeOverlay = null;

        setTimeout(function () {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 150);
    }

    // Expose for use from within iframe
    window.unitasCloseModal = closeModal;

    /**
     * Handle Escape key press.
     */
    function handleEscape(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            e.preventDefault();
            closeModal();
        }
    }

    /**
     * Reload modal iframe content (used by pagination inside the report).
     */
    window.unitasReloadModalContent = function (url) {
        if (activeOverlay) {
            var iframe = activeOverlay.querySelector('iframe');
            if (iframe) {
                iframe.src = url;
            }
        }
    };

    // ════════════════════════════════════════════════════════════════════════
    // 2. BUTTON LOADING
    // ════════════════════════════════════════════════════════════════════════

    function init() {
        if (typeof jQuery === 'undefined') {
            setTimeout(init, 100);
            return;
        }

        jQuery(document).ready(function ($) {
            var entityId = getCurrentEntityId();

            if (entityId && entityId > 0) {
                setTimeout(function () {
                    loadButtons(entityId, $);
                }, 500);
            }
        });
    }

    /**
     * Determine the current entity ID from URL parameters.
     */
    function getCurrentEntityId() {
        var urlParams = new URLSearchParams(window.location.search);
        var path = urlParams.get('path');
        var module = urlParams.get('module');
        var entitiesId = urlParams.get('entities_id');

        if (entitiesId) {
            return parseInt(entitiesId, 10);
        }

        if (path && module === 'items/items') {
            if (path.indexOf('/') !== -1) {
                var parts = path.split('/');
                var subEntityPart = parts[parts.length - 1];

                if (subEntityPart.indexOf('-') !== -1) {
                    return parseInt(subEntityPart.split('-')[0], 10);
                }
                return parseInt(subEntityPart, 10);
            }

            if (path.indexOf('-') !== -1) {
                return parseInt(path.split('-')[0], 10);
            }

            return parseInt(path, 10);
        }

        return 0;
    }

    /**
     * Fetch entity buttons from the server and inject them into the listing toolbar.
     */
    function loadButtons(entityId, $) {
        var url = 'index.php?module=unitas_ext/entity_buttons/ajax_get_buttons&entity_id=' + entityId;

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.buttons_html && response.button_count > 0) {
                    var $buttons = $(response.buttons_html);

                    // Find the listing button area
                    var $buttonArea = $('.entitly-listing-buttons-left');

                    if ($buttonArea.length) {
                        var $addButton = $buttonArea.find('button:first');
                        if ($addButton.length) {
                            $addButton.after($buttons);
                        } else {
                            $buttonArea.append($buttons);
                        }
                    } else {
                        // Fallback: find any button toolbar
                        $buttonArea = $('.btn-toolbar').first();
                        if ($buttonArea.length) {
                            $buttonArea.prepend($buttons);
                        }
                    }
                }
            },
            error: function () {
                // Silent fail — don't break the page
            }
        });
    }

    // Start initialization
    init();

})();
