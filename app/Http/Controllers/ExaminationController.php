<?php

namespace App\Http\Controllers;

use App\Enums\ExaminationAccessMode;
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
use App\Http\Requests\ConfirmQuestionCsvRequest;
use App\Services\AuditLogger;
use App\Services\Examinations\ExaminationSectionService;
use App\Services\Questions\ExaminationQuestionService;
use App\Services\Questions\QuestionCsvImporter;
use App\Services\Students\SubjectOfferingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExaminationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ExaminationSectionService $sections,
        protected ExaminationQuestionService $questions,
        protected SubjectOfferingService $offerings,
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
                'subject_offering_id' => $data['subject_offering_id'] ?? null,
                'instructor_id' => $request->user()->instructor?->id,
                'academic_year_id' => $data['academic_year_id'],
                'semester_id' => $data['semester_id'],
                'examination_period' => $data['examination_period'],
                'duration_minutes' => $data['duration_minutes'],
                'passing_percentage' => $data['passing_percentage'],
                'status' => $status,
                'section_id' => $data['section_ids'][0] ?? null,
                'needs_section_review' => false,
                'access_mode' => $data['access_mode'] ?? ExaminationAccessMode::SubjectAndSections,
            ]);

            if (! empty($data['section_ids'])) {
                $this->sections->sync($examination, $data['section_ids'], false);
            }

            if (! empty($data['student_ids'])) {
                $examination->assignedStudents()->sync($data['student_ids']);
            }
            $this->syncSettings($examination, $data);

            if (! empty($data['questions'])) {
                $this->questions->sync($examination, $data['questions'], $request->user()->instructor?->id);
            }

            return $examination->load('sections');
        });

        return $this->respondSaved($request, $examination, $this->savedMessage($examination));
    }

    public function edit(Request $request, Examination $examination): View
    {
        $this->authorize('update', $examination);

        $examination->load(['sections', 'settings', 'subject']);

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
                'subject_offering_id' => $data['subject_offering_id'] ?? null,
                'academic_year_id' => $data['academic_year_id'],
                'semester_id' => $data['semester_id'],
                'examination_period' => $data['examination_period'],
                'duration_minutes' => $data['duration_minutes'],
                'passing_percentage' => $data['passing_percentage'],
                'status' => $status,
                'access_mode' => $data['access_mode'] ?? ExaminationAccessMode::SubjectAndSections,
            ]);

            if (! empty($data['section_ids'])) {
                $this->sections->sync($examination, $data['section_ids']);
            } else {
                $examination->sections()->detach();
            }

            if (array_key_exists('student_ids', $data)) {
                $examination->assignedStudents()->sync($data['student_ids'] ?? []);
            }
            $this->syncSettings($examination, $data);

            if (array_key_exists('questions', $data)) {
                $this->questions->sync($examination, $data['questions'] ?? [], $request->user()->instructor?->id);
            }
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

    public function availableOfferings(Request $request): JsonResponse
    {
        $this->authorize('create', Examination::class);

        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $offerings = $this->offerings->offeringsForExam(
            (int) $data['subject_id'],
            (int) $data['academic_year_id'],
            (int) $data['semester_id'],
            $data['search'] ?? null,
        );

        return response()->json(['offerings' => $offerings->values()]);
    }

    public function previewQuestionsCsv(ImportQuestionCsvRequest $request, QuestionCsvImporter $importer): JsonResponse
    {
        $subjectId = $request->integer('subject_id') ?: null;
        $result = $importer->import($request->file('file')->getRealPath(), $subjectId);

        if ($result['stats']['valid'] === 0) {
            return response()->json([
                'message' => $result['stats']['total'] === 0
                    ? 'Unable to preview this CSV file.'
                    : 'No valid questions were found in this CSV file.',
                'errors' => $result['errors'],
                'rowErrors' => $result['rowErrors'],
                'stats' => $result['stats'],
                'preview' => $result['preview'],
            ]);
        }

        $token = (string) Str::uuid();
        Cache::put("exam-csv-import:{$token}", [
            'questions' => $result['questions'],
            'rowErrors' => $result['rowErrors'],
            'stats' => $result['stats'],
        ], now()->addMinutes(15));

        return response()->json([
            'message' => 'CSV preview ready.',
            'token' => $token,
            'questions' => $result['questions'],
            'errors' => $result['errors'],
            'rowErrors' => $result['rowErrors'],
            'warnings' => $result['warnings'],
            'stats' => $result['stats'],
            'preview' => $result['preview'],
            'imported' => $result['imported'],
        ]);
    }

    public function importQuestions(ConfirmQuestionCsvRequest $request, AuditLogger $audit): JsonResponse
    {
        $cacheKey = "exam-csv-import:{$request->input('token')}";
        $cached = Cache::get($cacheKey);

        if (! is_array($cached) || empty($cached['questions'])) {
            return response()->json([
                'message' => 'Import session expired. Please upload the CSV file again.',
            ], 422);
        }

        Cache::forget($cacheKey);

        $audit->log(
            $request->user(),
            'import',
            'examinations',
            'questions',
            null,
            [
                'context' => 'wizard',
                'import_mode' => $request->input('import_mode', 'append'),
                'imported' => count($cached['questions']),
                'stats' => $cached['stats'] ?? [],
            ],
        );

        return response()->json([
            'message' => count($cached['questions']).' question(s) ready to add.',
            'questions' => $cached['questions'],
            'errors' => array_map(
                fn (array $error) => "Row {$error['row']}\n".ucfirst(str_replace('_', ' ', $error['field'])).": {$error['message']}",
                $cached['rowErrors'] ?? [],
            ),
            'rowErrors' => $cached['rowErrors'] ?? [],
            'imported' => count($cached['questions']),
            'stats' => $cached['stats'] ?? [],
        ]);
    }

    public function questionCsvErrorReport(ImportQuestionCsvRequest $request, QuestionCsvImporter $importer): StreamedResponse|JsonResponse
    {
        $result = $importer->import(
            $request->file('file')->getRealPath(),
            $request->integer('subject_id') ?: null,
        );

        if ($result['rowErrors'] === []) {
            return response()->json([
                'message' => 'No import errors to download.',
            ], 422);
        }

        return response()->streamDownload(
            fn () => print ($importer->errorReport($result['rowErrors'])),
            'import-errors-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
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
            'offeringsUrl' => route('examinations.offerings'),
            'previewQuestionsUrl' => route('examinations.preview-questions-csv'),
            'importQuestionsUrl' => route('examinations.import-questions'),
            'questionCsvTemplateUrl' => route('examinations.question-csv-template'),
            'questionCsvErrorReportUrl' => route('examinations.question-csv-error-report'),
            'questions' => $examination
                ? $this->questions->toWizardPayload($examination->loadMissing('examQuestions.question.choices'))
                : [],
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
                'subjectOfferingId' => old('subject_offering_id', $examination?->subject_offering_id),
                'programId' => old('program_id', $primary?->program_id),
                'yearLevelId' => old('year_level_id', $primary?->year_level_id),
                'sectionIds' => old('section_ids', $examination?->sections->pluck('id')->all() ?? []),
                'accessMode' => old('access_mode', $examination?->access_mode?->value ?? ExaminationAccessMode::SubjectAndSections->value),
                'period' => old('examination_period', $examination?->examination_period?->value ?? 'MIDTERM'),
                'instructions' => old('instructions', $examination?->instructions ?? ''),
                'duration' => old('duration_minutes', $examination?->duration_minutes ?? config('examination.default_duration_minutes')),
                'passing' => old('passing_percentage', $examination?->passing_percentage ?? config('examination.default_passing_percentage')),
                'randomize' => old('randomize_questions', $examination?->settings?->randomize_questions ?? true),
                'backNav' => old('allow_back_navigation', $examination?->settings?->allow_back_navigation ?? true),
                'autoSubmit' => old('auto_submit_on_expire', $examination?->settings?->auto_submit_on_expire ?? true),
                'offlineMode' => old('offline_examination_mode', $examination?->settings?->offline_examination_mode?->value ?? 'disabled'),
                'allowOfflineContinuation' => old('allow_offline_continuation', $examination?->settings?->allow_offline_continuation ?? false),
                'requireOfflinePreparation' => old('require_offline_preparation', $examination?->settings?->require_offline_preparation ?? false),
                'allowPendingOfflineSubmission' => old('allow_pending_offline_submission', $examination?->settings?->allow_pending_offline_submission ?? true),
                'maxOfflineDuration' => old('max_offline_duration_minutes', $examination?->settings?->max_offline_duration_minutes ?? 30),
                'syncGracePeriod' => old('sync_grace_period_minutes', $examination?->settings?->sync_grace_period_minutes ?? 15),
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
                'offline_examination_mode' => $data['offline_examination_mode'] ?? 'disabled',
                'allow_offline_continuation' => (bool) ($data['allow_offline_continuation'] ?? false),
                'require_offline_preparation' => (bool) ($data['require_offline_preparation'] ?? false),
                'allow_pending_offline_submission' => (bool) ($data['allow_pending_offline_submission'] ?? true),
                'max_offline_duration_minutes' => $data['max_offline_duration_minutes'] ?? null,
                'sync_grace_period_minutes' => (int) ($data['sync_grace_period_minutes'] ?? 15),
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
