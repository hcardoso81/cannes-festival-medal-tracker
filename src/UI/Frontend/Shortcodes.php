<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Frontend;

use FestivalMedalTracker\Infrastructure\Persistence\FrontendPublicationRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class Shortcodes
{
    private FrontendPublicationRepository $publication;

    public function __construct(FrontendPublicationRepository $publication)
    {
        $this->publication = $publication;
    }

    public function registerHooks(): void
    {
        add_shortcode('medalByCountry', [$this, 'renderMedalByCountry']);
        add_shortcode('medalbycountry', [$this, 'renderMedalByCountry']);
        add_shortcode('medalsTotal', [$this, 'renderMedalsTotal']);
        add_shortcode('medalstotal', [$this, 'renderMedalsTotal']);
        add_shortcode('medalByCountryDetail', [$this, 'renderMedalByCountryDetail']);
        add_shortcode('medalbycountrydetail', [$this, 'renderMedalByCountryDetail']);
    }

    public function renderMedalByCountry(): string
    {
        if (!$this->publication->isEnabled()) {
            return '';
        }

        $this->enqueueAssets();
        $rows = $this->publication->getCountryTotals();

        if (empty($rows)) {
            return $this->emptyMessage();
        }

        ob_start();
        ?>
        <table class="fmb-table fmb-table-country-total">
            <thead>
                <tr>
                    <th scope="col"></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html((string) $row['country']); ?></th>
                        <td><?php echo esc_html($this->formatMedalValue($row['total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        return $this->wrapShortcode((string) ob_get_clean());
    }

    public function renderMedalsTotal(): string
    {
        if (!$this->publication->isEnabled()) {
            return '';
        }

        $this->enqueueAssets();
        $publishedRows = $this->publication->getPublishedRows();

        if (empty($publishedRows)) {
            return $this->emptyMessage();
        }

        $totals = $this->publication->getMedalTotals();

        ob_start();
        ?>
        <table class="fmb-table fmb-table-medal-total">
            <thead>
                <tr>
                    <th scope="col"></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                    <td><?php echo esc_html($this->formatMedalValue($totals['gp'])); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Oro', 'cannes-festival-medal-tracker'); ?></th>
                    <td><?php echo esc_html($this->formatMedalValue($totals['gold'])); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Plata', 'cannes-festival-medal-tracker'); ?></th>
                    <td><?php echo esc_html($this->formatMedalValue($totals['silver'])); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Bronce', 'cannes-festival-medal-tracker'); ?></th>
                    <td><?php echo esc_html($this->formatMedalValue($totals['bronze'])); ?></td>
                </tr>
            </tbody>
        </table>
        <?php

        return $this->wrapShortcode((string) ob_get_clean());
    }

    public function renderMedalByCountryDetail(): string
    {
        if (!$this->publication->isEnabled()) {
            return '';
        }

        $this->enqueueAssets();
        $rows = $this->publication->getPublishedRows();

        if (empty($rows)) {
            return $this->emptyMessage();
        }

        ob_start();
        ?>
        <table class="fmb-table fmb-table-country-detail">
            <thead>
                <tr>
                    <th scope="col"></th>
                    <th scope="col"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Oro', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Plata', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Bronce', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html((string) $row['country']); ?></th>
                        <td><?php echo esc_html($this->formatMedalValue($row['gp'])); ?></td>
                        <td><?php echo esc_html($this->formatMedalValue($row['gold'])); ?></td>
                        <td><?php echo esc_html($this->formatMedalValue($row['silver'])); ?></td>
                        <td><?php echo esc_html($this->formatMedalValue($row['bronze'])); ?></td>
                        <td><?php echo esc_html($this->formatMedalValue($row['total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        return $this->wrapShortcode((string) ob_get_clean());
    }

    private function enqueueAssets(): void
    {
        if (!$this->publication->isEnabled()) {
            return;
        }

        wp_enqueue_style(
            'fmb-frontend',
            FMB_URL . 'assets/css/frontend.css',
            [],
            FMB_VERSION
        );
    }

    private function wrapShortcode(string $content): string
    {
        ob_start();
        ?>
        <div class="fmb-shortcode">
            <div class="block-head block-head-d is-left term-color-1205">
                <h4 class="heading"><?php echo esc_html__('Medallero Cannes', 'cannes-festival-medal-tracker'); ?></h4>
            </div>
            <?php echo $content; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function formatMedalValue($value): string
    {
        $number = absint($value);

        return 0 === $number ? '' : (string) $number;
    }

    private function emptyMessage(): string
    {
        if (!$this->publication->isEnabled()) {
            return '';
        }

        return $this->wrapShortcode('<p class="fmb-empty">' . esc_html__('Todavia no se importaron medallas.', 'cannes-festival-medal-tracker') . '</p>');
    }
}
