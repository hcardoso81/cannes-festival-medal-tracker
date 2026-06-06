<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Domain\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class MedalNormalizer
{
    private const DEFAULT_ALLOWED_COUNTRIES = [
        'ARGENTINA',
        'BOLIVIA',
        'CHILE',
        'COLOMBIA',
        'COSTA RICA',
        'CUBA',
        'REPUBLICA DOMINICANA',
        'ECUADOR',
        'EL SALVADOR',
        'GUATEMALA',
        'HAITI',
        'HONDURAS',
        'MEXICO',
        'NICARAGUA',
        'PANAMA',
        'PARAGUAY',
        'PERU',
        'PUERTO RICO',
        'URUGUAY',
        'VENEZUELA',
    ];

    private const DEFAULT_PRIZE_SYNONYMS = [
        'gp'     => ['gp', 'grand prix', 'grand prix campaign'],
        'gold'   => ['gold lion', 'gold lion campaign', 'gold'],
        'silver' => ['silver lion', 'silver lion campaign', 'silver'],
        'bronze' => ['bronze lion', 'bronze lion campaign', 'bronze'],
    ];

    public function normalizeCountry(string $country): string
    {
        $country = sanitize_text_field(wp_unslash($country));
        $country = trim(preg_replace('/\s+/', ' ', $country) ?: '');

        if ('' === $country) {
            return '';
        }

        return function_exists('mb_convert_case')
            ? mb_convert_case($country, MB_CASE_TITLE, 'UTF-8')
            : ucwords(strtolower($country));
    }

    public function normalizeAllowedCountries(string $location): array
    {
        $analysis = $this->analyzeLocationCountries($location);

        return $analysis['counted'];
    }

    public function analyzeLocationCountries(string $location): array
    {
        $counted = [];
        $ignored = [];
        $parts   = preg_split('/\s*\/\s*/', $location) ?: [];
        $allowed = $this->getAllowedCountryMap();

        foreach ($parts as $part) {
            $country = $this->normalizeCountry((string) $part);
            $countryKey = $this->countryKey($country);

            if ('' === $country) {
                continue;
            }

            if (isset($allowed[$countryKey])) {
                if (!isset($counted[$countryKey])) {
                    $counted[$countryKey] = $allowed[$countryKey];
                }

                continue;
            }

            if (!isset($ignored[$countryKey])) {
                $ignored[$countryKey] = $country;
            }
        }

        return [
            'counted'            => array_values($counted),
            'ignored'            => array_values($ignored),
            'has_multiple_parts' => count(array_filter($parts, static fn ($part): bool => '' !== trim((string) $part))) > 1,
        ];
    }

    public function normalizePrize(string $prize): ?string
    {
        $prize = $this->normalizePrizeValue($prize);

        foreach ($this->getPrizeSynonyms() as $medalType => $synonyms) {
            foreach ($synonyms as $synonym) {
                if ($prize === $this->normalizePrizeValue((string) $synonym)) {
                    return $medalType;
                }
            }
        }

        return null;
    }

    public function isAllowedCountry(string $country): bool
    {
        return in_array($this->countryKey($country), array_map([$this, 'countryKey'], $this->getAllowedCountries()), true);
    }

    public function getAllowedCountries(): array
    {
        $countries = self::DEFAULT_ALLOWED_COUNTRIES;

        /**
         * Allows projects to customize which countries are counted.
         *
         * Values are compared case-insensitively and accents are ignored.
         */
        $filtered = apply_filters('fmb_allowed_countries', $countries);

        return is_array($filtered) ? array_values($filtered) : $countries;
    }

    public function getCountedCountries(): array
    {
        return array_values($this->getAllowedCountryMap());
    }

    public function getPrizeSynonyms(): array
    {
        $synonyms = self::DEFAULT_PRIZE_SYNONYMS;

        /**
         * Allows projects to extend accepted prize labels.
         *
         * Expected shape:
         * [
         *     'gp' => ['GP', 'Grand Prix Campaign'],
         *     'gold' => ['Gold Lion'],
         * ]
         */
        $filtered = apply_filters('fmb_prize_synonyms', $synonyms);

        return is_array($filtered) ? $filtered : $synonyms;
    }

    private function getAllowedCountryMap(): array
    {
        $countries = [];

        foreach ($this->getAllowedCountries() as $country) {
            $key = $this->countryKey((string) $country);

            if ('' !== $key && !isset($countries[$key])) {
                $countries[$key] = $key;
            }
        }

        return $countries;
    }

    private function normalizePrizeValue(string $prize): string
    {
        $prize = sanitize_text_field(wp_unslash($prize));
        $prize = strtolower(trim($prize));

        return preg_replace('/\s+/', ' ', $prize) ?: '';
    }

    private function countryKey(string $country): string
    {
        $country = sanitize_text_field(wp_unslash($country));
        $country = remove_accents($country);
        $country = strtoupper(trim($country));

        return preg_replace('/\s+/', ' ', $country) ?: '';
    }
}
