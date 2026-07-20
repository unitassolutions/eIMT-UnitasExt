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
                        <td>Multi-entity map reports with custom markers, legends, filter panels, and sidebar support.</td>
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
                        <td>Custom field type for drawing polylines, polygons, and circles on Google Maps. Stores Waze-compatible geometry JSON with distance, area, or radius.</td>
                    </tr>
                    <tr>
                        <td><strong>Waze Integration</strong></td>
                        <td>Reverse-geocoding autofill of road and cross-street names when a closure is drawn on a geometry field, plus a keyed CIFS feed that publishes active closures to the Waze app.</td>
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
                    <li><strong>Waze Closure Feed:</strong> Live CIFS feed endpoint publishes active road closures (Push to Waze checked, status Closed, drawn polyline) directly to the Waze app.</li>
                    <li><strong>Reliable clearing:</strong> Every feed response uses a rolling expiry window, so reopened closures drop off Waze within minutes of being delisted.</li>
                    <li><strong>Hardened feed URL:</strong> 128-bit secret key, blank 404 for any request without it, no search-engine indexing, one-click key regeneration.</li>
                    <li><strong>Feed mapping UI:</strong> The Waze Integration page maps the Road Closures entity, its fields, the Closed status choice, direction, and Reason-to-subtype conversions.</li>
                    <li><strong>Feed content:</strong> Actual closure start times, road-following polylines from the geometry field, direction, closure subtype, and description.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
