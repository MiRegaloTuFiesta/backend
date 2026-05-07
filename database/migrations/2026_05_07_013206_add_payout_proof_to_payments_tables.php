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
        Schema::table('contributions', function (Blueprint $table) {
            $table->string('payout_proof_path')->nullable();
        });
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->string('payout_proof_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn('payout_proof_path');
        });
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->dropColumn('payout_proof_path');
        });
    }
};
