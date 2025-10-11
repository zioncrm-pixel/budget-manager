<?php

namespace App\Services\CashflowImport;

use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ValueParser
{
    /**
     * @param array<int, mixed> $values
     * @param array{mode?:string,column?:?int,format?:?string,value?:?string} $mapping
     * @param Carbon|null $fallback
     * @return array{?Carbon, ?string}
     */
    public function parseDateFromMapping(array $values, array $mapping, ?Carbon $fallback = null): array
    {
        $mode = $mapping['mode'] ?? 'column';

        if ($mode === 'same_as_transaction') {
            if ($fallback) {
                return [$fallback, null];
            }

            return [null, 'לא נבחר תאריך לחיוב.'];
        }

        if ($mode === 'fixed') {
            $rawValue = $mapping['value'] ?? null;

            if ($rawValue === null || $rawValue === '') {
                return [null, 'לא הוזן תאריך קבוע.'];
            }

            return $this->parseDateValue($rawValue, $mapping['format'] ?? null);
        }

        $column = $mapping['column'] ?? null;

        if ($column === null) {
            return [null, 'לא נבחר טור תאריך.'];
        }

        $rawValue = $values[$column] ?? null;

        return $this->parseDateValue($rawValue, $mapping['format'] ?? null);
    }

    private function parseDateValue(mixed $rawValue, ?string $format = null): array
    {
        if ($rawValue === null || $rawValue === '') {
            return [null, 'חסר ערך תאריך בשורה.'];
        }

        if ($rawValue instanceof Carbon) {
            return [$rawValue, null];
        }

        if ($format) {
            try {
                $date = Carbon::createFromFormat($format, (string) $rawValue);
                return [$date, null];
            } catch (\Throwable) {
                // Fallback to auto detection
            }
        }

        if ($rawValue instanceof \DateTimeInterface) {
            return [Carbon::instance($rawValue), null];
        }

        if (is_numeric($rawValue)) {
            if ($rawValue > 10000 && $rawValue < 500000) {
                try {
                    return [Carbon::instance(ExcelDate::excelToDateTimeObject((float) $rawValue)), null];
                } catch (\Throwable) {
                    // ignore and continue
                }
            }
        }

        if (!is_string($rawValue)) {
            return [null, 'הערך בטור התאריך אינו ניתן לזיהוי.'];
        }

        $rawValue = trim($rawValue);

        if ($rawValue === '') {
            return [null, 'הערך בטור התאריך ריק.'];
        }

        $formats = [
            'd/m/Y', 'd/m/y', 'd-m-Y', 'd-m-y',
            'Y-m-d', 'Y/m/d', 'Ymd',
            'd.m.Y', 'd.m.y',
            'm/d/Y', 'm/d/y',
            'd M Y', 'd M y',
        ];

        foreach ($formats as $candidate) {
            try {
                $date = Carbon::createFromFormat($candidate, $rawValue);
                if ($date !== false) {
                    return [$date, null];
                }
            } catch (\Throwable) {
                // continue
            }
        }

        try {
            return [Carbon::parse($rawValue), null];
        } catch (\Throwable) {
            return [null, sprintf('לא ניתן לפרש את ערך התאריך: %s', $rawValue)];
        }
    }

    /**
     * @param array<int, mixed> $values
     * @param array{
     *   mode?:string,
     *   column?:?int,
     *   debit_column?:?int,
     *   credit_column?:?int,
     *   negate?:bool
     * } $mapping
     * @return array{?float, ?string, ?string} amount, direction (income/expense), error
     */
    public function parseAmountFromMapping(array $values, array $mapping): array
    {
        $mode = $mapping['mode'] ?? 'single';

        if ($mode === 'split') {
            return $this->parseSplitAmount($values, $mapping);
        }

        $column = $mapping['column'] ?? null;

        if ($column === null) {
            return [null, null, 'לא נבחר טור סכום.'];
        }

        $rawValue = $values[$column] ?? null;
        $number = $this->parseNumber($rawValue);

        if ($number === null) {
            return [null, null, 'לא ניתן לפרש את הסכום בשורה.'];
        }

        if (!empty($mapping['negate'])) {
            $number *= -1;
        }

        if (abs($number) < 0.0001) {
            return [null, null, 'הסכום בשורה הוא אפס.'];
        }

        $direction = $number >= 0 ? 'income' : 'expense';

        return [abs($number), $direction, null];
    }

    /**
     * @param array<int, mixed> $values
     * @param array{
     *   column?:?int,
     *   debit_column?:?int,
     *   credit_column?:?int
     * } $mapping
     * @return array{?float, ?string, ?string}
     */
    private function parseSplitAmount(array $values, array $mapping): array
    {
        $debitColumn = $mapping['debit_column'] ?? null;
        $creditColumn = $mapping['credit_column'] ?? null;

        if ($debitColumn === null && $creditColumn === null) {
            return [null, null, 'יש לבחור לפחות אחד מהטורים: זכות או חובה.'];
        }

        $debitValue = $debitColumn !== null ? $this->parseNumber($values[$debitColumn] ?? null) : null;
        $creditValue = $creditColumn !== null ? $this->parseNumber($values[$creditColumn] ?? null) : null;

        if (($debitValue === null || abs($debitValue) < 0.0001) && ($creditValue === null || abs($creditValue) < 0.0001)) {
            return [null, null, 'לא נמצאו ערכי זכות/חובה בשורה.'];
        }

        if ($debitValue !== null && $creditValue !== null && abs($debitValue) > 0.0001 && abs($creditValue) > 0.0001) {
            $net = $creditValue - $debitValue;

            if (abs($net) < 0.0001) {
                return [null, null, 'הסכומים בטורי הזכות והחובה מאזנים לאפס.'];
            }

            $direction = $net >= 0 ? 'income' : 'expense';

            return [abs($net), $direction, null];
        }

        if ($creditValue !== null && abs($creditValue) > 0.0001) {
            return [abs($creditValue), 'income', null];
        }

        if ($debitValue !== null && abs($debitValue) > 0.0001) {
            return [abs($debitValue), 'expense', null];
        }

        return [null, null, 'לא ניתן היה לפרש את סכומי הזכות/חובה.'];
    }

    /**
     * @param array<int, mixed> $values
     * @param array{
     *   mode?:string,
     *   column?:?int,
     *   income_values?:array<int,string>,
     *   expense_values?:array<int,string>,
     *   fixed_value?:?string
     * } $mapping
     * @return array{?string, ?string}
     */
    public function resolveType(array $values, array $mapping, ?string $direction): array
    {
        $mode = $mapping['mode'] ?? 'auto_from_amount';

        if ($mode === 'fixed') {
            $fixed = $mapping['fixed_value'] ?? null;
            if (!in_array($fixed, ['income', 'expense'], true)) {
                return [null, 'יש לבחור האם מדובר בהכנסה או בהוצאה.'];
            }

            return [$fixed, null];
        }

        if ($mode === 'column') {
            $column = $mapping['column'] ?? null;
            if ($column === null) {
                return [null, 'לא נבחר טור לקביעת סוג התזרים.'];
            }

            $value = $values[$column] ?? null;
            if ($value === null || $value === '') {
                return [null, 'חסר ערך בטור סוג התזרים.'];
            }

            $normalized = Str::lower(trim((string) $value));
            $incomeValues = collect($mapping['income_values'] ?? [])->map(fn ($item) => Str::lower(trim((string) $item)))->filter()->all();
            $expenseValues = collect($mapping['expense_values'] ?? [])->map(fn ($item) => Str::lower(trim((string) $item)))->filter()->all();

            if (in_array($normalized, $incomeValues, true)) {
                return ['income', null];
            }

            if (in_array($normalized, $expenseValues, true)) {
                return ['expense', null];
            }

            return [null, 'ערך לא מוכר בטור סוג התזרים.'];
        }

        if ($direction !== null) {
            return [$direction, null];
        }

        return [null, 'לא הצלחנו לנחש את סוג התזרים מתוך הסכום.'];
    }

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

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
