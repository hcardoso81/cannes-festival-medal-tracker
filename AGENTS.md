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
8. Upsert totals into `{prefix}_fmb_country_medals`.
9. Delete the temporary uploaded file.
10. Redirect back to the admin page with a transient-backed summary.

Ignored rows must keep enough debug context: spreadsheet row number, raw `location`, raw `prize` and reason. Show sanitized details in the admin notice and write the structured list to `logs/fmb-error.log`.

The admin page includes a destructive reset action with action `fmb_reset_medals`. It must always validate `manage_options`, verify nonce `fmb_reset_medals_nonce`, ask for browser confirmation and log deleted row count.

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
