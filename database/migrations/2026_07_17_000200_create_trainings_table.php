<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 40)->default('other');
            $table->string('provider')->nullable();
            $table->string('mode', 20)->default('online');
            $table->unsignedSmallInteger('duration_hours')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 20)->default('assigned');
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->string('certificate_url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->constrained('employees')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['due_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
