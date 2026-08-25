<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_instructor', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('semester_id');
        });

        Schema::table('student_subjects', function (Blueprint $table) {
            $table->foreignId('subject_offering_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('subject_instructor')
                ->nullOnDelete();
        });

        Schema::table('student_subjects', function (Blueprint $table) {
            $table->dropUnique('student_subjects_unique_enrollment');
        });

        Schema::table('student_subjects', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'subject_offering_id', 'academic_year_id', 'semester_id'],
                'student_subjects_offering_unique'
            );
        });

        Schema::table('student_subject_change_requests', function (Blueprint $table) {
            $table->json('add_subject_offering_ids')->nullable()->after('remove_subject_ids');
            $table->json('remove_subject_offering_ids')->nullable()->after('add_subject_offering_ids');
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->foreignId('subject_offering_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('subject_instructor')
                ->nullOnDelete();
        });

        $this->backfillSectionOfferings();
        $this->backfillStudentSubjectOfferings();
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_offering_id');
        });

        Schema::table('student_subject_change_requests', function (Blueprint $table) {
            $table->dropColumn(['add_subject_offering_ids', 'remove_subject_offering_ids']);
        });

        Schema::table('student_subjects', function (Blueprint $table) {
            $table->dropUnique('student_subjects_offering_unique');
            $table->dropConstrainedForeignId('subject_offering_id');
        });

        Schema::table('student_subjects', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'subject_id', 'academic_year_id', 'semester_id'],
                'student_subjects_unique_enrollment'
            );
        });

        Schema::table('subject_instructor', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    protected function backfillSectionOfferings(): void
    {
        $tbaInstructorId = $this->tbaInstructorId();

        foreach (DB::table('subject_section')->get() as $pair) {
            $exists = DB::table('subject_instructor')
                ->where('subject_id', $pair->subject_id)
                ->where('section_id', $pair->section_id)
                ->where('academic_year_id', $pair->academic_year_id)
                ->where('semester_id', $pair->semester_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('subject_instructor')->insert([
                'subject_id' => $pair->subject_id,
                'instructor_id' => $tbaInstructorId,
                'section_id' => $pair->section_id,
                'academic_year_id' => $pair->academic_year_id,
                'semester_id' => $pair->semester_id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function backfillStudentSubjectOfferings(): void
    {
        $tbaInstructorId = $this->tbaInstructorId();

        foreach (DB::table('student_subjects')->whereNull('subject_offering_id')->get() as $row) {
            $student = DB::table('students')->where('id', $row->student_id)->first();

            $offeringQuery = DB::table('subject_instructor')
                ->where('subject_id', $row->subject_id)
                ->where('academic_year_id', $row->academic_year_id)
                ->where('semester_id', $row->semester_id)
                ->whereNotNull('section_id');

            if ($student?->section_id) {
                $offering = (clone $offeringQuery)
                    ->where('section_id', $student->section_id)
                    ->orderBy('id')
                    ->first();
            } else {
                $offering = null;
            }

            $offering ??= $offeringQuery->orderBy('id')->first();

            if (! $offering) {
                $offeringId = DB::table('subject_instructor')->insertGetId([
                    'subject_id' => $row->subject_id,
                    'instructor_id' => $tbaInstructorId,
                    'section_id' => $student?->section_id,
                    'academic_year_id' => $row->academic_year_id,
                    'semester_id' => $row->semester_id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $offeringId = $offering->id;
            }

            DB::table('student_subjects')
                ->where('id', $row->id)
                ->update(['subject_offering_id' => $offeringId]);
        }
    }

    protected function tbaInstructorId(): int
    {
        $existing = DB::table('instructors')->where('employee_id', 'TBA-0000')->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $userId = DB::table('users')->insertGetId([
            'name' => 'To Be Announced',
            'username' => 'tba.instructor',
            'first_name' => 'To Be',
            'last_name' => 'Announced',
            'email' => 'tba.instructor@system.local',
            'password' => bcrypt(str()->random(32)),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('instructors')->insertGetId([
            'user_id' => $userId,
            'employee_id' => 'TBA-0000',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
