<?php

namespace App\Services\CashflowImport;

use App\Models\Budget;
use App\Models\CashFlowSourceBudget;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CashflowImportProcessor
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    /**
     * @param array{
     *     mapping: array,
     *     excluded_rows?: array<int,int>,
     *     defaults?: array{category_id:?int, cash_flow_source_id:?int},
     *     row_assignments?: array<string,array{category_id:?int,cash_flow_source_id:?int,notes:?string}>
     * } $payload
     */
    public function transform(array $session, int $userId, array $payload, Collection $categories, Collection $sources): array
    {
        $mapping = $payload['mapping'];
        $excluded = collect($payload['excluded_rows'] ?? [])->map(fn ($value) => (int) $value)->all();
        if (array_key_exists('header_row_index', $payload) && $payload['header_row_index'] !== null) {
            $excluded[] = (int) $payload['header_row_index'];
        }
        $defaults = $payload['defaults'] ?? ['category_id' => null, 'cash_flow_source_id' => null];
        $assignments = $payload['row_assignments'] ?? [];

        $rows = $session['payload']['rows'] ?? [];
        $analysis = $session['payload']['analysis'] ?? [];

        $dateParser = new ValueParser();

        $results = [];
        $errors = [];
        $suggestions = $this->buildSuggestions($userId, $rows, $mapping, $dateParser, $categories, $sources);

        foreach ($rows as $row) {
            $rowIndex = $row['index'];

            if (in_array($rowIndex, $excluded, true)) {
                continue;
            }

            $values = $row['values'] ?? [];
            $dateColumnIndex = $mapping['date']['column'] ?? null;

            [$date, $dateError] = $dateParser->parseDateFromMapping($values, $mapping['date'] ?? []);
            if ($dateError) {
                if ($this->shouldSilentlySkipRowOnDateError($row, $values, $dateColumnIndex)) {
                    continue;
                }
                $errors[] = $this->buildRowError($rowIndex, 'date', $dateError, $values);
                continue;
            }

            [$amount, $direction, $amountError] = $dateParser->parseAmountFromMapping($values, $mapping['amount'] ?? []);
            if ($amountError) {
                $errors[] = $this->buildRowError($rowIndex, 'amount', $amountError, $values);
                continue;
            }

            [$type, $typeError] = $dateParser->resolveType($values, $mapping['type'] ?? [], $direction);
            if ($typeError) {
                $errors[] = $this->buildRowError($rowIndex, 'type', $typeError, $values);
                continue;
            }

            if ($type === null) {
                $errors[] = $this->buildRowError($rowIndex, 'type', 'לא ניתן היה לזהות את סוג התזרים (הכנסה/הוצאה).', $values);
                continue;
            }

            $postingDateMapping = $mapping['posting_date'] ?? ['mode' => 'same_as_transaction'];
            [$postingDate, $postingDateError] = $dateParser->parseDateFromMapping($values, $postingDateMapping, $date);
            if ($postingDateError) {
                $postingColumnIndex = ($postingDateMapping['mode'] ?? 'column') === 'column'
                    ? ($postingDateMapping['column'] ?? null)
                    : null;

                if ($postingColumnIndex !== null && $this->shouldSilentlySkipRowOnDateError($row, $values, $postingColumnIndex)) {
                    continue;
                }

                $errors[] = $this->buildRowError($rowIndex, 'posting_date', $postingDateError, $values);
                continue;
            }

            $description = $this->valueFromMapping($values, $mapping['description'] ?? null);
            if (!$description || !is_string($description)) {
                $errors[] = $this->buildRowError($rowIndex, 'description', 'חובה לבחור תיאור לכל תזרים.', $values);
                continue;
            }

            $referenceNumber = $this->valueFromMapping($values, $mapping['reference'] ?? null);
            $notes = $this->valueFromMapping($values, $mapping['notes'] ?? null);

            $assignment = $assignments[(string) $rowIndex] ?? [];
            $rowCategoryId = $assignment['category_id'] ?? $defaults['category_id'] ?? $suggestions['categories'][(string) Str::lower(trim($description))] ?? null;
            $rowSourceId = $assignment['cash_flow_source_id'] ?? $defaults['cash_flow_source_id'] ?? $suggestions['sources'][(string) Str::lower(trim($description))] ?? null;
            $rowNotes = $assignment['notes'] ?? $notes;

            $category = $rowCategoryId ? $categories->get($rowCategoryId) : null;
            $source = $rowSourceId ? $sources->get($rowSourceId) : null;

            if ($category && $category->type !== $type) {
                $errors[] = $this->buildRowError($rowIndex, 'category_id', 'הקטגוריה שנבחרה אינה תואמת לסוג התזרים.', $values);
                continue;
            }

            if ($source && $source->type !== $type) {
                $errors[] = $this->buildRowError($rowIndex, 'cash_flow_source_id', 'מקור התזרים שנבחר אינו תואם לסוג התזרים.', $values);
                continue;
            }

            $results[] = [
                'row_index' => $rowIndex,
                'original_row_number' => $row['original_index'] ?? ($rowIndex + 1),
                'transaction_date' => $date->toDateString(),
                'posting_date' => $postingDate?->toDateString(),
                'transaction_month' => $date->format('m'),
                'transaction_year' => $date->format('Y'),
                'description' => trim($description),
                'amount' => round($amount, 2),
                'type' => $type,
                'category_id' => $category?->id,
                'category_name' => $category?->name,
                'cash_flow_source_id' => $source?->id,
                'cash_flow_source_name' => $source?->name,
                'reference_number' => $referenceNumber ? (string) $referenceNumber : null,
                'notes' => $rowNotes ? (string) $rowNotes : null,
                'raw_values' => $values,
            ];
        }

        $summary = $this->buildSummary($results);

        return [
            'rows' => $results,
            'errors' => $errors,
            'summary' => $summary,
            'analysis' => [
                'header_candidates' => $analysis['header_candidates'] ?? [],
                'detected_date_range' => $analysis['detected_date_range'] ?? ['min' => null, 'max' => null],
            ],
        ];
    }

    public function commit(array $session, int $userId, array $payload, Collection $categories, Collection $sources): array
    {
        $transformation = $this->transform($session, $userId, $payload, $categories, $sources);

        if (!empty($transformation['errors'])) {
            return $transformation;
        }

        $rows = $transformation['rows'];

        $this->db->transaction(function () use ($rows, $userId) {
            $categoryUpdates = [];
            $sourceUpdates = [];

            foreach ($rows as $row) {
                $transaction = Transaction::create([
                    'user_id' => $userId,
                    'category_id' => $row['category_id'],
                    'cash_flow_source_id' => $row['cash_flow_source_id'],
                    'amount' => $row['amount'],
                    'type' => $row['type'],
                    'transaction_date' => $row['transaction_date'],
                    'posting_date' => $row['posting_date'] ?? $row['transaction_date'],
                    'description' => $row['description'],
                    'notes' => $row['notes'],
                    'reference_number' => $row['reference_number'],
                    'status' => 'completed',
                ]);

                if ($transaction->category_id) {
                    $categoryUpdates[] = [
                        'category_id' => $transaction->category_id,
                        'date' => $transaction->transaction_date,
                    ];
                }

                if ($transaction->cash_flow_source_id) {
                    $sourceUpdates[] = [
                        'cash_flow_source_id' => $transaction->cash_flow_source_id,
                        'date' => $transaction->transaction_date,
                    ];
                }
            }

            $this->refreshBudgets($userId, $categoryUpdates, $sourceUpdates);
        });

        $summary = $this->buildSummary($rows);

        return [
            'rows' => $rows,
            'errors' => [],
            'summary' => $summary,
        ];
    }

    private function refreshBudgets(int $userId, array $categoryUpdates, array $sourceUpdates): void
    {
        $categoryGroups = collect($categoryUpdates)
            ->map(function ($update) {
                $date = $update['date'] instanceof Carbon ? $update['date'] : Carbon::parse($update['date']);

                return [
                    'category_id' => $update['category_id'],
                    'year' => $date->year,
                    'month' => $date->month,
                    'date' => $date,
                ];
            })
            ->unique(fn ($item) => $item['category_id'] . '-' . $item['year'] . '-' . $item['month']);

        $sourceGroups = collect($sourceUpdates)
            ->map(function ($update) {
                $date = $update['date'] instanceof Carbon ? $update['date'] : Carbon::parse($update['date']);

                return [
                    'cash_flow_source_id' => $update['cash_flow_source_id'],
                    'year' => $date->year,
                    'month' => $date->month,
                    'date' => $date,
                ];
            })
            ->unique(fn ($item) => $item['cash_flow_source_id'] . '-' . $item['year'] . '-' . $item['month']);

        foreach ($categoryGroups as $item) {
            $budget = Budget::where('user_id', $userId)
                ->where('category_id', $item['category_id'])
                ->where('year', $item['year'])
                ->where('month', $item['month'])
                ->first();

            if ($budget) {
                $budget->updateSpentAmount();
            }
        }

        foreach ($sourceGroups as $item) {
            $budget = CashFlowSourceBudget::where('user_id', $userId)
                ->where('cash_flow_source_id', $item['cash_flow_source_id'])
                ->where('year', $item['year'])
                ->where('month', $item['month'])
                ->first();

            if ($budget) {
                $budget->updateSpentAmount();
            }
        }
    }

    private function buildSummary(array $rows): array
    {
        $collection = collect($rows);

        $incomeTotal = $collection->where('type', 'income')->sum('amount');
        $expenseTotal = $collection->where('type', 'expense')->sum('amount');

        $dateRange = $collection
            ->map(fn ($row) => Carbon::parse($row['transaction_date']))
            ->sort();

        $months = $collection
            ->map(fn ($row) => Carbon::parse($row['transaction_date'])->format('Y-m'))
            ->unique()
            ->values()
            ->all();

        return [
            'count' => $collection->count(),
            'income_total' => round($incomeTotal, 2),
            'expense_total' => round($expenseTotal, 2),
            'date_range' => [
                'min' => $dateRange->first()?->toDateString(),
                'max' => $dateRange->last()?->toDateString(),
            ],
            'months' => $months,
        ];
    }

    private function buildSuggestions(int $userId, array $rows, array $mapping, ValueParser $parser, Collection $categories, Collection $sources): array
    {
        $descriptionColumn = $mapping['description']['column'] ?? null;
        if ($descriptionColumn === null) {
            return ['categories' => [], 'sources' => []];
        }

        $descriptions = collect($rows)
            ->map(fn ($row) => $row['values'][$descriptionColumn] ?? null)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim($value))
            ->unique()
            ->take(200);

        if ($descriptions->isEmpty()) {
            return ['categories' => [], 'sources' => []];
        }

        $transactions = Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('description', $descriptions->values()->all())
            ->with(['category', 'cashFlowSource'])
            ->latest('transaction_date')
            ->get();

        $categorySuggestions = [];
        $sourceSuggestions = [];

        foreach ($transactions as $transaction) {
            $key = Str::lower(trim($transaction->description));

            if ($transaction->category && $categories->has($transaction->category_id)) {
                $categorySuggestions[$key] = $transaction->category_id;
            }

            if ($transaction->cashFlowSource && $sources->has($transaction->cash_flow_source_id)) {
                $sourceSuggestions[$key] = $transaction->cash_flow_source_id;
            }
        }

        return [
            'categories' => $categorySuggestions,
            'sources' => $sourceSuggestions,
        ];
    }

    private function valueFromMapping(array $values, ?array $mapping): mixed
    {
        if (!$mapping || !array_key_exists('column', $mapping)) {
            return null;
        }

        $column = $mapping['column'];

        if ($column === null) {
            return null;
        }

        return $values[$column] ?? null;
    }

    private function buildRowError(int $rowIndex, string $field, string $message, array $values): array
    {
        return [
            'row_index' => $rowIndex,
            'field' => $field,
            'message' => $message,
            'values' => $values,
        ];
    }

    private function shouldSilentlySkipRowOnDateError(array $row, array $values, ?int $dateColumnIndex): bool
    {
        if (!empty($row['auto_skip'])) {
            return true;
        }

        $skipReasons = is_array($row['skip_reasons'] ?? null) ? $row['skip_reasons'] : [];
        $metadataReasons = ['metadata_row', 'summary_row', 'short_single_value', 'empty_row'];

        if (!empty(array_intersect($metadataReasons, $skipReasons))) {
            return true;
        }

        $dateValue = $dateColumnIndex !== null ? ($values[$dateColumnIndex] ?? null) : null;
        if (is_string($dateValue)) {
            $trimmed = trim($dateValue);
            if ($trimmed !== '' && !preg_match('/\d/', $trimmed)) {
                return true;
            }
        }

        return false;
    }
}
