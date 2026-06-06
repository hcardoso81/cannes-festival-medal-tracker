<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Infrastructure\Persistence;

use FestivalMedalTracker\Infrastructure\WordPress\DatabaseInstaller;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportRepository
{
    public function createApprovedImport(array $summary, int $userId): int
    {
        global $wpdb;

        $wpdb->insert(
            DatabaseInstaller::importsTableName(),
            [
                'source_file'       => sanitize_file_name((string) ($summary['source_file'] ?? '')),
                'imported_at'       => current_time('mysql'),
                'status'            => 'approved',
                'valid_rows'        => absint($summary['valid_rows'] ?? 0),
                'ignored_rows'      => absint($summary['ignored_rows'] ?? 0),
                'countries_created' => absint($summary['countries_created'] ?? 0),
                'countries_updated' => absint($summary['countries_updated'] ?? 0),
                'user_id'           => absint($userId),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d']
        );

        return (int) $wpdb->insert_id;
    }

    public function addDelta(int $importId, string $country, int $gp, int $gold, int $silver, int $bronze): void
    {
        global $wpdb;

        $wpdb->insert(
            DatabaseInstaller::importDeltasTableName(),
            [
                'import_id' => absint($importId),
                'country'   => $country,
                'gp'        => max(0, $gp),
                'gold'      => max(0, $gold),
                'silver'    => max(0, $silver),
                'bronze'    => max(0, $bronze),
            ],
            ['%d', '%s', '%d', '%d', '%d', '%d']
        );
    }

    public function find(int $importId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . DatabaseInstaller::importsTableName() . ' WHERE id = %d LIMIT 1',
                absint($importId)
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function getDeltas(int $importId): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT country, gp, gold, silver, bronze
                FROM ' . DatabaseInstaller::importDeltasTableName() . '
                WHERE import_id = %d
                ORDER BY country ASC',
                absint($importId)
            ),
            ARRAY_A
        ) ?: [];
    }

    public function listImports(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT i.*,
                    (
                        SELECT COUNT(*)
                        FROM ' . DatabaseInstaller::importDeltasTableName() . ' d
                        WHERE d.import_id = i.id
                    ) AS delta_count,
                    (
                        SELECT COALESCE(SUM(d.gp + d.gold + d.silver + d.bronze), 0)
                        FROM ' . DatabaseInstaller::importDeltasTableName() . ' d
                        WHERE d.import_id = i.id
                    ) AS medal_delta_total
                FROM ' . DatabaseInstaller::importsTableName() . ' i
                WHERE i.status = %s
                ORDER BY i.imported_at DESC, i.id DESC
                LIMIT %d',
                'approved',
                max(1, $limit)
            ),
            ARRAY_A
        ) ?: [];
    }

    public function listSourceFiles(string $status = 'approved'): array
    {
        global $wpdb;

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT DISTINCT source_file
                FROM ' . DatabaseInstaller::importsTableName() . '
                WHERE status = %s
                ORDER BY source_file ASC',
                $status
            )
        );

        return is_array($rows) ? array_map('strval', $rows) : [];
    }

    public function deleteImport(int $importId): int
    {
        global $wpdb;

        $deletedDeltas = $wpdb->delete(
            DatabaseInstaller::importDeltasTableName(),
            ['import_id' => absint($importId)],
            ['%d']
        );
        $deletedImport = $wpdb->delete(
            DatabaseInstaller::importsTableName(),
            ['id' => absint($importId)],
            ['%d']
        );

        return (is_int($deletedDeltas) ? $deletedDeltas : 0) + (is_int($deletedImport) ? $deletedImport : 0);
    }

    public function deleteAll(): int
    {
        global $wpdb;

        $deletedDeltas = $wpdb->query('DELETE FROM ' . DatabaseInstaller::importDeltasTableName());
        $deletedImports = $wpdb->query('DELETE FROM ' . DatabaseInstaller::importsTableName());

        return (is_int($deletedDeltas) ? $deletedDeltas : 0) + (is_int($deletedImports) ? $deletedImports : 0);
    }
}
