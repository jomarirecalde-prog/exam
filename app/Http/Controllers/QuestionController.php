<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportQuestionCsvRequest;
use App\Http\Requests\ImportQuestionCsvRequest;
use App\Models\Examination;
use App\Models\Subject;
use App\Services\AuditLogger;
use App\Services\Questions\QuestionCsvExporter;
use App\Services\Questions\QuestionCsvImporter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('create', Examination::class);

        $query = \App\Models\Question::query()->with('subject')->latest();

        if ($request->filled('search')) {
            $query->where('question_text', 'like', '%'.$request->string('search').'%');
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', (int) $request->input('subject_id'));
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', strtolower((string) $request->input('difficulty')));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

        return view('pages.questions.index', [
            'questions' => $query->paginate(10)->withQueryString(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'subject_id' => $request->input('subject_id'),
                'difficulty' => $request->input('difficulty'),
                'type' => $request->input('type'),
            ],
            'csv' => [
                'templateUrl' => route('questions.csv-template'),
                'previewUrl' => route('questions.preview-csv'),
                'confirmUrl' => route('questions.import-csv'),
                'exportUrl' => route('questions.export-csv'),
                'errorReportUrl' => route('questions.error-report'),
            ],
        ]);
    }

    public function previewCsv(ImportQuestionCsvRequest $request, QuestionCsvImporter $importer): JsonResponse
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
        Cache::put("question-csv-import:{$token}", [
            'questions' => $result['questions'],
            'subject_id' => $subjectId,
            'rowErrors' => $result['rowErrors'],
            'stats' => $result['stats'],
        ], now()->addMinutes(15));

        return response()->json([
            'message' => 'CSV preview ready.',
            'token' => $token,
            'stats' => $result['stats'],
            'preview' => $result['preview'],
            'errors' => $result['errors'],
            'rowErrors' => $result['rowErrors'],
        ]);
    }

    public function importCsv(Request $request, QuestionCsvImporter $importer, AuditLogger $audit): JsonResponse
    {
        $this->authorize('create', Examination::class);

        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'import_mode' => ['required', 'in:create,update,upsert'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $cacheKey = "question-csv-import:{$data['token']}";
        $cached = Cache::get($cacheKey);

        if (! is_array($cached) || empty($cached['questions'])) {
            return response()->json([
                'message' => 'Import session expired. Please upload the CSV file again.',
            ], 422);
        }

        $mode = match ($data['import_mode']) {
            'create' => 'create',
            'update' => 'update',
            default => 'upsert',
        };

        $counts = $importer->persist(
            $cached['questions'],
            (int) $data['subject_id'],
            $request->user()->instructor?->id,
            $mode,
        );

        Cache::forget($cacheKey);

        $audit->log(
            $request->user(),
            'import',
            'question_bank',
            'questions',
            null,
            [
                'import_mode' => $data['import_mode'],
                'subject_id' => (int) $data['subject_id'],
                'created' => $counts['created'],
                'updated' => $counts['updated'],
                'skipped' => $counts['skipped'],
            ],
        );

        return response()->json([
            'message' => $this->importMessage($counts),
            'counts' => $counts,
        ]);
    }

    public function exportCsv(ExportQuestionCsvRequest $request, QuestionCsvExporter $exporter, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->validatedFilters();
        $csv = $exporter->export($filters);

        $audit->log(
            $request->user(),
            'export',
            'question_bank',
            'questions',
            null,
            [
                'filters' => $filters,
                'count' => $exporter->count($filters),
            ],
        );

        return response()->streamDownload(
            fn () => print ($csv),
            $exporter->filename($filters),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function csvTemplate(QuestionCsvImporter $importer): StreamedResponse
    {
        $this->authorize('create', Examination::class);

        return response()->streamDownload(
            fn () => print ($importer->template()),
            'question-import-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function errorReport(Request $request, QuestionCsvImporter $importer): StreamedResponse|JsonResponse
    {
        $this->authorize('create', Examination::class);

        if ($request->hasFile('file')) {
            $request->validate([
                'file' => ImportQuestionCsvRequest::fileRules(),
                'subject_id' => ['nullable', 'exists:subjects,id'],
            ]);

            $result = $importer->import(
                $request->file('file')->getRealPath(),
                $request->integer('subject_id') ?: null,
            );
            $rowErrors = $result['rowErrors'];
        } else {
            $data = $request->validate([
                'token' => ['required', 'uuid'],
            ]);

            $cached = Cache::get("question-csv-import:{$data['token']}");

            if (! is_array($cached)) {
                return response()->json([
                    'message' => 'Import session expired. Please upload the CSV file again.',
                ], 422);
            }

            $rowErrors = $cached['rowErrors'] ?? [];
        }

        if ($rowErrors === []) {
            return response()->json([
                'message' => 'No import errors to download.',
            ], 422);
        }

        return response()->streamDownload(
            fn () => print ($importer->errorReport($rowErrors)),
            'import-errors-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @param  array{created: int, updated: int, skipped: int}  $counts
     */
    protected function importMessage(array $counts): string
    {
        $parts = [];

        if ($counts['created'] > 0) {
            $parts[] = "{$counts['created']} created";
        }

        if ($counts['updated'] > 0) {
            $parts[] = "{$counts['updated']} updated";
        }

        if ($counts['skipped'] > 0) {
            $parts[] = "{$counts['skipped']} skipped";
        }

        return $parts === []
            ? 'No records were imported.'
            : 'Successfully imported questions: '.implode(', ', $parts).'.';
    }
}
