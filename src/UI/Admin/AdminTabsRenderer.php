<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminTabsRenderer
{
    public function render(string $active): void
    {
        $tabs = [
            'medals'   => [
                'label' => __('Medallero', 'cannes-festival-medal-tracker'),
                'url'   => admin_url('admin.php?page=fmb-medal-tracker'),
            ],
            'frontend' => [
                'label' => __('Frontend', 'cannes-festival-medal-tracker'),
                'url'   => admin_url('admin.php?page=fmb-medal-tracker-frontend'),
            ],
        ];
        ?>
        <nav class="nav-tab-wrapper fmb-admin-tabs" aria-label="<?php echo esc_attr__('Secciones del medallero', 'cannes-festival-medal-tracker'); ?>">
            <?php foreach ($tabs as $key => $tab) : ?>
                <a
                    class="<?php echo esc_attr('nav-tab' . ($active === $key ? ' nav-tab-active' : '')); ?>"
                    href="<?php echo esc_url((string) $tab['url']); ?>"
                >
                    <?php echo esc_html((string) $tab['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }
}
