<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\CashFlowSource;
use App\Models\CashFlowSourceBudget;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashFlowSourceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'color' => ['required', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'allows_refunds' => ['required', 'boolean'],
            'planned_amount' => ['nullable', 'numeric', 'min:0'],
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $data['is_active'] = (bool) $data['is_active'];
        $data['allows_refunds'] = (bool) $data['allows_refunds'];

        DB::transaction(function () use ($user, $data): void {
            $source = $user->cashFlowSources()->create(collect($data)->only([
                'name',
                'type',
                'color',
                'icon',
                'description',
                'is_active',
                'allows_refunds',
            ])->all());

            $this->upsertBudgetIfNeeded($user->id, $source, $data);
        });

        return back()->with('success', 'מקור התזרים נוסף בהצלחה');
    }

    public function update(Request $request, CashFlowSource $cashFlowSource): RedirectResponse
    {
        abort_if($cashFlowSource->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'color' => ['required', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'allows_refunds' => ['required', 'boolean'],
            'planned_amount' => ['nullable', 'numeric', 'min:0'],
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'remove_budget' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) $data['is_active'];
        $data['allows_refunds'] = (bool) $data['allows_refunds'];

        $cashFlowSource->update(collect($data)->only([
            'name',
            'type',
            'color',
            'icon',
            'description',
            'is_active',
            'allows_refunds',
        ])->all());

        if ($request->boolean('remove_budget')) {
            $this->removeBudgetIfExists($cashFlowSource, $data);
        } else {
            $this->upsertBudgetIfNeeded($cashFlowSource->user_id, $cashFlowSource, $data);
        }

        return back()->with('success', 'מקור התזרים עודכן בהצלחה');
    }

    public function destroy(CashFlowSource $cashFlowSource): RedirectResponse
    {
        abort_if($cashFlowSource->user_id !== Auth::id(), 403);

        DB::transaction(function () use ($cashFlowSource): void {
            Transaction::where('cash_flow_source_id', $cashFlowSource->id)
                ->update(['cash_flow_source_id' => null]);

            $cashFlowSource->delete();
        });

        return back()->with('success', 'מקור התזרים נמחק בהצלחה');
    }

    public function duplicate(Request $request, CashFlowSource $cashFlowSource): RedirectResponse
    {
        abort_if($cashFlowSource->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'planned_amount' => ['nullable', 'numeric', 'min:0'],
            'with_transactions' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $duplicateTransactions = $request->boolean('with_transactions', false);

        DB::transaction(function () use ($user, $cashFlowSource, $data, $duplicateTransactions): void {
            $newName = $this->generateDuplicateSourceName($user->id, $cashFlowSource->name);

            $newSource = CashFlowSource::create([
                'user_id' => $user->id,
                'name' => $newName,
                'type' => $cashFlowSource->type,
                'color' => $cashFlowSource->color,
                'icon' => $cashFlowSource->icon,
                'description' => $cashFlowSource->description,
                'is_active' => $cashFlowSource->is_active,
                'allows_refunds' => $cashFlowSource->allows_refunds,
            ]);

            $plannedAmount = $data['planned_amount'] ?? null;

            if ($plannedAmount === null) {
                $existingBudget = CashFlowSourceBudget::where('user_id', $user->id)
                    ->where('cash_flow_source_id', $cashFlowSource->id)
                    ->where('year', (int) $data['year'])
                    ->where('month', (int) $data['month'])
                    ->first();

                if ($existingBudget) {
                    $plannedAmount = $existingBudget->planned_amount;
                }
            }

            if ($plannedAmount !== null) {
                $budget = CashFlowSourceBudget::create([
                    'user_id' => $user->id,
                    'cash_flow_source_id' => $newSource->id,
                    'year' => (int) $data['year'],
                    'month' => (int) $data['month'],
                    'planned_amount' => $plannedAmount,
                    'spent_amount' => 0,
                    'remaining_amount' => $plannedAmount,
                ]);

                $budget->updateSpentAmount();
            }

            if ($duplicateTransactions) {
                $year = (int) $data['year'];
                $month = (int) $data['month'];

                $transactions = Transaction::where('user_id', $user->id)
                    ->where('cash_flow_source_id', $cashFlowSource->id)
                    ->whereYear('transaction_date', $year)
                    ->whereMonth('transaction_date', $month)
                    ->get();

                $categoryUpdates = [];
                $assignmentDates = [];

                foreach ($transactions as $transaction) {
                    $originalDate = $transaction->transaction_date instanceof Carbon
                        ? $transaction->transaction_date->copy()
                        : Carbon::parse($transaction->transaction_date);

                    $targetMonth = Carbon::create($year, $month, 1)->startOfDay();
                    $day = min($originalDate->day, $targetMonth->copy()->endOfMonth()->day);
                    $newDate = $targetMonth->copy()->day($day)->setTime(
                        (int) $originalDate->format('H'),
                        (int) $originalDate->format('i'),
                        (int) $originalDate->format('s')
                    );

                    $newTransaction = $transaction->replicate();
                    $newTransaction->cash_flow_source_id = $newSource->id;
                    $newTransaction->transaction_date = $newDate;
                    $newTransaction->save();

                    if ($newTransaction->category_id) {
                        $categoryUpdates[] = [
                            'category_id' => $newTransaction->category_id,
                            'date' => $newTransaction->transaction_date,
                        ];
                    }

                    $assignmentDates[] = $newTransaction->transaction_date;
                }

                if (!empty($assignmentDates)) {
                    $this->refreshBudgetsForAssignments($newSource, $assignmentDates);
                }

                if (!empty($categoryUpdates)) {
                    collect($categoryUpdates)
                        ->unique(function ($item) {
                            $carbon = $item['date'] instanceof Carbon ? $item['date'] : Carbon::parse($item['date']);

                            return $item['category_id'] . '-' . $carbon->format('Y-m');
                        })
                        ->each(function ($item) use ($user) {
                            $this->refreshCategoryBudget($user->id, $item['category_id'], $item['date']);
                        });
                }
            }
        });

        return back()->with('success', 'מקור התזרים שוכפל בהצלחה');
    }

    public function transactions(Request $request, CashFlowSource $cashFlowSource): JsonResponse
    {
        abort_if($cashFlowSource->user_id !== Auth::id(), 403);

        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = $cashFlowSource->transactions()
            ->with(['category'])
            ->orderByDesc('transaction_date');

        if ($request->filled('year')) {
            $query->whereYear('transaction_date', $year);
        }

        if ($request->filled('month')) {
            $query->whereMonth('transaction_date', $month);
        }

        $transactions = $query->get();

        $response = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'category_id' => $transaction->category_id,
                'category' => $transaction->category ? [
                    'id' => $transaction->category->id,
                    'name' => $transaction->category->name,
                    'icon' => $transaction->category->icon,
                    'color' => $transaction->category->color,
                ] : null,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'transaction_date' => optional($transaction->transaction_date)->toDateString(),
                'description' => $transaction->description,
                'notes' => $transaction->notes,
                'reference_number' => $transaction->reference_number,
                'status' => $transaction->status,
                'created_at' => optional($transaction->created_at)->toIso8601String(),
                'updated_at' => optional($transaction->updated_at)->toIso8601String(),
            ];
        });

        $incomeTotal = $transactions->where('type', 'income')->sum('amount');
        $expenseTotal = $transactions->where('type', 'expense')->sum('amount');

        if ($cashFlowSource->allows_refunds) {
            if ($cashFlowSource->type === 'income') {
                $netAmount = $incomeTotal - $expenseTotal;
            } else {
                $netAmount = $expenseTotal - $incomeTotal;
            }
        } else {
            $netAmount = $cashFlowSource->type === 'income'
                ? $incomeTotal
                : $expenseTotal;
        }

        return response()->json([
            'transactions' => $response,
            'summary' => [
                'total_income' => $incomeTotal,
                'total_expense' => $expenseTotal,
                'net_amount' => $netAmount,
                'transaction_count' => $transactions->count(),
            ],
        ]);
    }

    public function availableTransactions(Request $request, CashFlowSource $cashFlowSource): JsonResponse
    {
        abort_if($cashFlowSource->user_id !== Auth::id(), 403);

        $user = $request->user();
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = $user->transactions()
            ->with(['category', 'cashFlowSource'])
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->where(function ($q) use ($cashFlowSource) {
                $q->whereNull('cash_flow_source_id')
                    ->orWhere('cash_flow_source_id', '!=', $cashFlowSource->id);
            });

        if (!$cashFlowSource->allows_refunds) {
            $query->where('type', $cashFlowSource->type);
        }

        if ($request->get('exclude_ids')) {
            $ids = collect(explode(',', $request->get('exclude_ids')))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($ids) {
                $query->whereNotIn('id', $ids);
            }
        }

        $transactions = $query->orderByDesc('transaction_date')->limit(100)->get()->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'category_id' => $transaction->category_id,
                'description' => $transaction->description,
                'amount' => $transaction->amount,
                'transaction_date' => optional($transaction->transaction_date)->toDateString(),
                'type' => $transaction->type,
                'category' => $transaction->category ? [
                    'id' => $transaction->category->id,
                    'name' => $transaction->category->name,
                    'icon' => $transaction->category->icon,
                ] : null,
                'cash_flow_source' => $transaction->cashFlowSource ? [
                    'id' => $transaction->cashFlowSource->id,
                    'name' => $transaction->cashFlowSource->name,
                ] : null,
            ];
        });

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function assignTransactions(Request $request, CashFlowSource $cashFlowSource): JsonResponse
    {
        abort_if($cashFlowSource->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'exists:transactions,id'],
        ]);

        $user = $request->user();

        $transactions = Transaction::whereIn('id', $data['transaction_ids'])
            ->where('user_id', $user->id)
            ->get();

        $assignedIds = [];
        $dates = [];

        foreach ($transactions as $transaction) {
            if ($cashFlowSource->type !== $transaction->type && !$cashFlowSource->allows_refunds) {
                continue;
            }

            $transaction->cash_flow_source_id = $cashFlowSource->id;
            $transaction->save();

            $assignedIds[] = $transaction->id;
            $dates[] = $transaction->transaction_date;
        }

        $this->refreshBudgetsForAssignments($cashFlowSource, $dates);

        return response()->json([
            'assigned' => $assignedIds,
        ]);
    }

    public function unassignTransaction(Request $request, CashFlowSource $cashFlowSource, Transaction $transaction): JsonResponse
    {
        abort_if($cashFlowSource->user_id !== Auth::id(), 403);
        abort_if($transaction->user_id !== Auth::id(), 403);

        if ($transaction->cash_flow_source_id === $cashFlowSource->id) {
            $date = $transaction->transaction_date;
            $transaction->cash_flow_source_id = null;
            $transaction->save();

            $this->refreshBudget($cashFlowSource, $date);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroyBudget(Request $request, CashFlowSource $cashFlowSource): RedirectResponse
    {
        abort_if($cashFlowSource->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $budget = CashFlowSourceBudget::where('user_id', $cashFlowSource->user_id)
            ->where('cash_flow_source_id', $cashFlowSource->id)
            ->where('year', $data['year'])
            ->where('month', $data['month'])
            ->first();

        if ($budget) {
            $budget->delete();
        }

        return back()->with('success', 'תקציב המקור הוסר בהצלחה');
    }

    private function upsertBudgetIfNeeded(int $userId, CashFlowSource $source, array $data): void
    {
        if (!array_key_exists('planned_amount', $data)) {
            return;
        }

        $planned = $data['planned_amount'];

        if ($planned === null || $planned === '') {
            return;
        }

        $year = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);

        $budget = CashFlowSourceBudget::updateOrCreate(
            [
                'user_id' => $userId,
                'cash_flow_source_id' => $source->id,
                'year' => $year,
                'month' => $month,
            ],
            [
                'planned_amount' => $planned,
                'remaining_amount' => $planned,
            ]
        );

        $budget->refresh();
        $budget->updateSpentAmount();
    }

    private function removeBudgetIfExists(CashFlowSource $source, array $data): void
    {
        $year = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);

        CashFlowSourceBudget::where('user_id', $source->user_id)
            ->where('cash_flow_source_id', $source->id)
            ->where('year', $year)
            ->where('month', $month)
            ->delete();
    }

    private function refreshBudget(CashFlowSource $source, $date): void
    {
        if (!$date) {
            return;
        }

        $carbon = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);

        $budget = CashFlowSourceBudget::where('user_id', $source->user_id)
            ->where('cash_flow_source_id', $source->id)
            ->where('year', $carbon->year)
            ->where('month', $carbon->month)
            ->first();

        if ($budget) {
            $budget->updateSpentAmount();
        }
    }

    private function refreshBudgetsForAssignments(CashFlowSource $source, array $assignments): void
    {
        if (empty($assignments)) {
            return;
        }

        $unique = collect($assignments)
            ->filter()
            ->map(fn ($date) => $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date))
            ->map(fn ($carbon) => [$carbon->year, $carbon->month])
            ->unique()
            ->all();

        foreach ($unique as [$year, $month]) {
            $budget = CashFlowSourceBudget::where('user_id', $source->user_id)
                ->where('cash_flow_source_id', $source->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($budget) {
                $budget->updateSpentAmount();
            }
        }
    }

    private function refreshCategoryBudget(int $userId, int $categoryId, $date): void
    {
        if (!$categoryId || !$date) {
            return;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        $budget = Budget::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('year', $carbon->year)
            ->where('month', $carbon->month)
            ->first();

        if ($budget) {
            $budget->updateSpentAmount();
        }
    }

    private function generateDuplicateSourceName(int $userId, string $currentName): string
    {
        $baseName = trim(preg_replace('/\s+\d+$/', '', $currentName)) ?: trim($currentName);

        $existingNames = CashFlowSource::where('user_id', $userId)
            ->where('name', 'like', $baseName . '%')
            ->pluck('name')
            ->all();

        $suffix = 1;
        do {
            $candidate = trim($baseName . ' ' . $suffix);
            $suffix++;
        } while (in_array($candidate, $existingNames, true));

        return $candidate;
    }
}
