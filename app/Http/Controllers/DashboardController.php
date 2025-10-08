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

        return Inertia::render('Dashboard', [
            'user' => $user,
            'currentYear' => (int) $year,
            'currentMonth' => (int) $month,
            'totalIncome' => $totals['income'],
            'totalExpenses' => $totals['expenses'],
            'balance' => $totals['balance'],
            'categoriesWithBudgets' => $categoriesWithBudgets,
            'cashFlowSources' => $cashFlowSources,
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

        return Inertia::render('Cashflow/Manage', [
            'user' => $user,
            'currentYear' => (int) $year,
            'currentMonth' => (int) $month,
            'totalIncome' => $totals['income'],
            'totalExpenses' => $totals['expenses'],
            'balance' => $totals['balance'],
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
        $allCashFlowSources = $user->cashFlowSources()->orderBy('name')->get()->map(function ($source) {
            return [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'color' => $source->color,
                'icon' => $source->icon,
                'description' => $source->description,
                'is_active' => $source->is_active,
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

    private function buildAccountStatementRows($user, int $year, int $month)
    {
        $groupedBySource = $user->transactions()
            ->with(['cashFlowSource'])
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->whereNotNull('cash_flow_source_id')
            ->select(
                'cash_flow_source_id',
                'type',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('MAX(created_at) as latest_created_at'),
                DB::raw('MAX(updated_at) as latest_updated_at')
            )
            ->groupBy('cash_flow_source_id', 'type')
            ->orderBy(DB::raw('MAX(updated_at)'), 'desc')
            ->get()
            ->map(function ($group) {
                $cashFlowSource = $group->cashFlowSource;
                $latestUpdatedAt = $group->latest_updated_at ? Carbon::parse($group->latest_updated_at) : null;
                $latestCreatedAt = $group->latest_created_at ? Carbon::parse($group->latest_created_at) : null;
                $sortBase = $latestUpdatedAt ?? $latestCreatedAt;
                $sortTimestamp = $sortBase ? $sortBase->valueOf() : 0;

                return [
                    'id' => 'source_' . $group->cash_flow_source_id . '_' . $group->type,
                    'type' => 'cash_flow_source',
                    'cash_flow_source_id' => $group->cash_flow_source_id,
                    'source_name' => $cashFlowSource->name,
                    'source_color' => $cashFlowSource->color,
                    'source_icon' => $cashFlowSource->icon,
                    'transaction_type' => $group->type,
                    'total_amount' => $group->total_amount,
                    'transaction_count' => $group->transaction_count,
                    'formatted_amount' => $group->type === 'income' ? '+' . number_format($group->total_amount, 2) : '-' . number_format($group->total_amount, 2),
                    'amount_color' => $group->type === 'income' ? 'text-green-600' : 'text-red-600',
                    'can_add_transactions' => true,
                    'latest_created_at' => optional($latestCreatedAt)->toIso8601String(),
                    'latest_updated_at' => optional($latestUpdatedAt)->toIso8601String(),
                    'created_timestamp' => optional($latestCreatedAt)->valueOf() ?? $sortTimestamp,
                    'updated_timestamp' => optional($latestUpdatedAt)->valueOf() ?? $sortTimestamp,
                    'sort_timestamp' => $sortTimestamp,
                ];
            });

        $individualTransactions = $user->transactions()
            ->with(['category'])
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->whereNull('cash_flow_source_id')
            ->orderBy('transaction_date', 'desc')
            ->get()
            ->map(function ($transaction) {
                $latestUpdatedAt = $transaction->updated_at;
                $latestCreatedAt = $transaction->created_at;
                $sortBase = $latestUpdatedAt ?? $latestCreatedAt;
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
                    'transaction_date' => $transaction->transaction_date->format('d/m/Y'),
                    'latest_created_at' => optional($latestCreatedAt)->toIso8601String(),
                    'latest_updated_at' => optional($latestUpdatedAt)->toIso8601String(),
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
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
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
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('MAX(transaction_date) as latest_transaction_date'),
                DB::raw('MAX(created_at) as latest_created_at'),
                DB::raw('MAX(updated_at) as latest_updated_at')
            )
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
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

            $latestTransactionDate = $aggregate?->latest_transaction_date
                ? Carbon::parse($aggregate->latest_transaction_date)
                : null;

            $latestCreatedAt = $aggregate?->latest_created_at
                ? Carbon::parse($aggregate->latest_created_at)
                : null;

            $latestUpdatedAt = $aggregate?->latest_updated_at
                ? Carbon::parse($aggregate->latest_updated_at)
                : null;

            $totalAmount = $aggregate?->total_amount ?? 0;
            $transactionCount = $aggregate?->transaction_count ?? 0;

            $formattedAmount = $totalAmount !== 0
                ? ($source->type === 'income' ? '+' : '-') . number_format(abs($totalAmount), 2)
                : number_format(0, 2);

            return [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
                'color' => $source->color,
                'icon' => $source->icon,
                'description' => $source->description,
                'is_active' => $source->is_active,
                'created_at' => optional($source->created_at)->toIso8601String(),
                'updated_at' => optional($source->updated_at)->toIso8601String(),
                'monthly_total_amount' => $totalAmount,
                'monthly_transaction_count' => $transactionCount,
                'monthly_formatted_amount' => $formattedAmount,
                'monthly_amount_color' => $source->type === 'income' ? 'text-green-600' : 'text-red-600',
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
