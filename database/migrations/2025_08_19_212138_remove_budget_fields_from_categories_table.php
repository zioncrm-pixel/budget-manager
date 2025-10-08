<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // הסרת אינדקס
            $table->dropIndex(['user_id', 'budget_year', 'budget_month']);
            
            // הסרת עמודות התקציב
            $table->dropColumn([
                'budget_year',
                'budget_month', 
                'planned_amount',
                'spent_amount',
                'remaining_amount'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // הוספת שדות תקציב לקטגוריות
            $table->integer('budget_year')->nullable(); // שנה
            $table->integer('budget_month')->nullable(); // חודש
            $table->decimal('planned_amount', 10, 2)->nullable(); // סכום מתוכנן
            $table->decimal('spent_amount', 10, 2)->default(0); // סכום שהוצא
            $table->decimal('remaining_amount', 10, 2)->nullable(); // סכום שנותר
            
            // אינדקסים לביצועים טובים יותר
            $table->index(['user_id', 'budget_year', 'budget_month']);
        });
    }
};