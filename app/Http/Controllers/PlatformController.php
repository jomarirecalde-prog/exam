<?php

namespace App\Http\Controllers;

use App\Enums\ExamStatus;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\Program;
use App\Models\Question;
use App\Models\Student;
use App\Services\Examinations\ExaminationAccessService;
use App\Services\Examinations\ExaminationAttemptService;
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

        return view('pages.examinations.index', compact('exams'));
    }

    public function questions(): View
    {
        $questions = Question::query()->with('subject')->latest()->paginate(10);

        return view('pages.questions.index', compact('questions'));
    }

    public function results(Request $request): View
    {
        $query = Grade::query()->with(['student.user', 'examination.subject'])->latest();

        if ($request->user()->hasRole('student') && $request->user()->student) {
            $query->where('student_id', $request->user()->student->id);
        }

        $grades = $query->paginate(10);

        return view('pages.results.index', compact('grades'));
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
            ->with(['subject', 'sections'])
            ->whereIn('status', [ExamStatus::Active, ExamStatus::Published]);

        if ($user->hasRole('instructor') && $user->instructor) {
            $query->ownedByInstructor($user->instructor);
        } elseif (! $user->hasAnyRole(['superadmin', 'admin'])) {
            $query->whereRaw('1 = 0');
        }

        $active = $query->latest()->get();

        return view('pages.monitoring.index', compact('active'));
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

    public function result(Examination $examination, ExaminationAccessService $access): View
    {
        $user = auth()->user();

        if (! $user || ! $access->canViewResult($user, $examination)) {
            abort(403, 'You are not authorized to access this examination.');
        }

        $student = $user->student;
        $grade = $student
            ? Grade::query()->where('examination_id', $examination->id)->where('student_id', $student->id)->first()
            : Grade::query()->where('examination_id', $examination->id)->latest()->first();

        return view('pages.examinations.result', [
            'examination' => $examination,
            'grade' => $grade,
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

        $choices = $question->choices->map(fn ($choice) => [
            'id' => strtoupper((string) $choice->label),
            'text' => $choice->choice_text,
        ])->all();

        if ($choices === []) {
            $choices = [
                ['id' => 'A', 'text' => 'Option A'],
                ['id' => 'B', 'text' => 'Option B'],
                ['id' => 'C', 'text' => 'Option C'],
                ['id' => 'D', 'text' => 'Option D'],
            ];
        }

        return [
            'id' => $question->id,
            'text' => $question->question_text,
            'choices' => $choices,
            'points' => $points ?? $question->points,
        ];
    }

    protected function sampleQuestions(): array
    {
        return [
            [
                'id' => 1,
                'text' => 'Which of the following best describes an information system?',
                'choices' => [
                    ['id' => 'A', 'text' => 'A collection of hardware only'],
                    ['id' => 'B', 'text' => 'People, processes, and technology working together'],
                    ['id' => 'C', 'text' => 'A programming language'],
                    ['id' => 'D', 'text' => 'A network protocol'],
                ],
            ],
            [
                'id' => 2,
                'text' => 'An information system includes people, processes, and technology.',
                'choices' => [
                    ['id' => 'A', 'text' => 'True'],
                    ['id' => 'B', 'text' => 'False'],
                ],
            ],
        ];
    }
}
