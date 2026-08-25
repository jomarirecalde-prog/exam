<?php

namespace App\Http\Controllers;

use App\Enums\ReactivationWarningMode;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Services\Examinations\ExaminationMonitoringService;
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
            $rows = $monitoring->attemptsForExamination($examination, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], str_contains(strtolower($e->getMessage()), 'authorized') ? 403 : 422);
        }

        return response()->json([
            'examination' => [
                'id' => $examination->id,
                'title' => $examination->title,
                'subject' => $examination->subject?->code,
            ],
            'attempts' => $rows,
        ]);
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
        $validated = $request->validate([
            'reactivation_reason' => ['required', 'string', 'max:1000'],
            'warning_mode' => ['required', 'string', 'in:reset,keep,manual'],
            'manual_warning_count' => ['nullable', 'integer', 'min:0', 'max:3'],
        ]);

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
            ],
        ]);
    }
}
