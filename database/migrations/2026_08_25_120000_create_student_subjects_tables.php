<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending_verification');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'subject_id', 'academic_year_id', 'semester_id'],
                'student_subjects_unique_enrollment'
            );
            $table->index(['student_id', 'academic_year_id', 'semester_id'], 'student_subjects_term_idx');
            $table->index(['subject_id', 'academic_year_id', 'semester_id'], 'student_subjects_subject_term_idx');
            $table->index('status');
        });

        Schema::create('student_subject_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->json('add_subject_ids')->nullable();
            $table->json('remove_subject_ids')->nullable();
            $table->text('reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_change_requests');
        Schema::dropIfExists('student_subjects');
    }
};
