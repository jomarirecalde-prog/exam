<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('suffix', 20)->nullable()->after('last_name');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('student_id');
            $table->string('sex', 20)->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('sex');
            $table->text('home_address')->nullable()->after('date_of_birth');
            $table->string('registration_status', 20)->default('approved')->after('is_active');
            $table->timestamp('registered_at')->nullable()->after('registration_status');
            $table->timestamp('approved_at')->nullable()->after('registered_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('approved_by');

            $table->index('registration_status');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['registration_status']);
            $table->dropColumn([
                'phone', 'sex', 'date_of_birth', 'home_address',
                'registration_status', 'registered_at', 'approved_at', 'approved_by', 'rejection_reason',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('suffix');
        });
    }
};
