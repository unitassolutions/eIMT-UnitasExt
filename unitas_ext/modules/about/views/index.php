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
                        <td>Custom field type for drawing polylines, polygons, and circles on Google Maps. Stores Waze-compatible geometry JSON with distance, area, or radius, and renders on map and pivot map reports.</td>
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
                    <li><strong>Geometry on map reports:</strong> Drawn closures now render on map reports and pivot map reports — the actual polyline, polygon, or circle is drawn on the map.</li>
                    <li><strong>Status color-coding:</strong> Shape color follows the report Background Color field, so closures are colored by their road status.</li>
                    <li><strong>Marker companion:</strong> Each shape also places a marker at its midpoint, keeping clustering, the sidebar list, and popups working and closures visible when zoomed out.</li>
                    <li><strong>Mixed pivot layers:</strong> One pivot map can combine marker entities (shelters, resources) with geometry entities (road closures).</li>
                    <li><strong>Popup fix:</strong> Geometry fields added to report popups now show the readable distance summary instead of an encoded polyline.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
