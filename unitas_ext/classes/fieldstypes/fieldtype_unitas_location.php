<?php
/**
 * fieldtype_unitas_location
 *
 * Address-driven location field. Binds to one plain text address field, shows
 * autocomplete suggestions in the form, and geocodes server-side on save using
 * the IP-restricted server key. Stores a 4-part tab value:
 *
 *     {lat}\t{lng}\t{address}\t{status}
 *
 * No per-field API key. See plan sections 5.3, 7.8, 7.10.
 *
 * @package UNITAS Extension
 */
require_once __DIR__ . '/../location/unitas_location_value.php';
require_once __DIR__ . '/../google/unitas_google_keys.php';
require_once __DIR__ . '/../google/unitas_geocoder.php';
require_once __DIR__ . '/../google/unitas_google_loader.php';

class fieldtype_unitas_location
{
    public $options;

    /** True once the field CSS link has been emitted this request. */
    private static $css_emitted = false;

    function __construct()
    {
        $this->options = array('title' => 'Location (Google)');
    }

    /** Emit the field stylesheet at most once per request. */
    private static function css_once()
    {
        if (self::$css_emitted) return '';
        self::$css_emitted = true;
        return '<link rel="stylesheet" href="plugins/unitas_ext/css/unitas_location.css?v=' . rawurlencode(PLUGIN_UNITAS_EXT_VERSION) . '">';
    }

    function get_configuration()
    {
        // Same-entity text fields only (core field types read $_POST['entities_id']).
        $entities_id = (int)($_POST['entities_id'] ?? 0);
        $src_choices = array('' => '');
        if ($entities_id > 0) {
            $q = db_query("select id, name from app_fields where entities_id = " . $entities_id . " and type = 'fieldtype_input' order by name");
            while ($r = db_fetch_array($q)) {
                $src_choices[$r['id']] = $r['name'];
            }
        }

        $yesno = array('yes' => 'Yes', 'no' => 'No');

        $zoom_choices = array();
        for ($i = 3; $i <= 20; $i++) $zoom_choices[$i] = $i;

        $cfg = array();

        $cfg[] = array(
            'title' => 'Address Field', 'name' => 'source_field_id', 'type' => 'dropdown',
            'choices' => $src_choices, 'default' => '',
            'tooltip_icon' => 'The address text field this location is looked up from.',
            'params' => array('class' => 'form-control input-large')
        );

        $cfg[] = array(
            'title' => 'Address Autocomplete', 'name' => 'enable_autocomplete', 'type' => 'dropdown',
            'choices' => $yesno, 'default' => 'yes',
            'tooltip_icon' => 'Show Google Places suggestions on the address field.',
            'params' => array('class' => 'form-control input-small')
        );

        $cfg[] = array(
            'title' => 'Form Map Preview', 'name' => 'form_map_preview', 'type' => 'dropdown',
            'choices' => $yesno, 'default' => 'yes',
            'tooltip_icon' => 'Show a map with a draggable pin in the form.',
            'params' => array('class' => 'form-control input-small')
        );

        $cfg[] = array(
            'title' => 'Map Width', 'name' => 'map_width', 'type' => 'input',
            'default' => '100%', 'tooltip_icon' => 'e.g. 100%, 600px',
            'params' => array('class' => 'form-control input-small')
        );

        $cfg[] = array(
            'title' => 'Map Height', 'name' => 'map_height', 'type' => 'input',
            'default' => '300px', 'tooltip_icon' => 'e.g. 300px, 400px',
            'params' => array('class' => 'form-control input-small')
        );

        $cfg[] = array(
            'title' => 'Default Zoom', 'name' => 'zoom', 'type' => 'dropdown',
            'choices' => $zoom_choices, 'default' => 16,
            'params' => array('class' => 'form-control input-small')
        );

        return $cfg;
    }

    // ── Form rendering ───────────────────────────────────────────────────────
    function render($field, $obj, $params = array())
    {
        $fid   = (int)$field['id'];
        $cfg   = new fields_types_cfg($field['configuration']);
        $raw   = isset($obj['field_' . $fid]) ? $obj['field_' . $fid] : '';
        $parsed = unitas_location_value::parse($raw);

        $map_cfg = unitas_map_config::get();
        $api_key = unitas_google_keys::browser();

        $src_id  = (int)$cfg->get('source_field_id');
        $preview = ($cfg->get('form_map_preview') !== 'no') && $api_key !== '';

        $w = $cfg->get('map_width') ?: '100%';
        $h = $cfg->get('map_height') ?: '300px';
        if (!strstr($w, '%') && !strstr($w, 'px')) $w .= 'px';
        if (!strstr($h, '%') && !strstr($h, 'px')) $h .= 'px';

        // Unique per-render instance id: the same field can be rendered several
        // times on one page (info side panel + info modal + a stacked edit
        // modal), and modal containers can sit EARLIER in the DOM than page
        // content, so neither "first" nor "last" element with a given id is
        // reliable. The JS binds to the elements carrying THIS uid.
        $uid = 'u' . substr(md5(uniqid((string)$fid, true)), 0, 10);

        // Hidden input carries the stored value. No 'required' class: coordinates
        // cannot be guaranteed at submit time even when the admin marks required.
        $html = self::css_once();
        $html .= '<input type="hidden" name="fields[' . $fid . ']" id="fields_' . $fid . '" value="'
              . htmlspecialchars($raw, ENT_QUOTES) . '">';

        $html .= '<div id="unitas_loc_status_' . $uid . '" class="unitas-loc-status" aria-live="polite">'
              . htmlspecialchars(self::initial_status_text($parsed['status']))
              . '</div>';

        if ($preview) {
            $html .= '<div id="unitas_loc_map_' . $uid . '" class="unitas-loc-map" style="width:' . $w . ';height:' . $h . ';"></div>';
        } elseif ($api_key === '') {
            $html .= '<em class="text-muted">Map preview unavailable (no browser key).</em>';
        } else {
            $src_name = '';
            if ($src_id > 0) {
                $sf = db_find('app_fields', $src_id);
                $src_name = isset($sf['name']) ? $sf['name'] : ('#' . $src_id);
            }
            $html .= '<em class="text-muted">Location is looked up from ' . htmlspecialchars($src_name) . ' when you save.</em>';
        }

        if ($api_key === '') {
            return $html; // no key: field still stores its value, just no map/JS
        }

        $html .= unitas_google_loader::emit();

        // Self-emit autocomplete on the bound source field, so it works inside
        // AJAX modal forms where the page-level injection is skipped (plan 7.7).
        // The direct attach mirrors how the Extension smart input wires its
        // autocomplete: an inline per-field script emitted with the form HTML
        // that targets #fields_{id} directly (no delegation dependency); the
        // retry loop covers the widget script still loading in modal contexts.
        if ($src_id > 0 && $cfg->get('enable_autocomplete') !== 'no') {
            $sf = db_find('app_fields', $src_id);
            if (isset($sf['type']) && $sf['type'] === 'fieldtype_input') {
                require_once __DIR__ . '/../location/unitas_address_autocomplete_rules.php';
                $html .= unitas_address_autocomplete_rules::emit_assets(array($src_id));
                // Attach to EVERY element with this id (a modal form over a
                // listing can duplicate fields_{id}; getElementById would pick
                // the wrong one), and never give up silently.
                $html .= '<script>'
                       . '(function(){var tries=0;'
                       . 'function a(){'
                       . 'var els=document.querySelectorAll(\'[id="fields_' . (int)$src_id . '"]\');'
                       . 'if(els.length&&window.UnitasAddressAutocomplete){'
                       . 'for(var i=0;i<els.length;i++){window.UnitasAddressAutocomplete.attach(els[i]);}'
                       . 'return;'
                       . '}'
                       . 'if(++tries<50){setTimeout(a,100);}'
                       . 'else{try{console.warn("[unitas-autocomplete] attach gave up for fields_' . (int)$src_id . ': input "+(els.length?"found":"MISSING")+", widget "+(window.UnitasAddressAutocomplete?"loaded":"MISSING"));}catch(e){}}'
                       . '}'
                       . 'a();'
                       . '})();'
                       . '</script>';
            }
        }

        $html .= self::map_widget_js($uid, $fid, $parsed, array(
            'src_id'   => $src_id,
            'is_form'  => true,
            'can_edit' => true,
            'path'     => '',
            'pin_url'  => '',
            'zoom'     => (int)($cfg->get('zoom') ?: 16),
            'map_id'   => self::resolve_map_id($map_cfg),
            'center'   => self::default_center($map_cfg),
            'api_url'  => self::classic_api_url($api_key, $map_cfg),
        ));

        return $html;
    }

    /**
     * Classic Maps JS URL, built exactly like the geometry field's (same
     * callback and shared load flags, so the two coordinate).
     */
    private static function classic_api_url($api_key, $map_cfg)
    {
        $map_ids_param = trim(($map_cfg['map_style_light'] ?? '') . ',' . ($map_cfg['map_style_dark'] ?? ''), ',');
        return 'https://maps.googleapis.com/maps/api/js?key=' . $api_key
             . ($map_ids_param ? '&map_ids=' . $map_ids_param : '')
             . '&callback=_unitasGeoApiReady';
    }

    /** Initial status line text for a stored status (form status div). */
    private static function initial_status_text($status)
    {
        $s = self::status_strings();
        switch ($status) {
            case 'approximate': return $s['approximate'];
            case 'not_found':   return $s['notFound'];
            case 'error':       return $s['error'];
            case 'manual':      return $s['manual'];
            default:            return '';
        }
    }

    /**
     * Inline, self-contained map widget for one rendered instance - the same
     * methodology as the geometry field (which is proven in every context this
     * app has): synchronous classic constructors, unconditional execution, no
     * shared state between instances or opens, the shared _unitasGeoApi* load
     * flags, and the readyState/window.load wrapper. Element ids carry the
     * per-render $uid, so any number of copies of the field coexist on a page.
     */
    private static function map_widget_js($uid, $fid, $parsed, array $o)
    {
        $C = json_encode(array(
            'uid'     => $uid,
            'fieldId' => $fid,
            'srcId'   => (int)$o['src_id'],
            'lat'     => $parsed['lat'],
            'lng'     => $parsed['lng'],
            'isForm'  => (bool)$o['is_form'],
            'canEdit' => (bool)$o['can_edit'],
            'path'    => (string)$o['path'],
            'pinUrl'  => (string)$o['pin_url'],
            'zoom'    => (int)$o['zoom'],
            'mapId'   => (string)$o['map_id'],
            'center'  => $o['center'],
            'apiUrl'  => (string)$o['api_url'],
            'strings' => self::status_strings(),
        ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        return '<script>'
            . '(function(){'
            . 'var C=' . $C . ';'
            . 'var map=null,marker=null;'
            . 'function el(x){return document.getElementById(x);}'
            . 'function statusMsg(t){var s=el("unitas_loc_status_"+C.uid);if(s){s.textContent=t||"";}}'
            . 'function normalize(t){t=(t==null?"":String(t)).replace(/<[^>]*>/g," ").replace(/[\t\r\n]+/g," ").replace(/  +/g," ").trim();return t.length>500?t.slice(0,500):t;}'
            . 'function cs(v){return String(Math.round(v*1e7)/1e7);}'
            . 'function fmt(lat,lng,addr,st){var a=normalize(addr);var hp=(lat!=null&&lng!=null);if(!hp&&a===""&&st==="")return "";return (hp?cs(lat):"")+"\t"+(hp?cs(lng):"")+"\t"+a+"\t"+st;}'
            // Form inputs resolved inside THIS instance own form, never by page-wide id.
            . 'function formEl(){var s=el("unitas_loc_status_"+C.uid)||el("unitas_loc_map_"+C.uid);return (s&&s.closest)?s.closest("form"):null;}'
            . 'function hiddenEl(){var f=formEl();return f?f.querySelector(\'[name="fields[\'+C.fieldId+\']"]\'):null;}'
            . 'function sourceEl(){if(!C.srcId)return null;var f=formEl();var e=f?f.querySelector(\'[id="fields_\'+C.srcId+\'"]\'):null;return e||document.getElementById("fields_"+C.srcId);}'
            . 'function place(lat,lng,center){if(!map)return;var p={lat:Number(lat),lng:Number(lng)};'
            . 'if(marker){marker.setPosition(p);}'
            . 'else{marker=new google.maps.Marker({map:map,position:p,draggable:!!C.canEdit});'
            . 'if(C.canEdit){marker.addListener("dragend",function(){var q=marker.getPosition();manual(q.lat(),q.lng());});}}'
            . 'if(center){map.setCenter(p);}}'
            . 'function manual(lat,lng){'
            . 'if(C.isForm){var s=sourceEl();var addr=normalize(s?s.value:"");if(!addr){return;}'
            . 'var h=hiddenEl();if(h){h.value=fmt(lat,lng,addr,"manual");}'
            . 'statusMsg(C.strings.manual);place(lat,lng,false);return;}'
            . 'var prev=marker?marker.getPosition():null;'
            . 'var b=new URLSearchParams();b.set("path",C.path);b.set("field_id",C.fieldId);b.set("lat",lat);b.set("lng",lng);'
            . 'fetch(C.pinUrl,{method:"POST",headers:{"X-Requested-With":"XMLHttpRequest"},body:b})'
            . '.then(function(r){return r.json().catch(function(){return {ok:false};});})'
            . '.then(function(d){if(d&&d.ok){place(lat,lng,false);statusMsg(C.strings.manual);}'
            . 'else{if(prev){place(prev.lat(),prev.lng(),false);}statusMsg((d&&d.error)?d.error:"Could not save pin.");}})'
            . '.catch(function(){if(prev){place(prev.lat(),prev.lng(),false);}statusMsg("Could not save pin.");});}'
            . 'function wireSource(){if(!C.isForm)return;var s=sourceEl();if(!s)return;'
            . 'if(s.getAttribute("data-unitas-loc-"+C.uid)==="1")return;s.setAttribute("data-unitas-loc-"+C.uid,"1");'
            . 's.addEventListener("unitas:address-selected",function(e){var d=e.detail||{};var h=hiddenEl();'
            . 'if(h){h.value=fmt(d.lat,d.lng,d.address,"autocomplete");}statusMsg(C.strings.selected);'
            . 'if(d.lat!=null){place(d.lat,d.lng,true);}});'
            . 's.addEventListener("unitas:address-edited",function(){var h=hiddenEl();if(h){h.value="";}statusMsg(C.strings.willLookup);});}'
            . 'function draw(){var m=el("unitas_loc_map_"+C.uid);'
            . 'if(m){var o={zoom:C.zoom,center:(C.lat!=null?{lat:C.lat,lng:C.lng}:C.center)};'
            . 'if(C.mapId){o.mapId=C.mapId;}else{o.mapTypeId="roadmap";}'
            . 'map=new google.maps.Map(m,o);'
            . 'if(C.lat!=null){place(C.lat,C.lng,false);}'
            . 'if(C.canEdit){map.addListener("click",function(ev){manual(ev.latLng.lat(),ev.latLng.lng());});}}'
            . 'wireSource();}'
            // Identical load choreography to the geometry field.
            . 'function init(){'
            . 'if(window.google&&google.maps&&google.maps.Map){draw()}'
            . 'else{'
            . 'window._unitasGeoQueue=window._unitasGeoQueue||[];'
            . 'window._unitasGeoQueue.push(draw);'
            . 'if(!window._unitasGeoApiLoading){'
            . 'window._unitasGeoApiLoading=true;'
            . 'window._unitasGeoApiReady=function(){window._unitasGeoApiLoaded=true;(window._unitasGeoQueue||[]).forEach(function(fn){fn()});window._unitasGeoQueue=[]};'
            . 'var sc=document.createElement("script");sc.src=' . json_encode((string)$o['api_url']) . ';sc.async=true;document.head.appendChild(sc);'
            . '}}}'
            . 'if(document.readyState==="complete"){init()}else{window.addEventListener("load",init)}'
            . '})();'
            . '</script>';
    }

    // ── Save-time processing (client hint only) ──────────────────────────────
    function process($options)
    {
        $posted  = trim((string)($options['value'] ?? ''));
        $current = (string)($options['current_field_value'] ?? '');

        if ($posted === '') return $current;               // no client hint

        $p = unitas_location_value::parse($posted);

        // The client may only assert 'autocomplete' or 'manual', with valid
        // coordinates and a non-empty address. Everything else the hook decides.
        if (!in_array($p['status'], unitas_location_value::CLIENT_STATUSES, true)) return $current;
        if (!unitas_location_value::is_valid_point($p['lat'], $p['lng']))         return $current;
        if ($p['address'] === '')                                                 return $current;

        return unitas_location_value::format($p['lat'], $p['lng'], $p['address'], $p['status']);
    }

    // ── Output contexts ──────────────────────────────────────────────────────
    function output($options)
    {
        $raw = (string)($options['value'] ?? '');
        $p   = unitas_location_value::parse($raw);

        if (isset($options['is_export'])) {
            return self::plain_summary($p);
        }
        if (isset($options['is_email'])) {
            return htmlspecialchars(self::plain_summary($p));
        }
        if (isset($options['is_listing'])) {
            return self::listing_indicator($p);
        }

        // Default: item page. Read-only map + status banner (interactive drag and
        // the pin endpoint are added in the next increment).
        return self::render_item_page($options, $p);
    }

    private static function plain_summary($p)
    {
        if ($p['lat'] !== null) {
            return $p['address'] . ' (' . $p['lat'] . ', ' . $p['lng'] . ')';
        }
        if ($p['status'] === 'not_found') {
            return $p['address'] . ' (location not found)';
        }
        return $p['address'];
    }

    private static function listing_indicator($p)
    {
        switch ($p['status']) {
            case 'approximate':
                return '<i class="fa fa-exclamation-triangle text-warning" title="Approximate location — verify the pin"></i>';
            case 'not_found':
                return '<i class="fa fa-exclamation-triangle text-warning" title="Location not found"></i>';
            case 'error':
                return '<i class="fa fa-exclamation-triangle text-danger" title="Location lookup failed"></i>';
            default:
                return ''; // autocomplete / geocoded / manual / empty: nothing, like core
        }
    }

    private static function render_item_page($options, $p)
    {
        $field = isset($options['field']) ? $options['field'] : array();
        $fid   = (int)(isset($field['id']) ? $field['id'] : 0);
        $api_key = unitas_google_keys::browser();

        $html = self::css_once();

        // Admin-only warning if the save hook shim is missing (geocoding is off).
        global $app_user;
        if (isset($app_user['group_id']) && (int)$app_user['group_id'] === 0
            && class_exists('unitas_ext_installer')) {
            $health = unitas_ext_installer::shim_health();
            if (empty($health['s2'])) {
                $html .= '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> '
                       . 'UNITAS core save hook is missing; addresses are not being geocoded. Open UNITAS Extension &gt; Install to repair.</div>';
            }
        }

        $html .= self::status_banner($p);

        if ($p['lat'] === null || $api_key === '' || $fid === 0) {
            return $html; // nothing to map
        }

        $cfg     = new fields_types_cfg($field['configuration']);
        $map_cfg = unitas_map_config::get();
        $w = $cfg->get('map_width') ?: '100%';
        $h = $cfg->get('map_height') ?: '300px';
        if (!strstr($w, '%') && !strstr($w, 'px')) $w .= 'px';
        if (!strstr($h, '%') && !strstr($h, 'px')) $h .= 'px';

        // Interactive drag/click is enabled only on the item page for this
        // record when the viewer has update access. The pin endpoint re-checks
        // everything, so this only governs UX; it never grants access.
        global $current_entity_id, $current_item_id, $current_path;
        $field_entity = (int)(isset($field['entities_id']) ? $field['entities_id'] : 0);
        $can_edit = false;
        $path = '';
        if (isset($current_entity_id) && (int)$current_entity_id === $field_entity && !empty($current_item_id)) {
            $path = (isset($current_path) && $current_path) ? $current_path : ($current_entity_id . '-' . $current_item_id);
            $can_edit = users::has_access('update');
        }

        // Same per-instance uid scheme as render(): the info panel, info modal
        // and stacked modals can each render this output on one page, and each
        // instance gets its own elements and its own inline widget.
        $uid = 'u' . substr(md5(uniqid((string)$fid, true)), 0, 10);

        $html .= '<div id="unitas_loc_status_' . $uid . '" class="unitas-loc-status" aria-live="polite"></div>';
        $html .= '<div id="unitas_loc_map_' . $uid . '" class="unitas-loc-map" style="width:' . $w . ';height:' . $h . ';"></div>';
        $html .= self::map_widget_js($uid, $fid, $p, array(
            'src_id'   => 0,
            'is_form'  => false,
            'can_edit' => $can_edit,
            'path'     => $path,
            'pin_url'  => function_exists('url_for') ? url_for('unitas_ext/location/pin', 'action=save') : '',
            'zoom'     => (int)($cfg->get('zoom') ?: 16),
            'map_id'   => self::resolve_map_id($map_cfg),
            'center'   => self::default_center($map_cfg),
            'api_url'  => self::classic_api_url($api_key, $map_cfg),
        ));

        return $html;
    }

    private static function status_banner($p)
    {
        switch ($p['status']) {
            case 'approximate':
                return '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Approximate location. Google matched only part of this address. Verify the pin.</div>';
            case 'not_found':
                return '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Location not found. This record will not appear on maps.</div>';
            case 'error':
                return '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> Location lookup failed. This record will not appear on maps.</div>';
            case 'manual':
                return '<div class="text-muted"><i class="fa fa-thumb-tack"></i> Pin placed manually.</div>';
            default:
                return '';
        }
    }

    // ── Save hook (all save paths, via shim S2) ──────────────────────────────
    public static function update_items_fields($entities_id, $items_id, $item_info = false)
    {
        global $app_fields_cache;

        $entities_id = (int)$entities_id;
        $items_id    = (int)$items_id;
        if (empty($app_fields_cache[$entities_id])) return;

        $our_fields = array();
        foreach ($app_fields_cache[$entities_id] as $f) {
            if (isset($f['type']) && $f['type'] === 'fieldtype_unitas_location') $our_fields[] = $f;
        }
        if (!$our_fields) return; // no cost on unrelated entities

        // Always read the freshly-saved row so every save path (form, REST,
        // import, recurring, bulk) geocodes the address that was just written.
        $q = db_query("select * from app_entity_{$entities_id} where id = " . $items_id);
        $item_info = db_fetch_array($q);
        if (!$item_info) return;

        foreach ($our_fields as $f) {
            self::process_record($entities_id, $items_id, $f, $item_info);
        }
    }

    /**
     * Per-record decision + write. Shared by the save hook and the Phase 5
     * re-geocode batch tool. Returns the resulting status code.
     *
     * @param string[] $retry_statuses settled statuses the caller wants looked
     *        up again even though the stored address matches the source (the
     *        re-geocode tool passes not_found / approximate when opted in)
     */
    public static function process_record($entities_id, $items_id, $field, $item_info, $retry_statuses = array())
    {
        $cfg    = new fields_types_cfg($field['configuration']);
        $src_id = (int)$cfg->get('source_field_id');
        if ($src_id <= 0) return null;

        $fid    = (int)$field['id'];
        $source = unitas_location_value::normalize_address(isset($item_info['field_' . $src_id]) ? $item_info['field_' . $src_id] : '');
        $stored = unitas_location_value::parse(isset($item_info['field_' . $fid]) ? $item_info['field_' . $fid] : '');

        if ($source === '') {
            if ((string)(isset($item_info['field_' . $fid]) ? $item_info['field_' . $fid] : '') !== '') {
                self::store($entities_id, $items_id, $fid, '');
            }
            return null;
        }

        // No lookup when the stored address already matches the source and the
        // status is settled (not_found is retried only when the address changes,
        // or when the caller explicitly asks via $retry_statuses).
        $settled = array('autocomplete', 'geocoded', 'approximate', 'manual', 'not_found');
        if ($stored['address'] === $source && in_array($stored['status'], $settled, true)
            && !in_array($stored['status'], $retry_statuses, true)) {
            return $stored['status'];
        }

        $result = unitas_geocoder::lookup($source);
        $value  = unitas_location_value::format($result['lat'], $result['lng'], $source, $result['status']);
        self::store($entities_id, $items_id, $fid, $value);
        return $result['status'];
    }

    private static function store($entities_id, $items_id, $fid, $value)
    {
        db_query("update app_entity_" . (int)$entities_id . " set field_" . (int)$fid .
                 " = '" . db_input($value) . "' where id = '" . db_input((int)$items_id) . "'");
    }

    // ── Shared helpers ───────────────────────────────────────────────────────
    private static function resolve_map_id($map_cfg)
    {
        $theme = isset($map_cfg['default_theme']) ? $map_cfg['default_theme'] : 'auto';
        if ($theme === 'light') return (string)($map_cfg['map_style_light'] ?? '');
        if ($theme === 'dark')  return (string)($map_cfg['map_style_dark'] ?? '');
        $hour = (int)date('H');
        return ($hour >= 18 || $hour <= 6)
            ? (string)($map_cfg['map_style_dark'] ?? '')
            : (string)($map_cfg['map_style_light'] ?? '');
    }

    private static function default_center($map_cfg)
    {
        if (is_numeric($map_cfg['default_lat'] ?? null) && is_numeric($map_cfg['default_lng'] ?? null)) {
            return array('lat' => (float)$map_cfg['default_lat'], 'lng' => (float)$map_cfg['default_lng']);
        }
        return array('lat' => 35.7596, 'lng' => -79.0193);
    }

    private static function status_strings()
    {
        return array(
            'selected'  => 'Location set from address suggestion.',
            'willLookup'=> 'Location will be looked up when you save.',
            'manual'    => 'Pin placed manually.',
            'approximate'=> 'Approximate location. Verify the pin.',
            'notFound'  => 'Location not found. Check the address, or click the map to place the pin.',
            'error'     => 'Location lookup failed. Save again to retry.',
        );
    }
}
