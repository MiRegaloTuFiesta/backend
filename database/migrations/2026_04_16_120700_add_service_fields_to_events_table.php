<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->integer('creator_budget')->nullable();
            $table->boolean('requests_internal_service')->default(false);
            $table->integer('service_cost')->nullable();
            $table->boolean('service_adds_to_total')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['creator_budget', 'requests_internal_service', 'service_cost', 'service_adds_to_total']);
        });
    }
};
