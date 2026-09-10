/**
 * UNITAS Extension - location field widget (browser side).
 *
 * window.UnitasLocation.init(config) wires one location field:
 *   - listens to the bound address input's unitas:address-selected / -edited
 *     events (dispatched by the autocomplete widget),
 *   - shows a form map preview with a draggable / click-to-place pin,
 *   - keeps the hidden input's 4-part value in sync,
 *   - renders read-only on the item page.
 *
 * The server hook is authoritative: it re-geocodes when the stored address
 * differs from the source text on save.
 *
 * See plan sections 7.8, 7.10.
 */
(function () {
    'use strict';

    if (window.UnitasLocation) return;

    // ── value helpers (mirror unitas_location_value; server canonicalizes) ──
    function coordStr(v) { return (Math.round(v * 1e7) / 1e7).toString(); }

    function normalizeAddress(t) {
        t = (t == null) ? '' : String(t);
        t = t.replace(/<[^>]*>/g, '');
        t = t.replace(/[\t\r\n]+/g, ' ').replace(/\s+/g, ' ').trim();
        return t.length > 500 ? t.slice(0, 500) : t;
    }

    function fmt(lat, lng, address, status) {
        var hasPt = (lat != null && lng != null && !isNaN(lat) && !isNaN(lng));
        var latS = hasPt ? coordStr(lat) : '';
        var lngS = hasPt ? coordStr(lng) : '';
        address = normalizeAddress(address);
        if (latS === '' && address === '' && status === '') return '';
        return latS + '\t' + lngS + '\t' + address + '\t' + status;
    }

    function latLngOf(pos) {
        if (!pos) return null;
        var lat = (typeof pos.lat === 'function') ? pos.lat() : pos.lat;
        var lng = (typeof pos.lng === 'function') ? pos.lng() : pos.lng;
        if (lat == null || lng == null) return null;
        return { lat: Number(lat), lng: Number(lng) };
    }

    function Field(cfg) {
        this.cfg = cfg;
        this.hidden = document.getElementById('fields_' + cfg.fieldId);
        this.source = cfg.sourceFieldId ? document.getElementById('fields_' + cfg.sourceFieldId) : null;
        this.statusEl = document.getElementById('unitas_loc_status_' + cfg.fieldId);
        this.mapEl = document.getElementById('unitas_loc_map_' + cfg.fieldId);
        this.map = null;
        this.marker = null;
        this.value = cfg.value || { lat: null, lng: null, address: '', status: '' };

        this.showStatusForValue();
        this.bindSource();
        if (cfg.preview && this.mapEl) this.initMap();
    }

    Field.prototype.setStatus = function (text, cls) {
        if (!this.statusEl) return;
        this.statusEl.className = 'unitas-loc-status' + (cls ? ' ' + cls : '');
        this.statusEl.textContent = text || '';
    };

    Field.prototype.showStatusForValue = function () {
        var s = this.value.status, str = this.cfg.strings || {};
        if (s === 'approximate') this.setStatus(str.approximate, 'text-warning');
        else if (s === 'not_found') this.setStatus(str.notFound, 'text-warning');
        else if (s === 'error') this.setStatus(str.error, 'text-danger');
        else if (s === 'manual') this.setStatus(str.manual, 'text-muted');
        else this.setStatus('');
    };

    Field.prototype.bindSource = function () {
        if (!this.source || this.cfg.readonly) return;
        var self = this;
        this.source.addEventListener('unitas:address-selected', function (e) {
            var d = e.detail || {};
            self.value = { lat: d.lat, lng: d.lng, address: d.address, status: 'autocomplete' };
            self.hidden.value = fmt(d.lat, d.lng, d.address, 'autocomplete');
            self.setStatus((self.cfg.strings || {}).selected, 'text-muted');
            self.placeMarker(d.lat, d.lng, true);
        });
        this.source.addEventListener('unitas:address-edited', function () {
            self.hidden.value = '';
            self.value = { lat: null, lng: null, address: '', status: '' };
            self.setStatus((self.cfg.strings || {}).willLookup, 'text-muted');
            self.hideMarker();
        });
    };

    Field.prototype.initMap = function () {
        if (!window.UnitasGMaps) return;
        var self = this;
        var center = (this.value.lat != null)
            ? { lat: this.value.lat, lng: this.value.lng }
            : (this.cfg.defaultCenter || { lat: 35.7596, lng: -79.0193 });

        window.UnitasGMaps.load(['maps', 'marker']).then(function () {
            return google.maps.importLibrary('maps');
        }).then(function (mapsLib) {
            var opts = { center: center, zoom: self.cfg.zoom || 16 };
            if (self.cfg.mapId) opts.mapId = self.cfg.mapId; else opts.mapTypeId = 'roadmap';
            self.map = new mapsLib.Map(self.mapEl, opts);

            if (self.value.lat != null) self.placeMarker(self.value.lat, self.value.lng, false);

            // Click-to-place when editable and there is no pin yet.
            if (self.cfg.canEdit && !self.cfg.readonly) {
                self.map.addListener('click', function (ev) {
                    var ll = latLngOf(ev.latLng);
                    if (ll) self.onManualPlace(ll.lat, ll.lng);
                });
            }
        }).catch(function (err) {
            try { console.warn('[unitas-location] map load failed', err); } catch (e) {}
        });
    };

    Field.prototype.placeMarker = function (lat, lng, recenter) {
        if (!this.map || lat == null) return;
        var self = this;
        var pos = { lat: Number(lat), lng: Number(lng) };
        var draggable = this.cfg.canEdit && !this.cfg.readonly;

        if (this.marker) {
            this.setMarkerPos(pos);
        } else {
            google.maps.importLibrary('marker').then(function (markerLib) {
                if (self.cfg.mapId && markerLib.AdvancedMarkerElement) {
                    self.marker = new markerLib.AdvancedMarkerElement({ map: self.map, position: pos, gmpDraggable: draggable });
                    self.markerKind = 'adv';
                    if (draggable) self.marker.addListener('dragend', function () {
                        var ll = latLngOf(self.marker.position);
                        if (ll) self.onManualPlace(ll.lat, ll.lng);
                    });
                } else {
                    self.marker = new google.maps.Marker({ map: self.map, position: pos, draggable: draggable });
                    self.markerKind = 'legacy';
                    if (draggable) self.marker.addListener('dragend', function () {
                        var ll = latLngOf(self.marker.getPosition());
                        if (ll) self.onManualPlace(ll.lat, ll.lng);
                    });
                }
            });
        }
        if (recenter && this.map) this.map.setCenter(pos);
    };

    Field.prototype.setMarkerPos = function (pos) {
        if (!this.marker) return;
        if (this.markerKind === 'adv') this.marker.position = pos;
        else this.marker.setPosition(pos);
    };

    Field.prototype.hideMarker = function () {
        if (!this.marker) return;
        if (this.markerKind === 'adv') this.marker.map = null;
        else this.marker.setMap(null);
        this.marker = null;
    };

    // Manual pin. In a form it writes the hidden value; on the item page it
    // POSTs to the pin endpoint (which re-checks access) and reverts on failure.
    // Ignored when the address is empty (P13) in the form.
    Field.prototype.onManualPlace = function (lat, lng) {
        if (this.hidden) {
            var addr = this.source ? normalizeAddress(this.source.value) : this.value.address;
            if (!addr) return;
            this.value = { lat: lat, lng: lng, address: addr, status: 'manual' };
            this.hidden.value = fmt(lat, lng, addr, 'manual');
            this.setStatus((this.cfg.strings || {}).manual, 'text-muted');
            this.placeMarker(lat, lng, false);
            return;
        }
        this.pinPost(lat, lng);
    };

    Field.prototype.pinPost = function (lat, lng) {
        var self = this;
        var url = (window.UNITAS_GMAPS && window.UNITAS_GMAPS.pinUrl) || '';
        if (!url || !this.cfg.path) return;

        var prev = (this.value.lat != null) ? { lat: this.value.lat, lng: this.value.lng } : null;
        var body = new URLSearchParams();
        body.set('path', this.cfg.path);
        body.set('field_id', this.cfg.fieldId);
        body.set('lat', lat);
        body.set('lng', lng);

        fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
            .then(function (d) {
                if (d && d.ok) {
                    self.value = { lat: lat, lng: lng, address: self.value.address, status: 'manual' };
                    self.placeMarker(lat, lng, false);
                    self.setStatus((self.cfg.strings || {}).manual, 'text-muted');
                } else {
                    if (prev) self.placeMarker(prev.lat, prev.lng, false); else self.hideMarker();
                    self.setStatus((d && d.error) ? d.error : 'Could not save pin.', 'text-danger');
                }
            })
            .catch(function () {
                if (prev) self.placeMarker(prev.lat, prev.lng, false); else self.hideMarker();
                self.setStatus('Could not save pin.', 'text-danger');
            });
    };

    function init(cfg) {
        try {
            if (!cfg || !document.getElementById('fields_' + cfg.fieldId)) return;
            if (!window._unitasLocFields) window._unitasLocFields = {};
            if (window._unitasLocFields[cfg.fieldId]) return; // once per field
            window._unitasLocFields[cfg.fieldId] = new Field(cfg);
        } catch (err) {
            try { console.warn('[unitas-location] init failed', err); } catch (e) {}
        }
    }

    window.UnitasLocation = { init: init };
})();
