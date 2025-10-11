<?php

namespace App\Services\CashflowImport;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;

class SpreadsheetReader
{
    private const MAX_ROWS = 10000;
    private const MAX_COLUMNS = 50;

    /**
     * @return array{
     *     rows: array<int, array{
     *         index: int,
     *         original_index: int,
     *         values: array<int, mixed>
     *     }>,
     *     total_rows: int,
     *     total_columns: int
     * }
     */
    public function read(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getSheet(0);

        $rows = [];
        $maxColumns = 0;
        $rowCount = 0;

        /** @var Row $row */
        foreach ($worksheet->getRowIterator() as $row) {
            if ($rowCount >= self::MAX_ROWS) {
                break;
            }

            $rowCount++;
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $values = [];
            $columnCount = 0;

            foreach ($cellIterator as $cell) {
                if ($columnCount >= self::MAX_COLUMNS) {
                    break;
                }

                $values[] = $this->normalizeCellValue($cell?->getCalculatedValue());
                $columnCount++;
            }

            $maxColumns = max($maxColumns, $columnCount);

            $rows[] = [
                'index' => count($rows),
                'original_index' => $row->getRowIndex(),
                'values' => $this->trimTrailingEmptyValues($values),
            ];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'rows' => $rows,
            'total_rows' => $rowCount,
            'total_columns' => $maxColumns,
        ];
    }

    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_float($value)) {
            if ($this->looksLikeExcelDate($value)) {
                try {
                    return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
                } catch (\Throwable) {
                    // ignore, fall back to float value
                }
            }

            return round($value, 4);
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    private function looksLikeExcelDate(float $value): bool
    {
        // Excel dates typically start near 25569 (1970-01-01)
        return $value > 10000 && $value < 500000;
    }

    /**
     * Trim trailing null values to avoid excessively wide rows when the row is sparse.
     */
    private function trimTrailingEmptyValues(array $values): array
    {
        $collection = Collection::make($values);

        $lastNonNullIndex = $collection
            ->map(function ($value, $index) {
                return $value === null ? null : $index;
            })
            ->filter()
            ->last();

        if ($lastNonNullIndex === null) {
            return [];
        }

        return Arr::only($values, range(0, $lastNonNullIndex));
    }
}
