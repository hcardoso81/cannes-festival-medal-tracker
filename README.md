# Cannes Festival Medal Tracker

WordPress plugin to import, manage and display festival medal standings by country using GP, gold, silver and bronze medals.

## Technology Chips

![WordPress](https://img.shields.io/badge/WordPress-21759B?style=for-the-badge&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white)
![PhpSpreadsheet](https://img.shields.io/badge/PhpSpreadsheet-Excel%20Import-217346?style=for-the-badge&logo=microsoftexcel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Custom%20Tables-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-Frontend-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-Styles-1572B6?style=for-the-badge&logo=css3&logoColor=white)

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
- `Gold Lion`, `Gold Lion Campaign` and `Gold` map to gold.
- `Silver Lion`, `Silver Lion Campaign` and `Silver` map to silver.
- `Bronze Lion`, `Bronze Lion Campaign` and `Bronze` map to bronze.

Rows with empty countries or unrecognized prizes are ignored and reported in the import summary.
The header row can use different casing, such as `Location` and `Prize`, and may appear after an initial title row.
Ignored rows include the spreadsheet row number, original `location`, original `prize` and the reason. The same details are written to `logs/fmb-error.log`.

Only these countries are counted:

- `PERU`
- `COLOMBIA`
- `PUERTO RICO`
- `ECUADOR`
- `CHILE`
- `MEXICO`
- `COSTA RICA`
- `ARGENTINA`
- `HONDURAS`

Other countries, including Brazil, are ignored and listed in the import summary/log.

Prize synonyms can be extended with the `fmb_prize_synonyms` filter:

```php
add_filter('fmb_prize_synonyms', static function (array $synonyms): array {
    $synonyms['gp'][] = 'Grand Prix For Good';
    $synonyms['gold'][] = 'Gold';

    return $synonyms;
});
```

Allowed countries can be extended or replaced with the `fmb_allowed_countries` filter:

```php
add_filter('fmb_allowed_countries', static function (array $countries): array {
    $countries[] = 'URUGUAY';

    return $countries;
});
```

## Admin Usage

Go to **Medal Tracker** in the WordPress admin dashboard.

The page lets administrators with `manage_options` upload an `.xlsx`, `.xls` or `.csv` file. Uploading a file creates a pending preview only; no medal totals are persisted yet. The admin page shows the countries and prize values that will be counted before upload.

After reviewing the preview, use **Approve and continue** to merge the detected medals into the database. Existing countries are incremented; new countries are inserted. You can also discard the pending preview without changing the database. Approval and discard actions require a nonce, `manage_options` and browser confirmation.

The admin page also includes a reset action to delete all medal rows from the plugin table. The reset requires `manage_options`, a nonce and a browser confirmation.

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
- Import approval uses WordPress nonces and browser confirmation before database writes.
- Reset form uses WordPress nonces and browser confirmation.
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
Imports that finish with ignored rows also write the ignored row details to the log so invalid source values are not lost.
