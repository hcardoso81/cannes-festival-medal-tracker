<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Infrastructure\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

final class DatabaseInstaller
{
    private const SCHEMA_VERSION = '1.5.0';
    private const SCHEMA_OPTION = 'fmb_schema_version';

    public static function activate(): void
    {
        self::install();
        self::resetPluginData();
        self::seedInitialMedalStandings();
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
    }

    public static function maybeUpgrade(): void
    {
        if (self::SCHEMA_VERSION === get_option(self::SCHEMA_OPTION)) {
            return;
        }

        self::install();
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
    }

    private static function install(): void
    {
        global $wpdb;

        $tableName       = self::tableName();
        $importsTable    = self::importsTableName();
        $deltasTable     = self::importDeltasTableName();
        $charsetCollate  = $wpdb->get_charset_collate();
        $upgradeFilePath = ABSPATH . 'wp-admin/includes/upgrade.php';

        require_once $upgradeFilePath;

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            country VARCHAR(190) NOT NULL,
            gp INT(10) UNSIGNED NOT NULL DEFAULT 0,
            gold INT(10) UNSIGNED NOT NULL DEFAULT 0,
            silver INT(10) UNSIGNED NOT NULL DEFAULT 0,
            bronze INT(10) UNSIGNED NOT NULL DEFAULT 0,
            titanium INT(10) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY country (country)
        ) {$charsetCollate};";

        dbDelta($sql);

        $importsSql = "CREATE TABLE {$importsTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source_file VARCHAR(190) NOT NULL,
            imported_at DATETIME NOT NULL,
            undone_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'approved',
            valid_rows INT(10) UNSIGNED NOT NULL DEFAULT 0,
            ignored_rows INT(10) UNSIGNED NOT NULL DEFAULT 0,
            countries_created INT(10) UNSIGNED NOT NULL DEFAULT 0,
            countries_updated INT(10) UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            undo_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY source_file (source_file),
            KEY status (status),
            KEY imported_at (imported_at)
        ) {$charsetCollate};";

        dbDelta($importsSql);

        $deltasSql = "CREATE TABLE {$deltasTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            import_id BIGINT(20) UNSIGNED NOT NULL,
            country VARCHAR(190) NOT NULL,
            gp INT(10) UNSIGNED NOT NULL DEFAULT 0,
            gold INT(10) UNSIGNED NOT NULL DEFAULT 0,
            silver INT(10) UNSIGNED NOT NULL DEFAULT 0,
            bronze INT(10) UNSIGNED NOT NULL DEFAULT 0,
            titanium INT(10) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY import_country (import_id, country),
            KEY country (country)
        ) {$charsetCollate};";

        dbDelta($deltasSql);

    }

    private static function seedInitialMedalStandings(): void
    {
        global $wpdb;

        $tableName = self::tableName();

        $now = current_time('mysql');

        $rows = [
            ['country' => 'ARGENTINA', 'gp' => 0, 'gold' => 1, 'silver' => 5, 'bronze' => 10, 'titanium' => 0],
            ['country' => 'COLOMBIA', 'gp' => 0, 'gold' => 1, 'silver' => 2, 'bronze' => 6, 'titanium' => 0],
            ['country' => 'ECUADOR', 'gp' => 0, 'gold' => 0, 'silver' => 1, 'bronze' => 0, 'titanium' => 0],
            ['country' => 'MEXICO', 'gp' => 0, 'gold' => 9, 'silver' => 6, 'bronze' => 16, 'titanium' => 1],
            ['country' => 'PARAGUAY', 'gp' => 0, 'gold' => 0, 'silver' => 1, 'bronze' => 3, 'titanium' => 0],
            ['country' => 'PERU', 'gp' => 2, 'gold' => 3, 'silver' => 4, 'bronze' => 7, 'titanium' => 0],
            ['country' => 'PUERTO RICO', 'gp' => 2, 'gold' => 0, 'silver' => 3, 'bronze' => 2, 'titanium' => 0],
            ['country' => 'VENEZUELA', 'gp' => 1, 'gold' => 2, 'silver' => 0, 'bronze' => 0, 'titanium' => 1],
        ];

        foreach ($rows as $row) {
            $wpdb->insert(
                $tableName,
                [
                    'country'   => $row['country'],
                    'gp'        => $row['gp'],
                    'gold'      => $row['gold'],
                    'silver'    => $row['silver'],
                    'bronze'    => $row['bronze'],
                    'titanium'  => $row['titanium'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s']
            );
        }
    }

    private static function resetPluginData(): void
    {
        global $wpdb;

        $wpdb->query('DELETE FROM ' . self::tableName());
        $wpdb->query('DELETE FROM ' . self::importDeltasTableName());
        $wpdb->query('DELETE FROM ' . self::importsTableName());

        delete_option('fmb_frontend_published_medals');
        delete_option('fmb_frontend_published_at');
    }

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'fmb_country_medals';
    }

    public static function importsTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'fmb_imports';
    }

    public static function importDeltasTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'fmb_import_country_medals';
    }
}
