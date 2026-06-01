# Cannes Festival Medal Tracker

Context for AI agents and developers working on this WordPress plugin.

## Project

- Plugin: Cannes Festival Medal Tracker.
- Main file: `cannes-festival-medal-tracker.php`.
- Namespace: `FestivalMedalTracker`.
- Text domain: `cannes-festival-medal-tracker`.
- Version: `1.0.0`.
- Objective: import Excel festival results, aggregate medals by country, persist totals in a custom table and render standings through shortcodes.
- Database table: `{prefix}_fmb_country_medals`.
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
11. Persist only after `fmb_approve_import_preview` validates capability, nonce and browser-confirmed POST.
12. Record approved imports in a persistent admin import log with source filename, approval date and summary counts.
13. Allow pending previews to be discarded with `fmb_discard_import_preview`, capability, nonce and browser-confirmed POST.

The upload form must keep **Generar vista previa** disabled until a file has been selected. This is a UI convenience only; server-side upload validation remains required.

Processed rows must keep enough debug context: spreadsheet row number, raw `location`, raw `prize`, status, counted countries, ignored countries and reason when applicable. Show sanitized details in the pending preview, not duplicated in the admin notice. The upload/admin notice should remain a short confirmation that the Excel file was processed. Ignored row details must still be written to `logs/fmb-error.log`.

The pending preview must use collapsible accordions:

- A processed-row accordion with totals in the header and row-level details inside. Counted/processed rows should be visually highlighted in green and bold.
- A detected-medals accordion showing the country medal totals and whether approval will create or update each country.

When one `location` cell contains multiple country values separated by `/`, the row detail must state which allowed countries were counted and which countries were ignored. Example: `Spain / Brazil / Argentina` should count Argentina and ignore Spain and Brazil.

Imports are two-step by design: preview first, then approve and merge. Do not write imported medal totals to the database during upload processing. A pending preview can be discarded without touching persisted medal data.

The admin page must show a **Registro de importaciones** for approved imports. The register must clearly distinguish a pending preview from approved imports: pending files are marked as not merged/not saved yet, while the approved history is collapsible and lists only approved imports. Discarded previews must not be added to this history.

The admin page includes a destructive reset action with action `fmb_reset_medals`. It must always validate `manage_options`, verify nonce `fmb_reset_medals_nonce`, ask for browser confirmation and log deleted row count. Reset must also clear any pending preview and the approved import log, then log how many approved import history entries were removed.

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
- `REPÚBLICA DOMINICANA`
- `ECUADOR`
- `EL SALVADOR`
- `GUATEMALA`
- `HAITÍ`
- `HONDURAS`
- `MÉXICO`
- `NICARAGUA`
- `PANAMÁ`
- `PARAGUAY`
- `PERÚ`
- `PUERTO RICO`
- `URUGUAY`
- `VENEZUELA`

Brazil and all other countries are ignored. Country matching is case-insensitive and accent-insensitive. Extend or replace the list with the `fmb_allowed_countries` filter. The admin page must display the current counted countries and prize values before import.

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
- Destructive admin actions must include nonce, capability check, confirmation UI and plugin log entry.
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
