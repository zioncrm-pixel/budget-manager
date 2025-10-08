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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->integer('year'); // שנה
            $table->integer('month'); // חודש
            $table->decimal('planned_amount', 10, 2); // סכום מתוכנן
            $table->decimal('spent_amount', 10, 2)->default(0); // סכום שהוצא
            $table->decimal('remaining_amount', 10, 2); // סכום שנותר
            $table->timestamps();
            
            // אינדקסים לביצועים טובים יותר
            $table->index(['user_id', 'year', 'month']);
            $table->index(['user_id', 'category_id']);
            
            // וידוא שכל משתמש יכול להיות לו רק תקציב אחד לכל קטגוריה בכל חודש
            $table->unique(['user_id', 'category_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
