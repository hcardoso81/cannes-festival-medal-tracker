<?php

declare(strict_types=1);

namespace FestivalMedalTracker\Infrastructure\Excel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

final class PhpSpreadsheetExcelReader
{
    public function readRows(string $filePath): array
    {
        if (!class_exists(IOFactory::class)) {
            throw new RuntimeException(
                __('PhpSpreadsheet is not installed. Run composer install in the plugin directory.', 'cannes-festival-medal-tracker')
            );
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException(__('The uploaded file could not be read.', 'cannes-festival-medal-tracker'));
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            return [];
        }

        $headerRow = array_shift($rows);
        $headers   = $this->normalizeHeaders($headerRow);

        if (!isset($headers['location'], $headers['prize'])) {
            throw new RuntimeException(__('The file must contain location and prize columns.', 'cannes-festival-medal-tracker'));
        }

        $normalizedRows = [];

        foreach ($rows as $row) {
            $normalizedRows[] = [
                'location' => isset($row[$headers['location']]) ? (string) $row[$headers['location']] : '',
                'prize'    => isset($row[$headers['prize']]) ? (string) $row[$headers['prize']] : '',
            ];
        }

        return $normalizedRows;
    }

    private function normalizeHeaders(array $headerRow): array
    {
        $headers = [];

        foreach ($headerRow as $column => $value) {
            $key = strtolower(trim((string) $value));

            if ('' !== $key) {
                $headers[$key] = $column;
            }
        }

        return $headers;
    }
}
