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
