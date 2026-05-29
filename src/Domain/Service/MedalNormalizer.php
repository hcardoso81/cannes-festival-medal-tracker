<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Domain\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class MedalNormalizer
{
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
        $prize = strtolower(trim(sanitize_text_field(wp_unslash($prize))));

        if (in_array($prize, ['grand prix', 'gold lion'], true)) {
            return 'gold';
        }

        if ('silver lion' === $prize) {
            return 'silver';
        }

        if ('bronze lion' === $prize) {
            return 'bronze';
        }

        return null;
    }
}
