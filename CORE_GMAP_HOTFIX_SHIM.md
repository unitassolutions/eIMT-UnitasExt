# Core Google Map Endpoint Hotfix — Shim Design (DRAFT, for review)

> **Status:** DRAFT. Nothing here is wired in. This is a design sketch to review
> before implementation. Do not ship without the review + testing in section 7.

## 0. What this fixes and why it is separate from the migration

Upstream issue: **unitassolutions/Rukovoditel#1** — the core
`modules/items/actions/google_map.php` coordinate-update endpoints
(`update_latlng`, `update_latlng_multiple`, `update_latlng_directions`,
`save_value_in`) and the yandex equivalent:

1. **Broken access control** — the write is gated only by record *visibility*
   (`modules/items/module_top.php:74`, `records_visibility::add_access_query`);
   no *update* access is checked server-side. The field type computes
   `$has_update_access` (`includes/classes/fieldstypes/fieldtype_google_map.php:112`)
   but uses it only to set the marker `draggable` (line 138) — client-side only.
2. **Unvalidated input + unescaped output** — the endpoint stores
   `$_POST['lat']`/`$_POST['lng']` verbatim (`google_map.php:49`, no numeric
   cast), and the renderer echoes coordinates (line 130) and
   `urldecode($current_address)` (line 145) into `<script>`/`setContent`
   unescaped → stored XSS.
3. **Audit bypass** — the direct `db_query("update …")` does not run the normal
   save path's change logging (`modules/items/actions/items.php:264`,
   `new track_changes(...)`), so coordinate edits leave no history.

**Relationship to the shipped work (P12).** UNITAS-Ext already blocks this
endpoint with a hard 403 **once no core Google map fields remain**
(`unitas_ext/application_top.php:63-72`, `core_gmap_fields_present()`). The
permanent fix is to migrate every core Google map field to
`fieldtype_unitas_location` (UNITAS Extension > Location Tools), after which the
endpoint is dead and P12 blocks it.

**This hotfix is only for the window where core Google map fields still exist**
— an instance that has not migrated yet, or one that deliberately keeps some
core map fields. In that window P12 does **not** fire (the fields are still in
use), so the endpoint is live and vulnerable. The hotfix closes findings #1 and
#2-input for that window without waiting on migration.

## 1. Design goals and non-goals

**Goals**
- Enforce server-side **update** access on every guarded write.
- Reject non-numeric `lat`/`lng` before core stores them.
- **Zero core file edits** — no marker shim, no `shim_health()` surface, no
  re-application after a Rukovoditel core update.
- Fail safe and fail quiet: unauthorized or malformed request → bare 403, exit.
- No behavior change for legitimate, authorized drags.

**Non-goals (intentionally deferred to migration, not this hotfix)**
- Finding #2 *output* escaping of the **address** on render. Once `lat`/`lng`
  are forced numeric (goal above), the coordinate-render XSS is closed; the
  residual is the address echoed into the info window, which is only writable by
  a user who *has* update access (via the normal item form) — a
  privileged-user stored XSS that predates these endpoints. Fixing it requires
  altering the field type render (a core file → marker shim, section 5).
  Migrating the field to `fieldtype_unitas_location` eliminates it outright.
- Finding #3 change tracking. Enforcing access removes the *unauthorized* part;
  the remaining audit gap (authorized drags not logged) is core behavior.
  Logging it from the plugin means taking over the write (section 5), which is
  more surface than a hotfix should carry. Migration routes edits through the
  normal save path and closes this too.

## 2. Chosen approach — request interception in `application_top.php`

The plugin's `application_top.php` runs on every request during bootstrap,
before the core module action is dispatched — this is exactly how the existing
P12 block works (`application_top.php:63-72`). The hotfix is the same shape: a
guard placed **immediately after** the P12 block, so the layering is:

```
no core map fields  → P12 returns 403 (endpoint dead)            [shipped]
core map fields exist, guarded write action:
    not update-authorized → guard returns 403                    [hotfix]
    update-authorized     → sanitize lat/lng to float, fall through to core
anything else            → unchanged
```

Because it only reads request state, enforces access, and mutates `$_POST`
coordinates to floats before core reads them, it needs **no** change to
`google_map.php` — core still performs the write, now with an authorized user
and clean numeric input.

## 3. Code sketch

New file: `unitas_ext/classes/security/unitas_core_gmap_guard.php`

```php
<?php
/**
 * UNITAS Extension — interim guard for the core items/google_map (and yandex)
 * coordinate-update endpoints. See CORE_GMAP_HOTFIX_SHIM.md. DRAFT — review.
 *
 * Enforces server-side update access and forces lat/lng numeric before the
 * core action writes. No core files are modified.
 */
class unitas_core_gmap_guard
{
    // module => guarded actions (core spelling preserved)
    private static function guarded()
    {
        return array(
            'items/google_map' => array(
                'update_latlng', 'update_latlng_multiple',
                'update_latlng_directions', 'save_value_in',
            ),
            'items/yandex_map' => array('update_latlng'),
        );
    }

    public static function enforce()
    {
        // Required core classes must already be loaded by application_core.
        if (!class_exists('access_rules') || !class_exists('records_visibility')
            || !class_exists('users') || !class_exists('items')) {
            return;
        }

        $module = $_GET['module'] ?? '';
        $action = $_GET['action'] ?? '';
        $map = self::guarded();

        if (!isset($map[$module]) || !in_array($action, $map[$module], true)) {
            return; // not a guarded endpoint
        }

        $path = $_GET['path'] ?? ($_POST['path'] ?? '');
        list($entity_id, $item_id) = self::parse_path($path);

        if ($entity_id <= 0 || $item_id <= 0) {
            self::deny();
        }

        // (1) Access: must be able to see AND update this record.
        if (!self::user_can_update($entity_id, $item_id)) {
            self::deny();
        }

        // (2) Input: force coordinates numeric before core stores them.
        self::sanitize_coordinates();

        // Authorized + clean: fall through to the core action.
    }

    private static function parse_path($path)
    {
        if (!strlen($path)) return array(0, 0);
        $parts = explode('/', $path);
        $last  = explode('-', end($parts));
        $eid   = (int)($last[0] ?? 0);
        $iid   = (int)($last[1] ?? 0);
        return array($eid, $iid);
    }

    private static function user_can_update($entity_id, $item_id)
    {
        global $app_entities_cache;
        if (!isset($app_entities_cache[$entity_id])) return false;

        // Visibility (read) — mirrors modules/items/module_top.php:74.
        $q = db_query(
            "select * from app_entity_" . (int)$entity_id . " e where e.id='"
            . db_input($item_id) . "' "
            . records_visibility::add_access_query($entity_id) . " "
            . items::add_access_query_for_parent_entities($entity_id), false);
        $item = db_fetch_array($q);
        if (!$item) return false;

        // Update access — mirrors fieldtype_google_map.php:111-112.
        $access_rules = new access_rules($entity_id, $item);
        return users::has_access('update', $access_rules->get_access_schema());
    }

    private static function sanitize_coordinates()
    {
        foreach (array('lat', 'lng') as $k) {
            if (!isset($_POST[$k])) continue;
            if (is_array($_POST[$k])) {
                // update_latlng_multiple / _directions send arrays.
                $_POST[$k] = array_map(static function ($v) {
                    return (float)$v;
                }, $_POST[$k]);
            } else {
                $_POST[$k] = (float)$_POST[$k];
            }
        }
        // save_value_in sends no lat/lng — this is a no-op for it (access only).
    }

    private static function deny()
    {
        http_response_code(403);
        exit();
    }
}
```

Wiring in `unitas_ext/application_top.php`, immediately after the P12 block
(after line 72):

```php
// ── Interim guard for core items/google_map writes (pre-migration) ───────────
// Only reached when core map fields still exist (P12 above has not fired).
// Enforces update access and numeric coordinates. See CORE_GMAP_HOTFIX_SHIM.md.
require_once PLUGIN_UNITAS_EXT_PATH . '/classes/security/unitas_core_gmap_guard.php';
unitas_core_gmap_guard::enforce();
```

## 4. Why interception, not a marker shim

| | Interception (chosen) | Marker shim in `google_map.php` |
|---|---|---|
| Core files touched | none | `modules/items/actions/google_map.php` |
| Survives core update | yes, automatically | no — needs `shim_health()` + re-apply |
| Anchor-drift risk | none | yes (refuse-on-ambiguity, like S1/S2) |
| Covers finding #1 | yes | yes |
| Covers finding #2-input | yes (`$_POST` float cast) | yes |
| Covers finding #2-output (address) | no | possible, but that lives in the field type render, not the action |
| Covers finding #3 (tracking) | no | only if the shim takes over the write |

Interception gives the highest-value coverage (privilege escalation + coord
injection) at the lowest maintenance cost. The two items it does not cover are
better solved by migrating the field than by patching core.

## 5. If full coverage is required before migration (optional, heavier)

Only if an instance must keep core Google map fields *and* needs the
address-render XSS and the audit gap closed:

- **Address escaping (#2-output):** add a marker-delimited shim (S-style, per
  `install.php`'s `install_shim()` with refuse-on-non-unique-anchor) to
  `includes/classes/fieldstypes/fieldtype_google_map.php` around line 145,
  replacing `urldecode($current_address)` emitted into `setContent('<div>…')`
  with a `json_encode()`d, `htmlspecialchars()`d value. Also cast `$lat`/`$lng`
  to float at line 130. This adds a `shim_health()` entry and must be
  re-applied after core updates — accept that cost knowingly.
- **Change tracking (#3):** have `enforce()` perform the write itself
  (authorized), then add a `track_changes` entry mirroring
  `modules/items/actions/items.php:264`, and `exit()` instead of falling
  through. This duplicates core's write logic — more surface, more to keep in
  sync — so prefer migration unless there is a hard requirement.

Recommendation: do **not** build section 5 as part of the hotfix. Ship section 3;
drive instances to migration.

## 6. Caveats and open checks (resolve during review)

1. **Bootstrap ordering.** Confirm that on the target core version the plugin
   `application_top.php` executes before the `items/*` module action is
   dispatched (it does on 3.6.4 — P12 already relies on this). Re-verify on 3.7.
2. **Class availability at guard time.** `access_rules`, `records_visibility`,
   `items`, `users` must be loaded when `enforce()` runs. The `class_exists`
   guard makes a too-early call a safe no-op rather than a fatal — but if it
   no-ops, the endpoint is unguarded, so verify these are loaded by
   `application_core` before the plugin `application_top` on both 3.6.4 and 3.7.
3. **`filed_id` is core's misspelling** — the endpoint reads `_post::int('filed_id')`.
   The guard does not need the field id for the entity/item-level access check,
   so it deliberately ignores it; if a field-level check is wanted later, read
   `filed_id` with the same spelling.
4. **`save_value_in`** carries `distance`, not `lat`/`lng`; the guard enforces
   access and the coordinate sanitizer is a no-op for it. Correct as written.
5. **Parent-path items.** `parse_path()` reads only the last path segment
   (entity-item), matching how `module_top.php` derives `$current_entity_id` /
   `$current_item_id`. Nested paths still resolve to the same last-segment ids
   core uses; confirm against a nested-entity map field.
6. **AJAX/injection skip.** This guard is independent of the `$is_ajax_request`
   ob_start-skip logic; it runs regardless and exits before any injection. No
   interaction, but confirm placement is above the injection setup.
7. **Double 403 semantics.** When core map fields are absent, P12 already
   exits 403 before the guard; when present, the guard may 403. Both are bare
   403s — consistent, no information leak.

## 7. Test plan (before enabling)

- View-only user, direct POST to `update_latlng` (and the multiple/directions
  variants, and yandex) → **403**, no DB change, no history entry.
- Update-authorized user, legitimate drag → succeeds, coordinates stored as
  numerics, map renders.
- Non-numeric `lat`/`lng` from an authorized user → stored as `0`/float, no raw
  string reaches the field value; render emits a numeric `LatLng(...)`.
- `save_value_in` from a view-only user → 403; from an authorized user →
  unchanged behavior.
- Instance with **no** core map fields → P12 path still returns 403 (guard not
  reached); confirm no regression.
- After migrating all fields to `fieldtype_unitas_location` → endpoint 403 via
  P12; guard file present but inert.
- Re-run on core 3.7 to confirm bootstrap ordering and class availability
  (caveats 1–2).

## 8. Rollout note

This is defense-in-depth for the migration window. The durable fix remains:
migrate every core Google map field to `fieldtype_unitas_location`, at which
point P12 blocks the endpoint permanently and this guard becomes inert. Track
the upstream fix in unitassolutions/Rukovoditel#1.
