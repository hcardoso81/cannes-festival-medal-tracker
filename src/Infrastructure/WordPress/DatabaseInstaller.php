<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Infrastructure\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

final class DatabaseInstaller
{
    private const SCHEMA_VERSION = '1.2.0';
    private const SCHEMA_OPTION = 'fmb_schema_version';

    public static function activate(): void
    {
        self::install();
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
            PRIMARY KEY  (id),
            UNIQUE KEY import_country (import_id, country),
            KEY country (country)
        ) {$charsetCollate};";

        dbDelta($deltasSql);
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
