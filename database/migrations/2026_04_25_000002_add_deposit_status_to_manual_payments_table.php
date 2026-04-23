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
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->boolean('is_deposited')->default(false);
            $table->datetime('deposited_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->dropColumn(['is_deposited', 'deposited_at']);
        });
    }
};
