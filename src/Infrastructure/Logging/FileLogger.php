<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Infrastructure\Logging;

use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class FileLogger
{
    private const LOG_FILE = 'fmb-error.log';

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    public function exception(Throwable $throwable, array $context = []): void
    {
        $context['exception'] = get_class($throwable);
        $context['message']   = $throwable->getMessage();
        $context['file']      = $throwable->getFile();
        $context['line']      = $throwable->getLine();

        $this->write('exception', 'Unhandled import exception.', $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $directory = FMB_PATH . 'logs/';

        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }

        $this->protectDirectory($directory);

        $line = wp_json_encode(
            [
                'time'    => current_time('mysql'),
                'level'   => $level,
                'message' => $message,
                'context' => $context,
            ],
            JSON_UNESCAPED_SLASHES
        );

        if (false === $line) {
            $line = sprintf('[%s] %s: %s', current_time('mysql'), $level, $message);
        }

        error_log($line . PHP_EOL, 3, $directory . self::LOG_FILE);
    }

    private function protectDirectory(string $directory): void
    {
        $indexFile = $directory . 'index.php';

        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php\n// Silence is golden.\n");
        }

        $htaccessFile = $directory . '.htaccess';

        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Deny from all\n");
        }
    }
}
