<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('attempt_code')->nullable()->unique();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status')->default('NOT_STARTED');
            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->string('submission_mode')->default('ONLINE');
            $table->string('sync_status')->default('PENDING');
            $table->text('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('tab_switch_count')->default(0);
            $table->unsignedSmallInteger('suspicious_activity_count')->default(0);
            $table->json('question_order')->nullable();
            $table->timestamps();

            $table->unique(['examination_id', 'student_id', 'attempt_number'], 'exam_student_attempt_unique');
            $table->index(['examination_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('attempt_question_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('display_order');
            $table->json('question_snapshot');
            $table->json('choice_order')->nullable();
            $table->decimal('points', 8, 2);
            $table->timestamps();

            $table->unique(['examination_attempt_id', 'question_id'], 'attempt_question_unique');
        });

        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('examination_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_earned', 8, 2)->nullable();
            $table->boolean('requires_manual_grading')->default(false);
            $table->boolean('is_graded')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['examination_attempt_id', 'question_id'], 'attempt_answer_unique');
        });

        Schema::create('essay_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_answer_id')->constrained()->cascadeOnDelete();
            $table->longText('answer_text')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->json('rubric_scores')->nullable();
            $table->timestamps();

            $table->unique('student_answer_id');
        });

        Schema::create('exam_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['examination_attempt_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_activity_logs');
        Schema::dropIfExists('essay_answers');
        Schema::dropIfExists('student_answers');
        Schema::dropIfExists('attempt_question_snapshots');
        Schema::dropIfExists('examination_attempts');
    }
};
