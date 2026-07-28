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

