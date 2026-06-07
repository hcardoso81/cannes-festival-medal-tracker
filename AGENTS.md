# Cannes Festival Medal Tracker

Context for AI agents and developers working on this WordPress plugin.

## Project

- Plugin: Cannes Festival Medal Tracker.
- Main file: `cannes-festival-medal-tracker.php`.
- Namespace: `FestivalMedalTracker`.
- Text domain: `cannes-festival-medal-tracker`.
- Version: `1.0.0`.
- Objective: import Excel festival results, aggregate medals by country, persist totals in a custom table and render standings through shortcodes.
- Database tables: `{prefix}_fmb_country_medals`, `{prefix}_fmb_imports`, `{prefix}_fmb_import_country_medals`.
- Dependency: `phpoffice/phpspreadsheet` through Composer.
- Error log: `logs/fmb-error.log`.
- README technology chips: visual Shields.io badges with colors and logos for WordPress, PHP 7.4+, Composer, PhpSpreadsheet/Excel Import, MySQL/custom tables, HTML and CSS.

## Architecture

Keep the main plugin file lightweight. It may define constants, load autoloaders, register activation hooks and boot the plugin.

Current layers:

- `src/Bootstrap`: composition root and hook registration.
- `src/Domain/Service`: business normalization rules.
- `src/Application`: use cases, including medal import orchestration.
- `src/Infrastructure/Excel`: file reader adapters.
- `src/Infrastructure/Persistence`: `$wpdb` repositories.
- `src/Infrastructure/WordPress`: activation and WordPress infrastructure.
- `src/UI/Admin`: admin pages, admin-post handlers and admin assets.
- `src/UI/Frontend`: shortcode rendering and frontend assets.

## Clean Code and UI Composition

Do not build giant view/controller files. Keep admin pages, widgets and request handlers componentized so each class has one clear responsibility.

- Controllers such as `AdminPage` should register hooks, validate requests, orchestrate use cases and redirect.
- Rendering belongs in small view classes or widgets such as `AdminPageRenderer`.
- Extract repeated or bulky UI blocks instead of adding hundreds of lines to one file.
- Prefer readable methods with focused names over long procedural templates.
- Keep future admin widgets isolated enough that they can be moved, tested or replaced without touching import, persistence or shortcode behavior.
- If a file grows past roughly 500 lines, split by responsibility before adding more features.

## Admin Flow

The admin page is available under **Medal Tracker** and requires `manage_options`.

Imports are handled through `admin-post.php` with action `fmb_import_medals`. The flow is:

1. Validate capability.
2. Validate nonce.
3. Validate uploaded file extension and MIME type.
4. Store the upload through `wp_handle_upload`.
5. Read rows with PhpSpreadsheet.
6. Find and normalize the `location` and `prize` headers case-insensitively.
7. Accumulate medals by country.
8. Store a pending preview in a user-scoped transient without writing to the database.
9. Delete the temporary uploaded file.
10. Redirect back to the admin page with a transient-backed summary.
11. Persist only after `fmb_approve_import_preview` validates capability, nonce and a custom HTML confirmation modal POST.
12. Record approved imports in database tables with source filename, approval date, summary counts and per-country medal deltas.
13. Allow approved imports with stored deltas to be undone through `fmb_undo_import`, capability, nonce and a custom HTML confirmation modal POST. Undo must subtract only the medals contributed by that import, then remove that import and its stored deltas from the import history so it no longer appears as something that was imported before.
14. Allow pending previews to be discarded with `fmb_discard_import_preview`, capability, nonce and a custom HTML confirmation modal POST.

The upload form must keep **Generar vista previa** disabled until a file has been selected. This is a UI convenience only; server-side upload validation remains required.

All admin confirmations must use the plugin custom HTML confirmation modal instead of native `window.confirm()`. This applies to approval, discard, undo, reset and duplicate-file warnings. Keep confirmation messages attached to forms or actions declaratively, e.g. with `data-fmb-confirm` attributes, and centralize modal behavior in the admin assets.

Processed rows must keep enough debug context: spreadsheet row number, raw `location`, raw `prize`, status, counted countries, ignored countries and reason when applicable. Show sanitized details in the pending preview, not duplicated in the admin notice. The upload/admin notice should remain a short confirmation that the Excel file was processed. Ignored row details must still be written to `logs/fmb-error.log`.

The pending preview must use collapsible accordions:

- A processed-row accordion with totals in the header and row-level details inside. Counted/processed rows should be visually highlighted in green and bold.
- A detected-medals accordion showing the country medal totals and whether approval will create or update each country.
- An accumulated-medals accordion showing how the internal medal table will look after approving the pending import.
- A shortcode preview block directly below the accumulated medal table, showing how each shortcode would render with the pending import applied.

When one `location` cell contains multiple country values separated by `/`, the row detail must state which allowed countries were counted and which countries were ignored. Example: `Spain / Brazil / Argentina` should count Argentina and ignore Spain and Brazil.

Imports are two-step by design: preview first, then approve and merge. Do not write imported medal totals to the database during upload processing. A pending preview can be discarded without touching persisted medal data.

On the main import/admin page, shortcode previews must stay in context. Do not render a standalone shortcode preview between upload and current data. Show current shortcode previews below the current medal table, and show pending-import shortcode previews inside the pending preview widget below the accumulated medal table.
The pending preview widget itself must be collapsible/minimizable. Use a distinct orange header for the parent pending-preview accordion, and use yellow headers for the possible/future shortcode accordions inside it, so users can distinguish pending changes from already-applied data.

The admin page must show a **Registro de importaciones** for approved imports. The register must clearly distinguish a pending preview from approved imports: pending files are marked as not merged/not saved yet, while the approved history is collapsible and lists only active approved imports from the import tables. Discarded previews must not be added to this history. Approved imports with stored deltas must expose a **Deshacer** action while they are still approved. Once an import is undone, remove it from the visible register and from duplicate-file detection so users do not see stale evidence that it was imported before. Ignore legacy `wp_options` import history; old test data does not need to be migrated or shown.

The admin page includes a destructive reset action with action `fmb_reset_medals`. It must always validate `manage_options`, verify nonce `fmb_reset_medals_nonce`, ask for custom HTML modal confirmation and log deleted row count. Reset must also clear any pending preview, approved import log tables, frontend published snapshot and frontend published date, then log how many approved import history entries and published frontend rows were removed.

## Frontend Publication Flow

Frontend output is intentionally embargoed from the import workflow. Approved imports update the internal medal table, but shortcodes must render only the last manually published frontend snapshot.

The admin must expose a separate **Frontend** view under Medal Tracker. This view must include:

- A checkbox to enable or disable shortcode rendering. Enabled means shortcodes render the published snapshot; disabled means shortcode output is hidden.
- A **Publicar datos** action that copies the current internal medal table into the published frontend snapshot.
- A preview of the currently published medal table.
- A preview of pending changes between the published snapshot and the internal medal table.
- A preview of how the frontend will look after publishing the current internal medal table.

The publish action must validate `manage_options`, verify nonce, use the shared custom HTML confirmation modal and log the publication. Imports after publication must not affect shortcode output until the next manual publish action.

## Shortcodes

Register both requested camel-case names and lowercase aliases:

- `medalByCountry`
- `medalsTotal`
- `medalByCountryDetail`
- `medalbycountry`
- `medalstotal`
- `medalbycountrydetail`

All shortcode output must be escaped and should use semantic tables with `fmb-` CSS classes.
Medal ordering is GP, Gold, Silver and Bronze.
Country ranking must use medal hierarchy, not raw total first: GP first, then Gold, then Silver, then Bronze, then Total as a tiebreaker, then country name.
Shortcodes must read from the published frontend snapshot, not directly from the mutable internal import table. If frontend rendering is disabled, shortcodes should return no visible output.

## Prize Synonyms

Prize normalization lives in `FestivalMedalTracker\Domain\Service\MedalNormalizer`.

Default mappings:

- `GP`, `Grand Prix`, `Grand Prix Campaign` => `gp`
- `Gold`, `Gold Lion`, `Gold Lion Campaign` => `gold`
- `Silver`, `Silver Lion`, `Silver Lion Campaign` => `silver`
- `Bronze`, `Bronze Lion`, `Bronze Lion Campaign` => `bronze`

Extend synonyms with the `fmb_prize_synonyms` filter instead of editing import logic directly.

## Allowed Countries

Only the configured Hispanoamerica country allowlist is counted:

- `ARGENTINA`
- `BOLIVIA`
- `CHILE`
- `COLOMBIA`
- `COSTA RICA`
- `CUBA`
- `REPUBLICA DOMINICANA`
- `ECUADOR`
- `EL SALVADOR`
- `GUATEMALA`
- `HAITI`
- `HONDURAS`
- `MEXICO`
- `NICARAGUA`
- `PANAMA`
- `PARAGUAY`
- `PERU`
- `PUERTO RICO`
- `URUGUAY`
- `VENEZUELA`

Brazil and all other countries are ignored. Country matching is case-insensitive and accent-insensitive. Counted country labels are stored and displayed as the accent-free uppercase comparison key, e.g. `MEXICO`, `PERU`, `REPUBLICA DOMINICANA`. Extend or replace the list with the `fmb_allowed_countries` filter. The admin page must display the current counted countries and prize values before import.

## Logging

Plugin-specific errors must be written to `logs/fmb-error.log` through `FestivalMedalTracker\Infrastructure\Logging\FileLogger`.

Rules:

- Log import failures with enough context to debug locally.
- Log ignored import rows with source values, not only row numbers.
- Do not expose stack traces or absolute paths in admin notices.
- Keep `logs/index.php` and `logs/.htaccess` in place.
- Do not commit `.log` files.

## Security Rules

- Never process an admin request without capability and nonce checks.
- Destructive admin actions must include nonce, capability check, custom HTML confirmation UI and plugin log entry.
- Do not use native `window.confirm()` for plugin admin actions; use the shared HTML modal confirmation flow.
- Never trust uploaded filenames or MIME values alone.
- Use `wp_check_filetype_and_ext` and `wp_handle_upload` for Excel uploads.
- Delete imported upload files after processing.
- Sanitize spreadsheet cell values before normalization.
- Escape all admin and frontend output.
- Use `$wpdb->prepare`, `$wpdb->insert` with formats or trusted static SQL only.
- Keep medal values as non-negative integers.
- Do not remove persisted medal data on deactivation.

## Commit Guidance

Every completed task should suggest one conventional commit message, for example:

```text
feat(import): add Excel medal import workflow
```
