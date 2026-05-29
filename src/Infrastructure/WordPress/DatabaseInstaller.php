<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Infrastructure\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

final class DatabaseInstaller
{
    public static function activate(): void
    {
        global $wpdb;

        $tableName       = self::tableName();
        $charsetCollate  = $wpdb->get_charset_collate();
        $upgradeFilePath = ABSPATH . 'wp-admin/includes/upgrade.php';

        require_once $upgradeFilePath;

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            country VARCHAR(190) NOT NULL,
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
