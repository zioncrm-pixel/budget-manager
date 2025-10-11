<?php

namespace App\Services\CashflowImport;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DatasetAnalyzer
{
    /**
     * @param array<int, array{index:int, original_index:int, values:array<int,mixed>}> $rows
     * @param int $totalColumns
     * @return array{
     *     columns: array<int, array{
     *         index:int,
     *         label:string,
     *         sample_values:array<int, mixed>,
     *         detected_types:array<int, string>,
     *         header_guess: string|null
     *     }>,
     *     rows: array<int, array{
     *         index:int,
     *         original_index:int,
     *         values:array<int, mixed>,
     *         non_empty_count:int,
     *         auto_skip: bool,
     *         skip_reasons: array<int, string>,
     *         header_like_score: float
     *     }>,
     *     header_candidates: array<int, int>,
     *     detected_date_range: array{min:?string,max:?string},
     *     numeric_columns: array<int,int>
     * }
     */
    public function analyze(array $rows, int $totalColumns): array
    {
        $columnStatistics = $this->analyzeColumns($rows, $totalColumns);
        $rowInsights = $this->analyzeRows($rows, $columnStatistics);
        $headerCandidates = $this->identifyHeaderCandidates($rowInsights);
        $this->attachHeaderGuesses($columnStatistics, $rows, $headerCandidates);

        $dateRange = $this->calculateDateRange($columnStatistics);
        $numericColumns = $this->extractNumericColumns($columnStatistics);

        return [
            'columns' => $columnStatistics,
            'rows' => $rowInsights,
            'header_candidates' => $headerCandidates,
            'detected_date_range' => $dateRange,
            'numeric_columns' => $numericColumns,
        ];
    }

    /**
     * @param array<int, array{index:int, original_index:int, values:array<int,mixed>}> $rows
     * @return array<int, array{
     *     index:int,
     *     label:string,
     *     sample_values:array<int,mixed>,
     *     detected_types:array<int,string>,
     *     header_guess:string|null,
     *     date_candidates:array<int,string>,
     *     number_candidates:array<int,float>
     * }>
     */
    private function analyzeColumns(array $rows, int $totalColumns): array
    {
        $columns = [];

        for ($colIndex = 0; $colIndex < $totalColumns; $colIndex++) {
            $samples = [];
            $dateCandidates = [];
            $numberCandidates = [];
            $detectedTypes = [];

            foreach ($rows as $row) {
                if (!array_key_exists($colIndex, $row['values'])) {
                    continue;
                }

                $value = $row['values'][$colIndex];

                if ($value === null || $value === '') {
                    continue;
                }

                if (count($samples) < 5) {
                    $samples[] = $value;
                }

                if ($date = $this->tryParseDate($value)) {
                    $dateCandidates[] = $date;
                    $detectedTypes[] = 'date';
                } elseif (($number = $this->tryParseNumber($value)) !== null) {
                    $numberCandidates[] = $number;
                    $detectedTypes[] = 'number';
                } else {
                    $detectedTypes[] = 'text';
                }
            }

            $columns[] = [
                'index' => $colIndex,
                'label' => $this->buildColumnLabel($colIndex),
                'sample_values' => $samples,
                'detected_types' => array_values(array_unique($detectedTypes)),
                'header_guess' => null,
                'date_candidates' => array_map(fn (Carbon $date) => $date->toDateString(), $dateCandidates),
                'number_candidates' => $numberCandidates,
            ];
        }

        return $columns;
    }

    /**
     * @param array<int, array{index:int, original_index:int, values:array<int,mixed>}> $rows
     * @param array<int, array> $columnStatistics
     * @return array<int, array{
     *     index:int,
     *     original_index:int,
     *     values:array<int,mixed>,
     *     non_empty_count:int,
     *     auto_skip: bool,
     *     skip_reasons: array<int,string>,
     *     header_like_score: float
     * }>
     */
    private function analyzeRows(array $rows, array $columnStatistics): array
    {
        return array_map(function (array $row) use ($columnStatistics) {
            $values = $row['values'] ?? [];
            $nonEmpty = $this->countNonEmpty($values);
            $skipReasons = $this->detectSkipReasons($values);
            $headerScore = $this->calculateHeaderScore($values, $columnStatistics);
            $autoSkip = !empty($skipReasons) && $headerScore < 0.3;

            return [
                'index' => $row['index'],
                'original_index' => $row['original_index'],
                'values' => $values,
                'non_empty_count' => $nonEmpty,
                'auto_skip' => $autoSkip,
                'skip_reasons' => $skipReasons,
                'header_like_score' => $headerScore,
            ];
        }, $rows);
    }

    /**
     * @param array<int, array{values:array<int,mixed>, header_like_score:float}> $rows
     * @return array<int,int>
     */
    private function identifyHeaderCandidates(array $rows): array
    {
        $sorted = collect($rows)
            ->filter(fn ($row) => $row['non_empty_count'] >= 2)
            ->sortByDesc(fn ($row) => $row['header_like_score'])
            ->take(5)
            ->pluck('index')
            ->all();

        return array_values($sorted);
    }

    /**
     * @param array<int, array> $columns
     * @param array<int, array{values:array<int,mixed>}> $rows
     * @param array<int,int> $headerCandidates
     */
    private function attachHeaderGuesses(array &$columns, array $rows, array $headerCandidates): void
    {
        foreach ($headerCandidates as $rowIndex) {
            $row = $rows[$rowIndex] ?? null;

            if (!$row) {
                continue;
            }

            foreach ($row['values'] as $colIndex => $value) {
                if (!isset($columns[$colIndex])) {
                    continue;
                }

                if ($value !== null && is_string($value) && mb_strlen($value) <= 80) {
                    $columns[$colIndex]['header_guess'] ??= $value;
                }
            }
        }
    }

    /**
     * @param array<int, array> $columns
     * @return array{min:?string,max:?string}
     */
    private function calculateDateRange(array $columns): array
    {
        $dates = collect($columns)
            ->flatMap(fn ($column) => $column['date_candidates'] ?? [])
            ->map(fn ($date) => Carbon::parse($date))
            ->sort();

        if ($dates->isEmpty()) {
            return ['min' => null, 'max' => null];
        }

        return [
            'min' => $dates->first()->toDateString(),
            'max' => $dates->last()->toDateString(),
        ];
    }

    /**
     * @param array<int, array> $columns
     * @return array<int,int>
     */
    private function extractNumericColumns(array $columns): array
    {
        return collect($columns)
            ->filter(fn ($column) => in_array('number', $column['detected_types'] ?? [], true))
            ->pluck('index')
            ->values()
            ->all();
    }

    private function buildColumnLabel(int $colIndex): string
    {
        $letters = '';
        $index = $colIndex;

        do {
            $remainder = $index % 26;
            $letters = chr($remainder + 65) . $letters;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return 'עמודה ' . $letters;
    }

    /**
     * @param array<int,mixed> $values
     */
    private function countNonEmpty(array $values): int
    {
        return collect($values)->filter(function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        })->count();
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,string>
     */
    private function detectSkipReasons(array $values): array
    {
        $nonEmptyValues = collect($values)->filter(fn ($value) => $value !== null && $value !== '');

        if ($nonEmptyValues->isEmpty()) {
            return ['empty_row'];
        }

        $concatenated = Str::lower($nonEmptyValues->implode(' '));

        $keywords = [
            'סה\"כ', 'סה"כ', 'סיכום', 'יתרה', 'עמלה', 'הודעה', 'פירוט', 'פרטי חשבון',
            'דף חשבון', 'יתרת פתיחה', 'יתרת סגירה', 'סך הכל', 'total', 'balance', 'summary',
        ];

        foreach ($keywords as $keyword) {
            if (Str::contains($concatenated, Str::lower($keyword))) {
                return ['summary_row'];
            }
        }

        if ($nonEmptyValues->count() === 1 && mb_strlen($nonEmptyValues->first()) <= 4) {
            return ['short_single_value'];
        }

        if ($nonEmptyValues->every(fn ($value) => is_string($value) && !preg_match('/\d/u', $value))) {
            return ['metadata_row'];
        }

        return [];
    }

    /**
     * @param array<int,mixed> $values
     * @param array<int,array> $columnStatistics
     */
    private function calculateHeaderScore(array $values, array $columnStatistics): float
    {
        if (empty($values)) {
            return 0.0;
        }

        $nonEmpty = $this->countNonEmpty($values);

        if ($nonEmpty === 0) {
            return 0.0;
        }

        $textualCells = 0;

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $valueStr = is_string($value) ? trim($value) : (string) $value;
            $isShort = mb_strlen($valueStr) <= 40;
            $hasNoDigits = !preg_match('/\d/u', $valueStr);
            $hasLetters = preg_match('/[A-Za-zא-ת]/u', $valueStr);

            if ($isShort && $hasLetters && $hasNoDigits) {
                $textualCells++;
            }
        }

        return $textualCells / $nonEmpty;
    }

    private function tryParseDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            if ($value > 10000 && $value < 500000) {
                try {
                    return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value));
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $formats = [
            'd/m/Y', 'd/m/y', 'd-m-Y', 'd-m-y',
            'Y-m-d', 'Y/m/d', 'Ymd',
            'd.m.Y', 'd.m.y',
            'm/d/Y', 'm/d/y',
            'd M Y', 'd M y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable) {
                // continue
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function tryParseNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(["\u{00A0}", ' '], '', $normalized);
        $normalized = str_replace(['₪', '$', '€', '£'], '', $normalized);

        // Handle thousand separators
        if (preg_match('/^-?\d{1,3}(\,\d{3})+(\.\d+)?$/', $normalized)) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (preg_match('/^-?\d+,\d+$/', $normalized)) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            return (float) $normalized;
        }

        return null;
    }
}
