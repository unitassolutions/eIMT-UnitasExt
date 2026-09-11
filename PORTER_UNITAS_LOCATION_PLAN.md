# Ruko-Porter-Module — Unitas Location Support Plan

**Audience:** Ruko-Porter-Module project (this document is written to be carried
into that project as-is; it does not assume any particular state of the Porter
codebase beyond its core concept of exporting and importing Rukovoditel entity
configurations with id remapping).

**Source of requirements:** eIMT-UnitasExt v1.6.7 — the "Google Maps Platform
key lockdown" release. That release introduces a new field type
(`fieldtype_unitas_location`), a new configuration table
(`app_unitas_address_autocomplete_rules`), and two tables that must never be
exported. All schemas and configuration keys below are quoted from the shipped
Unitas-Ext code, not from design documents.

---

## 1. Why Porter needs changes

Unitas-Ext v1.6.x replaces the core `fieldtype_google_map` field (which carried
a Google API key inside each field's configuration JSON) with a Unitas-owned
`fieldtype_unitas_location` field that carries **no key at all** — keys live in
per-instance configuration. Three consequences for Porter:

1. A new field type exists whose configuration contains a **field-id
   reference** that must be remapped on import (`source_field_id`).
2. A new plugin table maps entities/fields to the standalone address
   autocomplete feature and should travel with exported entities.
3. Some Unitas tables now hold **secrets** (browser key, server key, Waze
   token, CIFS feed key) and per-instance history; Porter must be hard-coded
   to never export them.

There is also a defense-in-depth item for instances still carrying legacy core
Google map fields (section 5).

---

## 2. Field configuration remap: `fieldtype_unitas_location`

### 2.1 Configuration schema (as written by Unitas-Ext)

`app_fields.configuration` for a `fieldtype_unitas_location` field is a JSON
object with these keys (all values stored as strings, matching Rukovoditel's
`fields_types_cfg` convention):

| Key | Meaning | Remap? |
|---|---|---|
| `source_field_id` | Field id of a **same-entity** `fieldtype_input` text field that holds the address. The location field geocodes this field's value. | **Yes — field id** |
| `enable_autocomplete` | `yes` / `no` — attach the Unitas address autocomplete widget to the source field on item forms. | No |
| `form_map_preview` | `yes` / `no` — show the pin-preview map on item forms. | No |
| `map_width` | CSS width, e.g. `470px` or `100%`. | No |
| `map_height` | CSS height, e.g. `470px`. | No |
| `zoom` | Initial map zoom, `3`–`20`. | No |

`source_field_id` is the only id reference. It always points to a field **in
the same entity** as the location field itself.

### 2.2 Required Porter behavior

1. **Export:** export the location field's `app_fields` row as any other
   field. Record `source_field_id` in the manifest as a field dependency so
   the import can detect a missing source field.
2. **Import (second-pass remap):** `source_field_id` must be rewritten to the
   target instance's new field id. Because the source field may be created
   *after* the location field within the same import run (fields import in
   arbitrary/creation order), this rewrite **must run as a second pass after
   all fields of the entity exist** — the same pattern Porter needs for any
   configuration key that references another field. Do not attempt to resolve
   it inline during the first insert.
3. **Missing source field:** if the referenced field is not part of the import
   set and no mapping exists, import the location field with
   `source_field_id` set to `0` (Unitas-Ext treats `0`/missing as "not
   configured" and degrades gracefully: no autocomplete, no geocoding until an
   admin picks a source field) and add a **manifest warning** naming the field
   so the admin knows to re-select the Address Field in the field
   configuration.
4. **Column type:** `fieldtype_unitas_location` stores its value in a
   `TEXT NOT NULL` column (`field_{id}` on `app_entity_{entities_id}`). If
   Porter creates entity columns itself rather than delegating to
   Rukovoditel's `entities::prepare_field_type()`, use `TEXT NOT NULL`.
   (Rukovoditel's default for unknown types is already TEXT, so delegation is
   safe even on a target instance where the Unitas plugin registers the type.)
5. **Precondition check:** importing a `fieldtype_unitas_location` field into
   an instance where Unitas-Ext ≥ 1.6.7 is not installed produces a field
   whose type no core or plugin class implements. Porter should check for the
   plugin (e.g. `app_configuration` key `CFG_PLUGIN_UNITAS_EXT_INSTALLED`
   is truthy) and refuse — or warn loudly — when it is absent, exactly as it
   should already do for `fieldtype_unitas_geometry`.

### 2.3 Record data portability (only if Porter ever ports data)

Location values are stored as one tab-separated plain-text string:

```
{lat}\t{lng}\t{address}\t{status}
```

with `status` one of `autocomplete`, `geocoded`, `approximate`, `manual`,
`not_found`, `error`. There are **no ids inside the value** — record data is
portable verbatim, no remap needed. (Note this differs from the legacy core
`fieldtype_google_map` value, which is 3-part with a URL-encoded address.)

### 2.4 Pre-existing gap in the same shape: `fieldtype_unitas_geometry`

While adding the location remap, close the identical gap for the geometry
field type, which has carried field-id references since Unitas-Ext v1.2.0:

| Key | Meaning | Remap? |
|---|---|---|
| `waze_target_road_name` | Same-entity field id for Road Name autofill | **Yes — field id** |
| `waze_target_cross1` | Same-entity field id for Cross Street 1 autofill | **Yes — field id** |
| `waze_target_cross2` | Same-entity field id for Cross Street 2 autofill | **Yes — field id** |
| `drawing_mode`, `map_width`, `map_height`, `stroke_color`, etc. | Plain values | No |

Unresolvable targets: set to `0` (the geometry field validates targets at
render time and silently drops mismatches, so `0` is safe) and warn in the
manifest.

---

## 3. Table support: `app_unitas_address_autocomplete_rules`

### 3.1 Schema (Unitas-Ext v1.6.7)

```sql
CREATE TABLE `app_unitas_address_autocomplete_rules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entities_id` int(10) UNSIGNED NOT NULL,
  `fields_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fields_id` (`fields_id`),
  KEY `idx_entities_id` (`entities_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Each row attaches the Unitas address autocomplete widget to one plain
`fieldtype_input` text field, independent of any location field. `fields_id`
is unique — one rule per field.

### 3.2 Required Porter behavior

1. **Export:** when exporting an entity, include the rules whose
   `entities_id` is in the export set **and** whose `fields_id` resolves to an
   exported field. A rule whose field is excluded from the export is dropped
   with a manifest note (exporting it would be meaningless).
2. **Import:** remap both `entities_id` and `fields_id`; never carry source
   ids. Do not import `id` — let AUTO_INCREMENT assign it.
3. **Unique-key collision:** if the target instance already has a rule for
   the (remapped) `fields_id`, update it in place (upsert on `fields_id`)
   rather than failing the import; report it in the manifest as "rule
   replaced". This keeps re-imports idempotent.
4. **Plugin/table absence:** if Unitas-Ext is absent or older than 1.6.7 the
   table does not exist. Skip rule import with a manifest warning; never
   create the table yourself (the Unitas installer owns its schema and
   records a schema version).
5. **`is_active` and `notes`** travel verbatim.

---

## 4. Exclusions — never export

Hard-code these two tables out of any export path, including "export
everything" modes:

| Table | Why it must never leave an instance |
|---|---|
| `app_unitas_map_reports_config` | Single-row per-instance configuration holding **secrets**: the browser Google API key, the server Google API key (`google_server_api_key`), the Waze Partner Hub token (`waze_geocoding_token`), and the CIFS feed URL key. Keys are restricted per instance (website / egress-IP), so they are wrong on any other instance even if leaking them were acceptable. Also holds per-instance map ids, default center, geocoder error state. |
| `app_unitas_location_migration_log` | Per-instance history of core-map-field conversions (who converted which field when, rows rewritten). Meaningless and misleading on another instance. |

If Porter has a table allow-list, simply do not add these. If it has a
deny-list or a generic `app_unitas_*` sweep, add both names explicitly and add
a regression test that an export bundle never contains them (string-scan the
bundle for `google_server_api_key` and `waze_geocoding_token` as a
belt-and-suspenders check).

---

## 5. Defense in depth: strip `api_key` from legacy core map fields

Instances not yet migrated may still carry core Google map fields whose
configuration JSON embeds a Google API key:

- Types: `fieldtype_google_map`, `fieldtype_google_map_directions`,
  `fieldtype_google_map_nested`
- Key inside `configuration`: `api_key`

**Required Porter behavior on export:** for any `app_fields` row of those
types, remove the `api_key` member from the configuration JSON before writing
it to the bundle, and add a manifest warning:

> Field "{name}" (#{id}) is a legacy Google Map field. Its API key was
> stripped from the export. The target instance must either supply its own
> key in the field configuration or migrate the field to the Unitas Location
> type (Unitas-Ext ≥ 1.6.7, UNITAS Extension > Location Tools).

Rationale: a key exported from instance A is (a) a credential leak in a file
that gets emailed around, and (b) useless on instance B once keys are
website-restricted per instance. Stripping on export is the only place this
can be enforced centrally.

Do the same strip on **import** as a second layer, in case a bundle produced
by an older Porter version is imported.

---

## 6. Acceptance criteria

1. Export of an entity containing a `fieldtype_unitas_location` field and its
   source `fieldtype_input` field, imported into a clean instance with
   Unitas-Ext ≥ 1.6.7: the imported location field's `source_field_id` equals
   the **new** id of the imported source field, regardless of field creation
   order (second pass proven).
2. Same export with the source field deliberately excluded: location field
   imports with `source_field_id = 0` and the manifest carries the warning.
3. Geometry field with all three Waze target fields set: all three ids
   remapped; with one target excluded: that key becomes `0` plus warning.
4. Entity with two autocomplete rules (one active, one inactive): both import
   with remapped `entities_id`/`fields_id`, `is_active` preserved; re-import
   of the same bundle updates rather than duplicates (unique `fields_id`).
5. Any export bundle produced from an instance with configured keys contains
   **no occurrence** of the strings `google_server_api_key`,
   `waze_geocoding_token`, or the values of those columns, and does not
   contain `app_unitas_map_reports_config` or
   `app_unitas_location_migration_log` rows.
6. Export of a legacy `fieldtype_google_map` field with an `api_key` in its
   configuration: the bundle's copy has no `api_key` member and the manifest
   warns; other configuration members (`address_pattern`, `width`, `height`,
   `zoom`…) are untouched.
7. Import into an instance without Unitas-Ext: location/geometry fields and
   autocomplete rules are refused or skipped with clear manifest warnings; no
   partial writes to nonexistent tables.

---

## 7. Reference: Unitas-Ext facts Porter may rely on

- Plugin install flag: `app_configuration` key
  `CFG_PLUGIN_UNITAS_EXT_INSTALLED`; plugin DB semantic version in
  `CFG_PLUGIN_UNITAS_EXT_DB_VERSION`; independent integer schema version in
  `CFG_PLUGIN_UNITAS_EXT_SCHEMA_VERSION` (≥ 2 implies the autocomplete rules
  table and migration log exist).
- `fieldtype_unitas_location` and `fieldtype_unitas_geometry` are registered
  into core via installer-managed shims; on a healthy instance they appear in
  `fields_types::get_choices()` like any core type.
- The Unitas location value format and status vocabulary are stable public
  contracts as of v1.6.7 (documented in eIMT-UnitasExt `ARCHITECTURE.md`).
