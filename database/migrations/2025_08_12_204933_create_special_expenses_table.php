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
        Schema::create('special_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // שם ההוצאה המיוחדת (כרטיס אשראי, הלוואה וכו')
            $table->enum('type', ['credit_card', 'loan', 'insurance', 'subscription', 'other']); // סוג הוצאה מיוחדת
            $table->decimal('total_amount', 10, 2); // סכום כולל
            $table->decimal('paid_amount', 10, 2)->default(0); // סכום ששולם עד כה
            $table->decimal('remaining_amount', 10, 2); // סכום שנותר לשלם
            
            // תאריכים חשובים
            $table->date('start_date'); // תאריך התחלה
            $table->date('due_date')->nullable(); // תאריך יעד לתשלום
            $table->date('next_payment_date')->nullable(); // תאריך התשלום הבא
            
            // פרטים נוספים
            $table->string('account_number')->nullable(); // מספר חשבון/כרטיס
            $table->text('description')->nullable(); // תיאור
            $table->enum('status', ['active', 'completed', 'overdue', 'cancelled'])->default('active');
            $table->timestamps();
            
            // אינדקסים לביצועים טובים יותר
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_expenses');
    }
};
