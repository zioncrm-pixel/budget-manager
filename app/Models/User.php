<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // קשר לקטגוריות
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    // קשר לעסקאות
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // קשר להוצאות מיוחדות
    public function specialExpenses(): HasMany
    {
        return $this->hasMany(SpecialExpense::class);
    }

    // קשר לתקציבים
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    // קשר למקורות תזרים
    public function cashFlowSources(): HasMany
    {
        return $this->hasMany(CashFlowSource::class);
    }

    // פונקציה לקבלת או יצירת תקציבים לחודש מסוים
    public function getOrCreateBudgetsForMonth(int $year, int $month): \Illuminate\Database\Eloquent\Collection
    {
        // קבלת תקציבים קיימים
        $existingBudgets = $this->budgets()
            ->with('category')
            ->forMonth($year, $month)
            ->get();

        // אם אין תקציבים, צור תקציבים אוטומטית לפי הכנסות החודש הקודם
        if ($existingBudgets->isEmpty()) {
            $this->createDefaultBudgets($year, $month);
            $existingBudgets = $this->budgets()
                ->with('category')
                ->forMonth($year, $month)
                ->get();
        }

        // עדכון סכומים שהוצאו לכל תקציב
        foreach ($existingBudgets as $budget) {
            $budget->updateSpentAmount();
        }

        return $existingBudgets;
    }

    // פונקציה ליצירת תקציבים ברירת מחדל
    private function createDefaultBudgets(int $year, int $month): void
    {
        // קבלת הכנסות החודש הקודם
        $previousMonth = $month == 1 ? 12 : $month - 1;
        $previousYear = $month == 1 ? $year - 1 : $year;
        
        $previousMonthIncome = $this->getTotalIncomeForMonth($previousYear, $previousMonth);
        
        // אם אין הכנסות, השתמש בסכום ברירת מחדל
        if ($previousMonthIncome <= 0) {
            $previousMonthIncome = 10000; // 10,000 ש"ח ברירת מחדל
        }

        // קבלת קטגוריות הוצאות
        $expenseCategories = $this->categories()->where('type', 'expense')->get();
        
        // חלוקת התקציב לפי קטגוריות (אפשר לשנות את האחוזים)
        $budgetPercentages = [
            'מזון' => 0.25,        // 25%
            'תחבורה' => 0.15,     // 15%
            'חשמל ומים' => 0.10,  // 10%
            'בילויים' => 0.15,    // 15%
            'קניות' => 0.20,      // 20%
            'אחר' => 0.15         // 15%
        ];

        foreach ($expenseCategories as $category) {
            $percentage = $budgetPercentages[$category->name] ?? 0.10;
            $plannedAmount = $previousMonthIncome * $percentage;
            
            Budget::create([
                'user_id' => $this->id,
                'category_id' => $category->id,
                'year' => $year,
                'month' => $month,
                'planned_amount' => $plannedAmount,
                'spent_amount' => 0,
                'remaining_amount' => $plannedAmount,
            ]);
        }
    }

    // פונקציה לקבלת סכום הכנסות בחודש מסוים
    public function getTotalIncomeForMonth(int $year, int $month): float
    {
        return $this->transactions()
            ->where('type', 'income')
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->sum('amount');
    }

    // פונקציה לקבלת סכום הוצאות בחודש מסוים
    public function getTotalExpensesForMonth(int $year, int $month): float
    {
        return $this->transactions()
            ->where('type', 'expense')
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->sum('amount');
    }

    // פונקציה לקבלת מאזן בחודש מסוים
    public function getBalanceForMonth(int $year, int $month): float
    {
        $income = $this->getTotalIncomeForMonth($year, $month);
        $expenses = $this->getTotalExpensesForMonth($year, $month);
        return $income - $expenses;
    }

    // פונקציה לקבלת סכום כולל של הוצאות מיוחדות בחודש מסוים
    public function getTotalSpecialExpensesForMonth(int $year, int $month): float
    {
        return $this->transactions()
            ->whereNotNull('special_expense_id')
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->sum('amount');
    }
}
