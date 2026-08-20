<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->nullable()->unique();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->string('examination_period');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->decimal('passing_score', 8, 2)->nullable();
            $table->decimal('passing_percentage', 5, 2)->default(75);
            $table->date('examination_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('status')->default('DRAFT');
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'examination_date']);
            $table->index(['subject_id', 'section_id', 'examination_period']);
        });

        Schema::create('examination_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['examination_id', 'version_number']);
        });

        Schema::create('examination_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('randomize_choices')->default(false);
            $table->boolean('allow_back_navigation')->default(true);
            $table->boolean('one_question_per_page')->default(true);
            $table->boolean('show_question_numbers')->default(true);
            $table->boolean('show_timer')->default(true);
            $table->boolean('auto_submit_on_expire')->default(true);
            $table->boolean('allow_resume')->default(true);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('show_score_immediately')->default(false);
            $table->boolean('show_correct_answers')->default(false);
            $table->boolean('show_explanations')->default(false);
            $table->boolean('prevent_duplicate_submissions')->default(true);
            $table->boolean('require_fullscreen')->default(false);
            $table->boolean('detect_inactivity')->default(true);
            $table->unsignedSmallInteger('inactivity_timeout_seconds')->default(300);
            $table->boolean('enable_review_before_submit')->default(true);
            $table->boolean('disable_copy_paste')->default(true);
            $table->boolean('disable_right_click')->default(true);
            $table->boolean('detect_tab_switch')->default(true);
            $table->unsignedSmallInteger('question_pool_size')->nullable();
            $table->timestamps();

            $table->unique('examination_id');
        });

        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('examination_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(0);
            $table->decimal('points_override', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['examination_version_id', 'question_id'], 'exam_version_question_unique');
        });

        Schema::create('examination_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['examination_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_assignments');
        Schema::dropIfExists('examination_questions');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('examination_settings');
        Schema::dropIfExists('examination_versions');
        Schema::dropIfExists('examinations');
    }
};
