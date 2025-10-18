<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'cash_flow_source_id',
        'special_expense_id',
        'amount',
        'type',
        'transaction_date',
        'posting_date',
        'description',
        'notes',
        'reference_number',
        'status'
    ];

    protected $with = ['category', 'cashFlowSource'];

    protected $casts = [
        'transaction_date' => 'date',
        'posting_date' => 'date',
        'amount' => 'decimal:2',
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

    // קשר למקור תזרים
    public function cashFlowSource(): BelongsTo
    {
        return $this->belongsTo(CashFlowSource::class);
    }

    // קשר להוצאה מיוחדת
    public function specialExpense(): BelongsTo
    {
        return $this->belongsTo(SpecialExpense::class);
    }

    // פונקציה לקבלת סכום עם סימן + או -
    public function getFormattedAmount(): string
    {
        $sign = $this->type === 'income' ? '+' : '-';
        return $sign . number_format($this->amount, 2);
    }

    // פונקציה לקבלת סכום עם צבע
    public function getAmountWithColor(): string
    {
        $color = $this->type === 'income' ? 'text-green-600' : 'text-red-600';
        return '<span class="' . $color . '">' . $this->getFormattedAmount() . '</span>';
    }

    // פונקציה לקבלת סטטוס בעברית
    public function getStatusInHebrew(): string
    {
        return match($this->status) {
            'completed' => 'הושלם',
            'pending' => 'ממתין',
            'cancelled' => 'בוטל',
            default => 'לא ידוע'
        };
    }

    // Scope לקבלת עסקאות לפי חודש ושנה
    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereNotNull('posting_date')
                    ->whereYear('posting_date', $year)
                    ->whereMonth('posting_date', $month);
    }

    // Scope לקבלת עסקאות לפי סוג
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
