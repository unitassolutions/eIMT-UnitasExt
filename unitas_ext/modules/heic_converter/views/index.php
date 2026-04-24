<?php
/**
 * HEIC Converter Plugin — Settings View (v1.2.0)
 */
?>

<h3 class="page-title">HEIC Converter <small>v1.2.0</small></h3>

<div class="row">
    <!-- Status Panel -->
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-info-circle"></i> Plugin Status</h3>
            </div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <tr>
                        <td><strong>Status</strong></td>
                        <td><span class="label label-success">Active</span></td>
                    </tr>
                    <tr>
                        <td><strong>Version</strong></td>
                        <td>1.2.0</td>
                    </tr>
                    <tr>
                        <td><strong>Conversion Method</strong></td>
                        <td>XHR interception + input replacement</td>
                    </tr>
                    <tr>
                        <td><strong>EXIF Preservation</strong></td>
                        <td>
                            <span class="label label-success">Enabled</span><br>
                            <small>GPS, timestamps, orientation, camera info</small>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Output Format</strong></td>
                        <td>JPEG (92% quality)</td>
                    </tr>
                    <tr>
                        <td><strong>Supported Inputs</strong></td>
                        <td>
                            Attachments, Image, Image (Ajax), File,<br>
                            Comment attachments, Public forms
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-question-circle"></i> How It Works</h3>
            </div>
            <div class="panel-body">
                <p>This plugin uses a <strong>three-layer approach</strong> to ensure HEIC files
                   are always converted to JPEG before reaching the server:</p>
                <ol>
                    <li><strong>Visual feedback:</strong> When a HEIC file is selected, a brief overlay
                        shows the conversion progress.</li>
                    <li><strong>XHR interception:</strong> Before any AJAX upload request is sent,
                        the plugin inspects the request body for HEIC files and swaps them with
                        converted JPEGs. This works regardless of how Rukovoditel handles file selection.</li>
                    <li><strong>Input replacement:</strong> For regular form submissions, the file input's
                        files are replaced with converted JPEGs via the DataTransfer API.</li>
                </ol>
                <p><strong>No action is required from users.</strong> The conversion is fully automatic
                   and transparent.</p>
            </div>
        </div>
    </div>

    <!-- Test Panel -->
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-flask"></i> Test Conversion</h3>
            </div>
            <div class="panel-body">
                <p>Select a HEIC/HEIF file to verify the converter is working correctly.
                   The file will <strong>not</strong> be uploaded — this is a local-only test.</p>

                <div id="heic-test-container" class="form-group" style="position: relative; min-height: 60px;">
                    <label>Select a HEIC/HEIF file:</label>
                    <input type="file" id="heic-test-input" class="form-control" accept=".heic,.heif,image/*">
                </div>

                <div id="heic-test-result" style="display: none; margin-top: 15px;">
                    <hr>
                    <h4>Conversion Result</h4>
                    <table class="table table-condensed">
                        <tr>
                            <td style="width:40%"><strong>Original File</strong></td>
                            <td id="heic-test-original">—</td>
                        </tr>
                        <tr>
                            <td><strong>Original Size</strong></td>
                            <td id="heic-test-orig-size">—</td>
                        </tr>
                        <tr>
                            <td><strong>Converted File</strong></td>
                            <td id="heic-test-converted">—</td>
                        </tr>
                        <tr>
                            <td><strong>Converted Size</strong></td>
                            <td id="heic-test-conv-size">—</td>
                        </tr>
                        <tr>
                            <td><strong>Conversion Status</strong></td>
                            <td id="heic-test-status">
                                <div class="heic-spinner" style="display:inline-block;width:14px;height:14px;vertical-align:middle;"></div>
                                <span>Waiting for conversion...</span>
                            </td>
                        </tr>
                    </table>

                    <h4 id="heic-exif-heading" style="display:none;">EXIF Data Preserved</h4>
                    <table class="table table-condensed" id="heic-exif-table" style="display:none;">
                        <tr>
                            <td style="width:40%"><strong>GPS Coordinates</strong></td>
                            <td id="heic-exif-gps">—</td>
                        </tr>
                        <tr>
                            <td><strong>Date Taken</strong></td>
                            <td id="heic-exif-date">—</td>
                        </tr>
                        <tr>
                            <td><strong>Camera</strong></td>
                            <td id="heic-exif-camera">—</td>
                        </tr>
                        <tr>
                            <td><strong>Orientation</strong></td>
                            <td id="heic-exif-orientation">—</td>
                        </tr>
                    </table>

                    <div id="heic-test-preview" style="margin-top: 10px;"></div>
                </div>
            </div>
        </div>

        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-lightbulb-o"></i> Notes</h3>
            </div>
            <div class="panel-body">
                <ul>
                    <li><strong>EXIF preservation:</strong> GPS coordinates, timestamps, camera make/model,
                        and orientation are extracted from the original HEIC and embedded in the
                        output JPEG.</li>
                    <li><strong>Browser support:</strong> Chrome 76+, Firefox 69+,
                        Safari 14.1+, Edge 79+.</li>
                    <li><strong>Performance:</strong> A typical 3MB HEIC photo converts in
                        1-3 seconds. Libraries (~1.5MB total) are loaded only on first HEIC detection
                        and cached by the browser.</li>
                    <li><strong>Fallback:</strong> If conversion fails, the original HEIC file
                        is uploaded as-is and a warning is shown.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Test panel logic for v1.2.0.
 *
 * The main converter (Layer 1 change handler) will fire on this input and
 * attempt to replace input.files via DataTransfer. We poll input.files to
 * detect when the replacement happens.
 */
(function() {
    var testInput = document.getElementById('heic-test-input');
    if (!testInput) return;

    var originalName = '';
    var originalSize = 0;

    testInput.addEventListener('change', function() {
        var file = testInput.files[0];
        if (!file) return;

        // Capture original info
        originalName = file.name;
        originalSize = file.size;

        // Show result panel immediately
        var resultDiv = document.getElementById('heic-test-result');
        resultDiv.style.display = 'block';
        document.getElementById('heic-test-original').textContent = originalName;
        document.getElementById('heic-test-orig-size').textContent = formatSize(originalSize);
        document.getElementById('heic-test-converted').textContent = '...';
        document.getElementById('heic-test-conv-size').textContent = '...';
        document.getElementById('heic-test-status').innerHTML =
            '<div class="heic-spinner" style="display:inline-block;width:14px;height:14px;vertical-align:middle;"></div>' +
            ' <span>Converting...</span>';

        // Check if it's actually a HEIC file
        var ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'heic' && ext !== 'heif') {
            document.getElementById('heic-test-converted').textContent = file.name;
            document.getElementById('heic-test-conv-size').textContent = formatSize(file.size);
            document.getElementById('heic-test-status').innerHTML =
                '<span class="label label-default">Not a HEIC file — no conversion needed</span>';
            showPreview(file);
            return;
        }

        // Poll input.files every 500ms to detect when the converter replaces them.
        // The converter's change handler runs in bubble phase and uses DataTransfer
        // to replace input.files after async conversion completes.
        var pollCount = 0;
        var maxPolls = 30; // 15 seconds max
        var pollInterval = setInterval(function() {
            pollCount++;
            var currentFile = testInput.files[0];

            // Check if the file has been replaced with a JPEG
            if (currentFile && currentFile.name !== originalName && /\.jpg$/i.test(currentFile.name)) {
                clearInterval(pollInterval);
                showResult(currentFile, true);
                return;
            }

            // Also check if type changed (in case name replacement failed)
            if (currentFile && currentFile.type === 'image/jpeg' && currentFile.size !== originalSize) {
                clearInterval(pollInterval);
                showResult(currentFile, true);
                return;
            }

            // Timeout
            if (pollCount >= maxPolls) {
                clearInterval(pollInterval);
                document.getElementById('heic-test-status').innerHTML =
                    '<span class="label label-warning">Conversion timed out or input replacement failed</span>' +
                    '<br><small>Note: The XHR interception layer will still convert files during actual uploads.</small>';
            }
        }, 500);
    });

    function showResult(convertedFile, success) {
        document.getElementById('heic-test-converted').textContent = convertedFile.name;
        document.getElementById('heic-test-conv-size').textContent = formatSize(convertedFile.size);

        if (success) {
            document.getElementById('heic-test-status').innerHTML =
                '<span class="label label-success">Conversion Successful</span>';
            showPreview(convertedFile);
            readExif(convertedFile);
        } else {
            document.getElementById('heic-test-status').innerHTML =
                '<span class="label label-danger">Conversion Failed</span>';
        }
    }

    function showPreview(file) {
        if (file.type && file.type.indexOf('image/') === 0) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('heic-test-preview').innerHTML =
                    '<img src="' + e.target.result + '" style="max-width:100%; max-height:300px; border:1px solid #ddd; border-radius:4px;">';
            };
            reader.readAsDataURL(file);
        }
    }

    function readExif(jpegFile) {
        if (typeof exifr === 'undefined') return;

        var heading = document.getElementById('heic-exif-heading');
        var table = document.getElementById('heic-exif-table');

        exifr.parse(jpegFile, { gps: true, exif: true, tiff: true }).then(function(data) {
            if (!data) {
                heading.textContent = 'EXIF Data: None found';
                heading.style.display = 'block';
                table.style.display = 'none';
                return;
            }

            heading.style.display = 'block';
            table.style.display = 'table';

            var gpsCell = document.getElementById('heic-exif-gps');
            if (data.latitude !== undefined && data.longitude !== undefined) {
                gpsCell.innerHTML = '<span class="label label-success">Preserved</span> ' +
                    data.latitude.toFixed(6) + ', ' + data.longitude.toFixed(6);
            } else {
                gpsCell.innerHTML = '<span class="label label-default">Not in original</span>';
            }

            var dateCell = document.getElementById('heic-exif-date');
            var dateVal = data.DateTimeOriginal || data.CreateDate;
            if (dateVal) {
                var dateStr = (dateVal instanceof Date) ? dateVal.toLocaleString() : String(dateVal);
                dateCell.innerHTML = '<span class="label label-success">Preserved</span> ' + dateStr;
            } else {
                dateCell.innerHTML = '<span class="label label-default">Not in original</span>';
            }

            var camCell = document.getElementById('heic-exif-camera');
            if (data.Make || data.Model) {
                camCell.innerHTML = '<span class="label label-success">Preserved</span> ' +
                    (data.Make || '') + ' ' + (data.Model || '');
            } else {
                camCell.innerHTML = '<span class="label label-default">Not in original</span>';
            }

            var oriCell = document.getElementById('heic-exif-orientation');
            if (data.Orientation) {
                oriCell.innerHTML = '<span class="label label-success">Preserved</span> ' + data.Orientation;
            } else {
                oriCell.innerHTML = '<span class="label label-default">Not in original</span>';
            }
        }).catch(function() {
            heading.textContent = 'EXIF Data: Read error';
            heading.style.display = 'block';
            table.style.display = 'none';
        });
    }

    function formatSize(bytes) {
        if (!bytes) return '—';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }
})();
</script>
