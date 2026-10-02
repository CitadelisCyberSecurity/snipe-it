# Multi-fieldset per AssetModel: design pause point

## What's on the table
Letting a single AssetModel attach more than one CustomFieldset, instead
of the current single `models.fieldset_id` FK.

## Existing pivots in this neighborhood
- `custom_field_custom_fieldset`: CustomField to CustomFieldset (which
  fields belong to a fieldset). Already exists.
- `models_custom_fields`: AssetModel to CustomField, columns
  `asset_model_id`, `custom_field_id`, `default_value`. **Purpose is
  per-model default-value overrides only.** It does NOT determine
  which fields a model has. That still routes through
  `models.fieldset_id`, then `custom_field_custom_fieldset`, then
  `custom_fields`. Migration:
  `database/migrations/2018_04_16_133902_create_custom_field_default_values_table.php`.
  Relations:
  `AssetModel::customFields()` and `CustomField::assetModels()` both use
  `belongsToMany(..., 'models_custom_fields')->withPivot('default_value')`.

## Missing pivot for the feature
- AssetModel to CustomFieldset (many-to-many). Not present in any
  form today.

## Two paths I laid out

### Path A: smaller change
Add a new pivot:

```php
Schema::create('asset_model_custom_fieldset', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('model_id')->index();
    $table->unsignedBigInteger('custom_fieldset_id')->index();
    $table->unsignedInteger('order')->default(0);
    $table->timestamps();
    $table->unique(['model_id', 'custom_fieldset_id']);
    // No FKs, per project convention.
});

// Backfill from the existing single FK.
DB::statement("
    INSERT INTO asset_model_custom_fieldset
        (model_id, custom_fieldset_id, `order`, created_at, updated_at)
    SELECT id, fieldset_id, 0, NOW(), NOW()
    FROM models
    WHERE fieldset_id IS NOT NULL
");
```

- Keeps the mental model: model has fieldsets, fieldsets have fields,
  merge at render time.
- `models.fieldset_id` stays as a compatibility shim during the code
  sweep. A follow-up migration drops it once every consumer reads from
  the new pivot.
- Consumer sweep sites (from earlier grep): AssetsController + API
  transformer + create/edit blades + custom reports + CSV import +
  PDF label renderer + bulk edit. Roughly 15 to 25 sites, small
  individually.
- API contract change: `assets.*.custom_fieldset` becomes an array
  (`custom_fieldsets`). Softest rollout is emitting both keys through
  a deprecation window.
- Rough sizing: 2 to 3 focused days for the core (migration,
  relations, consumer sweep, tests) plus a docs / API-deprecation note.

### Path B: architecturally cleaner rewrite
Promote `models_custom_fields` from a defaults side-table into the
primary "which fields does this model have" table.

- Add `order` to `models_custom_fields`. Keep `default_value`.
- Demote CustomFieldset to a reusable template. Applying a fieldset
  populates rows in `models_custom_fields` for the target model.
- Model to field becomes direct. Fieldsets become a UI convenience for
  bulk-attach.
- Removes the three-hop `model, fieldset, pivot, field` walk from
  every consumer.
- Bigger migration (data reshaping) and bigger consumer sweep, but no
  more "which layer owns the field membership" ambiguity.

## Open questions (unresolved when we paused)
1. Path A or Path B: Snipe's call. B is cleaner, A ships faster.
2. Field ordering across attached fieldsets in Path A. Options:
   - Fieldset `order` plus within-fieldset field `order` (two-level sort).
   - Interleave everything by a single per-field order (needs a rule
     for cross-fieldset conflicts).
3. Duplicate fields (same CustomField in two attached fieldsets in
   Path A). They share a `db_column`, so the stored value is fine,
   but the form would render the field twice. Need to de-dupe when
   merging the field list.
4. Backward-compat API window on `custom_fieldset` (singular) response
   key. Suggest: keep it for one minor release next to the new
   `custom_fieldsets` array, drop it on the following major.
