<?php

namespace App\Http\Controllers;

use App\Enums\ViolationType;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Services\Examinations\ExaminationAccessService;
use App\Services\Examinations\ExaminationAttemptService;
use App\Services\Examinations\ExamViolationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ExaminationAttemptController extends Controller
{
    public function acceptPolicy(
        Request $request,
        Examination $examination,
        ExaminationAccessService $access,
        ExaminationAttemptService $attempts,
    ): JsonResponse {
        $user = $request->user();

        if (! $access->canTake($user, $examination)) {
            return response()->json(['message' => $access->denyTakeReason($user, $examination)], 403);
        }

        try {
            $attempt = $attempts->acceptPolicy($user, $examination);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Examination policy accepted.',
            'attempt' => $attempts->attemptState($attempt),
        ]);
    }

    public function start(
        Request $request,
        Examination $examination,
        ExaminationAccessService $access,
        ExaminationAttemptService $attempts,
    ): JsonResponse {
        $user = $request->user();

        if (! $access->canTake($user, $examination)) {
            return response()->json(['message' => $access->denyTakeReason($user, $examination)], 403);
        }

        try {
            $attempt = $attempts->startAttempt($user, $examination);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Examination started.',
            'attempt' => $attempts->attemptState($attempt),
        ]);
    }

    public function state(
        Request $request,
        Examination $examination,
        ExaminationAccessService $access,
        ExaminationAttemptService $attempts,
    ): JsonResponse {
        $user = $request->user();
        $student = $user->student;

        if (! $access->canTake($user, $examination)) {
            return response()->json(['message' => $access->denyTakeReason($user, $examination)], 403);
        }

        if (! $student) {
            return response()->json(['message' => 'Only students have examination attempts.'], 403);
        }

        $attempt = $attempts->findResumableAttempt($student, $examination);

        if (! $attempt) {
            return response()->json(['attempt' => null]);
        }

        return response()->json(['attempt' => $attempts->attemptState($attempt)]);
    }

    public function saveAnswer(
        Request $request,
        Examination $examination,
        int $question,
        ExaminationAccessService $access,
        ExaminationAttemptService $attempts,
    ): JsonResponse {
        $attempt = $this->resolveWritableAttempt($request, $examination, $access, $attempts);

        if ($attempt instanceof JsonResponse) {
            return $attempt;
        }

        $validated = $request->validate([
            'answer' => ['nullable'],
            'is_flagged' => ['sometimes', 'boolean'],
        ]);

        try {
            $attempts->saveAnswer(
                $attempt,
                $question,
                $validated['answer'] ?? null,
                (bool) ($validated['is_flagged'] ?? false),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Answer saved.']);
    }

    public function saveAnswers(
        Request $request,
        Examination $examination,
        ExaminationAccessService $access,
        ExaminationAttemptService $attempts,
    ): JsonResponse {
        $attempt = $this->resolveWritableAttempt($request, $examination, $access, $attempts);

        if ($attempt instanceof JsonResponse) {
            return $attempt;
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable'],
            'answers.*.is_flagged' => ['sometimes', 'boolean'],
        ]);

        try {
            $attempts->saveAnswersBulk($attempt, $validated['answers']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Answers saved.']);
    }

    public function recordViolation(
        Request $request,
        Examination $examination,
        ExaminationAccessService $access,
        ExaminationAttemptService $attempts,
        ExamViolationService $violations,
    ): JsonResponse {
        $attempt = $this->resolveWritableAttempt($request, $examination, $access, $attempts, allowLockedResponse: true);

        if ($attempt instanceof JsonResponse) {
            return $attempt;
        }

        $validated = $request->validate([
            'violation_type' => ['required', 'string'],
            'client_event_id' => ['nullable', 'string', 'max:128'],
            'metadata' => ['nullable', 'array'],
            'pending_answers' => ['nullable', 'array'],
        ]);

        try {
            $type = ViolationType::from($validated['violation_type']);
        } catch (\ValueError) {
            return response()->json(['message' => 'Invalid violation type.'], 422);
        }

        $metadata = $validated['metadata'] ?? [];
        if (! empty($validated['pending_answers'])) {
            $metadata['pending_answers'] = $validated['pending_answers'];
        }

        $result = $violations->recordViolation(
            $attempt,
            $type,
            $validated['client_event_id'] ?? null,
            $metadata,
        );

        return response()->json([
            'recorded' => $result['recorded'],
            'duplicate' => $result['duplicate'] ?? false,
            'warning_count' => $result['warning_count'],
            'max_warnings' => $result['max_warnings'],
            'locked' => $result['locked'],
            'message' => $result['message'] ?? null,
            'violation_type' => $result['violation_type'] ?? $type->value,
            'violation_label' => $result['violation_label'] ?? $type->label(),
            'attempt' => $attempts->attemptState($attempt->fresh()),
        ]);
    }

    public function submit(
        Request $request,
        Examination $examination,
        ExaminationAccessService $access,
        ExaminationAttemptService $attempts,
    ): JsonResponse {
        $attempt = $this->resolveWritableAttempt($request, $examination, $access, $attempts);

        if ($attempt instanceof JsonResponse) {
            return $attempt;
        }

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable'],
            'answers.*.is_flagged' => ['sometimes', 'boolean'],
            'auto' => ['sometimes', 'boolean'],
        ]);

        try {
            if (! empty($validated['answers'])) {
                $attempts->saveAnswersBulk($attempt, $validated['answers']);
            }

            $attempt = $attempts->submitAttempt($attempt->fresh(), (bool) ($validated['auto'] ?? false));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Examination submitted.',
            'attempt' => $attempts->attemptState($attempt),
            'result_url' => route('examinations.result', $examination),
        ]);
    }

    protected function resolveWritableAttempt(
        Request $request,
        Examination $examination,
        ExaminationAccessService $access,
        ExaminationAttemptService $attempts,
        bool $allowLockedResponse = false,
    ): ExaminationAttempt|JsonResponse {
        $user = $request->user();
        $student = $user->student;

        if (! $access->canTake($user, $examination)) {
            return response()->json(['message' => $access->denyTakeReason($user, $examination)], 403);
        }

        if (! $student) {
            return response()->json(['message' => 'Only students have examination attempts.'], 403);
        }

        $attempt = $attempts->findResumableAttempt($student, $examination);

        if (! $attempt) {
            return response()->json(['message' => 'No active examination attempt found.'], 404);
        }

        if ($attempt->status->isLocked() && ! $allowLockedResponse) {
            return response()->json([
                'message' => 'Your examination is locked.',
                'attempt' => $attempts->attemptState($attempt),
            ], 423);
        }

        return $attempt;
    }
}
