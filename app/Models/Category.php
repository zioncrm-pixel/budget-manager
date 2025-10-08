<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'color',
        'icon',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // קשר למשתמש
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // קשר לעסקאות
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // קשר לתקציבים
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    // פונקציה לחישוב סכום כולל לקטגוריה בחודש מסוים
    public function getTotalAmountForMonth(int $year, int $month): float
    {
        return $this->transactions()
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->sum('amount');
    }

    // פונקציה לקבלת סכום הכנסות או הוצאות לקטגוריה בחודש מסוים
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


}
