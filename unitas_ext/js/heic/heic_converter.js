/**
 * HEIC Converter for Rukovoditel — v1.2.0
 *
 * Automatically converts HEIC/HEIF files to JPEG with EXIF preservation,
 * transparently intercepting at the NETWORK LAYER before files reach the server.
 *
 * Architecture (v1.2.0 — XHR interception):
 *   Previous versions tried to intercept the 'change' event before Rukovoditel's
 *   handlers could see the HEIC file. This failed because Rukovoditel (or its
 *   jQuery file upload plugin) captures files in ways that can't be reliably
 *   pre-empted via event manipulation.
 *
 *   v1.2.0 uses a fundamentally different strategy:
 *     Layer 1 — VISUAL FEEDBACK: A change handler on file inputs shows a
 *       conversion overlay so users know HEIC files were detected.
 *     Layer 2 — XHR INTERCEPTION: XMLHttpRequest.prototype.send is monkey-patched.
 *       When any XHR sends a FormData body containing HEIC files, the send is
 *       paused, HEIC files are converted to JPEG (with EXIF), the FormData is
 *       rebuilt with JPEGs, and the original send proceeds.
 *     Layer 3 — INPUT REPLACEMENT: We still try to replace input.files via
 *       DataTransfer for regular (non-AJAX) form submissions.
 *
 *   This works because it doesn't matter HOW Rukovoditel captures the file —
 *   we catch it right before it leaves the browser.
 *
 * @version 1.2.0
 */
(function () {
    'use strict';

    // ── Configuration ──────────────────────────────────────────────────────────
    var HEIC_CONFIG = {
        jpegQuality: 0.92,
        heicExtensions: ['heic', 'heif'],
        heicMimeTypes: ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'],
        libraries: {
            heic2any: 'plugins/unitas_ext/js/heic/heic2any.min.js',
            exifr:    'plugins/unitas_ext/js/heic/exifr.umd.js',
            piexif:   'plugins/unitas_ext/js/heic/piexif.js'
        }
    };

    // ── State ───────────────────────────────────────────────────────────────────
    var librariesLoaded = false;
    var librariesLoading = false;
    var libraryQueue = [];
    var processedInputs = new WeakSet();

    // Conversion cache: WeakMap<original File, Promise<converted File>>
    // Allows the XHR patch to reuse conversions started by the change handler.
    var conversionCache = new WeakMap();

    // ── Library Loader ──────────────────────────────────────────────────────────

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = function () { reject(new Error('Failed to load: ' + src)); };
            document.head.appendChild(script);
        });
    }

    function ensureLibrariesLoaded() {
        if (librariesLoaded) {
            return Promise.resolve();
        }

        if (librariesLoading) {
            return new Promise(function (resolve, reject) {
                libraryQueue.push({ resolve: resolve, reject: reject });
            });
        }

        librariesLoading = true;

        return Promise.all([
            loadScript(HEIC_CONFIG.libraries.heic2any),
            loadScript(HEIC_CONFIG.libraries.exifr),
            loadScript(HEIC_CONFIG.libraries.piexif)
        ]).then(function () {
            librariesLoaded = true;
            librariesLoading = false;
            libraryQueue.forEach(function (p) { p.resolve(); });
            libraryQueue = [];
        }).catch(function (err) {
            librariesLoading = false;
            libraryQueue.forEach(function (p) { p.reject(err); });
            libraryQueue = [];
            throw err;
        });
    }

    // ── File Detection ──────────────────────────────────────────────────────────

    function isHeicFile(file) {
        if (!(file instanceof File) && !(file instanceof Blob)) return false;

        if (file.type && HEIC_CONFIG.heicMimeTypes.indexOf(file.type.toLowerCase()) !== -1) {
            return true;
        }

        var name = file.name || '';
        var ext = name.split('.').pop().toLowerCase();
        return HEIC_CONFIG.heicExtensions.indexOf(ext) !== -1;
    }

    // ── EXIF Preservation ───────────────────────────────────────────────────────

    function extractExif(heicFile) {
        if (typeof exifr === 'undefined') return Promise.resolve(null);

        return exifr.parse(heicFile, {
            tiff: true, exif: true, gps: true, ifd0: true,
            ifd1: false, interop: false, iptc: false, xmp: false,
            translateValues: false, translateKeys: false, reviveValues: false
        }).catch(function () { return null; });
    }

    function decimalToDmsRational(decimal) {
        var abs = Math.abs(decimal);
        var deg = Math.floor(abs);
        var minFloat = (abs - deg) * 60;
        var min = Math.floor(minFloat);
        var sec = Math.round((minFloat - min) * 60 * 10000);
        return [[deg, 1], [min, 1], [sec, 10000]];
    }

    function formatExifDate(val) {
        if (!val) return null;
        if (typeof val === 'string' && /^\d{4}:\d{2}:\d{2}/.test(val)) return val;

        var d;
        if (val instanceof Date) d = val;
        else if (typeof val === 'number') d = new Date(val);
        else if (typeof val === 'string') d = new Date(val);

        if (d && !isNaN(d.getTime())) {
            var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
            return d.getFullYear() + ':' + pad(d.getMonth() + 1) + ':' + pad(d.getDate()) +
                   ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        }
        return null;
    }

    function buildPiexifFromExifr(data) {
        if (!data || typeof piexif === 'undefined') return null;

        var zeroth = {}, exifIfd = {}, gpsIfd = {};

        // IFD0
        if (data.Make) zeroth[piexif.ImageIFD.Make] = String(data.Make);
        if (data.Model) zeroth[piexif.ImageIFD.Model] = String(data.Model);
        if (data.Orientation) zeroth[piexif.ImageIFD.Orientation] = Number(data.Orientation);
        if (data.Software) zeroth[piexif.ImageIFD.Software] = String(data.Software);

        var dt = formatExifDate(data.DateTime || data.ModifyDate);
        if (dt) zeroth[piexif.ImageIFD.DateTime] = dt;

        // EXIF IFD
        var dto = formatExifDate(data.DateTimeOriginal);
        if (dto) exifIfd[piexif.ExifIFD.DateTimeOriginal] = dto;

        var dtd = formatExifDate(data.DateTimeDigitized || data.CreateDate);
        if (dtd) exifIfd[piexif.ExifIFD.DateTimeDigitized] = dtd;

        if (data.SubSecTimeOriginal !== undefined) {
            exifIfd[piexif.ExifIFD.SubSecTimeOriginal] = String(data.SubSecTimeOriginal);
        }

        // GPS
        var lat = data.latitude || data.GPSLatitude;
        var lon = data.longitude || data.GPSLongitude;

        if (lat !== undefined && lon !== undefined) {
            if (typeof lat === 'number' && typeof lon === 'number') {
                gpsIfd[piexif.GPSIFD.GPSLatitudeRef] = lat >= 0 ? 'N' : 'S';
                gpsIfd[piexif.GPSIFD.GPSLatitude] = decimalToDmsRational(lat);
                gpsIfd[piexif.GPSIFD.GPSLongitudeRef] = lon >= 0 ? 'E' : 'W';
                gpsIfd[piexif.GPSIFD.GPSLongitude] = decimalToDmsRational(lon);
            } else if (Array.isArray(lat)) {
                gpsIfd[piexif.GPSIFD.GPSLatitude] = lat;
                gpsIfd[piexif.GPSIFD.GPSLatitudeRef] = data.GPSLatitudeRef || 'N';
                gpsIfd[piexif.GPSIFD.GPSLongitude] = lon;
                gpsIfd[piexif.GPSIFD.GPSLongitudeRef] = data.GPSLongitudeRef || 'W';
            }
        }

        var alt = data.GPSAltitude;
        if (alt !== undefined) {
            if (typeof alt === 'number') {
                gpsIfd[piexif.GPSIFD.GPSAltitude] = [Math.round(Math.abs(alt) * 100), 100];
                gpsIfd[piexif.GPSIFD.GPSAltitudeRef] = alt >= 0 ? 0 : 1;
            }
        }

        if (data.GPSDateStamp) gpsIfd[piexif.GPSIFD.GPSDateStamp] = String(data.GPSDateStamp);

        if (data.GPSSpeed !== undefined && typeof data.GPSSpeed === 'number') {
            gpsIfd[piexif.GPSIFD.GPSSpeed] = [Math.round(data.GPSSpeed * 100), 100];
            if (data.GPSSpeedRef) gpsIfd[piexif.GPSIFD.GPSSpeedRef] = String(data.GPSSpeedRef);
        }

        if (data.GPSImgDirection !== undefined && typeof data.GPSImgDirection === 'number') {
            gpsIfd[piexif.GPSIFD.GPSImgDirection] = [Math.round(data.GPSImgDirection * 100), 100];
            if (data.GPSImgDirectionRef) gpsIfd[piexif.GPSIFD.GPSImgDirectionRef] = String(data.GPSImgDirectionRef);
        }

        var hasData = Object.keys(zeroth).length || Object.keys(exifIfd).length || Object.keys(gpsIfd).length;
        if (!hasData) return null;

        return { '0th': zeroth, 'Exif': exifIfd, 'GPS': gpsIfd, '1st': {} };
    }

    function injectExifIntoJpeg(jpegBlob, piexifObj) {
        if (!piexifObj || typeof piexif === 'undefined') return Promise.resolve(jpegBlob);

        return new Promise(function (resolve) {
            var reader = new FileReader();
            reader.onload = function () {
                try {
                    var exifBytes = piexif.dump(piexifObj);
                    var newDataUrl = piexif.insert(exifBytes, reader.result);
                    var binary = atob(newDataUrl.split(',')[1]);
                    var array = new Uint8Array(binary.length);
                    for (var i = 0; i < binary.length; i++) array[i] = binary.charCodeAt(i);
                    resolve(new Blob([array], { type: 'image/jpeg' }));
                } catch (e) {
                    console.warn('[HEIC Converter] EXIF injection failed (non-critical):', e);
                    resolve(jpegBlob);
                }
            };
            reader.onerror = function () { resolve(jpegBlob); };
            reader.readAsDataURL(jpegBlob);
        });
    }

    // ── Conversion ──────────────────────────────────────────────────────────────

    /**
     * Convert a single HEIC file to JPEG with EXIF preservation.
     * Results are cached per File object so the XHR patch can reuse
     * conversions started by the change handler overlay.
     */
    function convertHeicToJpeg(heicFile) {
        // Check cache first
        if (conversionCache.has(heicFile)) {
            return conversionCache.get(heicFile);
        }

        var promise = ensureLibrariesLoaded().then(function () {
            return Promise.all([
                extractExif(heicFile),
                heic2any({ blob: heicFile, toType: 'image/jpeg', quality: HEIC_CONFIG.jpegQuality })
            ]);
        }).then(function (results) {
            var exifrData = results[0];
            var heicResult = results[1];
            var jpegBlob = Array.isArray(heicResult) ? heicResult[0] : heicResult;
            var piexifObj = buildPiexifFromExifr(exifrData);
            return injectExifIntoJpeg(jpegBlob, piexifObj);
        }).then(function (finalBlob) {
            var newName = heicFile.name.replace(/\.(heic|heif)$/i, '.jpg');
            return new File([finalBlob], newName, {
                type: 'image/jpeg',
                lastModified: heicFile.lastModified || Date.now()
            });
        });

        // Cache the promise
        conversionCache.set(heicFile, promise);
        return promise;
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // LAYER 2: XHR INTERCEPTION
    //
    // This is the core mechanism that actually prevents HEIC files from reaching
    // the server. It monkey-patches XMLHttpRequest.prototype.send to inspect
    // outgoing FormData bodies for HEIC files and convert them before sending.
    //
    // This works regardless of:
    //   - How Rukovoditel captures files (change event, jQuery plugin, etc.)
    //   - Whether uploads are immediate or deferred
    //   - Whether jQuery, vanilla XHR, or any library is used
    // ═════════════════════════════════════════════════════════════════════════════

    var originalXHRSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.send = function (body) {
        var xhr = this;

        // Only intercept FormData bodies
        if (!(body instanceof FormData)) {
            return originalXHRSend.call(xhr, body);
        }

        // Collect all FormData entries and check for HEIC files
        var entries = [];
        var heicCount = 0;

        // FormData.entries() is supported in all browsers that support HEIC
        var iterator = body.entries();
        var entry = iterator.next();
        while (!entry.done) {
            var pair = entry.value;
            entries.push({ name: pair[0], value: pair[1] });
            if (isHeicFile(pair[1])) {
                heicCount++;
            }
            entry = iterator.next();
        }

        // No HEIC files — send immediately, no delay
        if (heicCount === 0) {
            return originalXHRSend.call(xhr, body);
        }

        console.log('[HEIC Converter] XHR intercepted: ' + heicCount + ' HEIC file(s) in FormData. Converting before send...');

        // Show a page-level overlay during conversion
        var overlay = showPageOverlay(heicCount);

        // Convert all HEIC entries, leave non-HEIC entries as-is
        var conversionPromises = entries.map(function (entry) {
            if (isHeicFile(entry.value)) {
                return convertHeicToJpeg(entry.value).then(function (jpegFile) {
                    return { name: entry.name, value: jpegFile, isFile: true };
                }).catch(function (err) {
                    console.warn('[HEIC Converter] XHR conversion failed, sending original:', err);
                    return { name: entry.name, value: entry.value, isFile: true };
                });
            }
            return Promise.resolve({
                name: entry.name,
                value: entry.value,
                isFile: (entry.value instanceof File || entry.value instanceof Blob)
            });
        });

        Promise.all(conversionPromises).then(function (converted) {
            // Rebuild FormData with converted files
            var newFormData = new FormData();
            converted.forEach(function (item) {
                if (item.isFile && item.value instanceof File) {
                    newFormData.append(item.name, item.value, item.value.name);
                } else if (item.isFile && item.value instanceof Blob) {
                    newFormData.append(item.name, item.value);
                } else {
                    newFormData.append(item.name, item.value);
                }
            });

            overlay.success();
            console.log('[HEIC Converter] XHR conversion complete. Sending converted FormData.');

            // Now send the original XHR with the rebuilt FormData
            originalXHRSend.call(xhr, newFormData);
        }).catch(function (err) {
            console.error('[HEIC Converter] XHR conversion failed entirely, sending original:', err);
            overlay.error();
            // Fallback: send original FormData unchanged
            originalXHRSend.call(xhr, body);
        });

        // Don't call original send here — it happens in the Promise callback above
    };

    // ═════════════════════════════════════════════════════════════════════════════
    // LAYER 3: INPUT REPLACEMENT (for regular form POST submissions)
    //
    // Non-AJAX form submissions read input.files at submit time.
    // We replace input.files via DataTransfer after conversion.
    // ═════════════════════════════════════════════════════════════════════════════

    function handleFormSubmit(event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        var fileInputs = form.querySelectorAll('input[type="file"]');
        var hasHeic = false;

        for (var i = 0; i < fileInputs.length; i++) {
            var files = fileInputs[i].files;
            for (var j = 0; j < files.length; j++) {
                if (isHeicFile(files[j])) {
                    hasHeic = true;
                    break;
                }
            }
            if (hasHeic) break;
        }

        if (!hasHeic) return;

        // Prevent the form from submitting until conversion finishes
        event.preventDefault();

        var overlay = showPageOverlay(1);

        // Convert all HEIC files in all file inputs
        var inputPromises = Array.from(fileInputs).map(function (input) {
            var files = Array.from(input.files);
            var filePromises = files.map(function (file) {
                if (isHeicFile(file)) {
                    return convertHeicToJpeg(file).catch(function () { return file; });
                }
                return Promise.resolve(file);
            });

            return Promise.all(filePromises).then(function (convertedFiles) {
                try {
                    var dt = new DataTransfer();
                    convertedFiles.forEach(function (f) { dt.items.add(f); });
                    input.files = dt.files;
                } catch (e) {
                    console.warn('[HEIC Converter] DataTransfer failed for form input:', e);
                }
            });
        });

        Promise.all(inputPromises).then(function () {
            overlay.success();
            // Re-submit the form now that files are converted
            form.submit();
        }).catch(function () {
            overlay.error();
            form.submit(); // Submit anyway as fallback
        });
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // LAYER 1: VISUAL FEEDBACK (change handler on file inputs)
    //
    // This layer does NOT try to stop events or replace files. It only shows
    // an overlay so the user knows HEIC files were detected and will be converted.
    // The actual conversion is triggered here but serves the cache — the XHR
    // patch (Layer 2) uses the cached conversion result.
    // ═════════════════════════════════════════════════════════════════════════════

    function handleFileInputChange(event) {
        var input = event.target;
        if (!input || input.type !== 'file') return;

        var files = input.files;
        if (!files || files.length === 0) return;

        var heicFiles = [];
        for (var i = 0; i < files.length; i++) {
            if (isHeicFile(files[i])) heicFiles.push(files[i]);
        }

        if (heicFiles.length === 0) return;

        // Show overlay as visual feedback
        var overlay = showConversionOverlay(input, heicFiles.length);

        // Pre-warm the conversion cache — these conversions will be reused
        // by the XHR patch when the form submits
        var completed = 0;
        var promises = heicFiles.map(function (file) {
            return convertHeicToJpeg(file).then(function (jpegFile) {
                completed++;
                overlay.update(completed, heicFiles.length);
                return jpegFile;
            }).catch(function () {
                completed++;
                overlay.update(completed, heicFiles.length);
                return null;
            });
        });

        Promise.all(promises).then(function (results) {
            var successCount = results.filter(function (r) { return r !== null; }).length;
            if (successCount > 0) {
                overlay.success(successCount);

                // Also try to replace input.files (helps regular form submissions)
                try {
                    var dt = new DataTransfer();
                    var fileIndex = 0;
                    for (var i = 0; i < files.length; i++) {
                        if (isHeicFile(files[i]) && results[fileIndex]) {
                            dt.items.add(results[fileIndex]);
                            fileIndex++;
                        } else if (!isHeicFile(files[i])) {
                            dt.items.add(files[i]);
                        } else {
                            dt.items.add(files[i]); // Keep original on failure
                            fileIndex++;
                        }
                    }
                    input.files = dt.files;
                } catch (e) {
                    // DataTransfer replacement failed — XHR patch will handle it
                }
            } else {
                overlay.error('Conversion failed. Files will upload as HEIC.');
            }
        });
    }

    // ── UI Feedback ─────────────────────────────────────────────────────────────

    /**
     * Show conversion overlay near a specific file input.
     * Used by the change handler (Layer 1) for per-input feedback.
     */
    function showConversionOverlay(inputElement, fileCount) {
        var overlay = document.createElement('div');
        overlay.className = 'heic-conversion-overlay';
        overlay.innerHTML =
            '<div class="heic-conversion-content">' +
                '<div class="heic-spinner"></div>' +
                '<span class="heic-conversion-text">' +
                    'Converting ' + fileCount + ' HEIC ' +
                    (fileCount === 1 ? 'photo' : 'photos') + ' to JPEG...' +
                '</span>' +
            '</div>';

        var container = inputElement.closest('.form-group') ||
                        inputElement.closest('.upload-container') ||
                        inputElement.parentNode;

        if (container) {
            var pos = window.getComputedStyle(container).position;
            if (pos === 'static') container.style.position = 'relative';
            container.appendChild(overlay);
        }

        return {
            update: function (completed, total) {
                var text = overlay.querySelector('.heic-conversion-text');
                if (text) text.textContent = 'Converting photos... (' + completed + '/' + total + ')';
            },
            success: function (count) {
                overlay.className = 'heic-conversion-overlay heic-success';
                overlay.innerHTML =
                    '<div class="heic-conversion-content">' +
                        '<i class="fa fa-check-circle"></i> ' +
                        count + ' ' + (count === 1 ? 'photo' : 'photos') +
                        ' converted to JPEG (GPS & timestamps preserved)' +
                    '</div>';
                setTimeout(function () { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 3000);
            },
            error: function (msg) {
                overlay.className = 'heic-conversion-overlay heic-error';
                overlay.innerHTML =
                    '<div class="heic-conversion-content">' +
                        '<i class="fa fa-exclamation-triangle"></i> ' +
                        (msg || 'Conversion failed.') +
                    '</div>';
                setTimeout(function () { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 5000);
            }
        };
    }

    /**
     * Show a page-level overlay during XHR-level conversion.
     * Used by the XHR patch (Layer 2) when the change handler's overlay
     * has already disappeared.
     */
    function showPageOverlay(fileCount) {
        var overlay = document.createElement('div');
        overlay.className = 'heic-page-overlay';
        overlay.innerHTML =
            '<div class="heic-conversion-content">' +
                '<div class="heic-spinner"></div>' +
                '<span class="heic-conversion-text">' +
                    'Converting HEIC ' + (fileCount === 1 ? 'photo' : 'photos') +
                    ' before upload...' +
                '</span>' +
            '</div>';
        document.body.appendChild(overlay);

        return {
            success: function () {
                setTimeout(function () { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 500);
            },
            error: function () {
                overlay.innerHTML =
                    '<div class="heic-conversion-content heic-error">' +
                        '<i class="fa fa-exclamation-triangle"></i> ' +
                        'HEIC conversion failed at upload. Sending originals.' +
                    '</div>';
                setTimeout(function () { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 3000);
            }
        };
    }

    // ── Input Binding ───────────────────────────────────────────────────────────

    function bindFileInput(input) {
        if (processedInputs.has(input)) return;
        processedInputs.add(input);

        var accept = (input.getAttribute('accept') || '').toLowerCase();
        if (accept && accept.indexOf('image') === -1 && accept.indexOf('*') === -1 &&
            accept.indexOf('.heic') === -1 && accept.indexOf('.heif') === -1) {
            return;
        }

        // Bubble phase — we're NOT trying to stop the event, just observe it
        input.addEventListener('change', handleFileInputChange, false);
    }

    function scanAndBindInputs() {
        var inputs = document.querySelectorAll('input[type="file"]');
        for (var i = 0; i < inputs.length; i++) bindFileInput(inputs[i]);
    }

    // ── MutationObserver ────────────────────────────────────────────────────────

    function startObserver() {
        if (typeof MutationObserver === 'undefined') {
            setInterval(scanAndBindInputs, 2000);
            return;
        }

        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var node = added[j];
                    if (node.nodeType !== 1) continue;
                    if (node.tagName === 'INPUT' && node.type === 'file') {
                        bindFileInput(node);
                    } else if (node.querySelectorAll) {
                        var inputs = node.querySelectorAll('input[type="file"]');
                        for (var k = 0; k < inputs.length; k++) bindFileInput(inputs[k]);
                    }
                }
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    // ── Initialization ──────────────────────────────────────────────────────────

    function init() {
        scanAndBindInputs();
        startObserver();

        // Listen for regular form submissions (Layer 3)
        document.addEventListener('submit', handleFormSubmit, true);

        console.log('[HEIC Converter] v1.2.0 initialized — XHR interception + EXIF preservation.');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
