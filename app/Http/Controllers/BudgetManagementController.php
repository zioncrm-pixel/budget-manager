<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetManagementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'color' => ['required', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'planned_amount' => ['nullable', 'numeric', 'min:0'],
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $data['is_active'] = (bool) $data['is_active'];

        DB::transaction(function () use ($user, $data): void {
            if (!empty($data['category_id'])) {
                $category = Category::where('id', $data['category_id'])
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                $category->update([
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'color' => $data['color'],
                    'icon' => $data['icon'] ?? null,
                    'description' => $data['description'] ?? null,
                    'is_active' => $data['is_active'],
                ]);
            } else {
                $category = Category::create([
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'color' => $data['color'],
                    'icon' => $data['icon'] ?? null,
                    'description' => $data['description'] ?? null,
                    'is_active' => $data['is_active'],
                ]);
            }

            if (isset($data['planned_amount']) && $data['planned_amount'] !== null) {
                $budget = Budget::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'category_id' => $category->id,
                        'year' => $data['year'],
                        'month' => $data['month'],
                    ],
                    [
                        'planned_amount' => $data['planned_amount'],
                        'spent_amount' => 0,
                        'remaining_amount' => $data['planned_amount'],
                    ]
                );

                $budget->updateSpentAmount();
            }
        });

        return back()->with('success', 'הקטגוריה נוספה בהצלחה');
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        abort_if($budget->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'color' => ['required', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'planned_amount' => ['nullable', 'numeric', 'min:0'],
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        DB::transaction(function () use ($budget, $data): void {
            $category = $budget->category;

            $category->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'color' => $data['color'],
                'icon' => $data['icon'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'],
            ]);

            if (isset($data['planned_amount']) && $data['planned_amount'] !== null) {
                $budget->planned_amount = $data['planned_amount'];
                $budget->remaining_amount = $budget->planned_amount - $budget->spent_amount;
            }

            $budget->year = $data['year'];
            $budget->month = $data['month'];
            $budget->save();
            $budget->updateSpentAmount();
        });

        return back()->with('success', 'התקציב עודכן בהצלחה');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        abort_if($budget->user_id !== Auth::id(), 403);

        $removeCategory = $request->boolean('remove_category', false);

        DB::transaction(function () use ($budget, $removeCategory): void {
            $category = $budget->category;

            $budget->delete();

            if ($removeCategory && $category) {
                Transaction::where('category_id', $category->id)->update(['category_id' => null]);
                $category->budgets()->delete();
                $category->delete();
            }
        });

        return back()->with('success', 'התקציב נמחק בהצלחה');
    }

    public function destroyCategory(Request $request, Category $category): RedirectResponse
    {
        abort_if($category->user_id !== Auth::id(), 403);

        DB::transaction(function () use ($category): void {
            Transaction::where('category_id', $category->id)->update(['category_id' => null]);
            $category->budgets()->delete();
            $category->delete();
        });

        return back()->with('success', 'הקטגוריה נמחקה בהצלחה');
    }

    public function transactions(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== Auth::id(), 403);

        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = $category->transactions()
            ->with(['cashFlowSource'])
            ->orderByDesc('transaction_date');

        if ($request->filled('year')) {
            $query->whereYear('transaction_date', $year);
        }

        if ($request->filled('month')) {
            $query->whereMonth('transaction_date', $month);
        }

        $transactions = $query->get()->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'category_id' => $transaction->category_id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'transaction_date' => $transaction->transaction_date->toDateString(),
                'description' => $transaction->description,
                'notes' => $transaction->notes,
                'reference_number' => $transaction->reference_number,
                'status' => $transaction->status,
                'cash_flow_source' => $transaction->cashFlowSource ? [
                    'id' => $transaction->cashFlowSource->id,
                    'name' => $transaction->cashFlowSource->name,
                    'icon' => $transaction->cashFlowSource->icon,
                ] : null,
            ];
        });

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function availableTransactions(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== Auth::id(), 403);

        $user = Auth::user();
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $type = $request->get('type', $category->type);

        $query = $user->transactions()
            ->with(['cashFlowSource'])
            ->where('type', $type)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->where(function ($q) use ($category) {
                $q->whereNull('category_id')->orWhere('category_id', '!=', $category->id);
            });

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
                'transaction_date' => $transaction->transaction_date->toDateString(),
                'type' => $transaction->type,
                'cash_flow_source' => $transaction->cashFlowSource ? [
                    'id' => $transaction->cashFlowSource->id,
                    'name' => $transaction->cashFlowSource->name,
                    'icon' => $transaction->cashFlowSource->icon,
                ] : null,
            ];
        });

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function assignTransactions(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'exists:transactions,id'],
        ]);

        $user = $request->user();

        $transactions = Transaction::whereIn('id', $data['transaction_ids'])
            ->where('user_id', $user->id)
            ->get();

        $assigned = [];

        foreach ($transactions as $transaction) {
            if ($transaction->type !== $category->type) {
                continue;
            }

            $transaction->category_id = $category->id;
            $transaction->save();

            if ($transaction->type === 'expense') {
                $this->refreshBudget($user->id, $category->id, $transaction->transaction_date);
            }

            $assigned[] = $transaction->id;
        }

        return response()->json([
            'assigned' => $assigned,
        ]);
    }

    public function unassignTransaction(Request $request, Category $category, Transaction $transaction): JsonResponse
    {
        abort_if($category->user_id !== Auth::id(), 403);
        abort_if($transaction->user_id !== Auth::id(), 403);

        if ($transaction->category_id === $category->id) {
            $date = $transaction->transaction_date;
            $transaction->category_id = null;
            $transaction->save();

            if ($transaction->type === 'expense') {
                $this->refreshBudget($category->user_id, $category->id, $date);
            }
        }

        return response()->json([
            'success' => true,
        ]);
    }

    private function refreshBudget(int $userId, int $categoryId, $date): void
    {
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
}
