<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('reporter_email');
            $table->text('reason');
            $table->enum('status', ['pending', 'reviewed'])->default('pending');
            $table->timestamps();

            // Prevent duplicate reports from same email for same event
            $table->unique(['event_id', 'reporter_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reports');
    }
};
