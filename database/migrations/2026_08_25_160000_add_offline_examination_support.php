<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examination_settings', function (Blueprint $table) {
            $table->string('offline_examination_mode')->default('disabled')->after('detect_tab_switch');
            $table->boolean('allow_offline_continuation')->default(false)->after('offline_examination_mode');
            $table->boolean('require_offline_preparation')->default(false)->after('allow_offline_continuation');
            $table->boolean('allow_pending_offline_submission')->default(true)->after('require_offline_preparation');
            $table->unsignedSmallInteger('max_offline_duration_minutes')->nullable()->after('allow_pending_offline_submission');
            $table->unsignedSmallInteger('sync_grace_period_minutes')->default(15)->after('max_offline_duration_minutes');
        });

        Schema::table('examination_attempts', function (Blueprint $table) {
            $table->boolean('offline_enabled')->default(false)->after('reactivation_count');
            $table->timestamp('offline_prepared_at')->nullable()->after('offline_enabled');
            $table->uuid('offline_session_id')->nullable()->after('offline_prepared_at');
            $table->string('authorized_device_id', 128)->nullable()->after('offline_session_id');
            $table->timestamp('last_synced_at')->nullable()->after('authorized_device_id');
            $table->timestamp('pending_submission_at')->nullable()->after('last_synced_at');
            $table->text('offline_timing_token')->nullable()->after('pending_submission_at');

            $table->index(['authorized_device_id', 'status']);
            $table->index('offline_session_id');
        });

        Schema::create('offline_exam_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('device_identifier', 128);
            $table->string('device_name')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'device_identifier']);
        });

        Schema::create('exam_sync_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_event_uuid');
            $table->foreignId('examination_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->json('payload');
            $table->json('result')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('client_event_uuid');
            $table->index(['examination_attempt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sync_events');
        Schema::dropIfExists('offline_exam_devices');

        Schema::table('examination_attempts', function (Blueprint $table) {
            $table->dropIndex(['authorized_device_id', 'status']);
            $table->dropIndex(['offline_session_id']);
            $table->dropColumn([
                'offline_enabled',
                'offline_prepared_at',
                'offline_session_id',
                'authorized_device_id',
                'last_synced_at',
                'pending_submission_at',
                'offline_timing_token',
            ]);
        });

        Schema::table('examination_settings', function (Blueprint $table) {
            $table->dropColumn([
                'offline_examination_mode',
                'allow_offline_continuation',
                'require_offline_preparation',
                'allow_pending_offline_submission',
                'max_offline_duration_minutes',
                'sync_grace_period_minutes',
            ]);
        });
    }
};
