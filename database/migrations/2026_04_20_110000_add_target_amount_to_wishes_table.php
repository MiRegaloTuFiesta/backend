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
        Schema::table('wishes', function (Blueprint $table) {
            // Restore target_amount only if it doesn't exist
            if (!Schema::hasColumn('wishes', 'target_amount')) {
                $table->integer('target_amount')->default(0)->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->dropColumn('target_amount');
        });
    }
};
