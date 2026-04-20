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
            $table->integer('platform_fee')->default(0)->after('amount');
            $table->integer('gateway_fee')->default(0)->after('platform_fee');
            $table->integer('net_to_user')->default(0)->after('gateway_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn(['platform_fee', 'gateway_fee', 'net_to_user']);
        });
    }
};
