<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linked_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_account_id');
            $table->string('provider_email')->nullable();
            $table->string('provider_name')->nullable();
            $table->string('provider_avatar')->nullable();
            $table->timestamp('connected_at')->useCurrent();
            $table->timestamps();

            $table->unique(['provider', 'provider_account_id']);
            $table->index(['user_id', 'provider']);
        });

        Schema::create('google_classroom_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('google_account_id');
            $table->string('google_email')->nullable();
            $table->text('scopes')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('connected_at')->useCurrent();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('google_account_id');
        });

        Schema::create('google_classroom_course_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('google_course_id');
            $table->string('course_name');
            $table->string('course_section')->nullable();
            $table->string('instructor_name')->nullable();
            $table->foreignId('subject_offering_id')->nullable()->constrained('subject_instructor')->nullOnDelete();
            $table->string('match_confidence', 20)->nullable();
            $table->boolean('confirmed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'google_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_classroom_course_links');
        Schema::dropIfExists('google_classroom_connections');
        Schema::dropIfExists('linked_accounts');
    }
};
