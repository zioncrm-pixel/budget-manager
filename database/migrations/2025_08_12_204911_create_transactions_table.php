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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('special_expense_id')->nullable()->constrained()->onDelete('set null');
            
            $table->decimal('amount', 10, 2); // סכום העסקה
            $table->enum('type', ['income', 'expense']); // סוג: הכנסה או הוצאה
            $table->date('transaction_date'); // תאריך העסקה
            $table->string('description'); // תיאור העסקה
            $table->text('notes')->nullable(); // הערות נוספות
            
            // שדות נוספים שימושיים
            $table->string('reference_number')->nullable(); // מספר הפניה/צ'ק
            $table->enum('status', ['completed', 'pending', 'cancelled'])->default('completed');
            $table->timestamps();
            
            // אינדקסים לביצועים טובים יותר
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'category_id']);
            $table->index(['special_expense_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
