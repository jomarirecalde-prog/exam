<?php

namespace App\Http\Controllers;

use App\Enums\ExamStatus;
use App\Http\Requests\ImportQuestionCsvRequest;
use App\Http\Requests\StoreExaminationRequest;
use App\Http\Requests\UpdateExaminationRequest;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\YearLevel;
use App\Services\Examinations\ExaminationSectionService;
use App\Services\Questions\QuestionCsvImporter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExaminationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ExaminationSectionService $sections,
    ) {
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Examination::class);

        return view('pages.examinations.create', $this->formPayload($request));
    }

    public function store(StoreExaminationRequest $request): JsonResponse|RedirectResponse
    {
        $examination = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $status = $this->resolveStatus($data);

            $examination = Examination::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'subject_id' => $data['subject_id'],
                'instructor_id' => $request->user()->instructor?->id,
                'academic_year_id' => $data['academic_year_id'],
                'semester_id' => $data['semester_id'],
                'examination_period' => $data['examination_period'],
                'duration_minutes' => $data['duration_minutes'],
                'passing_percentage' => $data['passing_percentage'],
                'examination_date' => $data['examination_date'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'status' => $status,
                'section_id' => $data['section_ids'][0],
                'needs_section_review' => false,
            ]);

            $this->sections->sync($examination, $data['section_ids'], false);
            $this->syncSettings($examination, $data);
            $this->syncSchedule($examination, $data, $status);

            return $examination->load('sections');
        });

        return $this->respondSaved($request, $examination, $this->savedMessage($examination));
    }

    public function edit(Request $request, Examination $examination): View
    {
        $this->authorize('update', $examination);

        $examination->load(['sections', 'settings', 'schedule', 'subject']);

        return view('pages.examinations.create', $this->formPayload($request, $examination));
    }

    public function update(UpdateExaminationRequest $request, Examination $examination): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $examination) {
            $data = $request->validated();
            $status = $this->resolveStatus($data, $examination);

            $examination->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'subject_id' => $data['subject_id'],
                'academic_year_id' => $data['academic_year_id'],
                'semester_id' => $data['semester_id'],
                'examination_period' => $data['examination_period'],
                'duration_minutes' => $data['duration_minutes'],
                'passing_percentage' => $data['passing_percentage'],
                'examination_date' => $data['examination_date'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'status' => $status,
            ]);

            $this->sections->sync($examination, $data['section_ids']);
            $this->syncSettings($examination, $data);
            $this->syncSchedule($examination, $data, $status);
        });

        $examination->refresh()->load('sections');

        return $this->respondSaved($request, $examination, $this->savedMessage($examination));
    }

    public function availableSections(Request $request): JsonResponse
    {
        $this->authorize('create', Examination::class);

        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'year_level_id' => ['required', 'exists:year_levels,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $sections = $this->sections->filterSections(
            $request->user(),
            (int) $data['academic_year_id'],
            (int) $data['semester_id'],
            (int) $data['subject_id'],
            (int) $data['program_id'],
            (int) $data['year_level_id'],
            $data['q'] ?? null,
        );

        return response()->json([
            'sections' => $this->sections->present($sections),
        ]);
    }

    public function importQuestions(ImportQuestionCsvRequest $request, QuestionCsvImporter $importer): JsonResponse
    {
        $result = $importer->import($request->file('file')->getRealPath());

        if ($result['imported'] === 0) {
            return response()->json([
                'message' => 'No questions were imported.',
                'errors' => $result['errors'],
                'warnings' => $result['warnings'],
            ], 422);
        }

        return response()->json([
            'message' => $result['imported'].' question(s) imported.',
            'questions' => $result['questions'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
            'imported' => $result['imported'],
        ]);
    }

    public function questionCsvTemplate(QuestionCsvImporter $importer): StreamedResponse
    {
        $this->authorize('create', Examination::class);

        return response()->streamDownload(
            fn () => print ($importer->template()),
            'question-import-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    protected function formPayload(Request $request, ?Examination $examination = null): array
    {
        $primary = $examination?->sections->first() ?? $examination?->section;

        $wizard = [
            'examinationId' => $examination?->id,
            'storeUrl' => route('examinations.store'),
            'updateUrl' => $examination ? route('examinations.update', $examination) : null,
            'sectionsUrl' => route('examinations.sections'),
            'importQuestionsUrl' => route('examinations.import-questions'),
            'questionCsvTemplateUrl' => route('examinations.question-csv-template'),
            'indexUrl' => route('examinations.index'),
            'academicYears' => AcademicYear::query()->where('is_active', true)->orderByDesc('is_current')->orderByDesc('name')->get(['id', 'name', 'is_current']),
            'semesters' => Semester::query()->where('is_active', true)->orderBy('order')->get(['id', 'academic_year_id', 'name', 'is_current']),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'programs' => Program::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'yearLevels' => YearLevel::query()->where('is_active', true)->orderBy('level')->get(['id', 'program_id', 'name', 'level']),
            'selectedSections' => $examination?->sections->map(fn ($section) => [
                'id' => $section->id,
                'name' => $section->displayName(),
                'code' => $section->code,
                'label' => $section->displayName(),
            ])->values()->all() ?? [],
            'form' => [
                'title' => old('title', $examination?->title ?? ''),
                'academicYearId' => old('academic_year_id', $examination?->academic_year_id ?? AcademicYear::query()->where('is_current', true)->value('id')),
                'semesterId' => old('semester_id', $examination?->semester_id ?? Semester::query()->where('is_current', true)->value('id')),
                'subjectId' => old('subject_id', $examination?->subject_id),
                'programId' => old('program_id', $primary?->program_id),
                'yearLevelId' => old('year_level_id', $primary?->year_level_id),
                'sectionIds' => old('section_ids', $examination?->sections->pluck('id')->all() ?? []),
                'period' => old('examination_period', $examination?->examination_period?->value ?? 'MIDTERM'),
                'instructions' => old('instructions', $examination?->instructions ?? ''),
                'duration' => old('duration_minutes', $examination?->duration_minutes ?? config('examination.default_duration_minutes')),
                'passing' => old('passing_percentage', $examination?->passing_percentage ?? config('examination.default_passing_percentage')),
                'randomize' => old('randomize_questions', $examination?->settings?->randomize_questions ?? true),
                'backNav' => old('allow_back_navigation', $examination?->settings?->allow_back_navigation ?? true),
                'autoSubmit' => old('auto_submit_on_expire', $examination?->settings?->auto_submit_on_expire ?? true),
                'date' => old('examination_date', optional($examination?->examination_date)->format('Y-m-d') ?? ''),
                'start' => old('start_time', $examination?->start_time ? substr((string) $examination->start_time, 0, 5) : '08:00'),
                'end' => old('end_time', $examination?->end_time ? substr((string) $examination->end_time, 0, 5) : '10:00'),
            ],
            'errors' => $request->session()->get('errors')?->getBag('default')->toArray() ?? [],
        ];

        return [
            'examination' => $examination,
            'wizard' => $wizard,
        ];
    }

    protected function syncSettings(Examination $examination, array $data): void
    {
        $examination->settings()->updateOrCreate(
            ['examination_id' => $examination->id],
            [
                'randomize_questions' => (bool) ($data['randomize_questions'] ?? false),
                'allow_back_navigation' => (bool) ($data['allow_back_navigation'] ?? true),
                'auto_submit_on_expire' => (bool) ($data['auto_submit_on_expire'] ?? true),
            ]
        );
    }

    protected function syncSchedule(Examination $examination, array $data, ExamStatus $status): void
    {
        $from = null;
        $until = null;

        if (! empty($data['examination_date'])) {
            if (! empty($data['start_time'])) {
                $from = $data['examination_date'].' '.$data['start_time'];
            }
            if (! empty($data['end_time'])) {
                $until = $data['examination_date'].' '.$data['end_time'];
            }
        }

        $examination->schedule()->updateOrCreate(
            ['examination_id' => $examination->id],
            [
                'available_from' => $from,
                'available_until' => $until,
                'published_at' => $status === ExamStatus::Published || $status === ExamStatus::Active
                    ? ($examination->schedule?->published_at ?? now())
                    : $examination->schedule?->published_at,
            ]
        );
    }

    protected function resolveStatus(array $data, ?Examination $examination = null): ExamStatus
    {
        $requested = $data['status'] ?? $examination?->status?->value ?? ExamStatus::Draft->value;

        return ExamStatus::tryFrom((string) $requested) ?: ExamStatus::Draft;
    }

    protected function savedMessage(Examination $examination): string
    {
        return $examination->status === ExamStatus::Published
            ? 'Examination published.'
            : 'Examination saved successfully.';
    }

    protected function respondSaved(Request $request, Examination $examination, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'examination' => [
                    'id' => $examination->id,
                    'status' => $examination->status?->value,
                ],
                'updateUrl' => route('examinations.update', $examination),
                'redirect' => route('examinations.index'),
            ]);
        }

        return redirect()
            ->route('examinations.index')
            ->with('status', $message);
    }
}
