# /make-module

Generate a complete MVC module for a given domain model.

## Usage
```
/make-module <ModelName> <domain>
```
Example: `/make-module ProductGroup Catalog`

## What this does

For the given `ModelName` inside `app/Domain/<domain>/`, generate **in parallel**:

1. **Migration** — `database/migrations/YYYY_MM_DD_create_<table>_table.php`
   - All columns from CLAUDE.md schema for this model
   - Proper indexes, foreign keys, soft deletes where applicable

2. **Model** — `app/Domain/<domain>/Models/<ModelName>.php`
   - Fillable, casts, relationships, scopes
   - Implements `Auditable` (owen-it/laravel-auditing)
   - SoftDeletes where applicable

3. **Service** — `app/Domain/<domain>/Services/<ModelName>Service.php`
   - `index()`, `store(array $data)`, `update(Model $model, array $data)`, `destroy(Model $model)`
   - Business logic, no HTTP concerns

4. **Controller** (Livewire) — `app/Http/Livewire/<domain>/<ModelName>Index.php` + `<ModelName>Form.php`
   - Index: datatable with search/filter/sort
   - Form: create/edit modal

5. **Views** — `resources/views/livewire/<domain>/`
   - `<model-name>-index.blade.php` — table + search
   - `<model-name>-form.blade.php` — form modal
   - Use design system: stone-50 bg, amber-700 CTA, emerald/rose status colors

6. **Route** — append resource route to `routes/web.php` under auth middleware

7. **Policy** — `app/Policies/<ModelName>Policy.php`
   - Admin: full CRUD
   - Operator: viewAny + view only (or own records where applicable)

## Design rules
- Tailwind only, no UI library
- Heroicons for icons
- All strings via `__('key')` — add to both `lang/uz.json` and `lang/ru.json`
- Follow domain structure: `app/Domain/<domain>/`
