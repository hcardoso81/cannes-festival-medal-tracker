<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

use FestivalMedalTracker\Application\ImportMedalsUseCase;
use FestivalMedalTracker\Infrastructure\Persistence\MedalRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPageRenderer
{
    private AdminWidgetsRenderer $widgets;

    private ImportWorkflowRenderer $importWorkflow;

    private AdminTabsRenderer $tabs;

    public function __construct(ImportMedalsUseCase $importer, MedalRepository $repository)
    {
        $this->widgets        = new AdminWidgetsRenderer($importer);
        $this->importWorkflow = new ImportWorkflowRenderer($repository);
        $this->tabs           = new AdminTabsRenderer();
    }

    public function renderPage(array $notice, array $preview, array $rows, array $importLog, array $config): void
    {
        ?>
        <div class="wrap fmb-admin-page">
            <h1><?php echo esc_html__('Medallero del Festival', 'cannes-festival-medal-tracker'); ?></h1>
            <?php $this->tabs->render('medals'); ?>

            <?php $this->importWorkflow->renderNotice($notice); ?>

            <?php $this->widgets->renderCountingRules(); ?>

            <?php $this->widgets->renderUploadForm($config); ?>

            <?php $this->importWorkflow->renderPendingPreview($preview, $config); ?>

            <h2><?php echo esc_html__('Medallero actual', 'cannes-festival-medal-tracker'); ?></h2>
            <?php $this->widgets->renderCurrentTable($rows); ?>

            <?php $this->widgets->renderShortcodePreviews($rows); ?>

            <?php $this->importWorkflow->renderImportLog($importLog, $preview, $config); ?>

            <?php $this->widgets->renderResetZone($config); ?>
        </div>
        <?php
    }
}
