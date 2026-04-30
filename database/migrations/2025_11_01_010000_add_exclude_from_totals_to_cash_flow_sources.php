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
        Schema::table('cash_flow_sources', function (Blueprint $table) {
            $table->boolean('exclude_from_totals')->default(false)->after('month');
            $table->index(['user_id', 'exclude_from_totals']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_flow_sources', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'exclude_from_totals']);
            $table->dropColumn('exclude_from_totals');
        });
    }
};
