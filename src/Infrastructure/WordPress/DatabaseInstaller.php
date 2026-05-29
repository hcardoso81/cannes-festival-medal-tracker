<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Infrastructure\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

final class DatabaseInstaller
{
    private const SCHEMA_VERSION = '1.1.0';
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
    }

    public static function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'fmb_country_medals';
    }
}
