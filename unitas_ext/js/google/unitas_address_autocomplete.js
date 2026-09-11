/**
 * UNITAS Extension - address autocomplete widget (Places API New).
 *
 * Attaches a lightweight suggestion dropdown to plain text inputs
 * (input#fields_{id}) whose id is in window.UNITAS_AUTOCOMPLETE_FIELDS. Uses
 * the Places Autocomplete Data API (AutocompleteSuggestion) with session
 * tokens, so it binds to the existing Rukovoditel input without replacing it.
 *
 * Emits on the input:
 *   unitas:address-selected  detail {address, lat, lng, placeId}
 *   unitas:address-edited    (once per edit burst)
 *
 * Degrades silently: any load/request failure closes the list, logs one
 * console.warn, and leaves the input working as plain text.
 *
 * See plan section 7.6.
 */
(function () {
    'use strict';

    if (window.UnitasAddressAutocomplete) return; // safe re-include (AJAX modals)

    var MIN_CHARS = 3;
    var DEBOUNCE_MS = 300;
    var MAX_SUGGESTIONS = 5;
    var warned = false;

    function cfg() { return window.UNITAS_GMAPS || {}; }
    function activeFieldIds() { return window.UNITAS_AUTOCOMPLETE_FIELDS || []; }

    function warnOnce(msg, err) {
        if (warned) return;
        warned = true;
        try { console.warn('[unitas-autocomplete] ' + msg, err || ''); } catch (e) {}
    }

    function fieldIdOf(input) {
        if (!input || input.tagName !== 'INPUT') return null;
        var m = /^fields_(\d+)$/.exec(input.id || '');
        return m ? parseInt(m[1], 10) : null;
    }

    function eligible(input) {
        var id = fieldIdOf(input);
        return id !== null && activeFieldIds().indexOf(id) !== -1;
    }

    // ── Per-input controller ────────────────────────────────────────────────
    function Controller(input) {
        this.input = input;
        this.seq = 0;                 // request sequence, to drop stale responses
        this.token = null;            // AutocompleteSessionToken for the session
        this.places = null;           // resolved places library
        this.debounce = null;
        this.suggestions = [];
        this.activeIndex = -1;
        this.programmatic = false;    // set while we write the input ourselves
        this.open = false;
        this.editBurst = false;
        this.listEl = this.buildList();
        this.reposition = this.reposition.bind(this);
        this.bind();
    }

    Controller.prototype.buildList = function () {
        var el = document.createElement('div');
        el.className = 'izo-autocomplete-items';
        el.setAttribute('role', 'listbox');
        el.id = 'unitas-ac-list-' + (fieldIdOf(this.input) || Math.random().toString(36).slice(2));
        el.style.position = 'fixed';
        el.style.marginLeft = '0';
        el.style.zIndex = '11000';
        el.style.display = 'none';
        document.body.appendChild(el);

        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-controls', el.id);
        this.input.setAttribute('autocomplete', 'off');
        return el;
    };

    Controller.prototype.bind = function () {
        var self = this;
        this.input.addEventListener('input', function (e) { self.onInput(e); });
        this.input.addEventListener('keydown', function (e) { self.onKeyDown(e); });
        this.input.addEventListener('blur', function () {
            // Delay so a mousedown selection on the list can complete first.
            setTimeout(function () { self.close(); self.discardToken(); }, 150);
        });
        document.addEventListener('click', function (e) {
            if (e.target !== self.input && !self.listEl.contains(e.target)) self.close();
        });
    };

    Controller.prototype.ensurePlaces = function () {
        var self = this;
        if (this.places) return Promise.resolve(this.places);
        if (!window.UnitasGMaps) return Promise.reject(new Error('loader unavailable'));
        return window.UnitasGMaps.load(['places']).then(function () {
            return window.google.maps.importLibrary('places');
        }).then(function (lib) { self.places = lib; return lib; });
    };

    Controller.prototype.onInput = function () {
        if (this.programmatic) return; // our own write, not a user edit

        // Signal an edit burst once so a bound location field can clear itself.
        if (!this.editBurst) {
            this.editBurst = true;
            this.dispatch('unitas:address-edited', {});
        }

        var value = this.input.value.trim();
        if (value.length < MIN_CHARS) { this.close(); return; }

        var self = this;
        if (this.debounce) clearTimeout(this.debounce);
        this.debounce = setTimeout(function () { self.query(value); }, DEBOUNCE_MS);
    };

    Controller.prototype.query = function (value) {
        var self = this;
        var mySeq = ++this.seq;

        this.ensurePlaces().then(function (places) {
            if (!self.token) self.token = new places.AutocompleteSessionToken();

            var request = {
                input: value,
                sessionToken: self.token,
                language: 'en-US'
            };
            var regions = cfg().regionCodes || [];
            if (regions.length) request.includedRegionCodes = regions;
            var center = cfg().defaultCenter, radius = cfg().biasRadiusM || 0;
            if (center && radius > 0) request.locationBias = { center: center, radius: radius };

            return places.AutocompleteSuggestion.fetchAutocompleteSuggestions(request);
        }).then(function (res) {
            if (mySeq !== self.seq) return; // stale response, a newer query exists
            var list = (res && res.suggestions) ? res.suggestions : [];
            self.render(list.slice(0, MAX_SUGGESTIONS));
        }).catch(function (err) {
            self.close();
            warnOnce('suggestion lookup failed', err);
        });
    };

    Controller.prototype.predictionText = function (pred) {
        if (!pred) return '';
        var t = pred.text;
        if (!t) return '';
        // FormattableText exposes .text; fall back to toString.
        return String(t.text != null ? t.text : t);
    };

    Controller.prototype.render = function (suggestions) {
        this.suggestions = suggestions;
        this.activeIndex = -1;
        this.listEl.textContent = '';

        if (!suggestions.length) { this.close(); return; }

        var self = this;
        suggestions.forEach(function (s, i) {
            var pred = s.placePrediction;
            var row = document.createElement('div');
            row.setAttribute('role', 'option');
            row.id = self.listEl.id + '-opt-' + i;
            row.textContent = self.predictionText(pred); // textContent only: no HTML injection
            row.addEventListener('mousedown', function (e) {
                e.preventDefault(); // keep focus so blur does not pre-empt the pick
                self.choose(i);
            });
            self.listEl.appendChild(row);
        });

        this.reposition();
        this.listEl.style.display = 'block';
        this.open = true;
        this.input.setAttribute('aria-expanded', 'true');
        window.addEventListener('scroll', this.reposition, true);
        window.addEventListener('resize', this.reposition);
    };

    Controller.prototype.reposition = function () {
        var r = this.input.getBoundingClientRect();
        this.listEl.style.left = r.left + 'px';
        this.listEl.style.top = r.bottom + 'px';
        this.listEl.style.width = r.width + 'px';
    };

    Controller.prototype.highlight = function (index) {
        var rows = this.listEl.children;
        for (var i = 0; i < rows.length; i++) {
            rows[i].classList.toggle('izo-autocomplete-active', i === index);
        }
        this.activeIndex = index;
        if (index >= 0 && rows[index]) {
            this.input.setAttribute('aria-activedescendant', rows[index].id);
            rows[index].scrollIntoView({ block: 'nearest' });
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    };

    Controller.prototype.onKeyDown = function (e) {
        if (!this.open) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.highlight(Math.min(this.activeIndex + 1, this.suggestions.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.highlight(Math.max(this.activeIndex - 1, 0));
        } else if (e.key === 'Enter') {
            if (this.activeIndex >= 0) { e.preventDefault(); this.choose(this.activeIndex); }
        } else if (e.key === 'Escape') {
            this.close();
        }
    };

    Controller.prototype.choose = function (index) {
        var self = this;
        var s = this.suggestions[index];
        if (!s || !s.placePrediction) return;
        var pred = s.placePrediction;
        var placeId = pred.placeId || (pred.place && pred.place.id) || '';

        var place;
        try { place = pred.toPlace(); } catch (err) { warnOnce('toPlace failed', err); return; }

        // fetchFields includes the session token automatically and closes the session.
        place.fetchFields({ fields: ['formattedAddress', 'location'] }).then(function () {
            self.discardToken();

            var address = place.formattedAddress || self.predictionText(pred);
            var regions = cfg().regionCodes || [];
            if (regions.length === 1 && regions[0] === 'us') {
                address = address.replace(/,\s*USA$/, '');
            }

            var lat = null, lng = null;
            if (place.location) {
                lat = (typeof place.location.lat === 'function') ? place.location.lat() : place.location.lat;
                lng = (typeof place.location.lng === 'function') ? place.location.lng() : place.location.lng;
            }

            self.setValue(address);
            self.dispatch('unitas:address-selected', {
                address: address, lat: lat, lng: lng, placeId: placeId
            });
            self.close();
        }).catch(function (err) {
            self.close();
            warnOnce('place details failed', err);
        });
    };

    Controller.prototype.setValue = function (value) {
        this.programmatic = true;
        this.input.value = value;
        // Let Rukovoditel display rules and validators react to the change.
        this.input.dispatchEvent(new Event('input', { bubbles: true }));
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
        this.programmatic = false;
        this.editBurst = false; // a selection resets the edit burst
    };

    Controller.prototype.dispatch = function (name, detail) {
        this.input.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail }));
    };

    Controller.prototype.discardToken = function () {
        this.token = null; // next keystroke starts a new billable session
    };

    Controller.prototype.close = function () {
        if (!this.open) return;
        this.open = false;
        this.listEl.style.display = 'none';
        this.listEl.textContent = '';
        this.activeIndex = -1;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
        window.removeEventListener('scroll', this.reposition, true);
        window.removeEventListener('resize', this.reposition);
    };

    function attach(input) {
        if (input.getAttribute('data-unitas-ac') === '1') return;
        input.setAttribute('data-unitas-ac', '1');
        try { new Controller(input); } catch (err) { warnOnce('init failed', err); }
    }

    // Delegated focusin so inputs added later (AJAX modal forms) are covered.
    document.addEventListener('focusin', function (e) {
        if (eligible(e.target)) attach(e.target);
    });

    // Attach to any already-present eligible inputs on load.
    function attachExisting() {
        activeFieldIds().forEach(function (id) {
            var input = document.getElementById('fields_' + id);
            if (input && eligible(input)) attach(input);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachExisting);
    } else {
        attachExisting();
    }

    // rescan() is called after the field id list is appended to (e.g. a modal
    // rendered after the widget already loaded) to attach any present inputs.
    window.UnitasAddressAutocomplete = { attach: attach, rescan: attachExisting };
})();
