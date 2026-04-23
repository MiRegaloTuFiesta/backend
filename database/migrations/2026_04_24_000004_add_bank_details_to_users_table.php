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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('account_type_id')->nullable()->constrained()->onDelete('set null');
            $table->string('account_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['account_type_id']);
            $table->dropColumn(['bank_id', 'account_type_id', 'account_number']);
        });
    }
};
