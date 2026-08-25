<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\Grade;
use App\Models\Program;
use App\Models\Question;
use App\Models\Student;
use App\Services\Examinations\ExaminationAccessService;
use App\Services\Examinations\ExaminationAttemptService;
use App\Services\Examinations\ExamResultBreakdownService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function examinations(Request $request): View
    {
        $query = Examination::query()->with(['subject', 'sections'])->latest();
        $user = $request->user();

        if ($user->hasRole('student')) {
            $student = $user->student;

            if (! $student) {
                $query->whereRaw('1 = 0');
            } else {
                $query->visibleToStudent($student);
            }
        } elseif ($user->hasRole('instructor')) {
            if ($user->instructor) {
                $query->ownedByInstructor($user->instructor);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $exams = $query->paginate(10);

        $studentAttempts = collect();
        if ($user->hasRole('student') && $user->student && $exams->isNotEmpty()) {
            $studentAttempts = ExaminationAttempt::query()
                ->where('student_id', $user->student->id)
                ->whereIn('examination_id', $exams->pluck('id'))
                ->orderByDesc('id')
                ->get()
                ->unique('examination_id')
                ->keyBy('examination_id');
        }

        return view('pages.examinations.index', compact('exams', 'studentAttempts'));
    }

    public function questions(): View
    {
        $questions = Question::query()->with('subject')->latest()->paginate(10);

        return view('pages.questions.index', compact('questions'));
    }

    public function results(Request $request, ExaminationAccessService $access): View
    {
        $user = $request->user();

        if ($user->hasRole('student')) {
            $query = Grade::query()->with(['student.user', 'examination.subject'])->latest();

            if ($user->student) {
                $query->where('student_id', $user->student->id);
            } else {
                $query->whereRaw('1 = 0');
            }

            $grades = $query->paginate(10);

            return view('pages.results.index', compact('grades'));
        }

        if ($user->hasAnyRole(['superadmin', 'admin', 'instructor'])) {
            $search = trim((string) $request->query('q', ''));

            $query = Examination::query()
                ->with(['subject', 'sections', 'subjectOffering.section'])
                ->whereHas('attempts', function ($attemptQuery) {
                    $attemptQuery->whereIn('status', [
                        AttemptStatus::Submitted,
                        AttemptStatus::AutoSubmitted,
                        AttemptStatus::Synced,
                        AttemptStatus::LockedViolationLimit,
                    ]);
                })
                ->latest();

            if ($user->hasRole('instructor') && $user->instructor) {
                $query->ownedByInstructor($user->instructor);
            } elseif (! $user->hasAnyRole(['superadmin', 'admin'])) {
                $query->whereRaw('1 = 0');
            }

            if ($search !== '') {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhereHas('subject', fn ($subjectQuery) => $subjectQuery
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            }

            $examinations = $query->paginate(10)->through(function (Examination $exam) use ($access) {
                $submittedCount = ExaminationAttempt::query()
                    ->where('examination_id', $exam->id)
                    ->whereIn('status', [
                        AttemptStatus::Submitted,
                        AttemptStatus::AutoSubmitted,
                        AttemptStatus::Synced,
                        AttemptStatus::LockedViolationLimit,
                    ])
                    ->distinct()
                    ->count('student_id');

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject?->name ?? $exam->subject?->code,
                    'subject_code' => $exam->subject?->code,
                    'sections' => $exam->sections->pluck('name')->filter()->join(', ') ?: ($exam->subjectOffering?->section?->name ?? 'Unassigned'),
                    'status' => $exam->status->label(),
                    'submitted_count' => $submittedCount,
                    'eligible_count' => $access->eligibleStudents($exam)->count(),
                    'results_url' => route('results.show', $exam),
                ];
            });

            return view('pages.results.index', [
                'examinations' => $examinations,
                'search' => $search,
            ]);
        }

        abort(403);
    }

    public function resultsShow(
        Examination $examination,
        Request $request,
        ExaminationAccessService $access,
    ): View {
        $user = $request->user();

        if (! $user->hasAnyRole(['superadmin', 'admin', 'instructor'])) {
            abort(403);
        }

        if ($user->hasRole('instructor') && ! $access->canManage($user, $examination)) {
            abort(403, 'You are not authorized to view results for this examination.');
        }

        $examination->loadMissing(['subject', 'sections', 'subjectOffering.section']);

        $search = trim((string) $request->query('q', ''));

        $attemptsQuery = ExaminationAttempt::query()
            ->with(['student.user', 'grade'])
            ->where('examination_id', $examination->id)
            ->whereIn('status', [
                AttemptStatus::Submitted,
                AttemptStatus::AutoSubmitted,
                AttemptStatus::Synced,
                AttemptStatus::LockedViolationLimit,
            ])
            ->orderByDesc('submitted_at');

        if ($search !== '') {
            $attemptsQuery->whereHas('student', function ($studentQuery) use ($search) {
                $studentQuery->where('student_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $attempts = $attemptsQuery->get()->unique('student_id')->values();

        $summary = [
            'submitted' => $attempts->count(),
            'passed' => $attempts->filter(fn (ExaminationAttempt $attempt) => (bool) $attempt->grade?->passed)->count(),
            'failed' => $attempts->filter(fn (ExaminationAttempt $attempt) => $attempt->grade && ! $attempt->grade->passed)->count(),
            'pending' => $attempts->filter(fn (ExaminationAttempt $attempt) => $attempt->grade?->status === \App\Enums\ResultStatus::PendingGrading)->count(),
            'average' => round((float) $attempts->avg(fn (ExaminationAttempt $attempt) => $attempt->grade?->percentage), 1),
        ];

        $completedExaminations = Examination::query()
            ->with('subject')
            ->whereHas('attempts', function ($attemptQuery) {
                $attemptQuery->whereIn('status', [
                    AttemptStatus::Submitted,
                    AttemptStatus::AutoSubmitted,
                    AttemptStatus::Synced,
                    AttemptStatus::LockedViolationLimit,
                ]);
            })
            ->when($user->hasRole('instructor') && $user->instructor, fn ($query) => $query->ownedByInstructor($user->instructor))
            ->latest()
            ->limit(100)
            ->get(['id', 'title', 'subject_id']);

        return view('pages.results.show', compact(
            'examination',
            'attempts',
            'summary',
            'search',
            'completedExaminations',
        ));
    }

    public function reports(): View
    {
        return view('pages.reports.index', [
            'counts' => [
                'exams' => Examination::count(),
                'students' => Student::count(),
                'released' => Grade::where('is_released', true)->count(),
            ],
        ]);
    }

    public function monitoring(ExaminationAccessService $access): View
    {
        $user = auth()->user();

        $query = Examination::query()
            ->with(['subject', 'sections', 'subjectOffering.section'])
            ->whereNotIn('status', [ExamStatus::Draft, ExamStatus::Archived]);

        if ($user->hasRole('instructor') && $user->instructor) {
            $query->ownedByInstructor($user->instructor);
        } elseif (! $user->hasAnyRole(['superadmin', 'admin'])) {
            $query->whereRaw('1 = 0');
        }

        $examinations = $query->latest()->get()->map(function (Examination $exam) use ($access) {
            $studentsTotal = $access->eligibleStudents($exam)->count();
            $studentsTaking = ExaminationAttempt::query()
                ->where('examination_id', $exam->id)
                ->whereIn('status', [
                    AttemptStatus::InProgress,
                    AttemptStatus::SyncPending,
                ])
                ->count();
            $studentsLocked = ExaminationAttempt::query()
                ->where('examination_id', $exam->id)
                ->where('status', AttemptStatus::LockedViolationLimit)
                ->count();

            $isLive = in_array($exam->status, [ExamStatus::Active, ExamStatus::Published, ExamStatus::Scheduled], true);

            return [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject' => $exam->subject?->name ?? $exam->subject?->code,
                'subject_code' => $exam->subject?->code,
                'sections' => $exam->sections->pluck('name')->filter()->join(', ') ?: ($exam->subjectOffering?->section?->name ?? 'Unassigned'),
                'status' => $exam->status->label(),
                'is_live' => $isLive,
                'students_total' => $studentsTotal,
                'students_taking' => $studentsTaking,
                'students_locked' => $studentsLocked,
                'monitor_url' => route('monitoring.show', $exam),
            ];
        });

        $live = $examinations->filter(fn (array $exam) => $exam['is_live'])->values();
        $ended = $examinations->reject(fn (array $exam) => $exam['is_live'])->values();

        return view('pages.monitoring.index', compact('live', 'ended'));
    }

    public function monitoringShow(Examination $examination, ExaminationAccessService $access): View
    {
        $user = auth()->user();

        if (! $access->canMonitor($user, $examination)) {
            abort(403, 'You are not authorized to monitor this examination.');
        }

        $examination->loadMissing(['subject', 'sections', 'subjectOffering.section']);

        return view('pages.monitoring.show', compact('examination'));
    }

    public function sync(): View
    {
        $queue = Schema::hasTable('sync_queue')
            ? DB::table('sync_queue')->latest()->limit(20)->get()
            : collect();

        return view('pages.sync.index', [
            'queue' => $queue,
            'online' => config('examination.app_mode') === 'online',
        ]);
    }

    public function audit(): View
    {
        $logs = Schema::hasTable('audit_logs')
            ? DB::table('audit_logs')->latest()->paginate(15)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);

        return view('pages.audit.index', compact('logs'));
    }

    public function settings(): View
    {
        return view('pages.settings.index', [
            'institution' => config('examination.institution'),
            'appMode' => config('examination.app_mode'),
            'timezone' => config('examination.timezone'),
        ]);
    }

    public function offlineSync(): View
    {
        return view('pages.offline.sync-status');
    }

    public function take(
        Examination $examination,
        ExaminationAccessService $access,
        ExaminationAttemptService $attempts,
    ): View {
        $examination->load(['subject', 'sections', 'settings']);

        $user = auth()->user();

        if (! $user || ! $access->canTake($user, $examination)) {
            abort(403, $user ? $access->denyTakeReason($user, $examination) : 'You are not authorized to access this examination.');
        }

        $attached = $examination->examQuestions()->with('question.choices')->get();

        $questions = $attached->isNotEmpty()
            ? $attached->map(fn ($item) => $this->presentQuestion($item->question, $item->points_override))->filter()->values()
            : Question::query()->with('choices')->limit(10)->get()->map(fn ($question) => $this->presentQuestion($question))->values();

        if ($questions->isEmpty()) {
            $questions = collect($this->sampleQuestions());
        }

        $attemptState = null;
        if ($user->student) {
            $attempt = $attempts->findResumableAttempt($user->student, $examination);
            if ($attempt) {
                $attemptState = $attempts->attemptState($attempt);
            }
        }

        $settings = $examination->settings;
        $durationMinutes = max(1, (int) ($examination->duration_minutes ?: config('examination.default_duration_minutes', 60)));

        return view('pages.examinations.take', [
            'examination' => $examination,
            'questions' => $questions,
            'remaining' => $attemptState['remaining_seconds'] ?? ($durationMinutes * 60),
            'attemptState' => $attemptState,
            'schedule' => app(\App\Services\Examinations\ExaminationScheduleService::class)->schedulePayload($examination),
            'policyVersion' => config('examination.policy_version', '1.0'),
            'maxWarnings' => config('examination.max_violation_warnings', 3),
            'monitoring' => [
                'requireFullscreen' => (bool) ($settings?->require_fullscreen ?? true),
                'detectTabSwitch' => (bool) ($settings?->detect_tab_switch ?? true),
                'disableCopyPaste' => (bool) ($settings?->disable_copy_paste ?? true),
                'disableRightClick' => (bool) ($settings?->disable_right_click ?? true),
            ],
            'offline' => [
                'supported' => (bool) ($settings?->supportsOffline() ?? false),
                'mode' => $settings?->offline_examination_mode?->value ?? 'disabled',
                'mode_label' => $settings?->offline_examination_mode?->label() ?? 'Disabled',
                'require_preparation' => (bool) ($settings?->require_offline_preparation ?? false),
                'allow_pending_submission' => (bool) ($settings?->allow_pending_offline_submission ?? true),
            ],
        ]);
    }

    public function result(
        Examination $examination,
        Request $request,
        ExaminationAccessService $access,
        ExamResultBreakdownService $breakdown,
    ): View {
        $user = auth()->user();

        if (! $user || ! $access->canViewResult($user, $examination)) {
            abort(403, 'You are not authorized to access this examination.');
        }

        $examination->loadMissing('settings');

        $viewingStudent = null;

        if ($user->hasRole('student')) {
            $viewingStudent = $user->student;
        } elseif ($request->filled('student')) {
            if (! $access->canManage($user, $examination)) {
                abort(403, 'You are not authorized to access this examination.');
            }

            $viewingStudent = Student::query()->find((int) $request->query('student'));
        }

        $grade = $viewingStudent
            ? Grade::query()
                ->where('examination_id', $examination->id)
                ->where('student_id', $viewingStudent->id)
                ->first()
            : null;

        return view('pages.examinations.result', [
            'examination' => $examination,
            'grade' => $grade,
            'breakdown' => $grade ? $breakdown->build($examination, $grade) : null,
            'viewingStudent' => $viewingStudent,
            'viewingAsStaff' => ! $user->hasRole('student'),
        ]);
    }

    protected function listing(
        string $title,
        string $subtitle,
        string $emptyTitle,
        string $emptyBody,
        string $emptyIcon,
        array $columns,
        $rows,
    ): array {
        return compact('title', 'subtitle', 'emptyTitle', 'emptyBody', 'emptyIcon', 'columns', 'rows');
    }

    protected function presentQuestion(?Question $question, $points = null): ?array
    {
        if (! $question) {
            return null;
        }

        return $question->toExamPayload($points !== null ? (float) $points : null);
    }

    protected function sampleQuestions(): array
    {
        return [
            [
                'id' => 1,
                'type' => QuestionType::MultipleChoice->value,
                'text' => 'Which of the following best describes an information system?',
                'choices' => [
                    ['id' => 'A', 'text' => 'A collection of hardware only'],
                    ['id' => 'B', 'text' => 'People, processes, and technology working together'],
                    ['id' => 'C', 'text' => 'A programming language'],
                    ['id' => 'D', 'text' => 'A network protocol'],
                ],
                'points' => 1,
            ],
            [
                'id' => 2,
                'type' => QuestionType::TrueFalse->value,
                'text' => 'An information system includes people, processes, and technology.',
                'choices' => [
                    ['id' => 'true', 'text' => 'True'],
                    ['id' => 'false', 'text' => 'False'],
                ],
                'points' => 1,
            ],
        ];
    }
}
