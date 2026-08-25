<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examination_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('warning_count')->default(0)->after('suspicious_activity_count');
            $table->timestamp('policy_accepted_at')->nullable()->after('warning_count');
            $table->string('policy_version', 32)->nullable()->after('policy_accepted_at');
            $table->timestamp('locked_at')->nullable()->after('policy_version');
            $table->string('lock_reason')->nullable()->after('locked_at');
            $table->timestamp('reactivated_at')->nullable()->after('lock_reason');
            $table->foreignId('reactivated_by')->nullable()->after('reactivated_at')->constrained('users')->nullOnDelete();
            $table->text('reactivation_reason')->nullable()->after('reactivated_by');
            $table->unsignedSmallInteger('reactivation_count')->default(0)->after('reactivation_reason');
        });

        Schema::create('exam_policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('policy_version', 32);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['examination_id', 'student_id']);
            $table->index(['examination_attempt_id']);
        });

        Schema::create('exam_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->string('violation_type');
            $table->unsignedSmallInteger('warning_number');
            $table->timestamp('detected_at');
            $table->json('metadata')->nullable();
            $table->string('client_event_id')->nullable();
            $table->timestamps();

            $table->index(['examination_attempt_id', 'detected_at']);
            $table->index(['examination_id', 'student_id']);
            $table->unique(['examination_attempt_id', 'client_event_id'], 'exam_violation_client_event_unique');
        });

        Schema::create('exam_reactivation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reactivated_by')->constrained('users')->cascadeOnDelete();
            $table->text('reactivation_reason');
            $table->string('warning_mode');
            $table->unsignedSmallInteger('previous_warning_count');
            $table->unsignedSmallInteger('new_warning_count');
            $table->timestamp('reactivated_at');
            $table->timestamps();

            $table->index(['examination_attempt_id', 'reactivated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_reactivation_logs');
        Schema::dropIfExists('exam_violations');
        Schema::dropIfExists('exam_policy_acceptances');
        Schema::table('examination_attempts', function (Blueprint $table) {
            $table->dropForeign(['reactivated_by']);
            $table->dropColumn([
                'warning_count',
                'policy_accepted_at',
                'policy_version',
                'locked_at',
                'lock_reason',
                'reactivated_at',
                'reactivated_by',
                'reactivation_reason',
                'reactivation_count',
            ]);
        });
    }
};
