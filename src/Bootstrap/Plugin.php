<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Bootstrap;

use FestivalMedalTracker\Application\ImportMedalsUseCase;
use FestivalMedalTracker\Domain\Service\MedalNormalizer;
use FestivalMedalTracker\Infrastructure\Excel\PhpSpreadsheetExcelReader;
use FestivalMedalTracker\Infrastructure\Logging\FileLogger;
use FestivalMedalTracker\Infrastructure\Persistence\MedalRepository;
use FestivalMedalTracker\Infrastructure\WordPress\DatabaseInstaller;
use FestivalMedalTracker\UI\Admin\AdminPage;
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

        if (is_admin()) {
            $adminPage = new AdminPage(
                new ImportMedalsUseCase(
                    new PhpSpreadsheetExcelReader(),
                    new MedalNormalizer(),
                    $repository
                ),
                $repository,
                new FileLogger()
            );
            $adminPage->registerHooks();
        }

        (new Shortcodes($repository))->registerHooks();
    }
}
