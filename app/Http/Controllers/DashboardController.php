<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\CashFlowSourceBudget;

class DashboardController extends Controller
{
    /**
     * הצגת הדשבורד הראשי עם כל הנתונים
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        [$year, $month] = $this->resolvePeriod($request);

        $totals = $this->calculateTotals($user, $year, $month);
        $categoriesWithBudgets = $this->getCategoriesWithBudgets($user, $year, $month);
        $cashFlowSources = $this->getCashFlowSources($user);
        $transactions = $this->getTransactionsForPeriod($user, $year, $month);
        $accountStatus = $this->calculateAccountStatus($user, (int) $year, (int) $month);
        $incomeExpenseChart = $this->buildIncomeExpenseChartData($user, (int) $year, (int) $month);
        $categoryExpenseChart = $this->buildCategoryExpenseChartData($user, (int) $year, (int) $month);

        return Inertia::render('Dashboard', [
            'user' => $user,
            'currentYear' => (int) $year,
            'currentMonth' => (int) $month,
            'totalIncome' => $totals['income'],
            'totalExpenses' => $totals['expenses'],
            'balance' => $totals['balance'],
            'accountStatus' => $accountStatus,
            'categoriesWithBudgets' => $categoriesWithBudgets,
            'cashFlowSources' => $cashFlowSources,
            'monthlyTransactions' => $transactions,
            'incomeExpenseChart' => $incomeExpenseChart,
            'categoryExpenseChart' => $categoryExpenseChart,
        ]);
    }

    public function cashflow(Request $request)
    {
        $user = Auth::user();

        [$year, $month] = $this->resolvePeriod($request);

        $totals = $this->calculateTotals($user, $year, $month);
        $accountStatementRows = $this->buildAccountStatementRows($user, $year, $month);
        $transactions = $this->getTransactionsForPeriod($user, $year, $month);
        $categoriesWithBudgets = $this->getCategoriesWithBudgets($user, $year, $month);
        $cashFlowSources = $this->getCashFlowSources($user);
        $accountStatus = $this->calculateAccountStatus($user, (int) $year, (int) $month);

        return Inertia::render('Cashflow/Manage', [
            'user' => $user,
            'currentYear' => (int) $year,
            'currentMonth' => (int) $month,
            'totalIncome' => $totals['income'],
            'totalExpenses' => $totals['expenses'],
            'balance' => $totals['balance'],
            'accountStatus' => $accountStatus,
            'accountStatementRows' => $accountStatementRows,
            'allTransactions' => $transactions,
            'categoriesWithBudgets' => $categoriesWithBudgets,
            'cashFlowSources' => $cashFlowSources,
        ]);
    }

    public function budgets(Request $request)
    {
        $user = Auth::user();

        [$year, $month] = $this->resolvePeriod($request);

        $totals = $this->calculateTotals($user, $year, $month);
        $categoriesWithBudgets = $this->getCategoriesWithBudgets($user, $year, $month);
        $accountStatus = $this->calculateAccountStatus($user, (int) $year, (int) $month);

        $budgets = $user->getOrCreateBudgetsForMonth($year, $month);
        $cashFlowSources = $this->getCashFlowSources($user);
        $allCategories = $user->categories()->orderBy('name')->get();
        $transactionsForAssignment = $this->getTransactionsForAssignment($user, $year, $month);

        return Inertia::render('Budgets/Overview', [
            'user' => $user,
            'currentYear' => (int) $year,
            'currentMonth' => (int) $month,
            'totalIncome' => $totals['income'],
            'totalExpenses' => $totals['expenses'],
            'balance' => $totals['balance'],
            'accountStatus' => $accountStatus,
            'categoriesWithBudgets' => $categoriesWithBudgets,
            'cashFlowSources' => $cashFlowSources,
            'allCategories' => $allCategories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'is_active' => $category->is_active,
                ];
            }),
            'budgetsForMonth' => $budgets->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'category_id' => $budget->category_id,
                    'year' => $budget->year,
                    'month' => $budget->month,
                    'planned_amount' => $budget->planned_amount,
                    'spent_amount' => $budget->spent_amount,
                    'remaining_amount' => $budget->remaining_amount,
                ];
            }),
            'transactionsForAssignment' => $transactionsForAssignment,
        ]);
    }

    public function cashflowSources(Request $request)
    {
        $user = Auth::user();

        [$year, $month] = $this->resolvePeriod($request);

        $totals = $this->calculateTotals($user, $year, $month);

        $cashFlowSourcesWithStats = $this->getCashFlowSourcesWithStats($user, $year, $month);
        $activeCashFlowSources = $this->getCashFlowSources($user);
        $accountStatus = $this->calculateAccountStatus($user, (int) $year, (int) $month);
        $allCashFlowSources = $user->cashFlowSources()->orderBy('name')->get()->map(function ($source) {
            return [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'color' => $source->color,
                'icon' => $source->icon,
                'description' => $source->description,
                'is_active' => $source->is_active,
                'allows_refunds' => $source->allows_refunds,
                'created_at' => optional($source->created_at)->toIso8601String(),
                'updated_at' => optional($source->updated_at)->toIso8601String(),
            ];
        });

        $allCategories = $user->categories()->orderBy('name')->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'icon' => $category->icon,
                'color' => $category->color,
                'is_active' => $category->is_active,
            ];
        });

        $budgetsForMonth = $user->budgets()
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'category_id' => $budget->category_id,
                    'year' => $budget->year,
                    'month' => $budget->month,
                    'planned_amount' => $budget->planned_amount,
                    'spent_amount' => $budget->spent_amount,
                    'remaining_amount' => $budget->remaining_amount,
                ];
            });

        return Inertia::render('Cashflow/Sources', [
            'user' => $user,
            'currentYear' => (int) $year,
            'currentMonth' => (int) $month,
            'totalIncome' => $totals['income'],
            'totalExpenses' => $totals['expenses'],
            'balance' => $totals['balance'],
            'accountStatus' => $accountStatus,
            'cashFlowSourcesWithStats' => $cashFlowSourcesWithStats,
            'cashFlowSources' => $activeCashFlowSources,
            'allCashFlowSources' => $allCashFlowSources,
            'allCategories' => $allCategories,
            'budgetsForMonth' => $budgetsForMonth,
        ]);
    }

    private function resolvePeriod(Request $request): array
    {
        $year = (int) $request->get('year', Carbon::now()->year);
        $month = (int) $request->get('month', Carbon::now()->month);

        return [$year, $month];
    }

    private function calculateTotals($user, int $year, int $month): array
    {
        $income = $user->getTotalIncomeForMonth($year, $month);
        $expenses = $user->getTotalExpensesForMonth($year, $month);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $income - $expenses,
        ];
    }

    private function buildIncomeExpenseChartData($user, int $year, int $month): array
    {
        $transactions = $user->transactions()
            ->whereNotNull('posting_date')
            ->get(['type', 'amount', 'posting_date']);

        if ($transactions->isEmpty()) {
            return [
                'yearly' => [],
                'monthly' => $this->buildMonthlySeries([], $year),
                'weekly' => $this->buildWeeklySeries([], $year, $month),
                'daily' => $this->buildDailySeries([], $year, $month),
            ];
        }

        $transactions->transform(function ($transaction) {
            $transaction->posting_date = Carbon::parse($transaction->posting_date);
            return $transaction;
        });

        $yearly = $transactions
            ->groupBy(fn ($transaction) => $transaction->posting_date->format('Y'))
            ->map(function ($items, $key) {
                return [
                    'key' => (int) $key,
                    'label' => (string) $key,
                    'income' => round((float) $items->where('type', 'income')->sum('amount'), 2),
                    'expense' => round((float) $items->where('type', 'expense')->sum('amount'), 2),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        $yearTransactions = $transactions->filter(fn ($transaction) => (int) $transaction->posting_date->format('Y') === $year);
        $monthly = $this->buildMonthlySeries($yearTransactions, $year);

        $monthTransactions = $yearTransactions->filter(fn ($transaction) => (int) $transaction->posting_date->format('n') === $month);
        $weekly = $this->buildWeeklySeries($monthTransactions, $year, $month);
        $daily = $this->buildDailySeries($monthTransactions, $year, $month);

        return [
            'yearly' => $yearly,
            'monthly' => $monthly,
            'weekly' => $weekly,
            'daily' => $daily,
        ];
    }

    private function buildCategoryExpenseChartData($user, int $year, int $month): array
    {
        $transactions = $user->transactions()
            ->with('category')
            ->where('type', 'expense')
            ->whereNotNull('posting_date')
            ->get(['id', 'category_id', 'amount', 'posting_date']);

        if ($transactions->isEmpty()) {
            $monthlyLabels = collect(range(1, 12))
                ->map(fn ($monthNumber) => $this->getMonthLabel($monthNumber))
                ->values()
                ->all();

            $weeksInMonth = Carbon::create($year, $month, 1)->endOfMonth()->weekOfMonth ?: 1;
            $weeklyLabels = collect(range(1, $weeksInMonth))
                ->map(fn ($weekNumber) => 'שבוע ' . $weekNumber)
                ->values()
                ->all();

            $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
            $dailyLabels = collect(range(1, $daysInMonth))
                ->map(fn ($dayNumber) => str_pad((string) $dayNumber, 2, '0', STR_PAD_LEFT))
                ->values()
                ->all();

            return [
                'yearly' => ['labels' => [], 'datasets' => []],
                'monthly' => ['labels' => $monthlyLabels, 'datasets' => []],
                'weekly' => ['labels' => $weeklyLabels, 'datasets' => []],
                'daily' => ['labels' => $dailyLabels, 'datasets' => []],
            ];
        }

        $transactions->transform(function ($transaction) {
            $transaction->posting_date = Carbon::parse($transaction->posting_date);
            $transaction->abs_amount = abs((float) $transaction->amount);
            $transaction->category_descriptor = $this->makeCategoryDescriptor($transaction->category);
            return $transaction;
        });

        $categoryDescriptors = $transactions
            ->pluck('category_descriptor')
            ->unique('id')
            ->values();

        $yearDefinitions = $transactions
            ->map(fn ($transaction) => (int) $transaction->posting_date->format('Y'))
            ->unique()
            ->sort()
            ->map(fn ($yearValue) => [
                'key' => $yearValue,
                'label' => (string) $yearValue,
            ])
            ->values()
            ->all();

        $yearTransactions = $transactions->filter(
            fn ($transaction) => (int) $transaction->posting_date->format('Y') === $year
        );

        $monthDefinitions = collect(range(1, 12))
            ->map(fn ($monthNumber) => [
                'key' => $monthNumber,
                'label' => $this->getMonthLabel($monthNumber),
            ])
            ->values()
            ->all();

        $monthTransactions = $yearTransactions->filter(
            fn ($transaction) => (int) $transaction->posting_date->format('n') === $month
        );

        $weeksInMonth = Carbon::create($year, $month, 1)->endOfMonth()->weekOfMonth ?: 1;
        $weekDefinitions = collect(range(1, $weeksInMonth))
            ->map(fn ($weekNumber) => [
                'key' => $weekNumber,
                'label' => 'שבוע ' . $weekNumber,
            ])
            ->values()
            ->all();

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $dayDefinitions = collect(range(1, $daysInMonth))
            ->map(fn ($dayNumber) => [
                'key' => $dayNumber,
                'label' => str_pad((string) $dayNumber, 2, '0', STR_PAD_LEFT),
            ])
            ->values()
            ->all();

        $yearlySeries = $this->buildCategorySeries(
            $transactions,
            $categoryDescriptors,
            $yearDefinitions,
            fn ($transaction) => (int) $transaction->posting_date->format('Y')
        );

        $monthlySeries = $this->buildCategorySeries(
            $yearTransactions,
            $categoryDescriptors,
            $monthDefinitions,
            fn ($transaction) => (int) $transaction->posting_date->format('n')
        );

        $weeklySeries = $this->buildCategorySeries(
            $monthTransactions,
            $categoryDescriptors,
            $weekDefinitions,
            fn ($transaction) => $transaction->posting_date->weekOfMonth
        );

        $dailySeries = $this->buildCategorySeries(
            $monthTransactions,
            $categoryDescriptors,
            $dayDefinitions,
            fn ($transaction) => (int) $transaction->posting_date->format('j')
        );

        return [
            'yearly' => $yearlySeries,
            'monthly' => $monthlySeries,
            'weekly' => $weeklySeries,
            'daily' => $dailySeries,
        ];
    }

    private function buildCategorySeries($transactions, $categoryDescriptors, array $definitions, callable $keyResolver): array
    {
        $summary = [];

        foreach ($transactions as $transaction) {
            $categoryId = $transaction->category_descriptor['id'];
            $periodKey = $keyResolver($transaction);

            if ($periodKey === null) {
                continue;
            }

            if (!isset($summary[$categoryId])) {
                $summary[$categoryId] = [];
            }

            if (!isset($summary[$categoryId][$periodKey])) {
                $summary[$categoryId][$periodKey] = 0;
            }

            $summary[$categoryId][$periodKey] += $transaction->abs_amount;
        }

        $labels = array_map(fn ($definition) => $definition['label'], $definitions);

        $datasets = $categoryDescriptors
            ->map(function ($descriptor) use ($definitions, $summary) {
                $data = array_map(function ($definition) use ($descriptor, $summary) {
                    $key = $definition['key'];

                    if (!isset($summary[$descriptor['id']][$key])) {
                        return 0;
                    }

                    return round((float) $summary[$descriptor['id']][$key], 2);
                }, $definitions);

                return [
                    'categoryId' => $descriptor['id'],
                    'label' => $descriptor['name'],
                    'color' => $descriptor['color'],
                    'data' => $data,
                ];
            })
            ->values()
            ->all();

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    private function makeCategoryDescriptor($category = null): array
    {
        if ($category) {
            return [
                'id' => (string) $category->id,
                'name' => $category->name ?? 'קטגוריה',
                'color' => $category->color ?? '#6366F1',
            ];
        }

        return [
            'id' => 'uncategorized',
            'name' => 'ללא קטגוריה',
            'color' => '#9CA3AF',
        ];
    }

    private function buildMonthlySeries($transactions, int $year): array
    {
        $collection = collect($transactions);

        return collect(range(1, 12))
            ->map(function ($monthNumber) use ($collection, $year) {
                $items = $collection->filter(
                    fn ($transaction) => (int) $transaction->posting_date->format('n') === $monthNumber
                );

                return [
                    'key' => $monthNumber,
                    'label' => $this->getMonthLabel($monthNumber),
                    'income' => round((float) $items->where('type', 'income')->sum('amount'), 2),
                    'expense' => round((float) $items->where('type', 'expense')->sum('amount'), 2),
                    'year' => $year,
                ];
            })
            ->values()
            ->all();
    }

    private function buildWeeklySeries($transactions, int $year, int $month): array
    {
        $weeksInMonth = Carbon::create($year, $month, 1)->endOfMonth()->weekOfMonth;
        $weeksRange = $weeksInMonth > 0 ? range(1, $weeksInMonth) : [1];
        $collection = collect($transactions);

        return collect($weeksRange)
            ->map(function ($weekNumber) use ($collection) {
                $items = $collection->filter(
                    fn ($transaction) => $transaction->posting_date->weekOfMonth === $weekNumber
                );

                return [
                    'key' => $weekNumber,
                    'label' => 'שבוע ' . $weekNumber,
                    'income' => round((float) $items->where('type', 'income')->sum('amount'), 2),
                    'expense' => round((float) $items->where('type', 'expense')->sum('amount'), 2),
                ];
            })
            ->values()
            ->all();
    }

    private function buildDailySeries($transactions, int $year, int $month): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $collection = collect($transactions);

        return collect(range(1, $daysInMonth))
            ->map(function ($dayNumber) use ($collection) {
                $items = $collection->filter(
                    fn ($transaction) => (int) $transaction->posting_date->format('j') === $dayNumber
                );

                return [
                    'key' => $dayNumber,
                    'label' => str_pad((string) $dayNumber, 2, '0', STR_PAD_LEFT),
                    'income' => round((float) $items->where('type', 'income')->sum('amount'), 2),
                    'expense' => round((float) $items->where('type', 'expense')->sum('amount'), 2),
                ];
            })
            ->values()
            ->all();
    }

    private function getMonthLabel(int $monthNumber): string
    {
        $months = [
            1 => 'ינואר',
            2 => 'פברואר',
            3 => 'מרץ',
            4 => 'אפריל',
            5 => 'מאי',
            6 => 'יוני',
            7 => 'יולי',
            8 => 'אוגוסט',
            9 => 'ספטמבר',
            10 => 'אוקטובר',
            11 => 'נובמבר',
            12 => 'דצמבר',
        ];

        return $months[$monthNumber] ?? (string) $monthNumber;
    }

    private function calculateAccountStatus($user, int $year, int $month): float
    {
        $periodEndDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $totals = $user->transactions()
            ->whereNotNull('posting_date')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN type = 'income'
                        AND DATE(posting_date) <= ?
                        THEN amount
                        ELSE 0
                    END
                ) as total_income_until_period,
                SUM(
                    CASE
                        WHEN type = 'expense'
                        AND DATE(posting_date) <= ?
                        THEN amount
                        ELSE 0
                    END
                ) as total_expense_until_period
            ", [$periodEndDate, $periodEndDate])
            ->first();

        if (!$totals) {
            return 0.0;
        }

        $income = (float) ($totals->total_income_until_period ?? 0);
        $expenses = (float) ($totals->total_expense_until_period ?? 0);

        return $income - $expenses;
    }

    private function buildAccountStatementRows($user, int $year, int $month)
    {
        $groupedBySource = $user->transactions()
            ->with(['cashFlowSource'])
            ->whereNotNull('posting_date')
            ->whereYear('posting_date', $year)
            ->whereMonth('posting_date', $month)
            ->whereNotNull('cash_flow_source_id')
            ->select(
                'cash_flow_source_id',
                DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
                DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense"),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('MAX(posting_date) as latest_posting_date'),
                DB::raw('MAX(created_at) as latest_created_at'),
                DB::raw('MAX(updated_at) as latest_updated_at')
            )
            ->groupBy('cash_flow_source_id')
            ->orderByDesc(DB::raw('MAX(posting_date)'))
            ->get()
            ->map(function ($group) {
                $cashFlowSource = $group->cashFlowSource;
                if (!$cashFlowSource) {
                    return null;
                }

                $latestPostingDate = $group->latest_posting_date ? Carbon::parse($group->latest_posting_date) : null;
                $latestUpdatedAt = $group->latest_updated_at ? Carbon::parse($group->latest_updated_at) : null;
                $latestCreatedAt = $group->latest_created_at ? Carbon::parse($group->latest_created_at) : null;
                $sortBase = $latestPostingDate ?? $latestUpdatedAt ?? $latestCreatedAt;
                $sortTimestamp = $sortBase ? $sortBase->valueOf() : 0;

                $incomeTotal = (float) ($group->total_income ?? 0);
                $expenseTotal = (float) ($group->total_expense ?? 0);

                if ($cashFlowSource->type === 'income') {
                    $netAmount = $incomeTotal - $expenseTotal;
                    $isPositive = $netAmount >= 0;
                    $formattedAmount = $netAmount !== 0
                        ? ($isPositive ? '+' : '-') . number_format(abs($netAmount), 2)
                        : number_format(0, 2);
                    $amountColor = $isPositive ? 'text-green-600' : 'text-red-600';
                } else {
                    $netAmount = $expenseTotal - $incomeTotal;
                    $isPositive = $netAmount >= 0;
                    $formattedAmount = $netAmount !== 0
                        ? ($isPositive ? '-' : '+') . number_format(abs($netAmount), 2)
                        : number_format(0, 2);
                    $amountColor = $isPositive ? 'text-red-600' : 'text-green-600';
                }

                return [
                    'id' => 'source_' . $group->cash_flow_source_id . '_' . $cashFlowSource->type,
                    'type' => 'cash_flow_source',
                    'cash_flow_source_id' => $group->cash_flow_source_id,
                    'source_name' => $cashFlowSource->name,
                    'source_color' => $cashFlowSource->color,
                    'source_icon' => $cashFlowSource->icon,
                    'allows_refunds' => $cashFlowSource->allows_refunds,
                    'transaction_type' => $cashFlowSource->type,
                    'total_amount' => $netAmount,
                    'total_income_amount' => $incomeTotal,
                    'total_expense_amount' => $expenseTotal,
                    'transaction_count' => (int) $group->transaction_count,
                    'formatted_amount' => $formattedAmount,
                    'amount_color' => $amountColor,
                    'can_add_transactions' => true,
                    'latest_created_at' => optional($latestCreatedAt)->toIso8601String(),
                    'latest_updated_at' => optional($latestUpdatedAt)->toIso8601String(),
                    'latest_posting_at' => optional($latestPostingDate)->toIso8601String(),
                    'created_timestamp' => optional($latestCreatedAt)->valueOf() ?? $sortTimestamp,
                    'updated_timestamp' => optional($latestUpdatedAt)->valueOf() ?? $sortTimestamp,
                    'sort_timestamp' => $sortTimestamp,
                ];
            })
            ->filter();

        $individualTransactions = $user->transactions()
            ->with(['category'])
            ->whereNotNull('posting_date')
            ->whereYear('posting_date', $year)
            ->whereMonth('posting_date', $month)
            ->whereNull('cash_flow_source_id')
            ->orderBy('posting_date', 'desc')
            ->get()
            ->map(function ($transaction) {
                $postingDate = $transaction->posting_date;
                $latestUpdatedAt = $transaction->updated_at;
                $latestCreatedAt = $transaction->created_at;
                $sortBase = $postingDate ?? $latestUpdatedAt ?? $latestCreatedAt;
                $sortTimestamp = $sortBase ? $sortBase->valueOf() : 0;

                return [
                    'id' => 'transaction_' . $transaction->id,
                    'type' => 'individual_transaction',
                    'transaction_id' => $transaction->id,
                    'transaction_data' => $transaction,
                    'source_name' => $transaction->description,
                    'source_color' => $transaction->category->color ?? '#3B82F6',
                    'source_icon' => $transaction->category->icon ?? '📄',
                    'category_name' => $transaction->category->name ?? 'לא מוגדר',
                    'transaction_type' => $transaction->type,
                    'total_amount' => $transaction->amount,
                    'transaction_count' => 1,
                    'formatted_amount' => $transaction->type === 'income' ? '+' . number_format($transaction->amount, 2) : '-' . number_format($transaction->amount, 2),
                    'amount_color' => $transaction->type === 'income' ? 'text-green-600' : 'text-red-600',
                    'can_add_transactions' => false,
                    'transaction_date' => $postingDate
                        ? $postingDate->format('Y-m-d')
                        : optional($transaction->transaction_date)->format('Y-m-d'),
                    'latest_created_at' => optional($latestCreatedAt)->toIso8601String(),
                    'latest_updated_at' => optional($latestUpdatedAt)->toIso8601String(),
                    'latest_posting_at' => optional($postingDate)->toIso8601String(),
                    'created_timestamp' => optional($latestCreatedAt)->valueOf() ?? $sortTimestamp,
                    'updated_timestamp' => optional($latestUpdatedAt)->valueOf() ?? $sortTimestamp,
                    'sort_timestamp' => $sortTimestamp,
                ];
            });

        return $groupedBySource
            ->concat($individualTransactions)
            ->sortByDesc('sort_timestamp')
            ->values();
    }

    private function getTransactionsForPeriod($user, int $year, int $month)
    {
        return $user->transactions()
            ->with(['category', 'cashFlowSource'])
            ->whereNotNull('posting_date')
            ->whereYear('posting_date', $year)
            ->whereMonth('posting_date', $month)
            ->orderByDesc('updated_at')
            ->get();
    }

    private function getTransactionsForAssignment($user, int $year, int $month)
    {
        $periodColumn = DB::raw('COALESCE(posting_date, transaction_date)');

        return $user->transactions()
            ->with(['category', 'cashFlowSource'])
            ->whereYear($periodColumn, $year)
            ->whereMonth($periodColumn, $month)
            ->orderByDesc($periodColumn)
            ->get()
            ->map(function ($transaction) {
                $primaryDate = $transaction->posting_date ?? $transaction->transaction_date;

                return [
                    'id' => $transaction->id,
                    'amount' => (float) $transaction->amount,
                    'type' => $transaction->type,
                    'description' => $transaction->description,
                    'notes' => $transaction->notes,
                    'status' => $transaction->status,
                    'posting_date' => optional($transaction->posting_date)->toDateString(),
                    'transaction_date' => optional($transaction->transaction_date)->toDateString(),
                    'primary_date' => optional($primaryDate)->toDateString(),
                    'category_id' => $transaction->category_id,
                    'category' => $transaction->category ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'type' => $transaction->category->type,
                        'color' => $transaction->category->color,
                        'icon' => $transaction->category->icon,
                        'is_active' => $transaction->category->is_active,
                    ] : null,
                    'cash_flow_source' => $transaction->cashFlowSource ? [
                        'id' => $transaction->cashFlowSource->id,
                        'name' => $transaction->cashFlowSource->name,
                        'type' => $transaction->cashFlowSource->type,
                        'color' => $transaction->cashFlowSource->color,
                        'icon' => $transaction->cashFlowSource->icon,
                    ] : null,
                    'formatted_amount' => $transaction->type === 'income'
                        ? '+' . number_format((float) $transaction->amount, 2)
                        : '-' . number_format((float) $transaction->amount, 2),
                ];
            });
    }

    private function getCategoriesWithBudgets($user, int $year, int $month)
    {
        $budgets = $user->getOrCreateBudgetsForMonth($year, $month);

        return $user->categories()
            ->where('is_active', true)
            ->with(['budgets' => function ($query) use ($year, $month) {
                $query->where('year', $year)->where('month', $month);
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($category) use ($budgets) {
                $budget = $budgets->firstWhere('category_id', $category->id);

                return [
                    'id' => $category->id,
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'category_color' => $category->color,
                    'category_icon' => $category->icon,
                    'type' => $category->type,
                    'description' => $category->description,
                    'is_active' => $category->is_active,
                    'created_at' => optional($category->created_at)->toIso8601String(),
                    'updated_at' => optional($category->updated_at)->toIso8601String(),
                    'budget' => $budget ? [
                        'id' => $budget->id,
                        'planned_amount' => $budget->planned_amount,
                        'spent_amount' => $budget->spent_amount,
                        'remaining_amount' => $budget->remaining_amount,
                        'year' => $budget->year,
                        'month' => $budget->month,
                        'progress_percentage' => $budget->getProgressPercentage(),
                        'progress_color' => $budget->getProgressColor(),
                        'created_at' => optional($budget->created_at)->toIso8601String(),
                        'updated_at' => optional($budget->updated_at)->toIso8601String(),
                    ] : null,
                ];
            });
    }

    private function getCashFlowSources($user)
    {
        return $user->cashFlowSources()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function getCashFlowSourcesWithStats($user, int $year, int $month)
    {
        $sources = $user->cashFlowSources()->orderBy('name')->get();

        $aggregates = $user->transactions()
            ->select(
                'cash_flow_source_id',
                DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
                DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense"),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('MAX(posting_date) as latest_posting_date'),
                DB::raw('MAX(created_at) as latest_created_at'),
                DB::raw('MAX(updated_at) as latest_updated_at')
            )
            ->whereNotNull('posting_date')
            ->whereYear('posting_date', $year)
            ->whereMonth('posting_date', $month)
            ->whereNotNull('cash_flow_source_id')
            ->groupBy('cash_flow_source_id')
            ->get()
            ->keyBy('cash_flow_source_id');

        $budgets = CashFlowSourceBudget::where('user_id', $user->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('cash_flow_source_id');

        return $sources->map(function ($source) use ($aggregates, $budgets) {
            $aggregate = $aggregates->get($source->id);
            $budget = $budgets->get($source->id);

            $latestTransactionDate = $aggregate?->latest_posting_date
                ? Carbon::parse($aggregate->latest_posting_date)
                : null;

            $latestCreatedAt = $aggregate?->latest_created_at
                ? Carbon::parse($aggregate->latest_created_at)
                : null;

            $latestUpdatedAt = $aggregate?->latest_updated_at
                ? Carbon::parse($aggregate->latest_updated_at)
                : null;

            $incomeTotal = (float) ($aggregate?->total_income ?? 0);
            $expenseTotal = (float) ($aggregate?->total_expense ?? 0);
            $transactionCount = $aggregate?->transaction_count ?? 0;

            if ($source->allows_refunds) {
                if ($source->type === 'income') {
                    $netAmount = $incomeTotal - $expenseTotal;
                    $isPositive = $netAmount >= 0;
                    $formattedAmount = $netAmount !== 0
                        ? ($isPositive ? '+' : '-') . number_format(abs($netAmount), 2)
                        : number_format(0, 2);
                    $amountColor = $isPositive ? 'text-green-600' : 'text-red-600';
                } else {
                    $netAmount = $expenseTotal - $incomeTotal;
                    $isPositive = $netAmount >= 0;
                    $formattedAmount = $netAmount !== 0
                        ? ($isPositive ? '-' : '+') . number_format(abs($netAmount), 2)
                        : number_format(0, 2);
                    $amountColor = $isPositive ? 'text-red-600' : 'text-green-600';
                }
            } else {
                if ($source->type === 'income') {
                    $netAmount = $incomeTotal;
                    $formattedAmount = $incomeTotal !== 0
                        ? '+' . number_format(abs($incomeTotal), 2)
                        : number_format(0, 2);
                    $amountColor = 'text-green-600';
                } else {
                    $netAmount = $expenseTotal;
                    $formattedAmount = $expenseTotal !== 0
                        ? '-' . number_format(abs($expenseTotal), 2)
                        : number_format(0, 2);
                    $amountColor = 'text-red-600';
                }
            }

            return [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'color' => $source->color,
                'icon' => $source->icon,
                'description' => $source->description,
                'is_active' => $source->is_active,
                'allows_refunds' => $source->allows_refunds,
                'created_at' => optional($source->created_at)->toIso8601String(),
                'updated_at' => optional($source->updated_at)->toIso8601String(),
                'monthly_total_amount' => $netAmount ?? 0.0,
                'monthly_income_amount' => $incomeTotal,
                'monthly_expense_amount' => $expenseTotal,
                'monthly_transaction_count' => $transactionCount,
                'monthly_formatted_amount' => $formattedAmount,
                'monthly_amount_color' => $amountColor,
                'latest_transaction_at' => optional($latestTransactionDate)->toIso8601String(),
                'latest_transaction_date' => optional($latestTransactionDate)->toDateString(),
                'latest_activity_timestamp' => optional($latestUpdatedAt ?? $latestCreatedAt)->valueOf(),
                'budget' => $budget ? [
                    'id' => $budget->id,
                    'planned_amount' => $budget->planned_amount,
                    'spent_amount' => $budget->spent_amount,
                    'remaining_amount' => $budget->remaining_amount,
                    'progress_percentage' => $budget->getProgressPercentage(),
                    'progress_bar_color' => $budget->getProgressBarColor(),
                    'progress_color' => $budget->getProgressColor(),
                    'year' => $budget->year,
                    'month' => $budget->month,
                    'created_at' => optional($budget->created_at)->toIso8601String(),
                    'updated_at' => optional($budget->updated_at)->toIso8601String(),
                ] : null,
            ];
        });
    }
}
