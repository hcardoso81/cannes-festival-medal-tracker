<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Application;

use FestivalMedalTracker\Domain\Service\MedalNormalizer;
use FestivalMedalTracker\Infrastructure\Excel\PhpSpreadsheetExcelReader;
use FestivalMedalTracker\Infrastructure\Persistence\MedalRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportMedalsUseCase
{
    private PhpSpreadsheetExcelReader $reader;

    private MedalNormalizer $normalizer;

    private MedalRepository $repository;

    public function __construct(
        PhpSpreadsheetExcelReader $reader,
        MedalNormalizer $normalizer,
        MedalRepository $repository
    ) {
        $this->reader     = $reader;
        $this->normalizer = $normalizer;
        $this->repository = $repository;
    }

    public function import(string $filePath): array
    {
        $rows    = $this->reader->readRows($filePath);
        $summary = [
            'total_rows'        => count($rows),
            'valid_rows'        => 0,
            'ignored_rows'      => 0,
            'countries_created' => 0,
            'countries_updated' => 0,
            'errors'            => [],
            'imported'          => [],
        ];

        $accumulator = [];

        foreach ($rows as $index => $row) {
            $country = $this->normalizer->normalizeCountry((string) ($row['location'] ?? ''));
            $medal   = $this->normalizer->normalizePrize((string) ($row['prize'] ?? ''));

            if ('' === $country || null === $medal) {
                $summary['ignored_rows']++;
                $summary['errors'][] = sprintf(
                    /* translators: %d: spreadsheet row number. */
                    __('Row %d was ignored because location or prize is invalid.', 'cannes-festival-medal-tracker'),
                    $index + 2
                );
                continue;
            }

            if (!isset($accumulator[$country])) {
                $accumulator[$country] = ['gold' => 0, 'silver' => 0, 'bronze' => 0];
            }

            $accumulator[$country][$medal]++;
            $summary['valid_rows']++;
        }

        foreach ($accumulator as $country => $medals) {
            $result = $this->repository->upsertAndIncrement(
                $country,
                (int) $medals['gold'],
                (int) $medals['silver'],
                (int) $medals['bronze']
            );

            if ('created' === $result) {
                $summary['countries_created']++;
            } else {
                $summary['countries_updated']++;
            }

            $summary['imported'][] = [
                'country' => $country,
                'medals'  => $medals,
            ];
        }

        return $summary;
    }
}
