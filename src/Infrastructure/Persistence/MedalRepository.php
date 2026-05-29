<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Infrastructure\Persistence;

use FestivalMedalTracker\Infrastructure\WordPress\DatabaseInstaller;

if (!defined('ABSPATH')) {
    exit;
}

final class MedalRepository
{
    public function findByCountry(string $country): ?array
    {
        global $wpdb;

        $tableName = DatabaseInstaller::tableName();
        $row       = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE country = %s LIMIT 1",
                $country
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function upsertAndIncrement(string $country, int $gold, int $silver, int $bronze): string
    {
        global $wpdb;

        $existing = $this->findByCountry($country);
        $now      = current_time('mysql');

        if (null === $existing) {
            $wpdb->insert(
                DatabaseInstaller::tableName(),
                [
                    'country'    => $country,
                    'gold'       => max(0, $gold),
                    'silver'     => max(0, $silver),
                    'bronze'     => max(0, $bronze),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%s', '%d', '%d', '%d', '%s', '%s']
            );

            return 'created';
        }

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DatabaseInstaller::tableName() . '
                SET gold = gold + %d,
                    silver = silver + %d,
                    bronze = bronze + %d,
                    updated_at = %s
                WHERE country = %s',
                max(0, $gold),
                max(0, $silver),
                max(0, $bronze),
                $now,
                $country
            )
        );

        return 'updated';
    }

    public function getCountryTotals(): array
    {
        global $wpdb;

        $tableName = DatabaseInstaller::tableName();

        return $wpdb->get_results(
            "SELECT country, (gold + silver + bronze) AS total
            FROM {$tableName}
            ORDER BY total DESC, country ASC",
            ARRAY_A
        ) ?: [];
    }

    public function getMedalTotals(): array
    {
        global $wpdb;

        $tableName = DatabaseInstaller::tableName();
        $row       = $wpdb->get_row(
            "SELECT
                COALESCE(SUM(gold), 0) AS gold,
                COALESCE(SUM(silver), 0) AS silver,
                COALESCE(SUM(bronze), 0) AS bronze
            FROM {$tableName}",
            ARRAY_A
        );

        return is_array($row) ? $row : ['gold' => 0, 'silver' => 0, 'bronze' => 0];
    }

    public function getCountryDetails(): array
    {
        global $wpdb;

        $tableName = DatabaseInstaller::tableName();

        return $wpdb->get_results(
            "SELECT country, gold, silver, bronze, (gold + silver + bronze) AS total
            FROM {$tableName}
            ORDER BY gold DESC, silver DESC, bronze DESC, country ASC",
            ARRAY_A
        ) ?: [];
    }
}
