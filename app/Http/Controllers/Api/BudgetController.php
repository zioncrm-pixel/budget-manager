<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BudgetController extends Controller
{
    /**
     * List budgets for the requested month.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $budgets = $user->getOrCreateBudgetsForMonth($year, $month);

        return response()->json([
            'year' => $year,
            'month' => $month,
            'budgets' => $budgets->map(fn (Budget $budget) => [
                'id' => $budget->id,
                'category' => [
                    'id' => $budget->category->id,
                    'name' => $budget->category->name,
                    'icon' => $budget->category->icon,
                    'color' => $budget->category->color,
                ],
                'planned_amount' => $budget->planned_amount,
                'spent_amount' => $budget->spent_amount,
                'remaining_amount' => $budget->remaining_amount,
                'progress_percentage' => $budget->getProgressPercentage(),
                'progress_color' => $budget->getProgressColor(),
                'progress_bar_color' => $budget->getProgressBarColor(),
                'budget_status' => $budget->getBudgetStatus(),
            ]),
        ]);
    }

    /**
     * Show details for a specific budget.
     */
    public function show(Request $request, Budget $budget): JsonResponse
    {
        $this->authorizeOwnership($request, $budget);

        $budget->load('category');
        $budget->updateSpentAmount();

        return response()->json([
            'budget' => [
                'id' => $budget->id,
                'category' => [
                    'id' => $budget->category->id,
                    'name' => $budget->category->name,
                    'icon' => $budget->category->icon,
                    'color' => $budget->category->color,
                ],
                'planned_amount' => $budget->planned_amount,
                'spent_amount' => $budget->spent_amount,
                'remaining_amount' => $budget->remaining_amount,
                'year' => $budget->year,
                'month' => $budget->month,
                'progress_percentage' => $budget->getProgressPercentage(),
                'progress_color' => $budget->getProgressColor(),
                'progress_bar_color' => $budget->getProgressBarColor(),
                'budget_status' => $budget->getBudgetStatus(),
            ],
        ]);
    }

    /**
     * Update the planned amount for a budget entry.
     */
    public function update(Request $request, Budget $budget): JsonResponse
    {
        $this->authorizeOwnership($request, $budget);

        $validator = Validator::make($request->all(), [
            'planned_amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ], [
            'planned_amount.required' => 'סכום מתוכנן נדרש',
            'planned_amount.numeric' => 'סכום מתוכנן חייב להיות מספר',
            'planned_amount.min' => 'סכום מתוכנן חייב להיות חיובי',
            'planned_amount.max' => 'סכום מתוכנן לא יכול לעלות על 999,999.99',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $budget->planned_amount = $request->planned_amount;
        $budget->remaining_amount = $budget->planned_amount - $budget->spent_amount;
        $budget->save();
        $budget->updateSpentAmount();
        $budget->load('category');

        return response()->json([
            'success' => true,
            'message' => 'התקציב עודכן בהצלחה',
            'budget' => [
                'id' => $budget->id,
                'planned_amount' => $budget->planned_amount,
                'spent_amount' => $budget->spent_amount,
                'remaining_amount' => $budget->remaining_amount,
                'progress_percentage' => $budget->getProgressPercentage(),
                'progress_color' => $budget->getProgressColor(),
                'progress_bar_color' => $budget->getProgressBarColor(),
                'budget_status' => $budget->getBudgetStatus(),
            ],
        ]);
    }

    private function authorizeOwnership(Request $request, Budget $budget): void
    {
        abort_unless($budget->user_id === $request->user()->id, 404, 'תקציב לא נמצא');
    }
}
