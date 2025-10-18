<?php

namespace App\Services\CashflowImport;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ClipboardParser
{
    private const MAX_ROWS = 2000;
    private const MAX_COLUMNS = 50;

    /**
     * @return array{
     *     rows: array<int, array{index:int, original_index:int, values:array<int,mixed>}>,
     *     total_rows: int,
     *     total_columns: int
     * }
     */
    public function parse(string $content): array
    {
        $normalizedContent = trim($content);

        if ($normalizedContent === '') {
            return [
                'rows' => [],
                'total_rows' => 0,
                'total_columns' => 0,
            ];
        }

        $lines = preg_split("/\r\n|\n|\r/u", $normalizedContent);
        $rows = [];
        $maxColumns = 0;
        $rowCount = 0;

        foreach ($lines as $lineNumber => $line) {
            if ($rowCount >= self::MAX_ROWS) {
                break;
            }

            $cells = $this->parseLine($line);
            $values = $this->trimTrailingEmptyValues(array_map([$this, 'normalizeCellValue'], $cells));

            $rows[] = [
                'index' => $rowCount,
                'original_index' => $lineNumber + 1,
                'values' => $values,
            ];

            $maxColumns = max($maxColumns, count($values));
            $rowCount++;
        }

        return [
            'rows' => $rows,
            'total_rows' => $rowCount,
            'total_columns' => $maxColumns,
        ];
    }

    /**
     * Parse a single line into columns using tab delimiter.
     */
    private function parseLine(string $line): array
    {
        // str_getcsv handles quoted values and escaped characters.
        $cells = str_getcsv($line, "\t");

        return $cells === null ? [] : $cells;
    }

    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            return $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return $value + 0;
        }

        return $value;
    }

    /**
     * Trim trailing null values to avoid overly wide sparse rows.
     */
    private function trimTrailingEmptyValues(array $values): array
    {
        if (empty($values)) {
            return [];
        }

        $collection = Collection::make($values);

        $lastNonNullIndex = $collection
            ->map(static fn ($value, $index) => $value === null ? null : $index)
            ->filter()
            ->last();

        if ($lastNonNullIndex === null) {
            return [];
        }

        return Arr::only($values, range(0, $lastNonNullIndex));
    }
}
