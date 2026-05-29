<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Frontend;

use FestivalMedalTracker\Infrastructure\Persistence\MedalRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class Shortcodes
{
    private MedalRepository $repository;

    public function __construct(MedalRepository $repository)
    {
        $this->repository = $repository;
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
        $this->enqueueAssets();
        $rows = $this->repository->getCountryTotals();

        if (empty($rows)) {
            return $this->emptyMessage();
        }

        ob_start();
        ?>
        <table class="fmb-table fmb-table-country-total">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Country', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total medals', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html((string) $row['country']); ?></th>
                        <td><?php echo esc_html((string) absint($row['total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        return (string) ob_get_clean();
    }

    public function renderMedalsTotal(): string
    {
        $this->enqueueAssets();
        $totals = $this->repository->getMedalTotals();

        ob_start();
        ?>
        <table class="fmb-table fmb-table-medal-total">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Medal type', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                    <td><?php echo esc_html((string) absint($totals['gp'])); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Gold', 'cannes-festival-medal-tracker'); ?></th>
                    <td><?php echo esc_html((string) absint($totals['gold'])); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Silver', 'cannes-festival-medal-tracker'); ?></th>
                    <td><?php echo esc_html((string) absint($totals['silver'])); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Bronze', 'cannes-festival-medal-tracker'); ?></th>
                    <td><?php echo esc_html((string) absint($totals['bronze'])); ?></td>
                </tr>
            </tbody>
        </table>
        <?php

        return (string) ob_get_clean();
    }

    public function renderMedalByCountryDetail(): string
    {
        $this->enqueueAssets();
        $rows = $this->repository->getCountryDetails();

        if (empty($rows)) {
            return $this->emptyMessage();
        }

        ob_start();
        ?>
        <table class="fmb-table fmb-table-country-detail">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Country', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Gold', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Silver', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Bronze', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html((string) $row['country']); ?></th>
                        <td><?php echo esc_html((string) absint($row['gp'])); ?></td>
                        <td><?php echo esc_html((string) absint($row['gold'])); ?></td>
                        <td><?php echo esc_html((string) absint($row['silver'])); ?></td>
                        <td><?php echo esc_html((string) absint($row['bronze'])); ?></td>
                        <td><?php echo esc_html((string) absint($row['total'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        return (string) ob_get_clean();
    }

    private function enqueueAssets(): void
    {
        wp_enqueue_style(
            'fmb-frontend',
            FMB_URL . 'assets/css/frontend.css',
            [],
            FMB_VERSION
        );
    }

    private function emptyMessage(): string
    {
        return '<p class="fmb-empty">' . esc_html__('No medals have been imported yet.', 'cannes-festival-medal-tracker') . '</p>';
    }
}
