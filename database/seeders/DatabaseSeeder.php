<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\SpecialExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // יצירת משתמש לדוגמה
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'משתמש לדוגמה',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );

        // יצירת קטגוריות לדוגמה
        $incomeCategories = [
            ['name' => 'משכורת', 'type' => 'income', 'color' => '#10B981', 'icon' => '💰'],
            ['name' => 'הכנסות נוספות', 'type' => 'income', 'color' => '#059669', 'icon' => '💼'],
            ['name' => 'החזרי מס', 'type' => 'income', 'color' => '#047857', 'icon' => '📊'],
        ];

        $expenseCategories = [
            ['name' => 'מזון', 'type' => 'expense', 'color' => '#EF4444', 'icon' => '🍽️'],
            ['name' => 'תחבורה', 'type' => 'expense', 'color' => '#DC2626', 'icon' => '🚗'],
            ['name' => 'חשמל ומים', 'type' => 'expense', 'color' => '#B91C1C', 'icon' => '⚡'],
            ['name' => 'בילויים', 'type' => 'expense', 'color' => '#991B1B', 'icon' => '🎉'],
            ['name' => 'קניות', 'type' => 'expense', 'color' => '#7F1D1D', 'icon' => '🛒'],
        ];

        $categories = [];
        
        foreach ($incomeCategories as $cat) {
            $categories[] = Category::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $cat['name'],
                ],
                [
                    'type' => $cat['type'],
                    'color' => $cat['color'],
                    'icon' => $cat['icon'],
                    'description' => 'קטגוריה לדוגמה',
                ]
            );
        }

        foreach ($expenseCategories as $cat) {
            $categories[] = Category::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $cat['name'],
                ],
                [
                    'type' => $cat['type'],
                    'color' => $cat['color'],
                    'icon' => $cat['icon'],
                    'description' => 'קטגוריה לדוגמה',
                ]
            );
        }

        // יצירת הוצאות מיוחדות לדוגמה
        $specialExpenses = [
            [
                'name' => 'כרטיס אשראי לאומי',
                'type' => 'credit_card',
                'total_amount' => 5000,
                'start_date' => Carbon::now()->subMonths(2),
                'due_date' => Carbon::now()->addDays(15),
                'account_number' => '1234-5678-9012-3456',
            ],
            [
                'name' => 'הלוואת משכנתא',
                'type' => 'loan',
                'total_amount' => 800000,
                'start_date' => Carbon::now()->subYears(2),
                'due_date' => Carbon::now()->addYears(23),
                'account_number' => 'IL-123456789',
            ],
            [
                'name' => 'ביטוח רכב',
                'type' => 'insurance',
                'total_amount' => 2400,
                'start_date' => Carbon::now()->subMonths(6),
                'due_date' => Carbon::now()->addMonths(6),
                'account_number' => 'INS-987654321',
            ],
        ];

        foreach ($specialExpenses as $exp) {
            SpecialExpense::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $exp['name'],
                ],
                [
                    'type' => $exp['type'],
                    'total_amount' => $exp['total_amount'],
                    'paid_amount' => 0,
                    'remaining_amount' => $exp['total_amount'],
                    'start_date' => $exp['start_date'],
                    'due_date' => $exp['due_date'],
                    'description' => 'הוצאה מיוחדת לדוגמה',
                ]
            );
        }

        // יצירת עסקאות לחודש הנוכחי
        $currentMonth = Carbon::now();
        $transactions = [
            // הכנסות
            ['type' => 'income', 'category' => 'משכורת', 'amount' => 12000, 'description' => 'משכורת חודש ינואר', 'date' => $currentMonth->copy()->day(1)],
            ['type' => 'income', 'category' => 'הכנסות נוספות', 'amount' => 1500, 'description' => 'עבודה נוספת', 'date' => $currentMonth->copy()->day(15)],
            ['type' => 'income', 'category' => 'החזרי מס', 'amount' => 800, 'description' => 'החזר מס שנתי', 'date' => $currentMonth->copy()->day(20)],
            
            // הוצאות מזון - הרבה עסקאות קטנות
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 120, 'description' => 'קניות ירקות', 'date' => $currentMonth->copy()->day(2)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 85, 'description' => 'לחם וחלב', 'date' => $currentMonth->copy()->day(4)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 200, 'description' => 'קניות שבועיות', 'date' => $currentMonth->copy()->day(6)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 150, 'description' => 'ארוחה במסעדה', 'date' => $currentMonth->copy()->day(8)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 95, 'description' => 'קפה ועוגה', 'date' => $currentMonth->copy()->day(10)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 180, 'description' => 'קניות נוספות', 'date' => $currentMonth->copy()->day(12)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 70, 'description' => 'פירות', 'date' => $currentMonth->copy()->day(14)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 110, 'description' => 'ארוחת צהריים', 'date' => $currentMonth->copy()->day(16)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 140, 'description' => 'קניות אחרונות', 'date' => $currentMonth->copy()->day(18)],
            
            // הוצאות תחבורה - כמה עסקאות בינוניות
            ['type' => 'expense', 'category' => 'תחבורה', 'amount' => 300, 'description' => 'דלק', 'date' => $currentMonth->copy()->day(3)],
            ['type' => 'expense', 'category' => 'תחבורה', 'amount' => 120, 'description' => 'חנייה', 'date' => $currentMonth->copy()->day(5)],
            ['type' => 'expense', 'category' => 'תחבורה', 'amount' => 80, 'description' => 'אוטובוס', 'date' => $currentMonth->copy()->day(7)],
            ['type' => 'expense', 'category' => 'תחבורה', 'amount' => 200, 'description' => 'דלק נוסף', 'date' => $currentMonth->copy()->day(9)],
            ['type' => 'expense', 'category' => 'תחבורה', 'amount' => 60, 'description' => 'חנייה נוספת', 'date' => $currentMonth->copy()->day(11)],
            
            // הוצאות חשמל ומים - עסקאות גדולות
            ['type' => 'expense', 'category' => 'חשמל ומים', 'amount' => 450, 'description' => 'חשבון חשמל', 'date' => $currentMonth->copy()->day(10)],
            ['type' => 'expense', 'category' => 'חשמל ומים', 'amount' => 180, 'description' => 'חשבון מים', 'date' => $currentMonth->copy()->day(15)],
            
            // הוצאות בילויים - עסקאות בינוניות
            ['type' => 'expense', 'category' => 'בילויים', 'amount' => 200, 'description' => 'ארוחה במסעדה', 'date' => $currentMonth->copy()->day(12)],
            ['type' => 'expense', 'category' => 'בילויים', 'amount' => 150, 'description' => 'סרט בקולנוע', 'date' => $currentMonth->copy()->day(19)],
            ['type' => 'expense', 'category' => 'בילויים', 'amount' => 300, 'description' => 'בילוי עם חברים', 'date' => $currentMonth->copy()->day(22)],
            
            // הוצאות קניות - עסקאות מגוונות
            ['type' => 'expense', 'category' => 'קניות', 'amount' => 150, 'description' => 'בגדים', 'date' => $currentMonth->copy()->day(18)],
            ['type' => 'expense', 'category' => 'קניות', 'amount' => 80, 'description' => 'ספרים', 'date' => $currentMonth->copy()->day(20)],
            ['type' => 'expense', 'category' => 'קניות', 'amount' => 120, 'description' => 'מוצרי בית', 'date' => $currentMonth->copy()->day(24)],
            ['type' => 'expense', 'category' => 'קניות', 'amount' => 200, 'description' => 'מתנה ליום הולדת', 'date' => $currentMonth->copy()->day(26)],
        ];

        foreach ($transactions as $trans) {
            $category = collect($categories)->firstWhere('name', $trans['category']);
            
            Transaction::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'description' => $trans['description'],
                    'transaction_date' => $trans['date'],
                ],
                [
                    'category_id' => $category->id,
                    'amount' => $trans['amount'],
                    'type' => $trans['type'],
                    'status' => 'completed',
                ]
            );
        }

        // יצירת עסקאות לחודש הקודם (יוני)
        $lastMonth = Carbon::now()->subMonth();
        $lastMonthTransactions = [
            ['type' => 'income', 'category' => 'משכורת', 'amount' => 12000, 'description' => 'משכורת חודש יוני', 'date' => $lastMonth->copy()->day(1)],
            ['type' => 'income', 'category' => 'הכנסות נוספות', 'amount' => 800, 'description' => 'עבודה נוספת', 'date' => $lastMonth->copy()->day(15)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 650, 'description' => 'קניות שבועיות', 'date' => $lastMonth->copy()->day(2)],
            ['type' => 'expense', 'category' => 'תחבורה', 'amount' => 250, 'description' => 'דלק', 'date' => $lastMonth->copy()->day(6)],
            ['type' => 'expense', 'category' => 'חשמל ומים', 'amount' => 380, 'description' => 'חשבון חשמל', 'date' => $lastMonth->copy()->day(10)],
            ['type' => 'expense', 'category' => 'בילויים', 'amount' => 150, 'description' => 'ארוחה במסעדה', 'date' => $lastMonth->copy()->day(12)],
            ['type' => 'expense', 'category' => 'קניות', 'amount' => 100, 'description' => 'בגדים', 'date' => $lastMonth->copy()->day(18)],
        ];

        foreach ($lastMonthTransactions as $trans) {
            $category = collect($categories)->firstWhere('name', $trans['category']);
            
            Transaction::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'description' => $trans['description'],
                    'transaction_date' => $trans['date'],
                ],
                [
                    'category_id' => $category->id,
                    'amount' => $trans['amount'],
                    'type' => $trans['type'],
                    'status' => 'completed',
                ]
            );
        }

        // יצירת עסקאות לחודש הבא (אוגוסט)
        $nextMonth = Carbon::now()->addMonth();
        $nextMonthTransactions = [
            ['type' => 'income', 'category' => 'משכורת', 'amount' => 12000, 'description' => 'משכורת חודש אוגוסט', 'date' => $nextMonth->copy()->day(1)],
            ['type' => 'income', 'category' => 'הכנסות נוספות', 'amount' => 2000, 'description' => 'עבודה נוספת', 'date' => $nextMonth->copy()->day(15)],
            ['type' => 'income', 'category' => 'החזרי מס', 'amount' => 1200, 'description' => 'החזר מס נוסף', 'date' => $nextMonth->copy()->day(20)],
            ['type' => 'expense', 'category' => 'מזון', 'amount' => 1400, 'description' => 'קניות שבועיות', 'date' => $nextMonth->copy()->day(3)],
            ['type' => 'expense', 'category' => 'תחבורה', 'amount' => 900, 'description' => 'דלק וחנייה', 'date' => $nextMonth->copy()->day(5)],
            ['type' => 'expense', 'category' => 'חשמל ומים', 'amount' => 520, 'description' => 'חשבון חשמל', 'date' => $nextMonth->copy()->day(10)],
            ['type' => 'expense', 'category' => 'בילויים', 'amount' => 800, 'description' => 'חופשה', 'date' => $nextMonth->copy()->day(12)],
            ['type' => 'expense', 'category' => 'קניות', 'amount' => 400, 'description' => 'קניות בית', 'date' => $nextMonth->copy()->day(18)],
        ];

        foreach ($nextMonthTransactions as $trans) {
            $category = collect($categories)->firstWhere('name', $trans['category']);
            
            Transaction::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'description' => $trans['description'],
                    'transaction_date' => $trans['date'],
                ],
                [
                    'category_id' => $category->id,
                    'amount' => $trans['amount'],
                    'type' => $trans['type'],
                    'status' => 'completed',
                ]
            );
        }
    }
}
