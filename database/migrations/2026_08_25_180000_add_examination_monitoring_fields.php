<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examination_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('current_question_index')->nullable()->after('question_order');
            $table->timestamp('last_activity_at')->nullable()->after('current_question_index');
            $table->string('connection_status', 32)->nullable()->after('last_activity_at');
            $table->boolean('reactivation_pending')->default(false)->after('reactivation_count');

            $table->index(['examination_id', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::table('examination_attempts', function (Blueprint $table) {
            $table->dropIndex(['examination_id', 'last_activity_at']);
            $table->dropColumn([
                'current_question_index',
                'last_activity_at',
                'connection_status',
                'reactivation_pending',
            ]);
        });
    }
};
