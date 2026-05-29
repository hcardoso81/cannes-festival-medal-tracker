# Cannes Festival Medal Tracker

WordPress plugin to import, manage and display festival medal standings by country using gold, silver and bronze medals.

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

- `Grand Prix` maps to gold.
- `Gold Lion` maps to gold.
- `Silver Lion` maps to silver.
- `Bronze Lion` maps to bronze.

Rows with empty countries or unrecognized prizes are ignored and reported in the import summary.

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
