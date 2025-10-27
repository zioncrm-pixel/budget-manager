<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Budget extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'year',
        'month',
        'planned_amount',
        'spent_amount',
        'remaining_amount'
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'planned_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    // קשר למשתמש
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // קשר לקטגוריה
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // פונקציה לעדכון סכום שהוצא
    public function updateSpentAmount(): void
    {
        $periodColumn = DB::raw('COALESCE(posting_date, transaction_date)');

        $categoryType = $this->category?->type ?? 'expense';

        $baseQuery = $this->user->transactions()
            ->where('category_id', $this->category_id)
            ->whereYear($periodColumn, $this->year)
            ->whereMonth($periodColumn, $this->month);

        $expenseTotal = (clone $baseQuery)->where('type', 'expense')->sum('amount');
        $incomeTotal = (clone $baseQuery)->where('type', 'income')->sum('amount');

        switch ($categoryType) {
            case 'income':
                $this->spent_amount = $incomeTotal;
                break;
            case 'both':
                $this->spent_amount = $expenseTotal - $incomeTotal;
                break;
            default:
                $this->spent_amount = $expenseTotal;
                break;
        }

        $this->remaining_amount = $this->planned_amount - $this->spent_amount;
        $this->save();
    }

    // פונקציה לקבלת אחוז התקדמות
    public function getProgressPercentage(): float
    {
        $planned = (float) $this->planned_amount;
        $effectiveSpent = max((float) $this->spent_amount, 0);

        if ($planned <= 0) {
            return $effectiveSpent > 0 ? 100.0 : 0.0;
        }

        $percentage = ($effectiveSpent / $planned) * 100;

        return round(min(max($percentage, 0), 999), 2);
    }

    // פונקציה לקבלת צבע לפי מצב התקציב
    public function getProgressColor(): string
    {
        $percentage = $this->getProgressPercentage();
        
        if ($percentage >= 90) return 'text-red-600'; // קרוב לסיום
        if ($percentage >= 75) return 'text-orange-600'; // בינוני
        if ($percentage >= 50) return 'text-yellow-600'; // טוב
        return 'text-green-600'; // מצוין
    }

    // פונקציה לקבלת צבע רקע לבר התקדמות
    public function getProgressBarColor(): string
    {
        $percentage = $this->getProgressPercentage();
        
        if ($percentage >= 90) return 'bg-red-500'; // אדום
        if ($percentage >= 75) return 'bg-orange-500'; // כתום
        if ($percentage >= 50) return 'bg-yellow-500'; // צהוב
        return 'bg-green-500'; // ירוק
    }

    // פונקציה לקבלת סטטוס תקציב
    public function getBudgetStatus(): string
    {
        $percentage = $this->getProgressPercentage();
        
        if ($percentage >= 100) return 'חרגת מהתקציב';
        if ($percentage >= 90) return 'קרוב לסיום';
        if ($percentage >= 75) return 'בינוני';
        if ($percentage >= 50) return 'טוב';
        return 'מצוין';
    }

    // Scope לקבלת תקציבים לפי חודש ושנה
    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    // Scope לקבלת תקציבים לפי קטגוריה
    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
