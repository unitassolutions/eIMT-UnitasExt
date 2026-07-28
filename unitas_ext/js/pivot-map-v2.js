/**
 * UnitasPivotMapV2 — modern renderer for pivot map reports (layout = modern)
 *
 * Consumes the JSON payload emitted by actions/view_google_v2.php and builds:
 *   - the Google map (Map ID themed, light/dark)
 *   - markers (icon > colored SVG pin > default) + one MarkerClusterer
 *   - geometry shapes (polyline / polygon / circle)
 *   - a floating interactive legend card (per-layer visibility toggles + counts)
 *   - a floating sidebar card (live search, layer groups, click = zoom + popup)
 *
 * All state is per-report (instances keyed by report id); DOM ids are scoped
 * unitas_pmv2_*_{reportId}. Theme switching reloads the fragment through the
 * component-defined global named in payload.report.reload_fn.
 *
 * @version 1.0.0
 */
(function () {
    'use strict';

    var instances = {};

    /* ── helpers ─────────────────────────────────────────────────────────── */

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function iconBtn(icon, className) {
        var b = el('button', className);
        b.type = 'button';
        b.appendChild(el('span', 'material-icons', icon));
        return b;
    }

    function setBtnIcon(btn, icon) {
        var i = btn.querySelector('.material-icons');
        if (i) i.textContent = icon;
    }

    // Modern teardrop pin as a data URI, colored per layer/record
    function svgPin(color) {
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="40" viewBox="0 0 28 40">'
                + '<path d="M14 0C6.3 0 0 6.3 0 14c0 10.5 14 26 14 26s14-15.5 14-26C28 6.3 21.7 0 14 0z" fill="' + color + '" stroke="#ffffff" stroke-width="1.5"/>'
                + '<circle cx="14" cy="14" r="5" fill="#ffffff"/>'
                + '</svg>';
        return {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
            scaledSize: new google.maps.Size(28, 40),
            anchor: new google.maps.Point(14, 40)
        };
    }

    /* ── init ────────────────────────────────────────────────────────────── */

    function init(payload) {
        var id = payload.report.id;
        var state = {
            cfg: payload,
            map: null,
            infoWindow: null,
            infoAnchorLayer: null,
            clusterer: null,
            markersById: {},
            markersByLayer: {},
            shapesByLayer: {},
            hiddenLayers: {},
            legendEntities: [],
            stage: document.getElementById('unitas_pmv2_stage_' + id),
            mapEl: document.getElementById('unitas_pmv2_map_' + id)
        };
        if (!state.stage || !state.mapEl) return;

        instances[id] = state;

        // Propagate dark mode to the wrap so the floating filter card
        // (rendered outside the reload target) restyles with the map theme
        var wrap = state.stage.closest('.unitas-pmv2-wrap');
        if (wrap) wrap.classList.toggle('unitas-pmv2-dark', payload.report.theme === 'dark');

        buildMap(state);
        buildMarkers(state);
        buildShapes(state);
        buildClusterer(state);
        fitOrCenter(state);
        if (payload.report.display_legend == 1) buildLegendCard(state);
        if (payload.report.display_sidebar == 1) buildSidebarCard(state);
    }

    /* ── map + theme ─────────────────────────────────────────────────────── */

    function buildMap(state) {
        var r = state.cfg.report;
        state.map = new google.maps.Map(state.mapEl, {
            zoom: r.zoom,
            mapId: r.map_id,
            streetViewControl: false,
            // v2 has its own floating panels; the native fullscreen button
            // sat behind the collapsed-sidebar reopen button
            fullscreenControl: false
        });
        state.infoWindow = new google.maps.InfoWindow();
        buildThemeControl(state);
    }

    function buildThemeControl(state) {
        var r = state.cfg.report;
        var wrap = el('div', 'unitas-pmv2-card unitas-pmv2-theme-btns');
        [['light_mode', 'light'], ['dark_mode', 'dark'], ['brightness_auto', 'auto']].forEach(function (t) {
            var b = iconBtn(t[0], 'unitas-pmv2-theme-btn' + (r.theme_choice === t[1] ? ' is-active' : ''));
            b.title = t[1];
            b.addEventListener('click', function () {
                var fn = window[r.reload_fn];
                if (typeof fn === 'function') fn(t[1]);
            });
            wrap.appendChild(b);
        });
        // Bottom center keeps the top edge free for the floating filter card
        state.map.controls[google.maps.ControlPosition.BOTTOM_CENTER].push(wrap);
    }

    /* ── markers / shapes / clusterer ────────────────────────────────────── */

    function buildMarkers(state) {
        state.cfg.markers.forEach(function (m) {
            var opts = {
                position: { lat: m.lat, lng: m.lng },
                title: m.name || ''
            };
            if (m.icon) opts.icon = m.icon;
            else if (m.color) opts.icon = svgPin(m.color);

            var marker = new google.maps.Marker(opts);
            marker._pmv2 = m;
            marker.addListener('click', function () { openPopup(state, m.id); });

            state.markersById[m.id] = marker;
            var key = String(m.layer);
            (state.markersByLayer[key] = state.markersByLayer[key] || []).push(marker);
        });
    }

    function buildShapes(state) {
        state.cfg.shapes.forEach(function (s) {
            var shape;
            if (s.kind === 'circle') {
                shape = new google.maps.Circle({
                    center: { lat: s.center[0], lng: s.center[1] },
                    radius: s.radius_m,
                    strokeColor: s.color, strokeWeight: s.weight, strokeOpacity: 0.9,
                    fillColor: s.color, fillOpacity: 0.2,
                    map: state.map
                });
            } else {
                var path = s.points.map(function (p) { return { lat: p[0], lng: p[1] }; });
                if (s.kind === 'polygon') {
                    shape = new google.maps.Polygon({
                        paths: path,
                        strokeColor: s.color, strokeWeight: s.weight, strokeOpacity: 0.9,
                        fillColor: s.color, fillOpacity: 0.2,
                        map: state.map
                    });
                } else {
                    shape = new google.maps.Polyline({
                        path: path,
                        strokeColor: s.color, strokeWeight: s.weight, strokeOpacity: 0.9,
                        map: state.map
                    });
                }
            }

            shape.addListener('click', function (e) {
                state.infoWindow.close();
                state.infoWindow.setContent('<div class="unitas-pmv2-popup">' + s.popup + '</div>');
                state.infoWindow.setPosition(e.latLng);
                state.infoWindow.open(state.map);
                state.infoAnchorLayer = String(s.layer);
            });

            var key = String(s.layer);
            (state.shapesByLayer[key] = state.shapesByLayer[key] || []).push(shape);
        });
    }

    function buildClusterer(state) {
        var visible = [];
        Object.keys(state.markersByLayer).forEach(function (key) {
            if (!state.hiddenLayers[key]) visible = visible.concat(state.markersByLayer[key]);
        });
        state.clusterer = new markerClusterer.MarkerClusterer({ map: state.map, markers: visible });
    }

    function fitOrCenter(state) {
        var c = state.cfg.report.center;
        if (c && c.mode === 'fixed') {
            state.map.setCenter({ lat: c.lat, lng: c.lng });
            state.map.setZoom(state.cfg.report.zoom);
            return;
        }
        var bounds = new google.maps.LatLngBounds();
        var any = false;
        state.cfg.markers.forEach(function (m) { bounds.extend({ lat: m.lat, lng: m.lng }); any = true; });
        state.cfg.shapes.forEach(function (s) {
            if (s.kind === 'circle') return; // companion marker covers the center
            s.points.forEach(function (p) { bounds.extend({ lat: p[0], lng: p[1] }); any = true; });
        });
        Object.keys(state.shapesByLayer).forEach(function (key) {
            state.shapesByLayer[key].forEach(function (sh) {
                if (typeof sh.getBounds === 'function' && sh.getBounds()) { bounds.union(sh.getBounds()); any = true; }
            });
        });
        if (any) state.map.fitBounds(bounds);
    }

    /* ── popups ──────────────────────────────────────────────────────────── */

    function openPopup(state, markerId) {
        var marker = state.markersById[markerId];
        if (!marker) return;
        var m = marker._pmv2;
        state.infoWindow.close();
        state.infoWindow.setContent('<div class="unitas-pmv2-popup">' + m.popup + '</div>');
        state.infoWindow.open(state.map, marker);
        state.infoAnchorLayer = String(m.layer);
    }

    /* ── layer visibility ────────────────────────────────────────────────── */

    function toggleLayer(state, key) {
        key = String(key);
        var markers = state.markersByLayer[key] || [];
        var shapes = state.shapesByLayer[key] || [];

        if (state.hiddenLayers[key]) {
            delete state.hiddenLayers[key];
            if (state.clusterer && markers.length) state.clusterer.addMarkers(markers);
            shapes.forEach(function (sh) { sh.setMap(state.map); });
        } else {
            state.hiddenLayers[key] = true;
            if (state.clusterer && markers.length) state.clusterer.removeMarkers(markers);
            shapes.forEach(function (sh) { sh.setMap(null); });
            if (state.infoAnchorLayer === key) state.infoWindow.close();
        }
        updateLayerStyling(state, key);
        refreshEntityToggleIcons(state);
    }

    // Group layer rows by their source entity, preserving configured order
    function groupByEntity(layers) {
        var list = [];
        var map = {};
        layers.forEach(function (layer) {
            if (!map[layer.entities_id]) {
                map[layer.entities_id] = { id: layer.entities_id, name: layer.entity_name, layers: [] };
                list.push(map[layer.entities_id]);
            }
            map[layer.entities_id].layers.push(layer);
        });
        return list;
    }

    // Entity master toggle: any layer visible -> hide all; none visible -> show all
    function toggleEntity(state, entity) {
        var anyVisible = entity.layers.some(function (l) {
            return l.count > 0 && !state.hiddenLayers[String(l.key)];
        });
        entity.layers.forEach(function (l) {
            var key = String(l.key);
            if (l.count === 0) return;
            var hidden = !!state.hiddenLayers[key];
            if ((anyVisible && !hidden) || (!anyVisible && hidden)) toggleLayer(state, key);
        });
    }

    function refreshEntityToggleIcons(state) {
        state.legendEntities.forEach(function (group) {
            var anyVisible = group.entity.layers.some(function (l) {
                return l.count > 0 && !state.hiddenLayers[String(l.key)];
            });
            setBtnIcon(group.masterBtn, anyVisible ? 'visibility' : 'visibility_off');
            group.masterBtn.classList.toggle('is-off-state', !anyVisible);
        });
    }

    function updateLayerStyling(state, key) {
        var off = !!state.hiddenLayers[key];
        var row = state.stage.querySelector('.unitas-pmv2-legend-row[data-layer="' + key + '"]');
        if (row) {
            row.classList.toggle('is-off', off);
            var toggle = row.querySelector('.unitas-pmv2-toggle');
            if (toggle) setBtnIcon(toggle, off ? 'visibility_off' : 'visibility');
        }
        state.stage.querySelectorAll('.unitas-pmv2-subgroup[data-layer="' + key + '"]').forEach(function (sub) {
            sub.classList.toggle('is-off', off);
        });
    }

    /* ── legend card ─────────────────────────────────────────────────────── */

    function swatchEl(layer) {
        if (layer.icon) {
            var img = el('img', 'unitas-pmv2-swatch unitas-pmv2-swatch-icon');
            img.src = layer.icon;
            img.alt = '';
            return img;
        }
        if (layer.kind === 'geometry') {
            var line = el('span', 'unitas-pmv2-swatch unitas-pmv2-swatch-line');
            line.style.background = layer.color || '#FF0000';
            return line;
        }
        var dot = el('span', 'unitas-pmv2-swatch unitas-pmv2-swatch-pin');
        dot.style.background = layer.color || '#9aa0a6';
        return dot;
    }

    function buildLegendCard(state) {
        var cfg = state.cfg;
        if (!cfg.layers.length) return;

        var card = el('div', 'unitas-pmv2-card unitas-pmv2-legend');

        var head = el('div', 'unitas-pmv2-card-head');
        head.appendChild(el('span', 'unitas-pmv2-card-title', cfg.i18n.layers || 'Layers'));
        var collapse = iconBtn('expand_less', 'unitas-pmv2-collapse-btn');
        head.appendChild(collapse);
        card.appendChild(head);

        var body = el('div', 'unitas-pmv2-legend-body');

        // Entities with a single layer render as one plain row; entities with
        // multiple styled layers get a collapsible header with a master toggle
        groupByEntity(cfg.layers).forEach(function (entity) {
            if (entity.layers.length === 1) {
                body.appendChild(legendRow(state, entity.layers[0], false));
                return;
            }

            var eg = el('div', 'unitas-pmv2-legend-entity');

            var ehead = el('div', 'unitas-pmv2-legend-entity-head');
            var chev = iconBtn('expand_less', 'unitas-pmv2-collapse-btn unitas-pmv2-legend-chev');
            ehead.appendChild(chev);
            ehead.appendChild(el('span', 'unitas-pmv2-legend-entity-title', entity.name));
            var total = entity.layers.reduce(function (sum, l) { return sum + l.count; }, 0);
            ehead.appendChild(el('span', 'unitas-pmv2-count', String(total)));

            var master = iconBtn('visibility', 'unitas-pmv2-toggle');
            master.title = 'Show or hide every layer of this entity';
            if (total === 0) {
                master.disabled = true;
            } else {
                master.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    toggleEntity(state, entity);
                });
            }
            ehead.appendChild(master);
            eg.appendChild(ehead);
            state.legendEntities.push({ entity: entity, masterBtn: master });

            var children = el('div', 'unitas-pmv2-legend-entity-body');
            // Children sort alphabetically by label; entity order stays as
            // configured. (A per-layer sort order in the map config can
            // replace this later.)
            entity.layers.slice().sort(function (a, b) {
                return String(a.label).localeCompare(String(b.label));
            }).forEach(function (layer) {
                children.appendChild(legendRow(state, layer, true));
            });
            eg.appendChild(children);

            ehead.addEventListener('click', function (ev) {
                if (ev.target.closest('.unitas-pmv2-toggle')) return;
                var collapsed = eg.classList.toggle('is-collapsed');
                setBtnIcon(chev, collapsed ? 'expand_more' : 'expand_less');
            });

            body.appendChild(eg);
        });

        card.appendChild(body);

        collapse.addEventListener('click', function () {
            var collapsed = card.classList.toggle('is-collapsed');
            setBtnIcon(collapse, collapsed ? 'expand_more' : 'expand_less');
        });

        state.stage.appendChild(card);
    }

    function legendRow(state, layer, isChild) {
        var row = el('div', 'unitas-pmv2-legend-row' + (isChild ? ' is-child' : ''));
        row.setAttribute('data-layer', String(layer.key));
        row.appendChild(swatchEl(layer));
        row.appendChild(el('span', 'unitas-pmv2-legend-label', layer.label));
        row.appendChild(el('span', 'unitas-pmv2-count', String(layer.count)));

        var toggle = iconBtn('visibility', 'unitas-pmv2-toggle');
        toggle.title = 'Show or hide this layer';
        if (layer.count === 0) {
            row.classList.add('is-empty');
            toggle.disabled = true;
        } else {
            toggle.addEventListener('click', function () { toggleLayer(state, layer.key); });
        }
        row.appendChild(toggle);
        return row;
    }

    /* ── sidebar card ────────────────────────────────────────────────────── */

    function buildSidebarCard(state) {
        var cfg = state.cfg;
        if (!cfg.markers.length) return;

        var card = el('div', 'unitas-pmv2-card unitas-pmv2-sidebar');
        var width = Math.min(380, Math.max(260, cfg.report.sidebar_width || 250));
        card.style.width = width + 'px';

        var head = el('div', 'unitas-pmv2-card-head');
        head.appendChild(el('span', 'unitas-pmv2-card-title', cfg.i18n.objects || 'Objects'));
        var collapse = iconBtn('close', 'unitas-pmv2-collapse-btn');
        head.appendChild(collapse);
        card.appendChild(head);

        var searchWrap = el('div', 'unitas-pmv2-search');
        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = cfg.i18n.search || 'Search';
        input.className = 'unitas-pmv2-search-input';
        var clear = iconBtn('close', 'unitas-pmv2-search-clear');
        searchWrap.appendChild(input);
        searchWrap.appendChild(clear);
        card.appendChild(searchWrap);

        var body = el('div', 'unitas-pmv2-sidebar-body');

        // Group by ENTITY (classic sidebar behavior); subgroup wrappers keep
        // layer-toggle dimming working even with sub-headers disabled
        groupByEntity(cfg.layers).forEach(function (entity) {
            var entityLayers = entity.layers.filter(function (layer) {
                return cfg.markers.some(function (m) { return m.layer === layer.key; });
            });
            if (!entityLayers.length) return;

            var group = el('div', 'unitas-pmv2-group');
            var total = 0;

            var gh = el('div', 'unitas-pmv2-group-head');
            gh.appendChild(el('span', 'unitas-pmv2-group-title', entity.name));
            var gcount = el('span', 'unitas-pmv2-count', '');
            gh.appendChild(gcount);
            var chev = iconBtn('expand_less', 'unitas-pmv2-collapse-btn');
            gh.appendChild(chev);
            group.appendChild(gh);

            var list = el('div', 'unitas-pmv2-group-list');
            // Sub-headers per legend layer are disabled for now (entity-level
            // grouping is enough); flip to entityLayers.length > 1 to restore.
            // The subgroup wrappers stay so layer toggles still dim their items.
            var showSubheads = false;

            entityLayers.forEach(function (layer) {
                var layerMarkers = cfg.markers.filter(function (m) { return m.layer === layer.key; });
                total += layerMarkers.length;

                var sub = el('div', 'unitas-pmv2-subgroup');
                sub.setAttribute('data-layer', String(layer.key));

                if (showSubheads) {
                    var sh = el('div', 'unitas-pmv2-subgroup-head');
                    sh.appendChild(swatchEl(layer));
                    sh.appendChild(el('span', 'unitas-pmv2-subgroup-title', layer.label));
                    sh.appendChild(el('span', 'unitas-pmv2-count', String(layerMarkers.length)));
                    sub.appendChild(sh);
                }

                layerMarkers.forEach(function (m) {
                    var item = el('a', 'unitas-pmv2-item');
                    item.href = 'javascript:void(0)';
                    var pill = el('span', 'unitas-pmv2-pill');
                    pill.style.background = m.color || '#9aa0a6';
                    item.appendChild(pill);
                    // Names come from the admin-defined sidebar heading template
                    // and may contain markup, exactly as the classic sidebar
                    // rendered them; search indexes the visible text only
                    var nameSpan = el('span', 'unitas-pmv2-item-name');
                    nameSpan.innerHTML = m.name || ('#' + m.id);
                    item.appendChild(nameSpan);
                    item.setAttribute('data-name', (nameSpan.textContent || '').toLowerCase());
                    item.addEventListener('click', function () { sidebarItemClick(state, m.id); });
                    sub.appendChild(item);
                });

                list.appendChild(sub);
            });

            gcount.textContent = String(total);
            group.appendChild(list);

            gh.addEventListener('click', function () {
                var collapsed = group.classList.toggle('is-collapsed');
                setBtnIcon(chev, collapsed ? 'expand_more' : 'expand_less');
            });

            body.appendChild(group);
        });

        card.appendChild(body);

        // Collapse to a floating reopen button
        var fab = iconBtn('list', 'unitas-pmv2-card unitas-pmv2-sidebar-fab');
        fab.style.display = 'none';
        collapse.addEventListener('click', function () {
            card.style.display = 'none';
            fab.style.display = '';
        });
        fab.addEventListener('click', function () {
            fab.style.display = 'none';
            card.style.display = '';
        });

        input.addEventListener('input', function () { filterSidebar(card, input.value); });
        clear.addEventListener('click', function () {
            input.value = '';
            filterSidebar(card, '');
            input.focus();
        });

        state.stage.appendChild(card);
        state.stage.appendChild(fab);
    }

    function sidebarItemClick(state, markerId) {
        var marker = state.markersById[markerId];
        if (!marker) return;

        // Clicking an item in a hidden layer re-enables the layer first
        var key = String(marker._pmv2.layer);
        if (state.hiddenLayers[key]) toggleLayer(state, key);

        state.map.panTo(marker.getPosition());
        if (state.map.getZoom() < 15) state.map.setZoom(15);
        openPopup(state, markerId);
    }

    function filterSidebar(card, query) {
        query = (query || '').trim().toLowerCase();
        card.querySelectorAll('.unitas-pmv2-group').forEach(function (group) {
            var groupVisible = 0;
            group.querySelectorAll('.unitas-pmv2-subgroup').forEach(function (sub) {
                var subVisible = 0;
                sub.querySelectorAll('.unitas-pmv2-item').forEach(function (item) {
                    var match = !query || (item.getAttribute('data-name') || '').indexOf(query) !== -1;
                    item.style.display = match ? '' : 'none';
                    if (match) subVisible++;
                });
                sub.style.display = subVisible ? '' : 'none';
                var subCount = sub.querySelector('.unitas-pmv2-subgroup-head .unitas-pmv2-count');
                if (subCount) subCount.textContent = String(subVisible);
                groupVisible += subVisible;
            });
            group.style.display = groupVisible ? '' : 'none';
            var count = group.querySelector('.unitas-pmv2-group-head .unitas-pmv2-count');
            if (count) count.textContent = String(groupVisible);
        });
    }

    /* ── export ──────────────────────────────────────────────────────────── */

    window.UnitasPivotMapV2 = { init: init, instances: instances };

})();
