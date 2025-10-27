<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CashFlowSource;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Return a paginated list of transactions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var Builder<Transaction> $query */
        $query = Transaction::with(['category', 'cashFlowSource', 'specialExpense'])
            ->where('user_id', $user->id);

        if ($request->filled('year') && $request->filled('month')) {
            $query->whereYear('transaction_date', (int) $request->year)
                ->whereMonth('transaction_date', (int) $request->month);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }

        if ($request->filled('cash_flow_source_id')) {
            $query->where('cash_flow_source_id', (int) $request->cash_flow_source_id);
        }

        $perPage = (int) $request->get('per_page', 25);
        $transactions = $query->orderBy('transaction_date', 'desc')->paginate($perPage);

        return response()->json($transactions);
    }

    /**
     * Return auxiliary data required to create or edit a transaction.
     */
    public function formOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'categories' => Category::where('user_id', $user->id)->orderBy('name')->get(),
            'cashFlowSources' => CashFlowSource::where('user_id', $user->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'budgets' => Budget::where('user_id', $user->id)
                ->whereYear('year', now()->year)
                ->whereMonth('month', now()->month)
                ->get(),
        ]);
    }

    /**
     * Store a new transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateTransaction($request);
        $user = $request->user();

        $category = Category::where('id', $data['category_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($category->type !== 'both' && $category->type !== $data['type']) {
            return response()->json([
                'message' => 'הקטגוריה שנבחרה אינה תואמת לסוג התזרים',
            ], 422);
        }

        $cashFlowSource = null;
        if ($data['cash_flow_source_id']) {
            $cashFlowSource = CashFlowSource::where('id', $data['cash_flow_source_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            if ($cashFlowSource->type !== $data['type'] && !$cashFlowSource->allows_refunds) {
                return response()->json([
                    'message' => 'מקור התזרים שנבחר אינו מאפשר זיכויים מסוג זה',
                ], 422);
            }
        }

        $transaction = DB::transaction(function () use ($user, $data, $cashFlowSource) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'category_id' => $data['category_id'],
                'cash_flow_source_id' => $cashFlowSource?->id,
                'special_expense_id' => $data['special_expense_id'] ?? null,
                'amount' => $data['amount'],
                'type' => $data['type'],
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'status' => 'completed',
            ]);

            if ($data['type'] === 'expense') {
                $this->updateBudget($user->id, $data['category_id'], $data['transaction_date']);
            }

            return $transaction;
        });

        return response()->json([
            'message' => 'התזרים נוסף בהצלחה',
            'transaction' => $transaction->fresh(['category', 'cashFlowSource', 'specialExpense']),
        ], 201);
    }

    /**
     * Show a single transaction.
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeOwnership($request, $transaction);

        return response()->json([
            'transaction' => $transaction->load(['category', 'cashFlowSource', 'specialExpense']),
        ]);
    }

    /**
     * Update an existing transaction.
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeOwnership($request, $transaction);

        $data = $this->validateTransaction($request);
        $user = $request->user();

        $category = Category::where('id', $data['category_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($category->type !== 'both' && $category->type !== $data['type']) {
            return response()->json([
                'message' => 'הקטגוריה שנבחרה אינה תואמת לסוג התזרים',
            ], 422);
        }

        $cashFlowSource = null;
        if ($data['cash_flow_source_id']) {
            $cashFlowSource = CashFlowSource::where('id', $data['cash_flow_source_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            if ($cashFlowSource->type !== $data['type'] && !$cashFlowSource->allows_refunds) {
                return response()->json([
                    'message' => 'מקור התזרים שנבחר אינו מאפשר זיכויים מסוג זה',
                ], 422);
            }
        }

        DB::transaction(function () use ($transaction, $data, $user, $cashFlowSource) {
            $oldType = $transaction->type;
            $oldCategory = $transaction->category_id;
            $oldDate = $transaction->transaction_date;

            $transaction->update([
                'category_id' => $data['category_id'],
                'cash_flow_source_id' => $cashFlowSource?->id,
                'special_expense_id' => $data['special_expense_id'] ?? null,
                'amount' => $data['amount'],
                'type' => $data['type'],
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
            ]);

            if ($oldType === 'expense') {
                $this->updateBudget($user->id, $oldCategory, $oldDate);
            }

            if ($data['type'] === 'expense') {
                $this->updateBudget($user->id, $data['category_id'], $data['transaction_date']);
            }
        });

        return response()->json([
            'message' => 'התזרים עודכן בהצלחה',
            'transaction' => $transaction->fresh(['category', 'cashFlowSource', 'specialExpense']),
        ]);
    }

    /**
     * Delete a transaction.
     */
    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeOwnership($request, $transaction);

        DB::transaction(function () use ($transaction, $request) {
            $type = $transaction->type;
            $category = $transaction->category_id;
            $date = $transaction->transaction_date;
            $userId = $request->user()->id;

            $transaction->delete();

            if ($type === 'expense') {
                $this->updateBudget($userId, $category, $date);
            }
        });

        return response()->json(['message' => 'התזרים נמחק בהצלחה']);
    }

    /**
     * Validate transaction input.
     */
    private function validateTransaction(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:income,expense'],
            'category_id' => ['required', 'exists:categories,id'],
            'cash_flow_source_id' => ['nullable', 'exists:cash_flow_sources,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'special_expense_id' => ['nullable', 'exists:special_expenses,id'],
        ]);
    }

    private function authorizeOwnership(Request $request, Transaction $transaction): void
    {
        abort_unless($transaction->user_id === $request->user()->id, 404, 'תזרים לא נמצא');
    }

    private function updateBudget(int $userId, int $categoryId, $date): void
    {
        if (! $date instanceof Carbon) {
            $date = Carbon::parse($date);
        }

        $budget = Budget::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();

        if ($budget) {
            $budget->updateSpentAmount();
        }
    }
}
