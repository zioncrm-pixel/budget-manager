<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowSourceBudget extends Model
{
    protected $fillable = [
        'user_id',
        'cash_flow_source_id',
        'year',
        'month',
        'planned_amount',
        'spent_amount',
        'remaining_amount',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'planned_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashFlowSource(): BelongsTo
    {
        return $this->belongsTo(CashFlowSource::class);
    }

    public function updateSpentAmount(): void
    {
        $source = $this->cashFlowSource;

        $query = $this->user->transactions()
            ->where('cash_flow_source_id', $this->cash_flow_source_id)
            ->whereYear('transaction_date', $this->year)
            ->whereMonth('transaction_date', $this->month);

        if ($source?->type) {
            $query->where('type', $source->type);
        }

        $this->spent_amount = $query->sum('amount');
        $this->remaining_amount = $this->planned_amount - $this->spent_amount;
        $this->save();
    }

    public function getProgressPercentage(): float
    {
        if ($this->planned_amount <= 0) {
            return 0;
        }

        return round(($this->spent_amount / $this->planned_amount) * 100, 2);
    }

    public function getProgressColor(): string
    {
        $percentage = $this->getProgressPercentage();

        if ($percentage >= 100) {
            return 'text-red-600';
        }

        if ($percentage >= 90) {
            return 'text-red-600';
        }

        if ($percentage >= 75) {
            return 'text-yellow-600';
        }

        return 'text-green-600';
    }

    public function getProgressBarColor(): string
    {
        $percentage = $this->getProgressPercentage();

        if ($percentage >= 100) {
            return 'bg-red-600';
        }

        if ($percentage >= 90) {
            return 'bg-red-500';
        }

        if ($percentage >= 75) {
            return 'bg-yellow-500';
        }

        return 'bg-green-500';
    }
}
