# CLAUDE.md — eIMT-UnitasExt Project Context

## Project Overview

**UNITAS Extension** (`unitas_ext`) is a Rukovoditel plugin for the **eIMT** (electronic Incident Management Tool) platform built for county and local emergency management agencies. It extends Rukovoditel with custom map reports, entity listing buttons, a report lightbox, automatic HEIC photo conversion, a custom geometry field type, and filter panel support.

**Repo:** `https://github.com/unitassolutions/eIMT-UnitasExt`  
**Plugin directory:** `plugins/unitas_ext/` inside a Rukovoditel installation  
**Current version:** 1.4.0  
**Rukovoditel compatibility:** 3.5+ (tested on 3.6.4, 3.7)

### Deployment Instances
- **Production:** chathameoc.com (Chatham County NC) — does NOT have Unitas Extension
- **Development:** dev.onunitas.com/eIMTv2/ — has Unitas Extension

## Rukovoditel Platform Context

Rukovoditel is an open-source PHP project management platform using Bootstrap 3, jQuery, and MySQL.

- **Core repo:** `https://github.com/unitassolutions/Rukovoditel` (current version)
- **Extension repo:** `https://github.com/unitassolutions/Rukovoditel-Extension`
- **DO NOT USE:** `https://github.com/Rukovoditel/Rukovoditel` (outdated v1.x)
- **Docs:** `https://docs.rukovoditel.net/`
- **Forum:** `https://forum.rukovoditel.net/`

### Rukovoditel Plugin Architecture
- `application_top.php` — executes on EVERY page load during PHP bootstrap, before the HTML template renders
- `menu.php` — registers admin menu items
- `public_modules.php` — registers module paths reachable WITHOUT login: core loads this file just before its auth check and compares `$_GET['module']` against `$allowed_modules`. An action name alone (e.g. `public`) grants nothing — the path must be registered here, and the action must still self-guard.
- `modules/{name}/actions/{action}.php` — PHP logic for each page
- `modules/{name}/views/{action}.php` — PHP templates for each page
- Plugin registered in `config/server.php`: `define('AVAILABLE_PLUGINS', 'ext,unitas_ext');`

### Rukovoditel Field Type Architecture
- Field type classes live in `includes/classes/fieldstypes/fieldtype_{name}.php`
- Loaded via `require()` in `includes/application_core.php`
- Listed in `includes/classes/fields_types.php::get_choices()` (hardcoded array, no plugin hook)
- Each class has: `__construct()` (title), `get_configuration()`, `render()`, `process()`, `output()`
- Unknown field types get `TEXT` column by default in `entities::prepare_field_type()`
- Our plugin patches these two core files to register `fieldtype_unitas_geometry`

### Critical Rukovoditel CSS Classes (for lightbox embed mode)
```
.page-header-fixed .page-container    → margin-top: 45px (header offset from style.css)
.page-sidebar-wrapper                 → left sidebar navigation
.page-header / .page-header-inner     → top header bar
.page-content-wrapper > .page-content → main content area
.page-footer / .footer                → bottom footer / copyright
.app-chat-button                      → floating "Messages" chat button
```

## Installation System

Follows the Rukovoditel Extension pattern using `app_configuration` table:
- `CFG_PLUGIN_UNITAS_EXT_INSTALLED` — flag that tables exist
- `CFG_PLUGIN_UNITAS_EXT_DB_VERSION` — tracks schema version for migrations

**Fresh install:** Admin adds `unitas_ext` to `AVAILABLE_PLUGINS`, navigates to any page, sees "UNITAS Extension → Install Extension" in menu, clicks Install. Creates all tables, patches core files, sets config flags.

**Upgrades:** On each page load, `application_top.php` compares `PLUGIN_UNITAS_EXT_VERSION` vs `CFG_PLUGIN_UNITAS_EXT_DB_VERSION`. If plugin version is higher, `run_migrations()` fires silently — checks column existence before ALTER TABLE, re-applies core patches, updates DB version.

**Core file patches:** The installer patches two Rukovoditel core files to register the geometry field type. These must be re-applied after Rukovoditel core updates. The install/upgrade page shows patch status.

## Plugin File Structure

```
unitas_ext/
├── application_top.php                          # Bootstrap: class loading, install check, JS/CSS injection via ob_start
├── menu.php                                     # Admin menu (guarded by install check)
├── public_modules.php                           # No-login registration: CIFS feed + public map report actions
├── install.php                                  # Installer class: tables, migrations, core patching
├── readme.md
├── classes/
│   ├── EntityButtons.php                        # Entity button CRUD
│   ├── fieldstypes/
│   │   └── fieldtype_unitas_geometry.php         # Custom geometry field type (polyline/polygon/circle + Waze autofill)
│   └── map/
│       ├── map_reports.php                      # Map report class
│       ├── mind_map_reports.php                 # Mind map report class
│       └── pivot_map_reports.php                # Pivot map report class (with legend dedup + filter dedup)
├── css/
│   ├── unitas_ext.css                           # Lightbox overlay + entity button + geometry widget styles
│   └── heic_converter.css                       # HEIC conversion overlay styles
├── db/
│   └── unitas_ext__v1.0.1.sql                   # DB schema reference
├── js/
│   ├── load-buttons.js                          # Entity button loader + report lightbox
│   ├── fieldtype/
│   │   └── unitas_geometry.js                   # Custom click-to-draw map widget + Waze street-name autofill
│   └── heic/
│       ├── heic_converter.js                    # HEIC detection + XHR interception (29KB)
│       ├── heic2any.min.js                      # HEIC to JPEG engine (1.3MB, lazy-loaded)
│       ├── exifr.umd.js                         # EXIF extraction from HEIC (76KB, lazy-loaded)
│       └── piexif.js                            # EXIF injection into JPEG (79KB, lazy-loaded)
├── languages/
│   └── en.php
└── modules/
    ├── about/                                   # About page (version, features, release notes)
    ├── entity_buttons/                          # Custom buttons on entity listing pages
    │   ├── actions/
    │   │   ├── ajax_get_buttons.php              # AJAX endpoint: button HTML (button_type drives lightbox decision)
    │   │   ├── form.php / save.php / delete.php / index.php / report_view.php
    │   └── views/
    ├── filters_panels/                          # Filter panels for map/pivot map reports
    │   ├── components/
    │   │   ├── map_reports_breadcrumb.php
    │   │   └── pivot_map_reports_breadcrumb.php
    │   ├── actions/ views/
    ├── heic_converter/                          # Admin settings + test page
    ├── install/                                 # Install/upgrade page
    ├── map_reports/                             # Google/OSM/Yandex map reports
    │   ├── actions/
    │   │   ├── view.php                         # Report view (NO $app_layout override — CSS handles embed mode)
    │   │   ├── view_google.php / view_openstreetmap.php / view_yandex.php
    │   ├── views/ components/
    ├── pivot_map_reports/                       # Pivot map reports
    │   ├── actions/ views/ components/
    │   │   ├── view_google.php                  # Has load_pivot_map_report{id}() for filter refresh
    ├── map_configuration/                       # Google Maps API key configuration
    ├── waze_integration/                        # Waze token config, reverse-geocoding proxy (v1.2.0), CIFS closure feed (v1.3.0)
    ├── license/                                 # License key management
    └── ...
```

## Feature: Geometry Field Type (v1.1.0)

### Purpose
Custom Rukovoditel field type for drawing road closure geometry on Google Maps. Polyline data stores both a Google-encoded polyline and raw point pairs — the raw `points` array matches the Waze CIFS feed polyline format.

### Data Format
Stored as JSON in a TEXT column:
```json
{
  "type": "polyline",
  "encoded_polyline": "e~l~Fjk~uOwHJy@p@...",
  "points": [[35.7596, -79.0193], [35.7601, -79.0188]],
  "distance_m": 523.4
}
```

### How It Works
- **Form:** Google Map with custom click-to-draw (DrawingManager was removed in Maps JS API v3.65). Mode per field config: polyline, polygon, or circle. Vertices editable; clear/redraw; info bar shows distance/area/radius + point count.
- **Output:** Read-only Google Map rendering the shape (deferred to `window.load`, reuses an already-loaded Maps API to avoid double-load conflicts).
- **Listing:** Shows distance ("0.3 mi"), area ("12.4 ac"), or radius ("0.5 mi radius").
- **Export:** Returns the encoded polyline string.
- **API key + Map ID:** Uses the global Unitas Extension map config (not per-field), including light/dark/auto Map ID themes.
- **Libraries:** Google Maps JS API with the `geometry` library only (`encodePath()`, spherical math).

### Core File Patches Required
Two patches applied by the installer:
1. `includes/application_core.php` — adds `require` for our field type class after `fieldtype_google_drive.php`
2. `includes/classes/fields_types.php` — adds `'fieldtype_unitas_geometry'` after `'fieldtype_mind_map'` in the Maps group

### Drawing Modes
The `drawing_mode` config dropdown offers polyline, polygon, and circle. A point (single marker) mode remains a possible future addition.

### On Map Reports (v1.4.0)
The geometry type is selectable as the map field for Unitas Map Reports and Pivot Map Reports (Google renderer only). Two shared statics on the field type do the work: `parse_for_map()` normalizes/validates a stored value and returns the shape plus a representative point; `render_map_shapes_js()` emits the Google Polyline/Polygon/Circle JS. In both map classes, `get_coordinates()` branches on the type **before** the legacy value normalization (which would corrupt JSON) and calls `add_geometry()`, which pushes to `$shapes[]` AND a companion marker into `$markers[]` — keeping clustering, sidebar, bounds, and the empty-state guard working. Shape color: background/status choice color → field `stroke_color` → `#FF0000`.

## Feature: Waze Integration (v1.2.0 — Phase 1)

### Reverse-Geocoding Autofill
- **Admin page:** UNITAS Extension → Extension Configuration → Waze Integration. Stores `waze_geocoding_token` + `waze_region` (na/row/il) as columns on `app_unitas_map_reports_config`; a Test Lookup button checks token health against the default map center.
- **Field mapping:** The geometry field configuration selects target text fields (Road Name / Cross Street 1 / Cross Street 2) from an all-entities dropdown; `render()` validates the targets belong to the same entity and silently drops mismatches.
- **Flow:** On draw completion the widget POSTs the path midpoint + endpoints to `unitas_ext/waze_integration/ajax_reverse_geocode`, which curls `https://www.waze.com/{partnerhub-api|row-partnerhub-api|il-partnerhub-api}/waze-map/streetsInfo?lat=..&lon=..&token=..` server-side. **The token never reaches the browser.** Road Name = nearest street at the midpoint; cross streets = nearest non-road street at each endpoint.
- **Fill policy:** Only empty or previously autofilled inputs are written (`data-unitas-autofill` marker); a manual edit permanently releases the field. A "Street Names" info-bar link re-runs the lookup after vertex edits.
- **Degradation:** No token configured → the feature is fully off; manual entry unchanged. All failures are silent (console.warn only).
- The proxy endpoint is registered in `application_top.php` `$is_ajax_request` so the ob_start HTML injection skips it.

### CIFS Closure Feed (v1.3.0 — Phase 2)
- **Endpoint:** `index.php?module=unitas_ext/waze_integration/public&key=<128-bit key>` — live, unauthenticated but keyed (`hash_equals`; anything without the exact key gets a bare empty 404). Registered in `$is_ajax_request`. GET/HEAD only; success responses send `X-Robots-Tag: noindex, nofollow`. Never put the URL in robots.txt.
- **Inclusion rule:** Push to Waze truthy + Road Status = configured Closed choice + polyline with 2+ points + starttime <= now. Cap 500 incidents, newest first. The push filter is type-aware: **`fieldtype_boolean` stores the literal strings `true`/`false`** (checked = `'true'`); other types use a generic truthiness check.
- **Times:** `starttime` = actual Date/Time Closed (parse chain: numeric → strtotime → date_added → now); `endtime` = now + rolling window (5–120 min, default 15) on every response. Waze only guarantees removal at endtime, so delisted records clear within ~window + one polling cycle.
- **Mapping:** JSON in `app_unitas_map_reports_config.waze_feed_config` (entity + field ids + closed/one-direction choice ids + reason→subtype map with labels captured at save time). Admin UI is a second portlet on the Waze Integration page; `ajax_feed_fields` (admin-only) loads per-entity field and choice selects.
- **IMPORTANT:** CIFS `polyline` = space-separated lat/lon pairs — the feed uses the stored `points` array, NOT `encoded_polyline`. Polygon/circle records are skipped.

## Feature: HEIC Converter

### Architecture — Three-Layer Approach
**Layer 1 — Visual Feedback:** Change handler shows conversion overlay. Does NOT stop/manipulate the event.
**Layer 2 — XHR Interception:** Monkey-patches `XMLHttpRequest.prototype.send`. When any XHR sends FormData with HEIC files, pauses send, converts to JPEG with EXIF, rebuilds FormData, sends converted version.
**Layer 3 — Form Submit Interception:** For non-AJAX form submissions, intercepts submit event, converts via DataTransfer, re-submits.

### Why XHR Interception
Rukovoditel's jQuery File Upload captures files synchronously during the change event. Async conversion finishes after Rukovoditel already holds the original HEIC blob. XHR interception catches files at the last possible moment — when bytes are about to leave the browser.

### EXIF Preservation
GPS, timestamps, orientation, camera make/model, altitude extracted via exifr.umd.js and injected into JPEG via piexif.js. Libraries lazy-loaded (~1.5MB total) only on first HEIC detection.

## Feature: Report Lightbox

### Flow
1. Admin creates entity button with `button_type = 'report'`
2. `ajax_get_buttons.php` generates `onclick="unitasOpenCleanReport(url, title)"` — decision based on `button_type`, NOT URL pattern
3. `load-buttons.js` creates overlay (dark backdrop) + modal (94%×92%) + close button (×) + iframe
4. URL includes `is_modal=1` which triggers CSS injection in `application_top.php`
5. CSS hides sidebar/header/footer/chat inside the iframe, overrides `.page-header-fixed .page-container { margin-top: 10px; margin-left: 10px }`
6. Map containers use `calc(100vh - 75px)` to account for filter bar + padding

### Do NOT use $app_layout overrides
`$app_layout = 'print_layout.php'` was tried in view actions and caused HTTP 500 errors. Removed from both `map_reports/actions/view.php` and `pivot_map_reports/actions/view.php`. CSS injection handles everything.

## Feature: Pivot Map Reports

### Filter Panel Deduplication
When the same entity appears multiple times in a pivot map (e.g., Shelters with different marker styles for each status), `render_entity_filters_panel()` uses a `$rendered_entities` array to render only one filter panel per unique `entities_id`.

### Legend Labels
Pivot map entities have a `legend_label` field. When populated, the legend shows this label instead of the entity name. Falls back to entity name if blank. Legend items ordered by `ce.id` (creation order), not alphabetically.

### Filter Panel Routing
Filter panel links in `pivot_map_reports/views/entities.php` and `map_reports/views/reports.php` must use `url_for('unitas_ext/filters_panels/fields', ...)` — NOT `url_for('ext/filters_panels/fields', ...)`. The latter routes to the Rukovoditel Extension which does not know about Unitas report types.

### Class References
`pivot_map_reports/views/view.php` must call `unitas_pivot_map_reports::render_entity_filters_panel()` — NOT `pivot_map_reports::` (which is the Ruko Extension class querying `app_ext_*` tables). The class file must be `require_once`d before the static method calls.

### JS Reload Functions
The filter panel callback calls `load_pivot_map_report{id}()` and `load_map_report{id}()`. These functions must exist in the respective `view_google.php` components. They wrap `loadMapTheme()` to trigger map reload when filter values change.

## application_top.php — Injection Architecture

All JS/CSS injected via a single `ob_start()` callback before `</body>`. NOTHING is echoed.

Three injection blocks build `$unitas_inject_html`:
1. **Entity Buttons** — JS + CSS on listing pages only
2. **HEIC Converter** — JS + CSS on all pages for authenticated users
3. **Lightbox Embed Mode** — CSS to hide chrome when `is_modal`/`is_embed` in URL

The `ob_start` callback uses `preg_replace` to neutralize `</body>` inside HTML comments before `str_replace`, preventing the "tag -->" artifact.

## Critical Pitfalls

### PHP Single-Quoted Strings
NEVER use contractions in PHP single-quoted strings:
```php
// FATAL — apostrophe terminates the string
'/* panels are not cut off */'   // CORRECT
'/* panels aren't cut off */'    // HTTP 500 on EVERY page
```

### ob_start Comment Artifact
Rukovoditel templates contain `<!-- end of </body> tag -->`. Naive `str_replace('</body>', ...)` matches the comment first. Fix: `preg_replace` neutralizes `</body>` in comments before `str_replace`.

### $app_layout Overrides
Do NOT set `$app_layout = 'print_layout.php'` in view actions. Causes HTTP 500. CSS injection handles embed mode.

### Class Loading for Static Methods
When calling `unitas_pivot_map_reports::` static methods from view templates, the class must be `require_once`d first. The Ruko Extension class (`pivot_map_reports::`) is autoloaded, but Unitas classes are not.

### Core File Patches After Updates
After Rukovoditel core updates, the geometry field type patches in `application_core.php` and `fields_types.php` will be overwritten. Visit the install page to re-apply, or upgrades handle it automatically.

### PHP OpCache
After deploying PHP changes, restart PHP-FPM/Apache or call `opcache_reset()`.

## Related Project: Module Porter

Separate plugin for exporting/importing Rukovoditel entity configurations between instances.
**Repo:** `https://github.com/unitassolutions/Ruko-Porter-Module`
**Blueprint:** `eimt_module_porter_blueprint_v2.md`
Currently at Phase 2. Phase 3 (access control, user group mapping) is next.

## Development Practices

- DRY principles, efficient readable code with clear comments
- Security first, then user experience
- No unnecessary changes between edits (step-by-step building)
- Never use contractions in PHP single-quoted strings
- Test on dev instance before production
- Always verify no PHP syntax errors after string manipulation
- When editing files: verify the file content before and after changes
