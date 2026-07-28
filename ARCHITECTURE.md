# Architecture Decisions & Known Issues

## Geometry Field Type

### ADR-001: Core File Patching for Field Type Registration
**Decision:** Installer patches two Rukovoditel core files to register `fieldtype_unitas_geometry`.
**Rationale:** Rukovoditel has no plugin hook for registering custom field types. `fields_types::get_choices()` is a hardcoded array. All existing field types (including Extension ones) are baked into core. Only alternative is JavaScript DOM injection of dropdown options, which is fragile.
**Patches:**
1. `includes/application_core.php` — adds `require` after `fieldtype_google_drive.php` (anchor line)
2. `includes/classes/fields_types.php` — adds to Maps group after `'fieldtype_mind_map'` (anchor line)
**Risk:** Patches are overwritten by Rukovoditel core updates. Mitigated: installer re-applies on upgrade, install page shows patch status.
**Important:** If Rukovoditel renames `fieldtype_google_drive.php` or `fieldtype_mind_map`, the anchor lines will not match and patching will fail gracefully (error logged, manual patch instructions shown).

### ADR-002: Generic Name (fieldtype_unitas_geometry)
**Decision:** Named `geometry` not `polyline` to support future point and polygon modes.
**Rationale:** Road closures need polylines now, but the architecture should support shelter locations (points) and flood zones (polygons) later. The `drawingMode` config dropdown is pre-wired for all three modes but only polyline is currently listed.

### ADR-003: Data Format (JSON with Encoded Polyline)
**Decision:** Store as JSON: `{"type":"polyline","encoded_polyline":"...","points":[[lat,lng],...],"distance_m":523}`
**Rationale:**
- `encoded_polyline` is directly usable by Waze Partner Feed API
- `points` array enables re-rendering on the map without decoding
- `distance_m` avoids recalculation on display
- JSON is self-describing and extensible for future polygon `area_sqm` etc.
**Tradeoff:** TEXT column is not indexable for spatial queries. If spatial filtering is needed later, a PostGIS migration or separate spatial index table would be required.

### ADR-004: Google Maps API Key from Global Config
**Decision:** Field type reads API key from `app_unitas_map_reports_config` table (Unitas Extension global config).
**Rationale:** One API key per instance, managed in one place. Avoids per-field key configuration that would be tedious and error-prone. All Unitas map features share the same key.

### ADR-020: Geometry on Map Reports — Shape Plus Companion Marker
**Decision:** When a geometry field is the map field, each record produces a drawn shape in a new `shapes[]` array **and** an ordinary marker at a representative point (polyline → middle vertex, polygon → centroid, circle → center). Shapes never enter `markers[]`. Parsing and Google drawing live on the field type itself (`fieldtype_unitas_geometry::parse_for_map()` / `render_map_shapes_js()`), shared by both map classes.
**Rationale:** `markers[]` feeds `markerClusterer` and the bounds pass calls `marker.getPosition()` — pushing a Polyline there would throw. The companion marker means clustering, the sidebar, bounds fitting, and the empty-state guard keep working untouched, and a 0.2-mile closure stays findable at county zoom where a thin line is a few pixels. Keeping parse/draw on the field type avoids duplicating ~90 lines across `map_reports` and `unitas_pivot_map_reports`, which already duplicate their other render methods.
**Tradeoff:** Two clickable targets per record (shape and marker, same popup), and slightly more visual density on polygon/circle layers.

### ADR-021: Geometry Values Bypass the Legacy Value Normalization
**Decision:** `get_coordinates()` handles `fieldtype_unitas_geometry` from the **raw** column value before the shared per-value normalization, then `continue`s.
**Rationale:** That block splits on `;`, squeezes `", "` to `","`, and truncates at `(` — all mapbbcode-specific rules that would corrupt or split a JSON object. Branching first also leaves every existing field type path byte-identical, so there is no regression surface.
**Tradeoff:** Multiple geometry objects in one field value are not supported (one JSON object per record).

### Known Issues — Geometry
- **Maps API libraries:** the widget uses custom click-to-draw (DrawingManager was removed in Maps JS API v3.65) and needs only the `geometry` library. Map report shape drawing needs no extra library — it uses the stored `points` array, not `encoded_polyline`.
- **TEXT column:** Polyline data stored as JSON text, not spatial data. Maximum size ~65KB per field value. Sufficient for road closures (even complex routes rarely exceed 1000 points = ~30KB JSON).
- **Validation split:** `process()` validates JSON structure on save; `parse_for_map()` additionally validates coordinate ranges and point counts at render time, skipping any record it cannot draw. A record with malformed geometry simply does not appear on the map report.
- **Google renderer only:** geometry map reports route to the Google view. Yandex has no shape support in `render_yandex_js()`, and the Leaflet/OSM path draws only the legacy mapbbcode `coordinates` string format.

---

## HEIC Converter

### ADR-005: Client-Side via XHR Interception
**Decision:** Convert HEIC to JPEG in the browser by intercepting `XMLHttpRequest.prototype.send`.
**Rationale:** Earlier versions (v1.0.0, v1.1.0 of the standalone plugin) tried event-level interception (capture phase + stopImmediatePropagation + redispatch). Failed because Rukovoditel's jQuery File Upload plugin captures files synchronously during the change event. XHR interception catches files at the network layer regardless of how Rukovoditel handles the change event.
**Tradeoff:** Monkey-patching XHR is invasive. Does not cover `fetch()` API (Rukovoditel uses jQuery/XHR). Does not cover REST API uploads.

### ADR-006: Lazy Loading
**Decision:** heic2any + exifr + piexif (~1.5MB) loaded only on first HEIC detection.
**Rationale:** Zero cost for users who never upload HEIC. After first load, browser-cached.

### Known Issues — HEIC
- EXIF coverage: GPS, timestamps, orientation, camera make/model, altitude preserved. Other fields (lens, flash) not transferred.
- Memory: 10+ large HEIC files simultaneously may cause phone performance issues.
- API uploads bypass client-side conversion entirely.

---

## Report Lightbox

### ADR-007: CSS Injection vs Template Switching
**Decision:** CSS injection via `application_top.php` `ob_start()`.
**Rationale:** `$app_layout = 'print_layout.php'` caused HTTP 500 in both map and pivot map view actions. CSS injection with `display: none !important` is version-agnostic.
**Important:** Do NOT add `$app_layout` overrides to any view actions. They were tried and failed.

### ADR-008: button_type vs URL Pattern for Lightbox
**Decision:** Check `$row['button_type'] == 'report'` to determine lightbox usage.
**Rationale:** URL pattern matching (`strpos($url, 'module=reports/view')`) fails for map reports (`module=unitas_ext/map_reports/view`). The admin declares intent via button_type.

### Known Issues — Lightbox
- Map height `calc(100vh - 75px)` assumes filter bar is ~45px. Taller filter bars may clip the map bottom.
- `.page-header-fixed .page-container { margin-top: 10px }` override is specific to the Bootstrap 3 theme Rukovoditel uses. Theme changes could require CSS updates.

### CSS Injection Specifics (when is_modal or is_embed in URL)
```
Hidden: .page-sidebar-wrapper, .page-sidebar, .page-header, .page-header-inner,
        .page-footer, .page-breadcrumb, .page-bar, .page-toolbar, .page-title,
        .footer, .app-chat-button, #sidebar, .navbar, .top-menu, footer, header

Overrides:
  .page-header-fixed .page-container → margin-top: 10px, margin-left: 10px
  .page-content → padding: 0 8px 8px 0
  body → overflow: hidden
  Map containers → height: calc(100vh - 75px)
```

---

## application_top.php Architecture

### ADR-009: Single ob_start, Zero echo
**Decision:** All injected HTML collected in `$unitas_inject_html`, delivered via one `ob_start()` callback.
**Rationale:** `echo` before `<!DOCTYPE html>` triggers browser quirks mode. `ob_start` injects before `</body>` after the full document.

### ADR-010: Comment-Safe str_replace
**Decision:** `preg_replace` neutralizes `</body>` in HTML comments before `str_replace`.
**Rationale:** Rukovoditel template has `<!-- end of </body> tag -->`. Naive `str_replace` injects inside the comment, leaving `tag -->` as visible text.

### Known Issues — Injection
- Nested `ob_start` with other plugins could conflict.
- AJAX responses pass through (no `</body>` tag), but edge cases may exist.

---

## Pivot Map Reports

### ADR-011: Filter Panel Deduplication by entities_id
**Decision:** `render_entity_filters_panel()` tracks rendered entities in an array and skips duplicates.
**Rationale:** Same entity can appear multiple times with different marker styles (e.g., Shelters with different status colors). Without dedup, identical filter panels render N times.

### ADR-012: Legend Labels
**Decision:** Added `legend_label` column to `app_unitas_pivot_map_reports_entities`. Used in legend when populated, falls back to entity name.
**Rationale:** When same entity appears multiple times, all legend items would show the same entity name. Custom labels allow "Open Shelters", "Closed Shelters", etc.

### ADR-022: Opt-In Layout Column with a Parallel v2 Renderer
**Decision:** Pivot map reports carry a `layout` column ('classic' default | 'modern'). Modern routes to a parallel component/action pair (`view_google_v2`) that emits ONE `json_encode` payload consumed by `js/pivot-map-v2.js`; classic files stay byte-identical apart from the two dispatch branches. The filter-panel contract is preserved: the v2 component still defines `load_pivot_map_report{id}()` — the refetch callback generated in `render_entity_filters_panel()` calls exactly that name — and the fragment POST keeps `{id, map_theme}`.
**Rationale:** Existing reports must not change; a per-report toggle reuses every admin screen, table, and filter panel with zero duplicate setup. A structured payload replaces the classic string-concatenated JS, which cannot support an interactive UI (layer toggles, search, and popups need marker registries). Marker/shape arrays gained additive keys (`entities_id`, `entity_row`, `name`) that classic renderers never read.
**Tradeoff:** Two Google renderers to maintain. v2 intentionally diverges where classic was broken or dated: scoped DOM ids instead of the global misspelled `goolge_map_container`, colored pins honor marker color, and every configured layer appears in the v2 legend (classic omitted layers without a color or icon).

### Known Issues — Pivot Map
- `render_entity_filters_panel()` and `render_legend()` require `unitas_pivot_map_reports::` class prefix (not `pivot_map_reports::`) and a `require_once` before the static method calls.
- The Ruko Extension classes (`pivot_map_reports::`) are autoloaded by the `ext` plugin. Unitas classes are not autoloaded — they must be explicitly required.

---

## Waze Integration

### ADR-013: Server-Side Proxy for Token Secrecy
**Decision:** The Waze reverse-geocoding token is stored in the DB and used only inside `modules/waze_integration/actions/ajax_reverse_geocode.php`. The widget JS receives just a `wazeLookup` boolean and target field ids — never the token.
**Rationale:** A browser-visible token is trivially exfiltratable and direct browser calls would hit CORS. The proxy keeps the token secret and normalizes upstream responses (sorted, deduped, capped at 5 entries per point).
**Tradeoff:** Every lookup costs a server round-trip — up to 3 sequential curl calls (3 s connect / 5 s total timeout each, early abort when Waze is unreachable).

### ADR-014: Config Columns on the Existing Single-Row Table
**Decision:** `waze_geocoding_token` and `waze_region` are columns on `app_unitas_map_reports_config` (id = 1), not a new table.
**Rationale:** One settings row, one memoized helper (`unitas_map_config::get()`), zero new tables. Matches ADR-004 — one API configuration per instance, managed in one place.
**Tradeoff:** The table name no longer matches its widened scope.

### ADR-015: Autofill Target Mapping in Field Configuration
**Decision:** The geometry field configuration offers three dropdowns listing `fieldtype_input` fields across ALL entities, labeled "Entity: Field (ID n)". `render()` validates selections against `$field['entities_id']` and silently zeroes mismatches.
**Rationale:** `get_configuration()` receives no arguments, so no entity context exists when the config UI is built. A global, entity-labeled list avoids typing raw ids or custom config-page JS.
**Tradeoff:** A wrong-entity selection produces no visible error — the entity prefix in the dropdown label is the guardrail.

### ADR-016: One-Shot Trigger at Draw Completion
**Decision:** The lookup fires once after `finishDrawing()` / `_finishCircle()` — never inside `save()`. A "Street Names" info-bar link re-runs it manually.
**Rationale:** `save()` also fires on every vertex drag (`set_at` / `insert_at` / `remove_at`); hooking it would spam the Waze API and fight the user mid-edit.
**Tradeoff:** After vertex edits the street names refresh only on request.

### ADR-017: Live Keyed Public Feed Endpoint with Uniform-404 Hardening
**Decision:** The CIFS feed is a live action (`waze_integration/actions/public.php`) guarded only by a 128-bit random URL key (`bin2hex(random_bytes(16))`, `hash_equals`, minimum-length gate). Every identity failure — bad method, not installed, disabled, missing/short/wrong key — returns a bare empty 404. Success responses carry `X-Robots-Tag: noindex, nofollow`; GET/HEAD only; the URL is rendered solely on the admin settings page and never added to robots.txt.
**Rationale:** Waze must fetch the feed unauthenticated and publishes no fetcher IPs or user agent, so allowlists would break the feed. A high-entropy secret URL is the standard partner-feed model; the uniform 404 denies bots, crawlers, and AI scrapers any signal that an endpoint exists. Anonymous access is granted by registering the path in `public_modules.php` — core loads that file just before its login check and compares `$_GET['module']` against `$allowed_modules` (verified in core source; an action named `public` grants nothing by itself).
**Tradeoff:** Anyone holding the exact URL can read the feed — mitigated by instant key rotation, and the payload is closure data Waze broadcasts publicly anyway.

### ADR-018: Rolling endtime as the Clearing Mechanism
**Decision:** Every feed response sets each closure `endtime = now + window` (configurable 5–120 min, default 15); `starttime` stays the record's actual Date/Time Closed; future-start records are excluded.
**Rationale:** Waze docs guarantee incident removal at `endtime` only — removal on feed disappearance is undocumented, and an omitted endtime defaults to +14 days. With Waze polling every few minutes, the window keeps rolling forward while a closure is listed, and anything delisted (reopened, unpushed) expires within ~window + one polling cycle. This is the same guarantee the team relied on in a prior production deployment, upgraded from a 15-minute cron to a per-poll live rebuild.
**Tradeoff:** The Waze app never shows a true expected reopen time (the Est. Reopen field is stored in the mapping for future use but not fed).

### ADR-019: Mapping Stored as One JSON Blob with Labels Captured at Save
**Decision:** The entity/field/choice mapping lives in a single `waze_feed_config` TEXT column as JSON; reason-choice labels are copied into the JSON when the admin saves.
**Rationale:** One column, no new tables, and the public feed answers every Waze poll with a single item query — zero choice-table lookups.
**Tradeoff:** Renaming a choice shows a stale label in feed descriptions until the mapping is re-saved.

### Known Issues — Waze Integration
- `partnerhub-api/waze-map/streetsInfo` is partner documentation, not a formal API contract. Verified live 2026-07-17: result entries use a `names` key, NOT the `streetNames` key shown in the support article — the proxy accepts both. It also parses defensively (missing `result` key returns an empty list) and the widget degrades silently to manual entry.
- Autofill writes only empty or previously autofilled inputs (tracked via `data-unitas-autofill`); a manual edit permanently releases the field. Two geometry fields targeting the same text fields will overwrite each other's autofilled values.
- CIFS feed (v1.3.0): uses the stored `points` array — CIFS `polyline` format is space-separated lat/lon pairs, never `encoded_polyline`. Polygon and circle records are skipped (CIFS closures require road-following polylines). Renamed choices show stale labels in feed descriptions until the mapping is re-saved (ADR-019).
- Anonymous access requires `public_modules.php` registration (core `$allowed_modules`) — confirmed against core source after dev testing showed the `public` action name alone is NOT sufficient. Core hardcodes `ext/map_reports/public` (the Rukovoditel Extension) but knows nothing of plugin paths; the same registration therefore also un-gated the previously login-blocked Unitas public map report URLs.
- Push filter is type-aware because `fieldtype_boolean` stores the literal strings `true`/`false` (an unchecked box is `'false'`, which a generic non-empty/non-zero check wrongly passes).

---

## General

### PHP Compatibility
- Tested: PHP 8.1, 8.2
- Uses `??` null coalescing (PHP 7.0+)
- Uses closures with `use` (PHP 5.3+)

### Critical Safety Rules
1. **Never use contractions in PHP single-quoted strings** — apostrophe terminates the string
2. **Never set $app_layout in view actions** — causes HTTP 500
3. **Always verify file content before and after str_replace** — stale context causes mismatches
4. **Restart PHP-FPM after deploying** — opcache serves stale compiled PHP
5. **Use `url_for('unitas_ext/...')` not `url_for('ext/...')`** for all internal routing
6. **require_once the class before calling static methods** on Unitas classes
