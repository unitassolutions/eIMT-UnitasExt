<?php
/**
 * UNITAS Extension — About Page View
 */
?>

<h3 class="page-title">UNITAS Extension</h3>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-info-circle"></i> Version Information</h3>
            </div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <tr>
                        <td style="width:40%"><strong>Version</strong></td>
                        <td><?php echo PLUGIN_UNITAS_EXT_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Developer</strong></td>
                        <td>Unitas Solutions LLC</td>
                    </tr>
                    <tr>
                        <td><strong>Website</strong></td>
                        <td><a href="https://www.onunitas.com" target="_blank">www.onunitas.com</a></td>
                    </tr>
                    <tr>
                        <td><strong>Compatibility</strong></td>
                        <td>Rukovoditel 3.5+</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-puzzle-piece"></i> Features</h3>
            </div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <tr>
                        <td style="width:40%"><strong>Entity Buttons</strong></td>
                        <td>Custom buttons on entity listing pages with full-screen report lightbox support.</td>
                    </tr>
                    <tr>
                        <td><strong>Map Reports</strong></td>
                        <td>Google Maps integration with custom styling, API key management, light/dark/auto themes, and sidebar navigation.</td>
                    </tr>
                    <tr>
                        <td><strong>Pivot Map Reports</strong></td>
                        <td>Multi-entity map reports with custom markers, legends, filter panels, and sidebar support. Optional modern layout with an interactive layer legend and a searchable floating sidebar.</td>
                    </tr>
                    <tr>
                        <td><strong>HEIC Converter</strong></td>
                        <td>Automatic HEIC/HEIF to JPEG conversion with EXIF preservation (GPS, timestamps, orientation). Transparent to users.</td>
                    </tr>
                    <tr>
                        <td><strong>Filter Panels</strong></td>
                        <td>Quick filter panels for map and pivot map reports with dropdown multiselect support.</td>
                    </tr>
                    <tr>
                        <td><strong>Geometry Field Type</strong></td>
                        <td>Custom field type for drawing polylines, polygons, and circles on Google Maps. Stores Waze-compatible geometry JSON with distance, area, or radius, and renders on map and pivot map reports.</td>
                    </tr>
                    <tr>
                        <td><strong>Waze Integration</strong></td>
                        <td>Reverse-geocoding autofill of road and cross-street names when a closure is drawn on a geometry field, plus a keyed CIFS feed that publishes active closures to the Waze app.</td>
                    </tr>
                    <tr>
                        <td><strong>Location Field Type</strong></td>
                        <td>Replacement for the core Google Map field: server-side geocoding of a companion address field, draggable pin preview, and a per-record geocoding status. Works with restricted Google API keys.</td>
                    </tr>
                    <tr>
                        <td><strong>Address Autocomplete</strong></td>
                        <td>Google Places (New) address suggestions on location source fields and on any text field via autocomplete rules. Replaces the legacy Google Autocomplete smart input.</td>
                    </tr>
                    <tr>
                        <td><strong>Location Tools</strong></td>
                        <td>Migration of core Google Map fields to the Location type, batch re-geocoding, and a location health triage view.</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-history"></i> Release Notes — v<?php echo PLUGIN_UNITAS_EXT_VERSION; ?></h3>
            </div>
            <div class="panel-body">
                <ul>
                    <li><strong>Cleaner modern pivot map filters:</strong> Reports with no configured filter fields no longer show an empty filter bar floating over the top of the map.</li>
                    <li><strong>Readable filter selections:</strong> The chosen value in a modern pivot map filter now renders in dark ink instead of a washed-out gray, while the "Select some options" placeholder stays a subtle hint.</li>
                </ul>
                <p style="margin-top:12px"><strong>Previously in v1.6.7 — Google Maps key lockdown</strong></p>
                <ul>
                    <li><strong>Two-key Google configuration:</strong> A website-restricted browser key and a separate IP-restricted server key that never reaches the browser. Both keys have one-click tests on the Google Map page.</li>
                    <li><strong>Location field type:</strong> Replaces the core Google Map field with server-side geocoding, a draggable pin, and a geocoding status stored with every value.</li>
                    <li><strong>Address autocomplete:</strong> Google Places (New) suggestions on address fields, replacing the legacy Google Autocomplete smart input. Configurable per field under Extension Configuration.</li>
                    <li><strong>Location Tools:</strong> One-click migration of existing Google Map fields, batch re-geocoding, and a location health view for finding records that need attention.</li>
                    <li><strong>Self-repairing core integration:</strong> Core changes are applied as managed shims with a health check and an admin banner after Rukovoditel core updates.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
