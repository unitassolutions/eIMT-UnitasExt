/**
 * UNITAS Extension - shared Google Maps JS loader (browser side).
 *
 * window.UnitasGMaps.load(libraries) -> Promise that resolves once Maps JS is
 * loaded and the requested libraries are available.
 *
 * Loads Maps JS the CLASSIC way (script tag + callback), never the dynamic
 * bootstrap: the dynamic bootstrap creates a half-empty google.maps stub
 * (importLibrary only) which breaks every consumer that tests
 * `window.google && google.maps` and expects the fully populated classic
 * namespace - the geometry field, core map fields, and the Extension's stock
 * components all do exactly that.
 *
 * Coordination: uses the SAME flags as the geometry field
 * (_unitasGeoApiLoading / _unitasGeoApiReady / _unitasGeoQueue), so whichever
 * of the two starts loading first, the other queues on it and Maps JS is
 * loaded at most once. A classic script tag emitted by core or the Extension
 * is detected and reused instead of loading a second copy.
 *
 * Safe to include more than once (AJAX modal HTML): the guard keeps the
 * first definition.
 *
 * See plan section 7.5.
 */
(function () {
    'use strict';

    if (window.UnitasGMaps) return; // already defined; keep the first one

    var cfg = window.UNITAS_GMAPS || {};
    var libPromises = {};

    /** Fully loaded classic namespace? */
    function mapsReady() {
        return !!(window.google && window.google.maps && window.google.maps.Map);
    }

    /** Import extra libraries on top of a loaded API (memoized). */
    function ensureLibs(libraries) {
        if (!libraries || !libraries.length) return Promise.resolve([]);
        if (!(window.google && window.google.maps)) {
            return Promise.reject(new Error('Google Maps namespace missing after load'));
        }
        if (typeof window.google.maps.importLibrary !== 'function') {
            // Old build without importLibrary: the classic libraries= param on
            // our own load already includes geometry, places and marker.
            return Promise.resolve([]);
        }
        return Promise.all(libraries.map(function (lib) {
            if (!libPromises[lib]) {
                libPromises[lib] = window.google.maps.importLibrary(lib);
            }
            return libPromises[lib];
        }));
    }

    /** Resolve when the shared geometry-compatible ready callback fires. */
    function waitForGeoReady() {
        return new Promise(function (resolve) {
            window._unitasGeoQueue = window._unitasGeoQueue || [];
            window._unitasGeoQueue.push(resolve);
        });
    }

    /** A foreign classic script tag is on the page: wait for it to finish. */
    function pollForMaps(timeoutMs) {
        return new Promise(function (resolve, reject) {
            if (mapsReady()) return resolve();
            var waited = 0;
            var iv = setInterval(function () {
                if (mapsReady()) { clearInterval(iv); resolve(); return; }
                waited += 200;
                if (waited >= timeoutMs) {
                    clearInterval(iv);
                    reject(new Error('Existing Google Maps script did not finish loading'));
                }
            }, 200);
        });
    }

    /** Start the classic load ourselves, using the shared geometry flags. */
    function classicLoad() {
        window._unitasGeoApiLoading = true;
        window._unitasGeoQueue = window._unitasGeoQueue || [];
        if (typeof window._unitasGeoApiReady !== 'function') {
            window._unitasGeoApiReady = function () {
                window._unitasGeoApiLoaded = true;
                (window._unitasGeoQueue || []).forEach(function (fn) { fn(); });
                window._unitasGeoQueue = [];
            };
        }

        var ready = waitForGeoReady();

        var url = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(cfg.browserKey || '')
                + '&libraries=geometry,places,marker'
                + (cfg.mapIds && cfg.mapIds.length ? '&map_ids=' + cfg.mapIds.join(',') : '')
                + '&callback=_unitasGeoApiReady';

        var s = document.createElement('script');
        s.src = url;
        s.async = true;
        document.head.appendChild(s);

        return ready;
    }

    function withTimeout(promise, ms) {
        return new Promise(function (resolve, reject) {
            var t = setTimeout(function () {
                reject(new Error('Google Maps load timed out'));
            }, ms);
            promise.then(
                function (v) { clearTimeout(t); resolve(v); },
                function (e) { clearTimeout(t); reject(e); }
            );
        });
    }

    /**
     * Load Maps JS (once) and the requested libraries.
     * @param {string[]} libraries e.g. ['maps','marker','places','geometry']
     * @returns {Promise}
     */
    function load(libraries) {
        libraries = (libraries && libraries.length) ? libraries : [];

        var p;
        if (mapsReady()) {
            p = Promise.resolve();
        } else if (window._unitasGeoApiLoading && !window._unitasGeoApiLoaded) {
            // The geometry field (or an earlier load() call) already started a
            // classic load; queue on its shared callback.
            p = waitForGeoReady();
        } else if (document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]')) {
            // A core field or the Extension already emitted a classic tag.
            p = pollForMaps(10000);
        } else if (!cfg.browserKey) {
            return Promise.reject(new Error('Google Maps browser key is not configured'));
        } else {
            p = classicLoad();
        }

        return withTimeout(p, 15000).then(function () { return ensureLibs(libraries); });
    }

    window.UnitasGMaps = { load: load, config: cfg };
})();
