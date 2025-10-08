<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BudgetController extends Controller
{
    /**
     * עדכון תקציב
     */
    public function update(Request $request): JsonResponse
    {
        // ולידציה של הקלט
        $validator = Validator::make($request->all(), [
            'budget_id' => 'required|exists:budgets,id',
            'planned_amount' => 'required|numeric|min:0|max:999999.99',
        ], [
            'budget_id.required' => 'מזהה תקציב נדרש',
            'budget_id.exists' => 'תקציב לא נמצא',
            'planned_amount.required' => 'סכום מתוכנן נדרש',
            'planned_amount.numeric' => 'סכום מתוכנן חייב להיות מספר',
            'planned_amount.min' => 'סכום מתוכנן חייב להיות חיובי',
            'planned_amount.max' => 'סכום מתוכנן לא יכול לעלות על 999,999.99',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // קבלת התקציב
            $budget = Budget::where('id', $request->budget_id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$budget) {
                return response()->json([
                    'success' => false,
                    'message' => 'תקציב לא נמצא או אין לך הרשאה לערוך אותו'
                ], 404);
            }

            // עדכון התקציב
            $budget->planned_amount = $request->planned_amount;
            $budget->remaining_amount = $request->planned_amount - $budget->spent_amount;
            $budget->save();

            // עדכון סכום שהוצא (למקרה שיש עסקאות חדשות)
            $budget->updateSpentAmount();

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
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'שגיאה בעדכון התקציב: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * קבלת פרטי תקציב לעריכה
     */
    public function show(int $budgetId): JsonResponse
    {
        try {
            $budget = Budget::where('id', $budgetId)
                ->where('user_id', Auth::id())
                ->with('category')
                ->first();

            if (!$budget) {
                return response()->json([
                    'success' => false,
                    'message' => 'תקציב לא נמצא'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'budget' => [
                    'id' => $budget->id,
                    'category_name' => $budget->category->name,
                    'category_icon' => $budget->category->icon,
                    'planned_amount' => $budget->planned_amount,
                    'spent_amount' => $budget->spent_amount,
                    'remaining_amount' => $budget->remaining_amount,
                    'year' => $budget->year,
                    'month' => $budget->month,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'שגיאה בקבלת פרטי התקציב: ' . $e->getMessage()
            ], 500);
        }
    }
}
