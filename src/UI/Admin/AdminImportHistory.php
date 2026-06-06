<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

use FestivalMedalTracker\Infrastructure\Persistence\ImportRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminImportHistory
{
    private ImportRepository $imports;

    public function __construct(ImportRepository $imports)
    {
        $this->imports = $imports;
    }

    public function list(int $limit): array
    {
        return $this->imports->listImports($limit);
    }

    public function approvedSourceFiles(): array
    {
        return array_values(
            array_unique(
                array_filter(
                    $this->imports->listSourceFiles('approved')
                )
            )
        );
    }

    public function hasApprovedFile(string $fileName): bool
    {
        foreach ($this->approvedSourceFiles() as $sourceFile) {
            if ($fileName === sanitize_file_name((string) $sourceFile)) {
                return true;
            }
        }

        return false;
    }
}
