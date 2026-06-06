<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Bootstrap;

use FestivalMedalTracker\Application\ImportMedalsUseCase;
use FestivalMedalTracker\Domain\Service\MedalNormalizer;
use FestivalMedalTracker\Infrastructure\Excel\PhpSpreadsheetExcelReader;
use FestivalMedalTracker\Infrastructure\Logging\FileLogger;
use FestivalMedalTracker\Infrastructure\Persistence\FrontendPublicationRepository;
use FestivalMedalTracker\Infrastructure\Persistence\ImportRepository;
use FestivalMedalTracker\Infrastructure\Persistence\MedalRepository;
use FestivalMedalTracker\Infrastructure\WordPress\DatabaseInstaller;
use FestivalMedalTracker\UI\Admin\AdminPage;
use FestivalMedalTracker\UI\Admin\FrontendAdminPage;
use FestivalMedalTracker\UI\Frontend\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    public static function boot(): void
    {
        add_action('plugins_loaded', [DatabaseInstaller::class, 'maybeUpgrade']);

        $repository = new MedalRepository();
        $imports    = new ImportRepository();
        $publication = new FrontendPublicationRepository();

        if (is_admin()) {
            $logger = new FileLogger();
            $adminPage = new AdminPage(
                new ImportMedalsUseCase(
                    new PhpSpreadsheetExcelReader(),
                    new MedalNormalizer(),
                    $repository,
                    $imports
                ),
                $repository,
                $imports,
                $logger
            );
            $adminPage->registerHooks();

            (new FrontendAdminPage($repository, $publication, $logger))->registerHooks();
        }

        (new Shortcodes($publication))->registerHooks();
    }
}
