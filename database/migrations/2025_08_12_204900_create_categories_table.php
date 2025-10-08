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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // שם הקטגוריה
            $table->enum('type', ['income', 'expense']); // סוג: הכנסה או הוצאה
            $table->string('color', 7)->default('#3B82F6'); // צבע לקטגוריה (hex)
            $table->string('icon')->nullable(); // אייקון (FontAwesome או אחר)
            $table->text('description')->nullable(); // תיאור
            $table->boolean('is_active')->default(true); // האם הקטגוריה פעילה
            $table->timestamps();
            
            // אינדקסים לביצועים טובים יותר
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
