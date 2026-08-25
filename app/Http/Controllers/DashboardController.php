<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\Grade;
use App\Models\Instructor;
use App\Models\Student;
use App\Services\Instructors\InstructorTeachingService;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $payload = array_merge($this->stats($request), [
            'greeting' => Navigation::greeting($user),
        ]);

        if ($user->hasRole('superadmin')) {
            return view('dashboards.superadmin', $payload);
        }

        if ($user->hasRole('admin')) {
            return view('dashboards.admin', $payload);
        }

        if ($user->hasRole('instructor')) {
            return view('dashboards.instructor', array_merge($payload, $this->instructorPayload($user)));
        }

        if ($user->hasRole('student')) {
            return view('dashboards.student', $payload);
        }

        return view('dashboard', $payload);
    }

    protected function stats(Request $request): array
    {
        $user = $request->user();
        $student = $user->student;

        $upcoming = Examination::query()
            ->with(['subject', 'sections'])
            ->when($user->hasRole('student'), function ($query) use ($student) {
                if (! $student) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->visibleToStudent($student);
            })
            ->when(! $user->hasRole('student'), function ($query) {
                $query->whereNotIn('status', [ExamStatus::Closed, ExamStatus::Archived]);
            })
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        $completed = $student
            ? ExaminationAttempt::query()
                ->with('examination.subject')
                ->where('student_id', $student->id)
                ->whereIn('status', [AttemptStatus::Submitted, AttemptStatus::AutoSubmitted])
                ->latest('submitted_at')
                ->limit(5)
                ->get()
            : collect();

        $released = $student
            ? Grade::query()
                ->with('examination.subject')
                ->where('student_id', $student->id)
                ->where('is_released', true)
                ->latest()
                ->limit(5)
                ->get()
            : collect();

        $activity = Schema::hasTable('audit_logs')
            ? DB::table('audit_logs')->latest()->limit(6)->get()
            : collect();

        return [
            'institution' => config('examination.institution.name'),
            'appMode' => config('examination.app_mode'),
            'counts' => [
                'students' => Student::count(),
                'instructors' => Instructor::count(),
                'activeExams' => Examination::where('status', ExamStatus::Active)->count(),
                'results' => Grade::where('is_released', true)->count(),
            ],
            'upcomingExams' => $user->hasRole('student')
                ? $upcoming
                : Examination::query()->with(['subject', 'sections'])->orderByDesc('updated_at')->limit(6)->get(),
            'completedAttempts' => $completed,
            'releasedGrades' => $released,
            'activity' => $activity,
            'chart' => Examination::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }

    protected function instructorPayload($user): array
    {
        $instructor = $user->instructor;
        $teachingAssignments = $instructor
            ? app(InstructorTeachingService::class)->assignments($instructor)->take(5)
            : collect();

        $upcomingExams = $instructor
            ? Examination::query()
                ->with(['subject', 'section'])
                ->where('instructor_id', $instructor->id)
                ->whereNotIn('status', [ExamStatus::Closed, ExamStatus::Archived])
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get()
            : collect();

        return [
            'teachingAssignments' => $teachingAssignments,
            'upcomingExams' => $upcomingExams,
            'counts' => [
                'assignedSubjects' => $teachingAssignments->count(),
                'assignedSections' => $teachingAssignments->sum('section_count'),
                'assignedStudents' => $teachingAssignments->sum('student_count'),
                'activeExams' => $instructor
                    ? Examination::query()
                        ->where('instructor_id', $instructor->id)
                        ->where('status', ExamStatus::Active)
                        ->count()
                    : 0,
            ],
        ];
    }
}
