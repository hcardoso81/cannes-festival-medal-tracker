<?php
/**
 * Lightweight PSR-4 autoloader for plugin classes.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'FestivalMedalTracker\\';

        if (0 !== strpos($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file     = FMB_PATH . 'src/' . str_replace('\\', '/', $relative) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
);
