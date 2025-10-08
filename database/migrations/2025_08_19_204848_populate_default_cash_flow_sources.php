<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\CashFlowSource;
use App\Models\Transaction;
use App\Models\Category;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // יצירת מקורות תזרים ברירת מחדל לכל משתמש
        $users = User::all();
        
        foreach ($users as $user) {
            // יצירת מקורות תזרים ברירת מחדל
            $defaultSources = [
                // מקורות הכנסה
                [
                    'name' => 'משכורת',
                    'type' => 'income',
                    'color' => '#10B981',
                    'icon' => '💰',
                    'description' => 'הכנסה ממשכורת',
                ],
                [
                    'name' => 'עבודה עצמאית',
                    'type' => 'income',
                    'color' => '#059669',
                    'icon' => '💼',
                    'description' => 'הכנסה מעבודה עצמאית',
                ],
                [
                    'name' => 'השקעות',
                    'type' => 'income',
                    'color' => '#047857',
                    'icon' => '📈',
                    'description' => 'הכנסה מהשקעות',
                ],
                
                // מקורות הוצאה
                [
                    'name' => 'כרטיס אשראי',
                    'type' => 'expense',
                    'color' => '#EF4444',
                    'icon' => '💳',
                    'description' => 'הוצאות בכרטיס אשראי',
                ],
                [
                    'name' => 'מזומן',
                    'type' => 'expense',
                    'color' => '#DC2626',
                    'icon' => '💵',
                    'description' => 'הוצאות במזומן',
                ],
                [
                    'name' => 'העברה בנקאית',
                    'type' => 'expense',
                    'color' => '#B91C1C',
                    'icon' => '🏦',
                    'description' => 'הוצאות בהעברה בנקאית',
                ],
                [
                    'name' => 'צ\'ק',
                    'type' => 'expense',
                    'color' => '#991B1B',
                    'icon' => '📋',
                    'description' => 'הוצאות בצ\'ק',
                ],
            ];
            
            $createdSources = [];
            foreach ($defaultSources as $sourceData) {
                $source = CashFlowSource::create([
                    'user_id' => $user->id,
                    'name' => $sourceData['name'],
                    'type' => $sourceData['type'],
                    'color' => $sourceData['color'],
                    'icon' => $sourceData['icon'],
                    'description' => $sourceData['description'],
                    'is_active' => true,
                ]);
                
                $createdSources[$sourceData['type']][] = $source;
            }
            
            // עדכון תזרימים קיימים עם מקור תזרים ברירת מחדל
            $transactions = Transaction::where('user_id', $user->id)->get();
            
            foreach ($transactions as $transaction) {
                if ($transaction->cash_flow_source_id) {
                    continue; // כבר יש מקור תזרים
                }
                
                $type = $transaction->type;
                if (isset($createdSources[$type]) && count($createdSources[$type]) > 0) {
                    // בחירת מקור ברירת מחדל לפי סוג
                    if ($type === 'income') {
                        $defaultSource = $createdSources[$type][0]; // משכורת
                    } else {
                        $defaultSource = $createdSources[$type][0]; // כרטיס אשראי
                    }
                    
                    $transaction->cash_flow_source_id = $defaultSource->id;
                    $transaction->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // מחיקת כל מקורות התזרים שנוצרו
        CashFlowSource::truncate();
        
        // איפוס cash_flow_source_id בתזרימים
        Transaction::query()->update(['cash_flow_source_id' => null]);
    }
};