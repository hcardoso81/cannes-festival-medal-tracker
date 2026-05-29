# Cannes Festival Medal Tracker

WordPress plugin to import, manage and display festival medal standings by country using gold, silver and bronze medals.

## Technology Chips

`WordPress` `PHP 7.4+` `OOP` `Namespaces` `Plugin API` `Shortcodes` `Admin Pages` `Custom Database Tables` `wpdb` `dbDelta` `Composer` `PhpSpreadsheet` `Excel Import` `HTML` `CSS`

## Requirements

- WordPress.
- PHP 7.4 or newer.
- Composer dependency `phpoffice/phpspreadsheet`.

Install the Excel reader dependency inside the plugin directory:

```bash
composer install
```

If `vendor/autoload.php` is missing, the plugin can still load, but Excel imports will show an admin error asking to install PhpSpreadsheet.

## Installation

1. Copy this directory into `wp-content/plugins/cannes-festival-medal-tracker`.
2. Run `composer install` in the plugin directory.
3. Activate **Cannes Festival Medal Tracker** in WordPress.
4. On activation, the plugin creates the table `{prefix}_fmb_country_medals`.

## Excel Format

The first row must contain at least these columns:

- `location`: country name.
- `prize`: medal prize.

Supported `prize` values:

- `GP`, `Grand Prix` and `Grand Prix Campaign` map to GP.
- `Gold Lion` maps to gold.
- `Silver Lion` maps to silver.
- `Bronze Lion` maps to bronze.

Rows with empty countries or unrecognized prizes are ignored and reported in the import summary.
The header row can use different casing, such as `Location` and `Prize`, and may appear after an initial title row.

Prize synonyms can be extended with the `fmb_prize_synonyms` filter:

```php
add_filter('fmb_prize_synonyms', static function (array $synonyms): array {
    $synonyms['gp'][] = 'Grand Prix For Good';
    $synonyms['gold'][] = 'Gold';

    return $synonyms;
});
```

## Admin Usage

Go to **Medal Tracker** in the WordPress admin dashboard.

The page lets administrators with `manage_options` upload an `.xlsx`, `.xls` or `.csv` file. Imported medals are accumulated by country. Existing countries are incremented; new countries are inserted.

## Shortcodes

Country totals:

```text
[medalByCountry]
[medalbycountry]
```

Medal totals by type:

```text
[medalsTotal]
[medalstotal]
```

Country detail:

```text
[medalByCountryDetail]
[medalbycountrydetail]
```

Frontend tables use semantic HTML and the `fmb-table` CSS class family for customization.

## Security

- Admin page requires `manage_options`.
- Upload form uses WordPress nonces.
- Uploads are processed with `wp_handle_upload`.
- File extensions and MIME types are validated.
- Database writes use `$wpdb->prepare`, `$wpdb->insert` and typed formats.
- Output is escaped before rendering.

## Logs

Import errors are written to:

```text
logs/fmb-error.log
```

The `logs/` directory includes `index.php` and `.htaccess` protections. Log files are ignored by Git.
