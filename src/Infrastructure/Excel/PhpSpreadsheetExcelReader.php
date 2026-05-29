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
    private const REQUIRED_COLUMNS = ['location', 'prize'];
    private const HEADER_SCAN_LIMIT = 20;

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

        $headerData = $this->findHeaderRow($rows);

        if (null === $headerData) {
            throw new RuntimeException(__('The file must contain location and prize columns.', 'cannes-festival-medal-tracker'));
        }

        $headers = $headerData['headers'];
        $rows = array_slice($rows, $headerData['offset'] + 1, null, true);

        $normalizedRows = [];

        foreach ($rows as $rowNumber => $row) {
            $normalizedRows[] = [
                'row_number' => is_numeric($rowNumber) ? (int) $rowNumber : 0,
                'location'   => isset($row[$headers['location']]) ? (string) $row[$headers['location']] : '',
                'prize'      => isset($row[$headers['prize']]) ? (string) $row[$headers['prize']] : '',
            ];
        }

        return $normalizedRows;
    }

    private function normalizeHeaders(array $headerRow): array
    {
        $headers = [];

        foreach ($headerRow as $column => $value) {
            $key = $this->normalizeHeaderKey((string) $value);

            if ('' !== $key) {
                $headers[$key] = $column;
            }
        }

        return $headers;
    }

    private function findHeaderRow(array $rows): ?array
    {
        $offset = 0;

        foreach ($rows as $rowNumber => $row) {
            if ($offset >= self::HEADER_SCAN_LIMIT) {
                break;
            }

            $headers = $this->normalizeHeaders($row);

            if ($this->hasRequiredHeaders($headers)) {
                return [
                    'offset'     => $offset,
                    'row_number' => is_numeric($rowNumber) ? (int) $rowNumber : 0,
                    'headers'    => $headers,
                ];
            }

            $offset++;
        }

        return null;
    }

    private function hasRequiredHeaders(array $headers): bool
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (!isset($headers[$column])) {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeaderKey(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?: $header;
        $header = strtolower(trim($header));
        $header = preg_replace('/\s+/', ' ', $header) ?: '';

        return sanitize_key(str_replace(' ', '_', $header));
    }
}
