<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('event_type', 40)->default('event');
            $table->boolean('all_day')->default(false);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('color', 20)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'starts_at']);
            $table->index(['employee_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_calendar_events');
    }
};
