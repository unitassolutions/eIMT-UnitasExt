/**
 * UNITAS Extension - shared Google Maps JS loader (browser side).
 *
 * window.UnitasGMaps.load(libraries) -> Promise that resolves once the
 * requested Maps JS libraries are available. Loads Maps JS at most once per
 * page using the browser key from window.UNITAS_GMAPS (emitted by
 * unitas_google_loader::emit()). If Maps JS is already present (classic load or
 * a prior bootstrap), its importLibrary is reused instead of loading again.
 *
 * Safe to include more than once (e.g. inside AJAX-loaded modal HTML): the
 * guard below keeps the first definition.
 *
 * See plan section 7.5.
 */
(function () {
    'use strict';

    if (window.UnitasGMaps) return; // already defined; keep the first one

    var cfg = window.UNITAS_GMAPS || {};
    var bootstrapAttempted = false;
    var libPromises = {};

    /**
     * Ensure google.maps.importLibrary exists. If Maps JS is already loaded we
     * reuse it; otherwise we run Google's official dynamic library import
     * bootstrap once, parametrized with our browser key, version and map IDs.
     */
    function ensureBootstrap() {
        if (window.google && window.google.maps &&
            typeof window.google.maps.importLibrary === 'function') {
            return; // reuse an existing loader
        }
        if (bootstrapAttempted) return;
        bootstrapAttempted = true;

        if (!cfg.browserKey) {
            throw new Error('Google Maps browser key is not configured');
        }

        var params = { key: cfg.browserKey, v: 'weekly' };
        if (cfg.mapIds && cfg.mapIds.length) params.mapIds = cfg.mapIds;

        // Google's official inline bootstrap: defines google.maps.importLibrary
        // synchronously and defers the actual script load to the first call.
        (function (g) {
            var h, a, k, p = 'The Google Maps JavaScript API', c = 'google', l = 'importLibrary',
                q = '__ib__', m = document, b = window;
            b = b[c] || (b[c] = {});
            var d = b.maps || (b.maps = {}), r = new Set(), e = new URLSearchParams(),
                u = function () {
                    return h || (h = new Promise(function (f, n) {
                        a = m.createElement('script');
                        e.set('libraries', Array.from(r).join(','));
                        for (k in g) e.set(k.replace(/[A-Z]/g, function (t) { return '_' + t[0].toLowerCase(); }), g[k]);
                        e.set('callback', c + '.maps.' + q);
                        a.src = 'https://maps.' + c + 'apis.com/maps/api/js?' + e;
                        d[q] = f;
                        a.onerror = function () { h = n(Error(p + ' could not load.')); };
                        a.nonce = (m.querySelector('script[nonce]') || {}).nonce || '';
                        m.head.append(a);
                    }));
                };
            if (d[l]) {
                // Already bootstrapped elsewhere; do not load twice.
                return;
            }
            d[l] = function (f) {
                var n = [].slice.call(arguments, 1);
                return r.add(f) && u().then(function () { return d[l].apply(d, [f].concat(n)); });
            };
        })(params);
    }

    /**
     * Load the requested Maps JS libraries.
     * @param {string[]} libraries e.g. ['maps','marker','places','geometry']
     * @returns {Promise} resolves with the loaded library modules (in order)
     */
    function load(libraries) {
        libraries = (libraries && libraries.length) ? libraries : ['maps'];
        try {
            ensureBootstrap();
        } catch (err) {
            return Promise.reject(err);
        }
        if (!(window.google && window.google.maps &&
              typeof window.google.maps.importLibrary === 'function')) {
            return Promise.reject(new Error('Google Maps loader is unavailable'));
        }
        return Promise.all(libraries.map(function (lib) {
            if (!libPromises[lib]) {
                libPromises[lib] = window.google.maps.importLibrary(lib);
            }
            return libPromises[lib];
        }));
    }

    window.UnitasGMaps = { load: load, config: cfg };
})();
