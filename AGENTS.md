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
6. Normalize `location` and `prize`.
7. Accumulate medals by country.
8. Upsert totals into `{prefix}_fmb_country_medals`.
9. Delete the temporary uploaded file.
10. Redirect back to the admin page with a transient-backed summary.

## Shortcodes

Register both requested camel-case names and lowercase aliases:

- `medalByCountry`
- `medalsTotal`
- `medalByCountryDetail`
- `medalbycountry`
- `medalstotal`
- `medalbycountrydetail`

All shortcode output must be escaped and should use semantic tables with `fmb-` CSS classes.

## Security Rules

- Never process an admin request without capability and nonce checks.
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
