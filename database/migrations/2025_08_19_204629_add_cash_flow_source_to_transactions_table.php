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
        Schema::table('transactions', function (Blueprint $table) {
            // הוספת עמודה למקור תזרים (nullable בתחילה)
            $table->foreignId('cash_flow_source_id')->after('category_id')->nullable()->constrained()->onDelete('cascade');
            
            // הוספת אינדקס למקור תזרים
            $table->index(['user_id', 'cash_flow_source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // הסרת אינדקס
            $table->dropIndex(['user_id', 'cash_flow_source_id']);
            
            // הסרת עמודה
            $table->dropForeign(['cash_flow_source_id']);
            $table->dropColumn('cash_flow_source_id');
        });
    }
};
