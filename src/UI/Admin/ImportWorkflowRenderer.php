<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

use FestivalMedalTracker\Infrastructure\Persistence\MedalRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportWorkflowRenderer
{
    private AdminNoticeRenderer $notices;

    private ImportPreviewRenderer $preview;

    private ImportLogRenderer $log;

    public function __construct(MedalRepository $repository)
    {
        $this->notices = new AdminNoticeRenderer();
        $this->preview = new ImportPreviewRenderer($repository);
        $this->log     = new ImportLogRenderer();
    }

    public function renderNotice(array $notice): void
    {
        $this->notices->render($notice);
    }

    public function renderPendingPreview(array $preview, array $config): void
    {
        $this->preview->render($preview, $config);
    }

    public function renderImportLog(array $entries, array $pendingPreview, array $config): void
    {
        $this->log->render($entries, $pendingPreview, $config);
    }
}
