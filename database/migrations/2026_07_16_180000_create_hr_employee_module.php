<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('careers', function (Blueprint $table) {
            $table->string('experience_required')->nullable()->after('employment_type');
            $table->string('salary_display')->nullable()->after('experience_required');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('gender', 32)->nullable()->after('date_of_birth');
            $table->text('current_address')->nullable()->after('gender');
            $table->text('permanent_address')->nullable()->after('current_address');
            $table->string('qualification')->nullable()->after('permanent_address');
            $table->text('skills')->nullable()->after('qualification');
            $table->string('total_experience')->nullable()->after('skills');
            $table->string('current_company')->nullable()->after('total_experience');
            $table->decimal('current_salary', 12, 2)->nullable()->after('current_company');
            $table->decimal('expected_salary', 12, 2)->nullable()->after('current_salary');
            $table->string('notice_period')->nullable()->after('expected_salary');
            $table->string('preferred_location')->nullable()->after('notice_period');
            $table->text('hr_remarks')->nullable()->after('message');
            $table->timestamp('interview_scheduled_at')->nullable()->after('hr_remarks');
        });

        DB::table('job_applications')->where('status', 'new')->update(['status' => 'applied']);
        DB::table('job_applications')->where('status', 'reviewed')->update(['status' => 'applied']);
        DB::table('job_applications')->where('status', 'archived')->update(['status' => 'rejected']);

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('document_type', 64);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->index(['job_application_id', 'document_type']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->nullable()->constrained('job_applications')->nullOnDelete();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->string('reporting_manager')->nullable();
            $table->json('salary_details')->nullable();
            $table->string('pf_uan')->nullable();
            $table->string('esic_number')->nullable();
            $table->json('bank_details')->nullable();
            $table->json('emergency_contact')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('status');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('career_id')->constrained('employees')->nullOnDelete();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('category', 64);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'category']);
        });

        Schema::create('employee_exit_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('status')->default('initiated');
            $table->date('resignation_date')->nullable();
            $table->date('last_working_day')->nullable();
            $table->text('reason')->nullable();
            $table->text('hr_remarks')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('employee_exit_records');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('application_documents');

        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth', 'gender', 'current_address', 'permanent_address',
                'qualification', 'skills', 'total_experience', 'current_company',
                'current_salary', 'expected_salary', 'notice_period', 'preferred_location',
                'hr_remarks', 'interview_scheduled_at', 'employee_id',
            ]);
        });

        Schema::table('careers', function (Blueprint $table) {
            $table->dropColumn(['experience_required', 'salary_display']);
        });
    }
};
