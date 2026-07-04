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

    public function upsertAndIncrement(string $country, int $gp, int $gold, int $silver, int $bronze, int $titanium): string
    {
        global $wpdb;

        $existing = $this->findByCountry($country);
        $now      = current_time('mysql');

        if (null === $existing) {
            $wpdb->insert(
                DatabaseInstaller::tableName(),
                [
                    'country'    => $country,
                    'gp'         => max(0, $gp),
                    'gold'       => max(0, $gold),
                    'silver'     => max(0, $silver),
                    'bronze'     => max(0, $bronze),
                    'titanium'   => max(0, $titanium),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s']
            );

            return 'created';
        }

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DatabaseInstaller::tableName() . '
                SET gp = gp + %d,
                    gold = gold + %d,
                    silver = silver + %d,
                    bronze = bronze + %d,
                    titanium = titanium + %d,
                    updated_at = %s
                WHERE country = %s',
                max(0, $gp),
                max(0, $gold),
                max(0, $silver),
                max(0, $bronze),
                max(0, $titanium),
                $now,
                $country
            )
        );

        return 'updated';
    }

    public function decrement(string $country, int $gp, int $gold, int $silver, int $bronze, int $titanium): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DatabaseInstaller::tableName() . '
                SET gp = GREATEST(gp - %d, 0),
                    gold = GREATEST(gold - %d, 0),
                    silver = GREATEST(silver - %d, 0),
                    bronze = GREATEST(bronze - %d, 0),
                    titanium = GREATEST(titanium - %d, 0),
                    updated_at = %s
                WHERE country = %s',
                max(0, $gp),
                max(0, $gold),
                max(0, $silver),
                max(0, $bronze),
                max(0, $titanium),
                current_time('mysql'),
                $country
            )
        );

        $wpdb->query(
            'DELETE FROM ' . DatabaseInstaller::tableName() . '
            WHERE gp = 0 AND gold = 0 AND silver = 0 AND bronze = 0 AND titanium = 0'
        );
    }

    public function getCountryTotals(): array
    {
        global $wpdb;

        $tableName = DatabaseInstaller::tableName();

        return $wpdb->get_results(
            "SELECT country, (gp + gold + silver + bronze + titanium) AS total
            FROM {$tableName}
            ORDER BY gp DESC, gold DESC, silver DESC, bronze DESC, titanium DESC, total DESC, country ASC",
            ARRAY_A
        ) ?: [];
    }

    public function getMedalTotals(): array
    {
        global $wpdb;

        $tableName = DatabaseInstaller::tableName();
        $row       = $wpdb->get_row(
            "SELECT
                COALESCE(SUM(gp), 0) AS gp,
                COALESCE(SUM(gold), 0) AS gold,
                COALESCE(SUM(silver), 0) AS silver,
                COALESCE(SUM(bronze), 0) AS bronze,
                COALESCE(SUM(titanium), 0) AS titanium
            FROM {$tableName}",
            ARRAY_A
        );

        return is_array($row) ? $row : ['gp' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0, 'titanium' => 0];
    }

    public function getCountryDetails(): array
    {
        global $wpdb;

        $tableName = DatabaseInstaller::tableName();

        return $wpdb->get_results(
            "SELECT country, gp, gold, silver, bronze, titanium, (gp + gold + silver + bronze + titanium) AS total
            FROM {$tableName}
            ORDER BY gp DESC, gold DESC, silver DESC, bronze DESC, titanium DESC, total DESC, country ASC",
            ARRAY_A
        ) ?: [];
    }

    public function deleteAll(): int
    {
        global $wpdb;

        $tableName = DatabaseInstaller::tableName();
        $deleted   = $wpdb->query("DELETE FROM {$tableName}");

        return is_int($deleted) ? $deleted : 0;
    }
}
