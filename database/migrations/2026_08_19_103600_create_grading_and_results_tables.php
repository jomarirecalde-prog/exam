<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_formulas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('grading_formula_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_formula_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type');
            $table->json('config');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_attempt_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('raw_score', 10, 2)->default(0);
            $table->decimal('total_points', 10, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('letter_grade')->nullable();
            $table->string('status')->default('PENDING_GRADING');
            $table->boolean('passed')->default(false);
            $table->boolean('is_released')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignId('grading_formula_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['examination_id', 'student_id']);
            $table->index(['student_id', 'is_released']);
        });

        Schema::create('grade_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('overridden_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('previous_score', 10, 2);
            $table->decimal('new_score', 10, 2);
            $table->decimal('previous_percentage', 5, 2)->nullable();
            $table->decimal('new_percentage', 5, 2)->nullable();
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_overrides');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('grading_formula_rules');
        Schema::dropIfExists('grading_formulas');
    }
};
