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

    public function preview(string $filePath): array
    {
        $rows    = $this->reader->readRows($filePath);
        $summary = [
            'total_rows'        => count($rows),
            'valid_rows'        => 0,
            'ignored_rows'      => 0,
            'countries_created' => 0,
            'countries_updated' => 0,
            'errors'            => [],
            'ignored_details'   => [],
            'imported'          => [],
            'preview'           => true,
        ];

        $accumulator = [];

        foreach ($rows as $index => $row) {
            $rawLocation = (string) ($row['location'] ?? '');
            $rawPrize    = (string) ($row['prize'] ?? '');
            $rowNumber   = !empty($row['row_number']) ? (int) $row['row_number'] : $index + 2;
            $country     = $this->normalizer->normalizeCountry($rawLocation);
            $medal       = $this->normalizer->normalizePrize($rawPrize);

            if ('' === $country || null === $medal || !$this->normalizer->isAllowedCountry($country)) {
                if ('' === $country) {
                    $reason = __('missing or invalid location', 'cannes-festival-medal-tracker');
                } elseif (null === $medal) {
                    $reason = __('unrecognized prize', 'cannes-festival-medal-tracker');
                } else {
                    $reason = __('country is not in the allowed list', 'cannes-festival-medal-tracker');
                }

                $summary['ignored_rows']++;
                $summary['errors'][] = sprintf(
                    /* translators: 1: spreadsheet row number, 2: location value, 3: prize value, 4: reason. */
                    __('Row %1$d ignored. Location: "%2$s". Prize: "%3$s". Reason: %4$s.', 'cannes-festival-medal-tracker'),
                    $rowNumber,
                    $this->cleanCellForSummary($rawLocation),
                    $this->cleanCellForSummary($rawPrize),
                    $reason
                );
                $summary['ignored_details'][] = [
                    'row'          => $rowNumber,
                    'raw_location' => $this->cleanCellForSummary($rawLocation),
                    'raw_prize'    => $this->cleanCellForSummary($rawPrize),
                    'reason'       => $reason,
                ];
                continue;
            }

            if (!isset($accumulator[$country])) {
                $accumulator[$country] = ['gp' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0];
            }

            $accumulator[$country][$medal]++;
            $summary['valid_rows']++;
        }

        foreach ($accumulator as $country => $medals) {
            $summary['imported'][] = [
                'country' => $country,
                'medals'  => $medals,
            ];
        }

        return $summary;
    }

    public function getAllowedCountries(): array
    {
        return $this->normalizer->getAllowedCountries();
    }

    public function getPrizeSynonyms(): array
    {
        return $this->normalizer->getPrizeSynonyms();
    }

    public function commitPreview(array $preview): array
    {
        $summary = $preview;
        $summary['countries_created'] = 0;
        $summary['countries_updated'] = 0;
        $summary['preview']           = false;
        $summary['committed']         = true;

        foreach ($summary['imported'] ?? [] as $item) {
            if (empty($item['country']) || !is_array($item['medals'] ?? null)) {
                continue;
            }

            $country = (string) $item['country'];
            $medals  = $item['medals'];
            $result = $this->repository->upsertAndIncrement(
                $country,
                (int) ($medals['gp'] ?? 0),
                (int) ($medals['gold'] ?? 0),
                (int) ($medals['silver'] ?? 0),
                (int) ($medals['bronze'] ?? 0)
            );

            if ('created' === $result) {
                $summary['countries_created']++;
            } else {
                $summary['countries_updated']++;
            }
        }

        return $summary;
    }

    private function cleanCellForSummary(string $value): string
    {
        $value = sanitize_text_field(wp_unslash($value));
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');

        return '' === $value ? '(empty)' : $value;
    }
}
