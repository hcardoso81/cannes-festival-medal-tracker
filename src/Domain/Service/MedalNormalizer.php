<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Domain\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class MedalNormalizer
{
    private const DEFAULT_ALLOWED_COUNTRIES = [
        'PERU',
        'COLOMBIA',
        'PUERTO RICO',
        'ECUADOR',
        'CHILE',
        'MEXICO',
        'COSTA RICA',
        'ARGENTINA',
        'HONDURAS',
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
