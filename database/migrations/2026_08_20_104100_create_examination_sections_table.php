<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['examination_id', 'section_id'], 'examination_section_unique');
            $table->index('section_id');
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->boolean('needs_section_review')->default(false)->after('status');
        });

        $this->makeNullableForeignKeys();
        $this->backfillExaminationSections();
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->dropColumn('needs_section_review');
        });

        Schema::dropIfExists('examination_sections');
    }

    protected function makeNullableForeignKeys(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropForeign(['instructor_id']);
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable()->change();
            $table->unsignedBigInteger('instructor_id')->nullable()->change();
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->foreign('section_id')->references('id')->on('sections')->nullOnDelete();
            $table->foreign('instructor_id')->references('id')->on('instructors')->nullOnDelete();
        });
    }

    protected function backfillExaminationSections(): void
    {
        $now = now();

        foreach (DB::table('examinations')->orderBy('id')->get(['id', 'section_id']) as $examination) {
            if ($examination->section_id) {
                $exists = DB::table('examination_sections')
                    ->where('examination_id', $examination->id)
                    ->where('section_id', $examination->section_id)
                    ->exists();

                if (! $exists) {
                    DB::table('examination_sections')->insert([
                        'examination_id' => $examination->id,
                        'section_id' => $examination->section_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                continue;
            }

            DB::table('examinations')
                ->where('id', $examination->id)
                ->update(['needs_section_review' => true]);
        }
    }
};
