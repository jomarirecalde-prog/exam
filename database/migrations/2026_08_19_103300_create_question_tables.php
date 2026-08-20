<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('learning_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('question_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('learning_objective_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('question_text');
            $table->string('image_path')->nullable();
            $table->string('attachment_path')->nullable();
            $table->json('correct_answer')->nullable();
            $table->decimal('points', 8, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->string('difficulty')->default('medium');
            $table->json('metadata')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_id', 'type', 'difficulty']);
        });

        Schema::create('question_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->text('choice_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('question_bank_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['question_bank_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_questions');
        Schema::dropIfExists('question_choices');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_banks');
        Schema::dropIfExists('learning_objectives');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('question_categories');
    }
};
