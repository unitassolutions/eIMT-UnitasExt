# CHANGELOG — eIMT-UnitasExt

## v1.3.0 (2026-07-17)

### New Features
- **Waze CIFS Closure Feed (Phase 2)** — Live public feed endpoint that Waze polls every few minutes. Publishes records with Push to Waze checked + status Closed + a drawn polyline as CIFS JSON (`{"incidents":[...]}`). Start time is the actual Date/Time Closed; end time is a rolling "now + window" (default 15 min, configurable 5–120) refreshed on every poll — the documented-reliable clearing mechanism, so reopened closures drop off Waze within roughly the window plus one polling cycle.
- **Keyed, discovery-hardened endpoint** — The feed URL carries a 128-bit random key (constant-time compared). Wrong or missing key, disabled feed, or bad method: bare empty 404, indistinguishable from a nonexistent page. Success responses send `X-Robots-Tag: noindex, nofollow`; GET/HEAD only; the URL renders only on the admin settings page; one-click key regeneration.
- **Closure Feed settings portlet** — On the Waze Integration page: enable toggle, expiry window, feed URL display with Open Feed preview, Regenerate Key, and a full field-mapping UI (entity, geometry/street/status/push/dates/reason/direction/details) with per-choice Reason → CIFS subtype mapping. Choice labels are captured at save time so the feed makes zero choice-table queries per poll.

- **Public module registration** — New `public_modules.php` registers the feed and both public map report actions with the Rukovoditel login bypass (`$allowed_modules`). Also fixes the pre-existing issue where Unitas public map report URLs redirected anonymous visitors to the login page.

### Files Added
| File | Purpose |
|---|---|
| `public_modules.php` | No-login registration for the feed + public map report actions |
| `modules/waze_integration/actions/public.php` | Public keyed CIFS feed endpoint |
| `modules/waze_integration/actions/ajax_feed_fields.php` | Admin AJAX: mapping field/choice selects |

### Files Changed
| File | Change |
|---|---|
| `application_top.php` | Version 1.3.0; feed endpoint added to AJAX injection skip |
| `install.php` | Four feed columns (enabled, key, window, config) + v1.3.0 migration |
| `modules/map_configuration/helpers/map_config.php` | Feed keys in fallback defaults |
| `modules/waze_integration/actions/index.php` | POST router (lookup / regenerate key / save feed), key lifecycle, subtype map assembly |
| `modules/waze_integration/views/index.php` | Closure Feed portlet + mapping UI + AJAX loaders |
| `modules/about/views/index.php` | Features + Release Notes for v1.3.0 |
| `readme.md` | Release summary entry |

---

## v1.2.0 (2026-07-16)

### New Features
- **Waze Integration (Phase 1)** — New admin configuration page (UNITAS Extension > Extension Configuration > Waze Integration) storing a Waze Partner Hub reverse-geocoding token and API region (North America / Rest of World / Israel), with a Test Lookup button for token health checks.
- **Street-name autofill on geometry fields** — When a road closure is drawn on a Geometry (Google Map) field, the widget looks up street names via the Waze Reverse Geocoding API and auto-fills configured sibling text fields: Road Name (path midpoint), Cross Street 1 (start vertex), Cross Street 2 (end vertex). Fires once per completed draw; a "Street Names" refresh link re-runs it after vertex edits. Only empty or previously autofilled fields are written — manual input is never overwritten. With no token configured the feature is fully disabled and manual entry works as before.
- **Secure reverse-geocoding proxy** — `ajax_reverse_geocode` endpoint calls Waze server-side; the token never reaches the browser. Authenticated users only, strict lat/lng validation, max 3 points per request, region path whitelist.
- **Geometry field configuration** — Three new field-configuration dropdowns select the autofill target fields (listed as "Entity: Field"). Targets are validated at render time to belong to the same entity as the geometry field.

### Files Added
| File | Purpose |
|---|---|
| `modules/waze_integration/actions/index.php` | Waze settings save action (admin only) |
| `modules/waze_integration/views/index.php` | Waze settings form + Test Lookup button |
| `modules/waze_integration/actions/ajax_reverse_geocode.php` | Server-side Waze reverse-geocoding proxy |

### Files Changed
| File | Change |
|---|---|
| `application_top.php` | Version 1.2.0; proxy endpoint added to AJAX injection skip |
| `install.php` | `waze_geocoding_token` + `waze_region` columns (create_tables + v1.2.0 migration) |
| `modules/map_configuration/helpers/map_config.php` | Waze keys in fallback defaults |
| `menu.php` | Waze Integration menu entry under Extension Configuration |
| `classes/fieldstypes/fieldtype_unitas_geometry.php` | Autofill target dropdowns, render-time same-entity validation, wazeTargets/wazeLookup in JS config, status span + refresh link |
| `js/fieldtype/unitas_geometry.js` | v3.1.0 — Waze lookup, road/cross-street heuristic, fill policy, manual refresh, abort on clear |
| `modules/about/views/index.php` | Features + Release Notes for v1.2.0 |
| `readme.md` | Release summary entries |

---

## v1.1.0 (2026-05-09)

### New Features
- **Geometry field type** (`fieldtype_unitas_geometry`) — Custom Rukovoditel field type for drawing polylines on Google Maps. Stores JSON with encoded polyline (Waze Partner Feed API compatible), point coordinates, and distance. Appears in the Maps group of the field type dropdown. Future: point and polygon drawing modes.
- **Core file patching** — Installer automatically patches two Rukovoditel core files (`application_core.php` and `fields_types.php`) to register the geometry field type. Re-applied automatically on upgrade. Install page shows patch status.

### Files Added
| File | Purpose |
|---|---|
| `classes/fieldstypes/fieldtype_unitas_geometry.php` | Geometry field type class |
| `js/fieldtype/unitas_geometry.js` | Google Maps Drawing Manager widget |

### Files Changed
| File | Change |
|---|---|
| `application_top.php` | Version bump to 1.1.0, loads geometry field type class |
| `install.php` | Added `patch_core_files()` and `core_patches_applied()` methods |
| `modules/install/views/index.php` | Added core patches status panel |

---

## v1.0.3 (2026-04-27)

### New Features
- **Legend labels** — Pivot map report entities support custom legend labels. When populated, displayed instead of entity name. Falls back to entity name if blank.
- **About page** — UNITAS Extension menu shows version, features, and release notes.
- **Install system** — Rukovoditel Extension-style installer with install button, auto-upgrade, and DB version tracking via `app_configuration`.

### Bug Fixes
- **Filter panel deduplication** — Pivot map reports with the same entity added multiple times now render only one filter panel per unique entity.
- **Filter panels routing** — Links in `pivot_map_reports/views/entities.php` and `map_reports/views/reports.php` changed from `url_for('ext/filters_panels/fields', ...)` to `url_for('unitas_ext/filters_panels/fields', ...)`.
- **Filter panels not rendering** — `views/view.php` called `pivot_map_reports::` (Ruko class, queries `app_ext_*` tables) instead of `unitas_pivot_map_reports::` (Unitas class, queries `app_unitas_*` tables). Fixed with correct class name + `require_once`.
- **Missing JS reload function** — Filter panel refetch callback called `load_pivot_map_report{id}()` which did not exist in Unitas components. Added to both `pivot_map_reports/components/view_google.php` and `map_reports/components/view_google.php`.
- **Pivot map 500 in lightbox** — Removed `$app_layout = 'print_layout.php'` from `map_reports/actions/view.php` and `pivot_map_reports/actions/view.php` (caused HTTP 500).

### Files Changed
| File | Change |
|---|---|
| `application_top.php` | Version 1.0.3, install check, auto-upgrade |
| `install.php` | Rewritten as `unitas_ext_installer` class with full SQL + migrations |
| `menu.php` | Guarded by install check, shows Install link when not installed |
| `classes/map/pivot_map_reports.php` | Filter dedup, legend labels, legend ordering |
| `pivot_map_reports/views/view.php` | Fixed class reference, added require_once |
| `pivot_map_reports/views/entities.php` | Filter panels link fix, legend_label field |
| `pivot_map_reports/views/entities_form.php` | Added legend_label text field |
| `pivot_map_reports/actions/entities.php` | Saves legend_label |
| `pivot_map_reports/actions/view.php` | Removed $app_layout override |
| `pivot_map_reports/components/view_google.php` | Added load_pivot_map_report function |
| `map_reports/views/reports.php` | Filter panels link fix |
| `map_reports/actions/view.php` | Removed $app_layout override |
| `map_reports/components/view_google.php` | Added load_map_report function |
| `db/unitas_ext__v1.0.1.sql` | Added legend_label column |

### Files Added
| File | Purpose |
|---|---|
| `modules/about/actions/index.php` | About page action |
| `modules/about/views/index.php` | About page view |
| `modules/install/actions/index.php` | Install page action |
| `modules/install/views/index.php` | Install page view |

---

## v1.0.2 (2026-04-24)

### Bug Fixes
- **Body text size difference** — `echo` statements in `application_top.php` output HTML before `<!DOCTYPE html>`, triggering browser quirks mode. Fixed with `ob_start()` callback injecting before `</body>`.
- **Debug output** — Removed `ini_set('display_errors', 1)` and `error_reporting(E_ALL)` from `menu.php`.
- **Lightbox not opening for map reports** — `ajax_get_buttons.php` used URL pattern matching. Fixed to check `$row['button_type'] == 'report'`.
- **Report showing full UI in lightbox** — Added CSS injection to hide sidebar/header/footer when `is_modal` in URL. Key override: `.page-header-fixed .page-container { margin-top: 10px; margin-left: 10px }`.
- **"tag -->" artifact** — `str_replace` matched `</body>` inside HTML comments. Fixed with `preg_replace` to neutralize `</body>` in comments first.
- **HTTP 500 from apostrophe** — `'aren't'` in PHP single-quoted string. Fixed: never use contractions.
- **Chat button visible in lightbox** — Added `.app-chat-button` to CSS hide list.
- **Map height in lightbox** — Map containers use `calc(100vh - 75px)` to account for filter bar + padding.

### New Features
- **HEIC Converter** — Automatic HEIC/HEIF to JPEG conversion with EXIF preservation via XHR interception.
- **Full-screen report lightbox** — Custom overlay with close button, escape key, click-outside.
- **External CSS files** — Inline styles extracted to `css/unitas_ext.css` and `css/heic_converter.css`.

### Files Changed
| File | Change |
|---|---|
| `application_top.php` | Rewritten: no echo, single ob_start, lightbox CSS, HEIC injection, embed mode CSS |
| `menu.php` | Removed debug lines, added HEIC Converter submenu |
| `js/load-buttons.js` | Rewritten: custom lightbox, button injection |
| `modules/entity_buttons/actions/ajax_get_buttons.php` | button_type check, modal params |
| `modules/map_reports/views/view.php` | Hide title/breadcrumbs in modal |
| `modules/pivot_map_reports/views/view.php` | Hide title in modal |

### Files Added
| File | Purpose |
|---|---|
| `css/unitas_ext.css` | Lightbox + entity button + geometry styles |
| `css/heic_converter.css` | Conversion overlay styles |
| `js/heic/heic_converter.js` | HEIC detection + XHR interception |
| `js/heic/heic2any.min.js` | HEIC to JPEG engine (1.3MB) |
| `js/heic/exifr.umd.js` | EXIF extraction (76KB) |
| `js/heic/piexif.js` | EXIF injection (79KB) |
| `modules/heic_converter/` | Admin settings + test page |

---

## v1.0.0 (Initial)

- Entity buttons on listing pages
- Map reports (Google Maps, OpenStreetMap, Yandex)
- Pivot map reports
- Filter panels for report breadcrumbs
- Google Maps API key configuration
