<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\Budget;
use App\Models\CashFlowSource;
use App\Models\CashFlowSourceBudget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransactionController extends Controller
{
    use AuthorizesRequests;
    /**
     * הצגת רשימת תזרימים
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Transaction::with(['category', 'cashFlowSource', 'specialExpense'])
            ->where('user_id', $user->id);
        
        // סינון לפי תאריך
        if ($request->filled('year') && $request->filled('month')) {
            $query->whereYear('transaction_date', $request->year)
                  ->whereMonth('transaction_date', $request->month);
        }
        
        // סינון לפי סוג
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        // סינון לפי קטגוריה
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        // סינון לפי מקור תזרים
        if ($request->filled('cash_flow_source_id')) {
            $query->where('cash_flow_source_id', $request->cash_flow_source_id);
        }
        
        $transactions = $query->orderBy('transaction_date', 'desc')
                             ->paginate(50);
        
        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'categories' => Category::where('user_id', $user->id)->get(),
            'cashFlowSources' => CashFlowSource::where('user_id', $user->id)->where('is_active', true)->get(),
            'filters' => $request->only(['year', 'month', 'type', 'category_id', 'cash_flow_source_id'])
        ]);
    }

    /**
     * הצגת טופס יצירת תזרים חדש
     */
    public function create()
    {
        $user = Auth::user();
        
        return Inertia::render('Transactions/Create', [
            'categories' => Category::where('user_id', $user->id)->get(),
            'cashFlowSources' => CashFlowSource::where('user_id', $user->id)->where('is_active', true)->get(),
            'budgets' => Budget::where('user_id', $user->id)
                              ->whereYear('year', now()->year)
                              ->whereMonth('month', now()->month)
                              ->get()
        ]);
    }

    /**
     * שמירת תזרים חדש
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:income,expense',
            'category_id' => 'required|exists:categories,id',
            'cash_flow_source_id' => 'nullable|exists:cash_flow_sources,id', // השתנה ל-nullable
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        
        // בדיקה שהקטגוריה שייכת למשתמש
        $category = Category::where('id', $request->category_id)
                           ->where('user_id', $user->id)
                           ->firstOrFail();
        
        // בדיקה שהקטגוריה תואמת לסוג התזרים
        if ($category->type !== $request->type) {
            return back()->withErrors([
                'category_id' => 'הקטגוריה שנבחרה אינה תואמת לסוג התזרים'
            ]);
        }

        // בדיקה שמקור התזרים שייך למשתמש (אם נבחר)
        $cashFlowSource = null;
        if ($request->filled('cash_flow_source_id')) {
            $cashFlowSource = CashFlowSource::where('id', $request->cash_flow_source_id)
                                          ->where('user_id', $user->id)
                                          ->firstOrFail();
            
            // בדיקה שמקור התזרים תואם לסוג התזרים
            if ($cashFlowSource->type !== $request->type) {
                return back()->withErrors([
                    'cash_flow_source_id' => 'מקור התזרים שנבחר אינו תואם לסוג התזרים'
                ]);
            }
        }

        DB::beginTransaction();
        
        try {
            // יצירת התזרים
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'category_id' => $request->category_id,
                'cash_flow_source_id' => $request->cash_flow_source_id ?? null,
                'special_expense_id' => $request->special_expense_id ?? null,
                'amount' => $request->amount,
                'type' => $request->type,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'notes' => $request->notes,
                'reference_number' => $request->reference_number,
                'status' => 'completed'
            ]);

            // עדכון התקציב אם זה הוצאה
            if ($request->type === 'expense') {
                $this->updateBudget($user->id, $request->category_id, $request->transaction_date);
            }

            if ($request->filled('cash_flow_source_id')) {
                $this->updateCashFlowSourceBudget($user->id, $request->cash_flow_source_id, $request->transaction_date);
            }

            DB::commit();

            return redirect()->back()->with('success', 'התזרים נוסף בהצלחה');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'שגיאה בהוספת התזרים: ' . $e->getMessage()]);
        }
    }

    /**
     * הצגת תזרים ספציפי
     */
    public function show(Transaction $transaction)
    {
        abort_if($transaction->user_id !== Auth::id(), 403);

        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load(['category', 'cashFlowSource', 'specialExpense'])
        ]);
    }

    /**
     * הצגת טופס עריכת תזרים
     */
    public function edit(Transaction $transaction)
    {
        abort_if($transaction->user_id !== Auth::id(), 403);

        $user = Auth::user();
        
        return Inertia::render('Transactions/Edit', [
            'transaction' => $transaction,
            'categories' => Category::where('user_id', $user->id)->get(),
            'cashFlowSources' => CashFlowSource::where('user_id', $user->id)->where('is_active', true)->get(),
            'budgets' => Budget::where('user_id', $user->id)
                              ->whereYear('year', $transaction->transaction_date->year)
                              ->whereMonth('month', $transaction->transaction_date->month)
                              ->get()
        ]);
    }

    /**
     * עדכון תזרים
     */
    public function update(Request $request, Transaction $transaction)
    {
        abort_if($transaction->user_id !== Auth::id(), 403);

        $request->validate([
            'type' => 'required|in:income,expense',
            'category_id' => 'nullable|exists:categories,id',
            'cash_flow_source_id' => 'nullable|exists:cash_flow_sources,id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        
        $category = null;
        if ($request->filled('category_id')) {
            $category = Category::where('id', $request->category_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if ($category->type !== $request->type) {
                return back()->withErrors([
                    'category_id' => 'הקטגוריה שנבחרה אינה תואמת לסוג התזרים'
                ]);
            }
        }

        $cashFlowSource = null;
        if ($request->filled('cash_flow_source_id')) {
            $cashFlowSource = CashFlowSource::where('id', $request->cash_flow_source_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if ($cashFlowSource->type !== $request->type) {
                return back()->withErrors([
                    'cash_flow_source_id' => 'מקור התזרים שנבחר אינו תואם לסוג התזרים'
                ]);
            }
        }

        DB::beginTransaction();
        
        try {
            // שמירת הסכום הישן לעדכון התקציב
            $oldAmount = $transaction->amount;
            $oldType = $transaction->type;
            $oldDate = $transaction->transaction_date;
            $oldCategoryId = $transaction->category_id;
            $oldCashFlowSourceId = $transaction->cash_flow_source_id;

            // עדכון התזרים
            $transaction->update([
                'category_id' => $request->category_id ?: null,
                'cash_flow_source_id' => $request->cash_flow_source_id ?? null,
                'special_expense_id' => $request->special_expense_id,
                'amount' => $request->amount,
                'type' => $request->type,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'notes' => $request->notes,
                'reference_number' => $request->reference_number,
            ]);

            if ($oldType === 'expense' && $oldCategoryId) {
                $this->updateBudget($user->id, $oldCategoryId, $oldDate);
            }

            if ($request->type === 'expense' && $request->filled('category_id')) {
                $this->updateBudget($user->id, $request->category_id, $request->transaction_date);
            }

            if ($oldCashFlowSourceId) {
                $this->updateCashFlowSourceBudget($user->id, $oldCashFlowSourceId, $oldDate);
            }

            if ($request->filled('cash_flow_source_id')) {
                $this->updateCashFlowSourceBudget($user->id, $request->cash_flow_source_id, $request->transaction_date);
            }

            DB::commit();

            return redirect()->back()->with('success', 'התזרים עודכן בהצלחה');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'שגיאה בעדכון התזרים: ' . $e->getMessage()]);
        }
    }

    /**
     * מחיקת תזרים
     */
    public function destroy(Transaction $transaction)
    {
        abort_if($transaction->user_id !== Auth::id(), 403);

        DB::beginTransaction();
        
        try {
            $user = Auth::user();
            $type = $transaction->type;
            $date = $transaction->transaction_date;
            $categoryId = $transaction->category_id;

            $transaction->delete();

            if ($type === 'expense' && $categoryId) {
                $this->updateBudget($user->id, $categoryId, $date);
            }

            if ($transaction->cash_flow_source_id) {
                $this->updateCashFlowSourceBudget($user->id, $transaction->cash_flow_source_id, $date);
            }

            DB::commit();

            return redirect()->back()->with('success', 'התזרים נמחק בהצלחה');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'שגיאה במחיקת התזרים: ' . $e->getMessage()]);
        }
    }

    public function duplicate(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_if($transaction->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $user = $request->user();

        DB::beginTransaction();

        try {
            $originalDate = $transaction->transaction_date instanceof Carbon
                ? $transaction->transaction_date->copy()
                : Carbon::parse($transaction->transaction_date);

            $targetMonth = Carbon::create($data['year'], $data['month'], 1)->startOfDay();
            $day = min($originalDate->day, $targetMonth->copy()->endOfMonth()->day);
            $newDate = $targetMonth->copy()->day($day)->setTime(
                (int) $originalDate->format('H'),
                (int) $originalDate->format('i'),
                (int) $originalDate->format('s')
            );

            $newTransaction = $transaction->replicate();
            $newTransaction->transaction_date = $newDate;
            $newTransaction->save();

            if ($newTransaction->type === 'expense' && $newTransaction->category_id) {
                $this->updateBudget($user->id, $newTransaction->category_id, $newTransaction->transaction_date);
            }

            if ($newTransaction->cash_flow_source_id) {
                $this->updateCashFlowSourceBudget($user->id, $newTransaction->cash_flow_source_id, $newTransaction->transaction_date);
            }

            DB::commit();

            return back()->with('success', 'התזרים שוכפל בהצלחה');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'שגיאה בשכפול התזרים: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * עדכון תקציב לקטגוריה ותאריך מסוימים
     */
    private function updateBudget($userId, $categoryId, $date)
    {
        if (!$date instanceof Carbon) {
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

    private function updateCashFlowSourceBudget($userId, $cashFlowSourceId, $date): void
    {
        if (!$cashFlowSourceId || !$date) {
            return;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        $budget = CashFlowSourceBudget::where('user_id', $userId)
            ->where('cash_flow_source_id', $cashFlowSourceId)
            ->where('year', $carbon->year)
            ->where('month', $carbon->month)
            ->first();

        if ($budget) {
            $budget->updateSpentAmount();
        }
    }

    public function duplicateBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'exists:transactions,id'],
            'date' => ['required', 'date'],
        ]);

        $user = $request->user();

        $transactions = Transaction::where('user_id', $user->id)
            ->whereIn('id', $data['transaction_ids'])
            ->get();

        if ($transactions->count() !== count($data['transaction_ids'])) {
            return back()->withErrors([
                'transaction_ids' => 'נמצאו תזרימים שאינם זמינים לשכפול.',
            ]);
        }

        $targetDate = Carbon::parse($data['date']);

        DB::beginTransaction();

        try {
            $categoryUpdates = [];
            $cashFlowUpdates = [];

            foreach ($transactions as $transaction) {
                $originalDate = $transaction->transaction_date instanceof Carbon
                    ? $transaction->transaction_date->copy()
                    : Carbon::parse($transaction->transaction_date);

                $newDate = $targetDate->copy()->setTime(
                    (int) $originalDate->format('H'),
                    (int) $originalDate->format('i'),
                    (int) $originalDate->format('s')
                );

                $newTransaction = $transaction->replicate();
                $newTransaction->transaction_date = $newDate;
                $newTransaction->save();

                if ($newTransaction->type === 'expense' && $newTransaction->category_id) {
                    $categoryUpdates[] = [
                        'category_id' => $newTransaction->category_id,
                        'date' => $newTransaction->transaction_date,
                    ];
                }

                if ($newTransaction->cash_flow_source_id) {
                    $cashFlowUpdates[] = [
                        'cash_flow_source_id' => $newTransaction->cash_flow_source_id,
                        'date' => $newTransaction->transaction_date,
                    ];
                }
            }

            collect($categoryUpdates)
                ->unique(function ($item) {
                    $carbon = $item['date'] instanceof Carbon ? $item['date'] : Carbon::parse($item['date']);

                    return $item['category_id'] . '-' . $carbon->format('Y-m');
                })
                ->each(function ($item) use ($user) {
                    $this->updateBudget($user->id, $item['category_id'], $item['date']);
                });

            collect($cashFlowUpdates)
                ->unique(function ($item) {
                    $carbon = $item['date'] instanceof Carbon ? $item['date'] : Carbon::parse($item['date']);

                    return $item['cash_flow_source_id'] . '-' . $carbon->format('Y-m');
                })
                ->each(function ($item) use ($user) {
                    $this->updateCashFlowSourceBudget($user->id, $item['cash_flow_source_id'], $item['date']);
                });

            DB::commit();

            return back()->with('success', 'התזרימים שוכפלו בהצלחה');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'שגיאה בשכפול התזרימים: ' . $e->getMessage(),
            ]);
        }
    }

    public function deleteBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'exists:transactions,id'],
        ]);

        $user = $request->user();

        $transactions = Transaction::where('user_id', $user->id)
            ->whereIn('id', $data['transaction_ids'])
            ->get();

        if ($transactions->count() !== count($data['transaction_ids'])) {
            return back()->withErrors([
                'transaction_ids' => 'נמצאו תזרימים שאינם זמינים למחיקה.',
            ]);
        }

        DB::beginTransaction();

        try {
            $categoryUpdates = [];
            $cashFlowUpdates = [];

            foreach ($transactions as $transaction) {
                $date = $transaction->transaction_date instanceof Carbon
                    ? $transaction->transaction_date->copy()
                    : Carbon::parse($transaction->transaction_date);

                if ($transaction->type === 'expense' && $transaction->category_id) {
                    $categoryUpdates[] = [
                        'category_id' => $transaction->category_id,
                        'date' => $date,
                    ];
                }

                if ($transaction->cash_flow_source_id) {
                    $cashFlowUpdates[] = [
                        'cash_flow_source_id' => $transaction->cash_flow_source_id,
                        'date' => $date,
                    ];
                }

                $transaction->delete();
            }

            collect($categoryUpdates)
                ->unique(function ($item) {
                    $carbon = $item['date'] instanceof Carbon ? $item['date'] : Carbon::parse($item['date']);

                    return $item['category_id'] . '-' . $carbon->format('Y-m');
                })
                ->each(function ($item) use ($user) {
                    $this->updateBudget($user->id, $item['category_id'], $item['date']);
                });

            collect($cashFlowUpdates)
                ->unique(function ($item) {
                    $carbon = $item['date'] instanceof Carbon ? $item['date'] : Carbon::parse($item['date']);

                    return $item['cash_flow_source_id'] . '-' . $carbon->format('Y-m');
                })
                ->each(function ($item) use ($user) {
                    $this->updateCashFlowSourceBudget($user->id, $item['cash_flow_source_id'], $item['date']);
                });

            DB::commit();

            return back()->with('success', 'התזרימים נמחקו בהצלחה');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'שגיאה במחיקת התזרימים: ' . $e->getMessage(),
            ]);
        }
    }
}
