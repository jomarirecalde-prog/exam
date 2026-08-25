<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->timestamp('available_from')->nullable()->after('end_time');
            $table->timestamp('deadline_at')->nullable()->after('available_from');
            $table->string('deadline_policy', 40)->default('allow_active_finish')->after('deadline_at');
            $table->timestamp('ended_at')->nullable()->after('deadline_policy');
            $table->foreignId('ended_by_user_id')->nullable()->after('ended_at')->constrained('users')->nullOnDelete();
            $table->text('end_reason')->nullable()->after('ended_by_user_id');
            $table->string('end_policy', 40)->nullable()->after('end_reason');

            $table->index(['status', 'available_from']);
            $table->index(['status', 'deadline_at']);
        });

        Schema::table('examination_attempts', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable()->after('submitted_at');
            $table->string('ended_reason', 255)->nullable()->after('ended_at');
            $table->string('finalization_status', 40)->nullable()->after('ended_reason');
        });
    }

    public function down(): void
    {
        Schema::table('examination_attempts', function (Blueprint $table) {
            $table->dropColumn(['ended_at', 'ended_reason', 'finalization_status']);
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->dropForeign(['ended_by_user_id']);
            $table->dropIndex(['status', 'available_from']);
            $table->dropIndex(['status', 'deadline_at']);
            $table->dropColumn([
                'available_from',
                'deadline_at',
                'deadline_policy',
                'ended_at',
                'ended_by_user_id',
                'end_reason',
                'end_policy',
            ]);
        });
    }
};
