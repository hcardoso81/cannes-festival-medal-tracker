<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Infrastructure\Persistence;

if (!defined('ABSPATH')) {
    exit;
}

final class FrontendPublicationRepository
{
    private const ENABLED_OPTION = 'fmb_frontend_shortcodes_enabled';
    private const SNAPSHOT_OPTION = 'fmb_frontend_published_medals';
    private const PUBLISHED_AT_OPTION = 'fmb_frontend_published_at';

    public function isEnabled(): bool
    {
        return '1' === (string) get_option(self::ENABLED_OPTION, '1');
    }

    public function setEnabled(bool $enabled): void
    {
        update_option(self::ENABLED_OPTION, $enabled ? '1' : '0', false);
    }

    public function getPublishedAt(): string
    {
        return (string) get_option(self::PUBLISHED_AT_OPTION, '');
    }

    public function getPublishedRows(): array
    {
        $rows = get_option(self::SNAPSHOT_OPTION, []);

        if (!is_array($rows)) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    public function publish(array $rows): void
    {
        update_option(self::SNAPSHOT_OPTION, $this->normalizeRows($rows), false);
        update_option(self::PUBLISHED_AT_OPTION, current_time('mysql'), false);
    }

    public function clearPublishedData(): int
    {
        $deletedRows = count($this->getPublishedRows());

        delete_option(self::SNAPSHOT_OPTION);
        delete_option(self::PUBLISHED_AT_OPTION);

        return $deletedRows;
    }

    public function getCountryTotals(): array
    {
        return array_map(
            static function (array $row): array {
                return [
                    'country' => (string) $row['country'],
                    'total'   => absint($row['total'] ?? 0),
                ];
            },
            $this->getPublishedRows()
        );
    }

    public function getMedalTotals(): array
    {
        $totals = ['gp' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0];

        foreach ($this->getPublishedRows() as $row) {
            $totals['gp'] += absint($row['gp'] ?? 0);
            $totals['gold'] += absint($row['gold'] ?? 0);
            $totals['silver'] += absint($row['silver'] ?? 0);
            $totals['bronze'] += absint($row['bronze'] ?? 0);
        }

        return $totals;
    }

    public function getPendingChanges(array $liveRows): array
    {
        $published = $this->indexRowsByCountry($this->getPublishedRows());
        $live = $this->indexRowsByCountry($liveRows);
        $countries = array_unique(array_merge(array_keys($published), array_keys($live)));
        sort($countries, SORT_NATURAL);
        $changes = [];

        foreach ($countries as $country) {
            $current = $published[$country] ?? $this->emptyCountryRow($country);
            $next = $live[$country] ?? $this->emptyCountryRow($country);
            $delta = [
                'country' => $country,
                'gp'      => (int) $next['gp'] - (int) $current['gp'],
                'gold'    => (int) $next['gold'] - (int) $current['gold'],
                'silver'  => (int) $next['silver'] - (int) $current['silver'],
                'bronze'  => (int) $next['bronze'] - (int) $current['bronze'],
            ];
            $delta['total'] = $delta['gp'] + $delta['gold'] + $delta['silver'] + $delta['bronze'];

            if (0 !== $delta['total'] || 0 !== $delta['gp'] || 0 !== $delta['gold'] || 0 !== $delta['silver'] || 0 !== $delta['bronze']) {
                $changes[] = $delta;
            }
        }

        usort(
            $changes,
            static function (array $a, array $b): int {
                return abs((int) $b['total']) <=> abs((int) $a['total'])
                    ?: strcmp((string) $a['country'], (string) $b['country']);
            }
        );

        return $changes;
    }

    private function normalizeRows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['country'])) {
                continue;
            }

            $country = sanitize_text_field((string) $row['country']);
            $gp = absint($row['gp'] ?? 0);
            $gold = absint($row['gold'] ?? 0);
            $silver = absint($row['silver'] ?? 0);
            $bronze = absint($row['bronze'] ?? 0);
            $normalized[] = [
                'country' => $country,
                'gp'      => $gp,
                'gold'    => $gold,
                'silver'  => $silver,
                'bronze'  => $bronze,
                'total'   => $gp + $gold + $silver + $bronze,
            ];
        }

        usort(
            $normalized,
            static function (array $a, array $b): int {
                return (int) $b['gp'] <=> (int) $a['gp']
                    ?: (int) $b['gold'] <=> (int) $a['gold']
                    ?: (int) $b['silver'] <=> (int) $a['silver']
                    ?: (int) $b['bronze'] <=> (int) $a['bronze']
                    ?: (int) $b['total'] <=> (int) $a['total']
                    ?: strcmp((string) $a['country'], (string) $b['country']);
            }
        );

        return $normalized;
    }

    private function indexRowsByCountry(array $rows): array
    {
        $indexed = [];

        foreach ($this->normalizeRows($rows) as $row) {
            $indexed[(string) $row['country']] = $row;
        }

        return $indexed;
    }

    private function emptyCountryRow(string $country): array
    {
        return [
            'country' => $country,
            'gp'      => 0,
            'gold'    => 0,
            'silver'  => 0,
            'bronze'  => 0,
            'total'   => 0,
        ];
    }
}
