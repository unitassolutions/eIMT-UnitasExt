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

