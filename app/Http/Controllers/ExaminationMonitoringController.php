<?php

namespace App\Http\Controllers;

use App\Enums\ReactivationWarningMode;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Services\Examinations\ExaminationMonitoringService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ExaminationMonitoringController extends Controller
{
    public function data(
        Request $request,
        Examination $examination,
        ExaminationMonitoringService $monitoring,
    ): JsonResponse {
        try {
            $since = $request->query('since')
                ? Carbon::parse((string) $request->query('since'))
                : null;

            $payload = $monitoring->dashboard($examination, $request->user(), $since);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains(strtolower($e->getMessage()), 'authorized') ? 403 : 422);
        }

        return response()->json($payload);
    }

    public function showAttempt(
        Request $request,
        ExaminationAttempt $attempt,
        ExaminationMonitoringService $monitoring,
    ): JsonResponse {
        try {
            $detail = $monitoring->attemptDetail($attempt, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains(strtolower($e->getMessage()), 'authorized') ? 403 : 422);
        }

        return response()->json(['student' => $detail]);
    }

    public function violations(
        Request $request,
        ExaminationAttempt $attempt,
        ExaminationMonitoringService $monitoring,
    ): JsonResponse {
        try {
            $history = $monitoring->violationHistory($attempt, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains(strtolower($e->getMessage()), 'authorized') ? 403 : 422);
        }

        return response()->json([
            'attempt_id' => $attempt->id,
            'student' => trim(($attempt->student?->user?->first_name ?? '').' '.($attempt->student?->user?->last_name ?? '')),
            'examination' => $attempt->examination?->title,
            'warning_count' => (int) $attempt->warning_count,
            'max_warnings' => $attempt->maxWarnings(),
            'status' => $attempt->status->value,
            'lock_reason' => $attempt->lock_reason,
            'violations' => $history,
        ]);
    }

    public function reactivate(
        Request $request,
        ExaminationAttempt $attempt,
        ExaminationMonitoringService $monitoring,
    ): JsonResponse {
        $attempt->loadMissing(['examination', 'student.user']);

        $request->merge([
            'reactivation_reason' => trim((string) $request->input('reactivation_reason', '')),
        ]);

        $maxWarnings = (int) config('examination.max_violation_warnings', 3);

        $validated = $request->validate([
            'reactivation_reason' => ['required', 'string', 'max:1000'],
            'warning_mode' => ['required', 'string', 'in:reset,keep,manual'],
            'manual_warning_count' => ['nullable', 'integer', 'min:0', 'max:'.$maxWarnings],
        ]);

        if ($validated['warning_mode'] === 'manual' && ! isset($validated['manual_warning_count'])) {
            return response()->json([
                'message' => 'Please enter the warning count to apply after reactivation.',
                'errors' => ['manual_warning_count' => ['Please enter the warning count to apply after reactivation.']],
            ], 422);
        }

        try {
            $mode = ReactivationWarningMode::from($validated['warning_mode']);
            $attempt = $monitoring->reactivate(
                $attempt,
                $request->user(),
                $validated['reactivation_reason'],
                $mode,
                $validated['manual_warning_count'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'authorized') ? 403 : 422;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Examination attempt reactivated.',
            'attempt' => [
                'attempt_id' => $attempt->id,
                'status' => $attempt->status->value,
                'warning_count' => (int) $attempt->warning_count,
                'max_warnings' => $attempt->maxWarnings(),
                'reactivated_at' => $attempt->reactivated_at?->toIso8601String(),
                'reactivation_pending' => (bool) $attempt->reactivation_pending,
            ],
        ]);
    }
}
