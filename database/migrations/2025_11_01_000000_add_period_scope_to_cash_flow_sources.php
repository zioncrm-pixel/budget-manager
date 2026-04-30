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
            $table->unsignedSmallInteger('year')->nullable()->after('allows_refunds');
            $table->unsignedTinyInteger('month')->nullable()->after('year');

            $table->index(['user_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_flow_sources', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'year', 'month']);
            $table->dropColumn(['year', 'month']);
        });
    }
};
