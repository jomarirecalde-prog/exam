<?php

namespace App\Http\Controllers;

use App\Enums\ExamEndPolicy;
use App\Http\Requests\EndExaminationRequest;
use App\Http\Requests\ExtendExaminationDeadlineRequest;
use App\Http\Requests\ReactivateExaminationRequest;
use App\Models\Examination;
use App\Services\Examinations\ExaminationAccessService;
use App\Services\Examinations\ExaminationEndService;
use App\Services\Examinations\ExaminationMonitoringService;
use App\Services\Examinations\ExaminationScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ExaminationControlController extends Controller
{
    use AuthorizesRequests;

    public function show(
        Examination $examination,
        ExaminationAccessService $access,
        ExaminationScheduleService $schedule,
        ExaminationMonitoringService $monitoring,
    ): JsonResponse {
        $this->authorize('update', $examination);

        $user = auth()->user();
        $dashboard = $monitoring->dashboard($examination, $user);

        return response()->json([
            'control' => [
                ...$schedule->schedulePayload($examination->fresh(['endedBy', 'subject'])),
                'title' => $examination->title,
                'subject' => $examination->subject?->name ?? $examination->subject?->code,
                'can_end' => $access->canManage($user, $examination)
                    && ! in_array($examination->status, [\App\Enums\ExamStatus::Ended, \App\Enums\ExamStatus::Closed, \App\Enums\ExamStatus::Expired, \App\Enums\ExamStatus::Draft], true),
                'can_reactivate' => $access->canManage($user, $examination)
                    && in_array($examination->status, [\App\Enums\ExamStatus::Ended, \App\Enums\ExamStatus::Closed, \App\Enums\ExamStatus::Expired], true),
                'can_extend_deadline' => $access->canManage($user, $examination)
                    && ! in_array($examination->status, [\App\Enums\ExamStatus::Ended, \App\Enums\ExamStatus::Closed, \App\Enums\ExamStatus::Expired, \App\Enums\ExamStatus::Draft], true),
                'edit_url' => route('examinations.edit', $examination),
            ],
            'summary' => $dashboard['summary'],
        ]);
    }

    public function end(
        EndExaminationRequest $request,
        Examination $examination,
        ExaminationEndService $end,
        ExaminationMonitoringService $monitoring,
    ): JsonResponse {
        $this->authorize('update', $examination);

        $validated = $request->validated();
        $policy = ExamEndPolicy::from($validated['end_policy']);

        try {
            $result = $end->endExamination(
                $examination,
                $request->user(),
                $policy,
                $validated['reason'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $takingCount = (int) ($result['affected_students'] ?? 0);

        return response()->json([
            'message' => 'Examination ended successfully.',
            ...$result,
            'activity' => [
                'type' => 'examination_ended',
                'message' => 'Instructor ended the examination.',
                'affected_students' => $takingCount,
                'offline_students' => $result['offline_students'] ?? 0,
                'occurred_at' => $result['ended_at'],
            ],
        ]);
    }

    public function extendDeadline(
        ExtendExaminationDeadlineRequest $request,
        Examination $examination,
        ExaminationEndService $end,
    ): JsonResponse {
        $this->authorize('update', $examination);

        $validated = $request->validated();

        try {
            $schedule = $end->extendDeadline(
                $examination,
                $request->user(),
                Carbon::parse("{$validated['deadline_date']} {$validated['deadline_time']}"),
                $validated['reason'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Examination deadline updated.',
            'examination' => $schedule,
        ]);
    }

    public function reactivate(
        ReactivateExaminationRequest $request,
        Examination $examination,
        ExaminationEndService $end,
    ): JsonResponse {
        $this->authorize('update', $examination);

        $validated = $request->validated();
        $newDeadline = null;

        if (! empty($validated['deadline_date'])) {
            $newDeadline = Carbon::parse("{$validated['deadline_date']} ".($validated['deadline_time'] ?? '23:59'));
        }

        try {
            $schedule = $end->reactivateExamination(
                $examination,
                $request->user(),
                $validated['reason'] ?? null,
                $newDeadline,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Examination reactivated successfully.',
            'examination' => $schedule,
            'activity' => [
                'type' => 'examination_reactivated',
                'message' => 'Instructor reactivated the examination.',
                'occurred_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
