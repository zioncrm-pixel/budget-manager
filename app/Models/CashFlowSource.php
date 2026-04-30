<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class CashFlowSource extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'color',
        'icon',
        'description',
        'is_active',
        'allows_refunds',
        'year',
        'month',
        'exclude_from_totals',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allows_refunds' => 'boolean',
        'year' => 'integer',
        'month' => 'integer',
        'exclude_from_totals' => 'boolean',
    ];

    public function scopeVisibleForPeriod(Builder $query, ?int $year, ?int $month): Builder
    {
        if (!$year || !$month) {
            return $query;
        }

        return $query->where(function (Builder $visibilityQuery) use ($year, $month) {
            $visibilityQuery
                ->whereNull('year')
                ->whereNull('month')
                ->orWhere(function (Builder $scopedQuery) use ($year, $month) {
                    $scopedQuery
                        ->where('year', $year)
                        ->where('month', $month);
                });
        });
    }

    // קשר למשתמש
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // קשר לתזרימים
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(CashFlowSourceBudget::class);
    }

    // פונקציה לחישוב סכום כולל למקור בחודש מסוים
    public function getTotalAmountForMonth(int $year, int $month): float
    {
        return $this->transactions()
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->sum('amount');
    }

    // פונקציה לקבלת סכום הכנסות או הוצאות למקור בחודש מסוים
    public function getAmountByTypeForMonth(int $year, int $month, string $type): float
    {
        return $this->transactions()
            ->where('type', $type)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->sum('amount');
    }

    // פונקציה לקבלת צבע ברירת מחדל לפי סוג
    public static function getDefaultColor(string $type): string
    {
        return match($type) {
            'income' => '#10B981', // ירוק להכנסות
            'expense' => '#EF4444', // אדום להוצאות
            default => '#3B82F6' // כחול כברירת מחדל
        };
    }

    // פונקציה לקבלת מספר התזרימים למקור בחודש מסוים
    public function getTransactionCountForMonth(int $year, int $month): int
    {
        return $this->transactions()
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->count();
    }
}
