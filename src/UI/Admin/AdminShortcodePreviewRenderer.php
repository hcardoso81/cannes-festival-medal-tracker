<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminShortcodePreviewRenderer
{
    public function render(array $rows): void
    {
        ?>
        <div class="fmb-shortcode-previews">
            <details class="fmb-accordion">
                <summary><?php echo esc_html__('[medalByCountry] - Total por pais', 'cannes-festival-medal-tracker'); ?></summary>
                <?php $this->renderCountryTotals($rows); ?>
            </details>
            <details class="fmb-accordion">
                <summary><?php echo esc_html__('[medalsTotal] - Totales acumulados', 'cannes-festival-medal-tracker'); ?></summary>
                <?php $this->renderMedalTotals($rows); ?>
            </details>
            <details class="fmb-accordion">
                <summary><?php echo esc_html__('[medalByCountryDetail] - Detalle por pais', 'cannes-festival-medal-tracker'); ?></summary>
                <?php $this->renderCountryDetails($rows); ?>
            </details>
        </div>
        <?php
    }

    private function renderCountryTotals(array $rows): void
    {
        if (empty($rows)) {
            $this->renderEmpty();
            return;
        }

        $rows = $this->sortByMedals($rows);
        ?>
        <table class="widefat striped fmb-admin-standings">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Pais', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total de medallas', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html((string) ($row['country'] ?? '')); ?></th>
                        <td><?php echo esc_html((string) absint($row['total'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderMedalTotals(array $rows): void
    {
        if (empty($rows)) {
            $this->renderEmpty();
            return;
        }

        $totals = ['gp' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0, 'titanium' => 0];

        foreach ($rows as $row) {
            $totals['gp'] += absint($row['gp'] ?? 0);
            $totals['gold'] += absint($row['gold'] ?? 0);
            $totals['silver'] += absint($row['silver'] ?? 0);
            $totals['bronze'] += absint($row['bronze'] ?? 0);
            $totals['titanium'] += absint($row['titanium'] ?? 0);
        }

        ?>
        <table class="widefat striped fmb-admin-standings">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Tipo de medalla', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['gp', 'gold', 'silver', 'bronze', 'titanium'] as $key) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($this->medalLabel($key)); ?></th>
                        <td><?php echo esc_html((string) absint($totals[$key])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderCountryDetails(array $rows): void
    {
        if (empty($rows)) {
            $this->renderEmpty();
            return;
        }

        $rows = $this->sortByMedals($rows);
        ?>
        <table class="widefat striped fmb-admin-standings">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Pais', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Oro', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Plata', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Bronce', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Titanio', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html((string) ($row['country'] ?? '')); ?></th>
                        <td><?php echo esc_html((string) absint($row['gp'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($row['gold'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($row['silver'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($row['bronze'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($row['titanium'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($row['total'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function sortByMedals(array $rows): array
    {
        usort(
            $rows,
            static function (array $a, array $b): int {
                return absint($b['gp'] ?? 0) <=> absint($a['gp'] ?? 0)
                    ?: absint($b['gold'] ?? 0) <=> absint($a['gold'] ?? 0)
                    ?: absint($b['silver'] ?? 0) <=> absint($a['silver'] ?? 0)
                    ?: absint($b['bronze'] ?? 0) <=> absint($a['bronze'] ?? 0)
                    ?: absint($b['titanium'] ?? 0) <=> absint($a['titanium'] ?? 0)
                    ?: absint($b['total'] ?? 0) <=> absint($a['total'] ?? 0)
                    ?: strcmp((string) ($a['country'] ?? ''), (string) ($b['country'] ?? ''));
            }
        );

        return $rows;
    }

    private function renderEmpty(): void
    {
        echo '<p class="fmb-empty-preview">' . esc_html__('Todavia no se importaron medallas.', 'cannes-festival-medal-tracker') . '</p>';
    }

    private function medalLabel(string $medal): string
    {
        $labels = [
            'gp'     => __('GP', 'cannes-festival-medal-tracker'),
            'gold'   => __('Oro', 'cannes-festival-medal-tracker'),
            'silver' => __('Plata', 'cannes-festival-medal-tracker'),
            'bronze' => __('Bronce', 'cannes-festival-medal-tracker'),
            'titanium' => __('Titanio', 'cannes-festival-medal-tracker'),
        ];

        return (string) ($labels[$medal] ?? $medal);
    }
}
