/**
 * UnitasGeoWidget - Google Maps Drawing Widget for fieldtype_unitas_geometry
 *
 * Supports polyline, polygon, and circle drawing modes.
 * Uses custom click-to-draw (DrawingManager removed in Maps JS API v3.65).
 * Requires only the geometry library (spherical + encoding).
 *
 * Drawing UX summary:
 *   Polyline / Polygon: click to place points, double-click or Finish button to complete.
 *     Polygon requires 3+ points; polyline requires 2+.
 *   Circle: click to place center, then click again to set radius.
 *     A status label shows live radius as the mouse moves.
 *
 * v3.1.0: Waze reverse-geocoding autofill — when a draw completes, street
 * names are looked up through the plugin proxy (token stays server-side)
 * and filled into configured sibling text fields. Controlled by
 * config.wazeLookup + config.wazeTargets {road, cross1, cross2}.
 *
 * @version 3.1.0
 */
(function() {
    'use strict';

    window.UnitasGeoWidget = function(config) {
        this.config           = config;
        this.map              = null;
        this.currentShape     = null;
        this._currentShapeType = null;
        this.isDrawing        = false;
        // Path drawing (polyline / polygon)
        this._drawPoints      = [];
        this._previewShape    = null;
        // Circle drawing
        this._circleCenter    = null;
        this._circlePreview   = null;
        // UI
        this._drawBtn         = null;
        this._finishBtn       = null;
        this._cancelBtn       = null;
        this._statusLabel     = null;
        // Saved shape hidden during drawing; restored on cancel
        this._savedShape      = null;
        // Active event listener handles
        this._listeners       = [];
        // Waze reverse-geocoding autofill
        this._wazeXhr         = null;
        this._wazeStatusTimer = null;
        this.init();
    };

    UnitasGeoWidget.prototype = {

        // ── Init ────────────────────────────────────────────────────────────────

        init: function() {
            var fid = this.config.fieldId;
            var el  = document.getElementById('unitas_geo_map_' + fid);
            if (!el) return;

            var mapOptions = {
                center:            { lat: this.config.defaultLat, lng: this.config.defaultLng },
                zoom:              this.config.defaultZoom,
                streetViewControl: false,
                fullscreenControl: true
            };
            if (this.config.mapId) {
                mapOptions.mapId = this.config.mapId;
            } else {
                mapOptions.mapTypeId = google.maps.MapTypeId.ROADMAP;
            }
            this.map = new google.maps.Map(el, mapOptions);

            this._addMapControls();
            this._wireSearchBox();

            if (this.config.existingData && this.config.existingData.type) {
                this.loadExisting(this.config.existingData);
            }
        },

        _addMapControls: function() {
            var self = this;
            var mode = this.config.drawingMode;
            var labels = { polyline: 'Draw Polyline', polygon: 'Draw Polygon', circle: 'Draw Circle' };

            var wrap = document.createElement('div');
            wrap.style.cssText = 'margin:10px;display:flex;align-items:center;';

            this._drawBtn    = this._btn('fa-pencil', labels[mode] || 'Draw', 'btn-primary', function() { self.startDrawing(); });
            this._finishBtn  = this._btn('fa-check',  'Finish',               'btn-success', function() { self.finishDrawing(); });
            this._cancelBtn  = this._btn('',           'Cancel',              'btn-default', function() { self.cancelDrawing(); });

            this._statusLabel = document.createElement('span');
            this._statusLabel.style.cssText = 'margin-left:8px;font-size:12px;color:#555;display:none;';

            this._finishBtn.style.display  = 'none';
            this._cancelBtn.style.display  = 'none';
            this._finishBtn.style.marginLeft = '5px';
            this._cancelBtn.style.marginLeft = '4px';

            wrap.appendChild(this._drawBtn);
            wrap.appendChild(this._finishBtn);
            wrap.appendChild(this._cancelBtn);
            wrap.appendChild(this._statusLabel);

            this.map.controls[google.maps.ControlPosition.TOP_CENTER].push(wrap);
        },

        _btn: function(icon, label, cls, fn) {
            var b = document.createElement('button');
            b.type      = 'button';
            b.className = 'btn btn-sm ' + cls;
            b.style.cursor = 'pointer';
            b.innerHTML = icon ? '<i class="fa ' + icon + '"></i> ' + label : label;
            b.onclick   = fn;
            return b;
        },

        // ── Drawing: shared entry ────────────────────────────────────────────────

        startDrawing: function() {
            this.isDrawing   = true;
            this._savedShape = this.currentShape || null;
            if (this._savedShape) this._savedShape.setMap(null);

            this.map.setOptions({ draggableCursor: 'crosshair', disableDoubleClickZoom: true });
            this._drawBtn.style.display   = 'none';
            this._cancelBtn.style.display = '';

            if (this.config.drawingMode === 'circle') {
                this._startCircleListeners();
            } else {
                this._startPathListeners(this.config.drawingMode);
            }
        },

        cancelDrawing: function() {
            this._cleanupDrawState(true);
        },

        _cleanupDrawState: function(cancelled) {
            this.isDrawing     = false;
            this._drawPoints   = [];
            this._circleCenter = null;

            if (this._previewShape)  { this._previewShape.setMap(null);  this._previewShape  = null; }
            if (this._circlePreview) { this._circlePreview.setMap(null); this._circlePreview = null; }

            this._listeners.forEach(function(l) { google.maps.event.removeListener(l); });
            this._listeners = [];

            this.map.setOptions({ draggableCursor: null, disableDoubleClickZoom: false });
            this._drawBtn.style.display      = '';
            this._finishBtn.style.display    = 'none';
            this._cancelBtn.style.display    = 'none';
            this._statusLabel.style.display  = 'none';

            if (cancelled && this._savedShape) {
                this._savedShape.setMap(this.map);
                this.currentShape = this._savedShape;
            }
            this._savedShape = null;
        },

        // ── Drawing: Polyline and Polygon ────────────────────────────────────────

        _startPathListeners: function(mode) {
            var self   = this;
            var minPts = (mode === 'polygon') ? 3 : 2;
            var sc     = this.config.strokeColor  || '#FF0000';
            var sw     = this.config.strokeWeight || 4;

            this._finishBtn.style.display = '';
            this._finishBtn.disabled      = true;
            this._updatePathLabel(0, minPts);

            var shapeOpts = {
                strokeColor: sc, strokeWeight: sw, strokeOpacity: 0.45,
                clickable: false, map: this.map
            };
            if (mode === 'polygon') {
                shapeOpts.fillColor   = sc;
                shapeOpts.fillOpacity = 0.1;
                this._previewShape = new google.maps.Polygon(shapeOpts);
            } else {
                this._previewShape = new google.maps.Polyline(shapeOpts);
            }

            var clickL = google.maps.event.addListener(this.map, 'click', function(e) {
                self._drawPoints.push(e.latLng);
                self._previewShape.setPath(self._drawPoints);
                self._finishBtn.disabled = (self._drawPoints.length < minPts);
                self._updatePathLabel(self._drawPoints.length, minPts);
            });

            var moveL = google.maps.event.addListener(this.map, 'mousemove', function(e) {
                if (self._drawPoints.length > 0) {
                    self._previewShape.setPath(self._drawPoints.concat([e.latLng]));
                }
            });

            // Double-click finishes. The second click of the double-click fires a
            // 'click' event first (adding a phantom point), so we pop it before finishing.
            var dblClickL = google.maps.event.addListener(this.map, 'dblclick', function() {
                if (self._drawPoints.length > 0) self._drawPoints.pop();
                self.finishDrawing();
            });

            this._listeners = [clickL, moveL, dblClickL];
        },

        _updatePathLabel: function(n, minPts) {
            var remaining = minPts - n;
            if (n === 0) {
                this._finishBtn.innerHTML = '<i class="fa fa-check"></i> Finish';
            } else if (remaining > 0) {
                this._finishBtn.innerHTML = '<i class="fa fa-check"></i> Finish (' + remaining + ' more needed)';
            } else {
                this._finishBtn.innerHTML = '<i class="fa fa-check"></i> Finish (' + n + ' pts)';
            }
        },

        finishDrawing: function() {
            var mode   = this.config.drawingMode;
            var minPts = (mode === 'polygon') ? 3 : 2;
            if (this._drawPoints.length < minPts) return;

            var pts = this._drawPoints.slice();
            this._cleanupDrawState(false);

            if (this.currentShape) this.currentShape.setMap(null);

            var sc = this.config.strokeColor  || '#FF0000';
            var sw = this.config.strokeWeight || 4;

            if (mode === 'polygon') {
                this.currentShape = new google.maps.Polygon({
                    paths: pts, strokeColor: sc, strokeWeight: sw, strokeOpacity: 0.9,
                    fillColor: sc, fillOpacity: 0.2, editable: true, map: this.map
                });
            } else {
                this.currentShape = new google.maps.Polyline({
                    path: pts, strokeColor: sc, strokeWeight: sw, strokeOpacity: 0.9,
                    editable: true, map: this.map
                });
            }
            this._currentShapeType = mode;
            this.attachEdits(this.currentShape);
            var data = this.save(this.currentShape);
            this._wazeLookup(data);
        },

        // ── Drawing: Circle ──────────────────────────────────────────────────────

        _startCircleListeners: function() {
            var self = this;
            var sc   = this.config.strokeColor  || '#FF0000';
            var sw   = this.config.strokeWeight || 4;

            this._circleCenter  = null;
            this._circlePreview = null;

            this._statusLabel.style.display = '';
            this._statusLabel.textContent   = 'Click map to place center';

            var clickL = google.maps.event.addListener(this.map, 'click', function(e) {
                if (!self._circleCenter) {
                    // First click: place center
                    self._circleCenter  = e.latLng;
                    self._circlePreview = new google.maps.Circle({
                        center: self._circleCenter, radius: 1,
                        strokeColor: sc, strokeWeight: sw, strokeOpacity: 0.7,
                        fillColor: sc, fillOpacity: 0.15,
                        clickable: false, map: self.map
                    });
                    self._statusLabel.textContent = 'Click to set radius';
                } else {
                    // Second click: fix radius and finish
                    var r = google.maps.geometry.spherical.computeDistanceBetween(self._circleCenter, e.latLng);
                    self._circlePreview.setRadius(r);
                    self._finishCircle();
                }
            });

            var moveL = google.maps.event.addListener(this.map, 'mousemove', function(e) {
                if (self._circleCenter && self._circlePreview) {
                    var r   = google.maps.geometry.spherical.computeDistanceBetween(self._circleCenter, e.latLng);
                    var rFt = Math.round(r * 3.28084).toLocaleString();
                    var rMi = (r / 1609.34).toFixed(2);
                    self._circlePreview.setRadius(r);
                    self._statusLabel.textContent = rMi + ' mi (' + rFt + ' ft) — click to set';
                }
            });

            this._listeners = [clickL, moveL];
        },

        _finishCircle: function() {
            // Transfer preview to currentShape before cleanupDrawState removes it
            var preview         = this._circlePreview;
            this._circlePreview = null;

            preview.setOptions({ clickable: true, editable: true, strokeOpacity: 0.9, fillOpacity: 0.2 });
            if (this.currentShape) this.currentShape.setMap(null);
            this.currentShape      = preview;
            this._currentShapeType = 'circle';

            this._cleanupDrawState(false);

            this.attachEditsCircle(this.currentShape);
            var data = this.save(this.currentShape);
            this._wazeLookup(data);
        },

        // ── Editing ─────────────────────────────────────────────────────────────

        attachEdits: function(shape) {
            var self = this;
            var path = shape.getPath();
            var cb   = function() { self.save(shape); };
            google.maps.event.addListener(path, 'set_at',    cb);
            google.maps.event.addListener(path, 'insert_at', cb);
            google.maps.event.addListener(path, 'remove_at', cb);
        },

        attachEditsCircle: function(circle) {
            var self = this;
            var cb   = function() { self.save(circle); };
            google.maps.event.addListener(circle, 'center_changed', cb);
            google.maps.event.addListener(circle, 'radius_changed', cb);
        },

        // ── Save / Load ─────────────────────────────────────────────────────────

        save: function(shape) {
            var type = this._currentShapeType || this.config.drawingMode;
            var data;

            if (type === 'polyline') {
                var path = shape.getPath();
                var pts = [], dist = 0;
                path.forEach(function(ll, i) {
                    pts.push([Math.round(ll.lat() * 1e7) / 1e7, Math.round(ll.lng() * 1e7) / 1e7]);
                    if (i > 0) dist += google.maps.geometry.spherical.computeDistanceBetween(path.getAt(i - 1), ll);
                });
                data = {
                    type:             'polyline',
                    encoded_polyline: google.maps.geometry.encoding.encodePath(path),
                    points:           pts,
                    distance_m:       Math.round(dist * 100) / 100
                };

            } else if (type === 'polygon') {
                var path = shape.getPath();
                var pts  = [];
                path.forEach(function(ll) {
                    pts.push([Math.round(ll.lat() * 1e7) / 1e7, Math.round(ll.lng() * 1e7) / 1e7]);
                });
                data = {
                    type:     'polygon',
                    points:   pts,
                    area_sqm: Math.round(google.maps.geometry.spherical.computeArea(path) * 100) / 100
                };

            } else if (type === 'circle') {
                var c = shape.getCenter();
                data = {
                    type:     'circle',
                    center:   [Math.round(c.lat() * 1e7) / 1e7, Math.round(c.lng() * 1e7) / 1e7],
                    radius_m: Math.round(shape.getRadius() * 100) / 100
                };
            }

            var input = document.getElementById('fields_' + this.config.fieldId);
            if (input) input.value = JSON.stringify(data);
            this.updateInfo(data);
            return data;
        },

        loadExisting: function(data) {
            var sc = this.config.strokeColor  || '#FF0000';
            var sw = this.config.strokeWeight || 4;

            if (data.type === 'polyline' && data.points && data.points.length >= 2) {
                var path = data.points.map(function(p) { return new google.maps.LatLng(p[0], p[1]); });
                this.currentShape = new google.maps.Polyline({
                    path: path, strokeColor: sc, strokeWeight: sw, strokeOpacity: 0.9,
                    editable: true, map: this.map
                });
                var b = new google.maps.LatLngBounds();
                path.forEach(function(p) { b.extend(p); });
                this.map.fitBounds(b, 40);
                this._currentShapeType = 'polyline';
                this.attachEdits(this.currentShape);

            } else if (data.type === 'polygon' && data.points && data.points.length >= 3) {
                var path = data.points.map(function(p) { return new google.maps.LatLng(p[0], p[1]); });
                this.currentShape = new google.maps.Polygon({
                    paths: path, strokeColor: sc, strokeWeight: sw, strokeOpacity: 0.9,
                    fillColor: sc, fillOpacity: 0.2, editable: true, map: this.map
                });
                var b = new google.maps.LatLngBounds();
                path.forEach(function(p) { b.extend(p); });
                this.map.fitBounds(b, 40);
                this._currentShapeType = 'polygon';
                this.attachEdits(this.currentShape);

            } else if (data.type === 'circle' && data.center && data.radius_m) {
                var center = new google.maps.LatLng(data.center[0], data.center[1]);
                this.currentShape = new google.maps.Circle({
                    center: center, radius: data.radius_m,
                    strokeColor: sc, strokeWeight: sw, strokeOpacity: 0.9,
                    fillColor: sc, fillOpacity: 0.2, editable: true, map: this.map
                });
                this.map.fitBounds(this.currentShape.getBounds());
                this._currentShapeType = 'circle';
                this.attachEditsCircle(this.currentShape);

            } else {
                return;
            }
            this.updateInfo(data);
        },

        // ── Info bar ─────────────────────────────────────────────────────────────

        updateInfo: function(data) {
            var fid = this.config.fieldId;
            var bar = document.getElementById('unitas_geo_info_' + fid);
            if (!bar) return;
            bar.style.display = 'block';

            var ds = document.getElementById('unitas_geo_distance_' + fid);
            var ps = document.getElementById('unitas_geo_points_'   + fid);
            if (ds) ds.innerHTML = '';
            if (ps) ps.innerHTML = '';

            if (!data) return;

            if (data.type === 'polyline' && data.distance_m > 0) {
                var mi = (data.distance_m / 1609.34).toFixed(2);
                var ft = Math.round(data.distance_m * 3.28084).toLocaleString();
                if (ds) ds.innerHTML = '<i class="fa fa-road"></i> <strong>' + mi + ' mi</strong> (' + ft + ' ft)';
                if (ps && data.points) ps.innerHTML = ' &nbsp;<i class="fa fa-map-pin"></i> ' + data.points.length + ' pts';

            } else if (data.type === 'polygon' && data.area_sqm > 0) {
                var acres   = data.area_sqm / 4046.86;
                var areaStr = acres >= 640 ? (acres / 640).toFixed(2) + ' sq mi' : acres.toFixed(1) + ' acres';
                if (ds) ds.innerHTML = '<i class="fa fa-square-o"></i> <strong>' + areaStr + '</strong>';
                if (ps && data.points) ps.innerHTML = ' &nbsp;<i class="fa fa-map-pin"></i> ' + data.points.length + ' pts';

            } else if (data.type === 'circle' && data.radius_m > 0) {
                var rMi = (data.radius_m / 1609.34).toFixed(2);
                var rFt = Math.round(data.radius_m * 3.28084).toLocaleString();
                if (ds) ds.innerHTML = '<i class="fa fa-circle-o"></i> <strong>' + rMi + ' mi radius</strong> (' + rFt + ' ft)';
            }
        },

        // ── Address search ───────────────────────────────────────────────────────

        _wireSearchBox: function() {
            var self = this;
            var fid  = this.config.fieldId;
            var inp  = document.getElementById('unitas_geo_addr_' + fid);
            var btn  = document.getElementById('unitas_geo_go_'   + fid);
            if (!inp || !btn) return;
            var go = function() { self.searchAddress(inp.value); };
            btn.onclick = go;
            inp.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); go(); }
            });
        },

        searchAddress: function(q) {
            if (!q || !q.trim() || !google.maps.Geocoder) return;
            var self = this;
            new google.maps.Geocoder().geocode({ address: q.trim() }, function(results, status) {
                if (status === 'OK' && results && results[0]) {
                    var geom = results[0].geometry;
                    if (geom.viewport) { self.map.fitBounds(geom.viewport); }
                    else               { self.map.setCenter(geom.location); self.map.setZoom(16); }
                }
            });
        },

        // ── Waze street-name autofill ────────────────────────────────────────────

        _wazeStatus: function(msg, autoClear) {
            var el = document.getElementById('unitas_geo_waze_' + this.config.fieldId);
            if (!el) return;
            if (this._wazeStatusTimer) { clearTimeout(this._wazeStatusTimer); this._wazeStatusTimer = null; }
            el.textContent = msg || '';
            if (msg && autoClear) {
                this._wazeStatusTimer = setTimeout(function() { el.textContent = ''; }, 4000);
            }
        },

        // Build the lookup points and their roles from the saved geometry.
        // Polyline: midpoint (road name anchor) + first/last vertices (cross
        // streets). Polygon / circle: a single representative road point.
        _wazePoints: function(data) {
            var t = this.config.wazeTargets || {};
            var points = [], roles = [];

            if (data.type === 'polyline' && data.points && data.points.length >= 2) {
                // Midpoint is always included: even when the road target is
                // disabled it anchors the "differs from road" comparison.
                points.push(this._pathMidpoint(data));
                roles.push('road');
                if (t.cross1) {
                    points.push({ lat: data.points[0][0], lng: data.points[0][1] });
                    roles.push('cross1');
                }
                if (t.cross2) {
                    var last = data.points[data.points.length - 1];
                    points.push({ lat: last[0], lng: last[1] });
                    roles.push('cross2');
                }
            } else if (data.type === 'polygon' && data.points && data.points.length && t.road) {
                points.push({ lat: data.points[0][0], lng: data.points[0][1] });
                roles.push('road');
            } else if (data.type === 'circle' && data.center && t.road) {
                points.push({ lat: data.center[0], lng: data.center[1] });
                roles.push('road');
            }
            return { points: points, roles: roles };
        },

        // Geometric midpoint measured along the path (not a vertex), so even
        // a 2-point line yields a distinct interpolated middle point.
        _pathMidpoint: function(data) {
            var pts    = data.points;
            var half   = (data.distance_m || 0) / 2;
            var walked = 0;
            for (var i = 1; i < pts.length; i++) {
                var a   = new google.maps.LatLng(pts[i - 1][0], pts[i - 1][1]);
                var b   = new google.maps.LatLng(pts[i][0], pts[i][1]);
                var seg = google.maps.geometry.spherical.computeDistanceBetween(a, b);
                if (walked + seg >= half && seg > 0) {
                    var mid = google.maps.geometry.spherical.interpolate(a, b, (half - walked) / seg);
                    return { lat: mid.lat(), lng: mid.lng() };
                }
                walked += seg;
            }
            var lastPt = pts[pts.length - 1];
            return { lat: lastPt[0], lng: lastPt[1] };
        },

        _wazeLookup: function(data) {
            if (!this.config.wazeLookup || !data) return;
            if (typeof jQuery === 'undefined') {
                console.warn('[UnitasGeo] Waze lookup skipped: jQuery not available');
                return;
            }

            var req = this._wazePoints(data);
            if (!req.points.length) return;

            if (this._wazeXhr) { this._wazeXhr.abort(); this._wazeXhr = null; }

            var self = this;
            this._wazeStatus('Looking up street names...');

            this._wazeXhr = jQuery.ajax({
                url:      'index.php?module=unitas_ext/waze_integration/ajax_reverse_geocode',
                type:     'POST',
                dataType: 'json',
                timeout:  15000,
                data:     { points: JSON.stringify(req.points) },
                success: function(response) {
                    self._wazeXhr = null;
                    if (response && response.success) {
                        self._wazeApply(response.results, req.roles);
                    } else {
                        console.warn('[UnitasGeo] Waze lookup: ' + (response && response.error ? response.error : 'unknown error'));
                        self._wazeStatus('');
                    }
                },
                error: function(xhr, status) {
                    self._wazeXhr = null;
                    if (status !== 'abort') {
                        console.warn('[UnitasGeo] Waze lookup failed: ' + status);
                    }
                    self._wazeStatus('');
                }
            });
        },

        _wazeApply: function(results, roles) {
            var t = this.config.wazeTargets || {};
            var byRole = {};
            for (var i = 0; i < roles.length; i++) byRole[roles[i]] = results[i];

            // Road name: nearest entry at the midpoint
            var roadList  = byRole.road;
            var roadEntry = (roadList && roadList.length) ? roadList[0] : null;
            var roadName  = roadEntry ? roadEntry.names[0] : null;

            // All aliases of the road (e.g. ["US-64","Main St"]) — a cross
            // street candidate matching ANY alias is really the road itself
            var roadSet = {};
            if (roadEntry) {
                roadEntry.names.forEach(function(n) { roadSet[n.toLowerCase()] = true; });
            }

            var filled   = 0;
            var anyFound = !!roadName;
            if (t.road && roadName && this._fillTarget(t.road, roadName)) filled++;

            var self = this;
            ['cross1', 'cross2'].forEach(function(role) {
                if (!t[role]) return;
                var list = byRole[role];
                if (!list || !list.length) return;
                anyFound = true;
                // Walk entries by distance; first name that is not the road wins
                for (var j = 0; j < list.length; j++) {
                    var pick = null;
                    for (var k = 0; k < list[j].names.length; k++) {
                        if (!roadSet[list[j].names[k].toLowerCase()]) { pick = list[j].names[k]; break; }
                    }
                    if (pick) {
                        if (self._fillTarget(t[role], pick)) filled++;
                        break;
                    }
                }
            });

            if (filled > 0) {
                this._wazeStatus('Street names updated', true);
            } else if (anyFound) {
                this._wazeStatus('Street names found (fields already filled)', true);
            } else {
                this._wazeStatus('No street names found', true);
            }
        },

        // Fill a sibling text field, but never clobber manual user input:
        // only empty fields or fields we previously autofilled are written.
        _fillTarget: function(fieldId, value) {
            var input = document.getElementById('fields_' + fieldId);
            if (!input) return false;

            var isOurs = input.getAttribute('data-unitas-autofill') === '1';
            if (input.value !== '' && !isOurs) return false;

            input.value = value;
            input.setAttribute('data-unitas-autofill', '1');

            // A real user edit permanently releases the field from autofill
            if (!input._unitasAutofillGuard) {
                input._unitasAutofillGuard = true;
                input.addEventListener('input', function(ev) {
                    if (ev.isTrusted) input.removeAttribute('data-unitas-autofill');
                });
            }

            var evt;
            try {
                evt = new Event('change', { bubbles: true });
            } catch (e) {
                evt = document.createEvent('Event');
                evt.initEvent('change', true, false);
            }
            input.dispatchEvent(evt);
            return true;
        },

        // Manual re-run from the info-bar link (e.g. after vertex edits)
        wazeRefresh: function() {
            var input = document.getElementById('fields_' + this.config.fieldId);
            if (!input || !input.value) return;
            var data = null;
            try { data = JSON.parse(input.value); } catch (e) { return; }
            if (data && data.type) this._wazeLookup(data);
        },

        // ── Clear / Redraw ───────────────────────────────────────────────────────

        clear: function() {
            // Abort any in-flight lookup so a cleared field cannot be filled late
            if (this._wazeXhr) { this._wazeXhr.abort(); this._wazeXhr = null; }
            this._wazeStatus('');
            if (this.isDrawing) this.cancelDrawing();
            if (this.currentShape) { this.currentShape.setMap(null); this.currentShape = null; }
            this._currentShapeType = null;
            var input = document.getElementById('fields_' + this.config.fieldId);
            if (input) input.value = '';
            var bar = document.getElementById('unitas_geo_info_' + this.config.fieldId);
            if (bar) bar.style.display = 'none';
        },

        redraw: function() { this.clear(); }
    };

})();
