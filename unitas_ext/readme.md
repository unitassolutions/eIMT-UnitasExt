**UNITAS Ext – Release Summary**



**Version 1.0**

* Initial release of the UNITAS Extension module.



**Version 1.0.1**

* Addressed identified bugs from the initial release.
* Implemented performance optimizations.
* Introduced minor enhancements to improve overall system reliability and user experience.



**Version 1.1.0**

* Added the Geometry (Google Map) custom field type for drawing road-closure polylines, polygons, and circles.
* Installer now patches Rukovoditel core files to register the field type and re-applies the patches on upgrade.



**Version 1.2.0**

* Waze Integration (Phase 1): reverse-geocoding token configuration page with Test Lookup health check.
* Automatic street-name autofill (road name + cross streets) when a closure is drawn on a geometry field.
* Secure server-side proxy keeps the Waze token out of the browser.



**Version 1.3.0**

* Waze CIFS Closure Feed (Phase 2): live keyed feed endpoint publishes active road closures to the Waze app.
* Rolling expiry window guarantees reopened closures clear from Waze within minutes of being delisted.
* Feed mapping UI, secret URL key with one-click regeneration, and discovery-hardened responses.



**Version 1.4.0**

* Geometry fields can now be used as the map field on map reports and pivot map reports.
* Drawn closures render as actual polylines, polygons, and circles, color-coded by road status.
* Pivot maps can mix marker entities and geometry entities on a single map.



**Version 1.5.0**

* Pivot Map Report v2: opt-in modern layout with a full-bleed map and floating panels.
* Interactive legend with per-layer show/hide toggles and counts.
* Floating searchable sidebar; clicking an item zooms to the record and opens its popup.
* Colored map pins and dark-mode styling in the modern layout.



**Version 1.5.1**

* Unitas map reports and pivot map reports can now be added to the main application menu from Application Structure > Entities > Menu.
* Reports placed in the main menu no longer appear twice in the navigation.



**Version 1.5.2**

* Fixed Unitas reports added to the main menu not rendering their menu link.
* Background Color is now available for Google map layers on pivot maps, and multi-value status fields can drive it.



**Version 1.6.7 — Google Maps key lockdown**

* Two-key Google configuration: a website-restricted browser key and an IP-restricted, Geocoding-only server key that is never rendered into HTML, JavaScript, logs, or exports. Both keys have one-click tests on the Google Map configuration page.
* New Location (Google Map) field type replaces the core Google Map field: it geocodes a companion address text field server-side, shows a draggable pin preview on forms and record pages, and stores latitude, longitude, address, and a geocoding status with each value.
* Unitas address autocomplete (Google Places API New) on location source fields and, via a new Address Autocomplete rules page, on any plain text field — replacing the legacy Extension Google Autocomplete smart input.
* Location Tools (admin): preflight report, one-click conversion of core Google Map fields to the Location type, a batch re-geocode driver, and a Location Health triage view.
* Core integration reworked as marker-delimited, self-repairing shims with a health check and an admin banner when a Rukovoditel core update overwrites them.
* Once no core Google Map fields remain, the core `items/google_map` endpoint is blocked, closing the unauthenticated key-bearing page.
* Marker clusterer library is now pinned and self-hosted instead of loaded from unpkg.

---

# Google Maps Platform Key Setup (per instance)

Each eIMT instance gets its **own Google Cloud project** (or at minimum its own pair of keys) so restrictions and metrics are per instance.

## Browser key

| Setting | Value |
|---|---|
| Name | `eimt-<instance>-browser` |
| Application restriction | **Websites** — `https://<instance-host>/*` for every hostname users reach the instance on (vanity domains and public form/report hosts included) |
| Do not add | `http://` entries, cross-instance wildcards, `localhost` (use a separate dev key) |
| API restrictions | Maps JavaScript API, Places API (New), Geocoding API |
| Stored in | UNITAS Extension > Extension Configuration > Google Map (public by design) |

Note: if server hardening sets `Referrer-Policy: no-referrer`, website-restricted keys fail. Use `strict-origin-when-cross-origin`.

## Server key

| Setting | Value |
|---|---|
| Name | `eimt-<instance>-server` |
| Application restriction | **IP addresses** — the server's public **outbound** IP as Google sees it (check with `curl -4 https://ifconfig.me`; add IPv6 if routed) |
| API restrictions | **Geocoding API only** |
| Stored in | Google Map configuration (write-only field), or `UNITAS_GOOGLE_SERVER_KEY` in `config/server.php` (takes precedence) |
| Never | Rendered into HTML/JS, logged, exported, or shared between instances |

## Project settings

* Enable only Maps JavaScript API, Places API (New), Geocoding API. After cutover, **disable** legacy Places, Directions, and Distance Matrix — API restrictions protect one key; disabling protects every key in the project.
* Set daily quota caps on Geocoding and Places (New), sized several times above the busiest real day.
* Watch Metrics Explorer by `credential_id`; alert on 403 spikes (the signature of abuse against a restricted key).
* Confirm the light/dark map IDs configured in Unitas were created in **this** project.

---

# Per-Instance Cutover Runbook (core Google Map → Unitas Location)

1. **Prepare** — Run the audit SQL (see the key lockdown plan, Appendix B). Confirm no `_directions`/`_nested`/GeliosSoft map fields. Take and verify a database backup. Collect the instance's outbound IP and all hostnames. **Confirm no integration reads raw map field values** through the REST API, exports, or direct SQL — the stored value format changes (four tab-separated parts, plain-text address).
2. **Google Cloud** — Create the browser and server keys (above), enable the three APIs, set quota caps, confirm map IDs.
3. **Deploy** Unitas-Ext 1.6.7+. Visit any admin page so the upgrade runs; confirm shim health is green on the install page. Restart PHP-FPM/Apache or `opcache_reset()`.
4. **Configure** browser key, server key, region codes, and bias radius on the Google Map page. Run **both** key tests; both must pass before converting anything.
5. **Migrate** — UNITAS Extension > Location Tools: Preflight → Convert (requires backup + key-test confirmations) → Re-geocode. Review Location Health.
6. **Replace autocomplete** — Verify Unitas autocomplete fires on converted source fields; add Address Autocomplete rules for any standalone fields; **deactivate the Extension "Google Autocomplete" smart input module** (its old-key Maps tag otherwise conflicts with the new loader).
7. **Review forms** — Converted location fields now render on item forms; adjust tab/row placement in the form designer.
8. **Verify** — Create records via suggestion, via typed text, via REST (if used); enter a nonsense address and confirm `not_found`; check map reports; check the console for `RefererNotAllowedMapError` / `ApiTargetBlockedMapError`.
9. **Retire the old key** — Watch old-key traffic by `credential_id` until zero for 24–48h; confirm no `api_key` remains in `app_fields.configuration` and none in the smart input module config; delete the key.
10. **Tighten** — Disable legacy Places/Directions/Distance Matrix; remove any interim legacy-Places allowance from the browser key; confirm core `items/google_map` now returns 403.

---

# Core Update Repair Runbook

After **any** Rukovoditel core update:

1. Deploy the core update.
2. Log in as admin — a fixed banner appears if the Unitas core shims were overwritten.
3. Open UNITAS Extension > Install. When a shim is missing the page shows a red **Repair Core Integration** button and a per-shim status list — click it (idempotent: re-applies the field type shim, the save hook, and the menu shims, refreshes the core-Google-map-fields flag, and resets OpCache). The button link carries the CSRF token; a hand-typed `action=` URL will be rejected.
4. If the repair reports an anchor was not found exactly once, **stop** — the anchor in `install.php` must be updated against the new core source before re-running. Do not hand-edit core.
5. Save a test record with a known address and confirm its location status is `geocoded`.

