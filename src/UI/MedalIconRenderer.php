<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI;

if (!defined('ABSPATH')) {
    exit;
}

final class MedalIconRenderer
{
    public static function render(string $medal): string
    {
        $icons = [
            'gp'     => ['file' => 'gp.png', 'label' => __('GP', 'cannes-festival-medal-tracker')],
            'gold'   => ['file' => 'gold.png', 'label' => __('Oro', 'cannes-festival-medal-tracker')],
            'silver' => ['file' => 'silver.png', 'label' => __('Plata', 'cannes-festival-medal-tracker')],
            'bronze' => ['file' => 'bronze.png', 'label' => __('Bronce', 'cannes-festival-medal-tracker')],
            'titanium' => ['file' => 'titanium.png', 'label' => __('Titanio', 'cannes-festival-medal-tracker')],
            'total'  => ['file' => 'total.png', 'label' => __('Total', 'cannes-festival-medal-tracker')],
        ];

        if (!isset($icons[$medal])) {
            return esc_html($medal);
        }

        $icon = $icons[$medal];

        return sprintf(
            '<img class="fmb-medal-icon fmb-medal-icon--%1$s" src="%2$s" alt="%3$s" title="%3$s">',
            esc_attr($medal),
            esc_url(self::iconUrl($icon['file'])),
            esc_attr((string) $icon['label'])
        );
    }

    private static function iconUrl(string $file): string
    {
        $relativePath = 'assets/images/medals/' . $file;
        $absolutePath = FMB_PATH . $relativePath;
        $version      = file_exists($absolutePath) ? (string) filemtime($absolutePath) : FMB_VERSION;

        return add_query_arg('ver', $version, FMB_URL . $relativePath);
    }
}
