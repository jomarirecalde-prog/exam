<?php

namespace App\Http\Controllers;

use App\Models\ExaminationAttempt;
use App\Services\Examinations\ExamAttemptSyncService;
use App\Services\Examinations\ExaminationAttemptService;
use App\Services\Examinations\SyncConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OfflineExamSyncController extends Controller
{
    public function sync(
        Request $request,
        ExaminationAttempt $attempt,
        ExamAttemptSyncService $sync,
    ): JsonResponse {
        $user = $request->user();

        if (! $this->ownsAttempt($user, $attempt)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'device_identifier' => ['required', 'string', 'max:128'],
            'events' => ['required', 'array', 'min:1'],
            'events.*.client_event_uuid' => ['required', 'uuid'],
            'events.*.event_type' => ['required', 'string', 'max:64'],
            'events.*.payload' => ['nullable', 'array'],
        ]);

        try {
            $result = $sync->syncEvents(
                $attempt,
                $validated['events'],
                $validated['device_identifier'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (SyncConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'conflicts' => [$e->details],
            ], 409);
        }

        return response()->json($result);
    }

    public function submitOffline(
        Request $request,
        ExaminationAttempt $attempt,
        ExamAttemptSyncService $sync,
    ): JsonResponse {
        $user = $request->user();

        if (! $this->ownsAttempt($user, $attempt)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'device_identifier' => ['required', 'string', 'max:128'],
            'client_event_uuid' => ['required', 'uuid'],
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable'],
            'answers.*.is_flagged' => ['sometimes', 'boolean'],
            'auto' => ['sometimes', 'boolean'],
            'timing_token' => ['nullable', 'string'],
        ]);

        try {
            $result = $sync->submitOffline($attempt, $validated, $validated['device_identifier']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (isset($result['results'][0]['result']['submitted']) && $result['results'][0]['result']['submitted']) {
            return response()->json([
                'message' => 'Examination submitted.',
                'submitted' => true,
                'result_url' => $result['results'][0]['result']['result_url'] ?? null,
                'attempt' => $result['attempt'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Examination submission pending synchronization.',
            'pending' => true,
            'attempt' => $result['attempt'] ?? null,
        ]);
    }

    public function syncStatus(
        Request $request,
        ExaminationAttemptService $attempts,
    ): JsonResponse {
        $user = $request->user();
        $student = $user->student;

        if (! $student) {
            return response()->json(['attempts' => []]);
        }

        $activeAttempts = ExaminationAttempt::query()
            ->where('student_id', $student->id)
            ->where(function ($q) {
                $q->where('offline_enabled', true)
                    ->orWhereNotNull('pending_submission_at');
            })
            ->with('examination.subject')
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'online' => true,
            'attempts' => $activeAttempts->map(fn ($attempt) => [
                'attempt_id' => $attempt->id,
                'examination_id' => $attempt->examination_id,
                'examination_title' => $attempt->examination?->title,
                'status' => $attempt->status->value,
                'offline_prepared_at' => $attempt->offline_prepared_at?->toIso8601String(),
                'last_synced_at' => $attempt->last_synced_at?->toIso8601String(),
                'pending_submission_at' => $attempt->pending_submission_at?->toIso8601String(),
                'attempt_state' => $attempts->attemptState($attempt),
            ]),
        ]);
    }

    protected function ownsAttempt($user, ExaminationAttempt $attempt): bool
    {
        if ($user->hasAnyRole(['superadmin', 'admin', 'instructor'])) {
            return true;
        }

        return $user->student && (int) $user->student->id === (int) $attempt->student_id;
    }
}
