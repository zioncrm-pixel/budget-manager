<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecialExpense extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'start_date',
        'due_date',
        'next_payment_date',
        'account_number',
        'description',
        'status'
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'next_payment_date' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
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

    // פונקציה לעדכון סכום ששולם
    public function updatePaidAmount(): void
    {
        $this->paid_amount = $this->transactions()->sum('amount');
        $this->remaining_amount = $this->total_amount - $this->paid_amount;
        
        // עדכון סטטוס אם שולם הכל
        if ($this->remaining_amount <= 0) {
            $this->status = 'completed';
        }
        
        $this->save();
    }

    // פונקציה לקבלת אחוז התקדמות
    public function getProgressPercentage(): float
    {
        if ($this->total_amount <= 0) return 0;
        return round(($this->paid_amount / $this->total_amount) * 100, 2);
    }

    // פונקציה לקבלת סטטוס בעברית
    public function getStatusInHebrew(): string
    {
        return match($this->status) {
            'active' => 'פעיל',
            'completed' => 'הושלם',
            'overdue' => 'באיחור',
            'cancelled' => 'בוטל',
            default => 'לא ידוע'
        };
    }

    // פונקציה לקבלת סוג בעברית
    public function getTypeInHebrew(): string
    {
        return match($this->type) {
            'credit_card' => 'כרטיס אשראי',
            'loan' => 'הלוואה',
            'insurance' => 'ביטוח',
            'subscription' => 'מנוי',
            'other' => 'אחר',
            default => 'לא ידוע'
        };
    }

    // פונקציה לבדיקה אם יש תשלום באיחור
    public function isOverdue(): bool
    {
        if (!$this->due_date) return false;
        return $this->due_date->isPast() && $this->status === 'active';
    }

    // פונקציה לקבלת צבע לפי סטטוס
    public function getStatusColor(): string
    {
        return match($this->status) {
            'active' => 'text-blue-600',
            'completed' => 'text-green-600',
            'overdue' => 'text-red-600',
            'cancelled' => 'text-gray-500',
            default => 'text-gray-600'
        };
    }
}
