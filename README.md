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

Rows with empty countries or unrecognized prizes are ignored and reported in the pending preview details.
The header row can use different casing, such as `Location` and `Prize`, and may appear after an initial title row.
Processed row details include the spreadsheet row number, original `location`, original `prize`, status and reason. When a `location` cell contains multiple countries separated by `/`, the preview states which allowed countries were counted and which non-allowed countries were ignored. For example, `Spain / Brazil / Argentina` counts Argentina and ignores Spain and Brazil. Ignored row details are also written to `logs/fmb-error.log`.

Only these Hispanoamerica countries are counted:

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

The page lets administrators with `manage_options` upload an `.xlsx`, `.xls` or `.csv` file. The **Generar vista previa** button stays disabled until a file is selected. If the selected file was already approved in the import history, the browser asks for confirmation before processing it again. Uploading a file creates a pending preview only; no medal totals are persisted yet. The admin page shows the countries and prize values that will be counted before upload.

The pending preview uses collapsible accordions. One accordion lists every processed row, highlighting counted rows in green and bold while keeping ignored rows visually separate. Another accordion shows the detected medal totals by country and the create/update action that would happen on approval. If a processed file does not produce medal results, the admin UI shows a red warning and keeps the approval action available so the file can still be recorded in the approved import history without changing medal totals.

After reviewing the preview, use **Approve and continue** to merge the detected medals into the database. Existing countries are incremented; new countries are inserted. When no medals were detected, approving only records the source file in the historical import log. You can also discard the pending preview without changing the database. Approval and discard actions require a nonce, `manage_options` and browser confirmation.

The upload notice only confirms that the Excel file was processed and points back to the preview; it does not duplicate row details. **Registro de importaciones** separates pending previews from approved imports: a pending file is marked as not merged and not yet saved in the medal table, while approved imports are listed in a collapsible history with filename, approval date, valid rows, ignored rows and country create/update counts. Discarded previews are not added to this history.

The admin page also includes a reset action to delete all medal rows from the plugin table. Reset also clears the pending preview and the approved import history, so **Registro de importaciones** returns to an empty state. The reset requires `manage_options`, a nonce, a browser confirmation and typing the exact confirmation phrase `reiniciar medallero`.

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
