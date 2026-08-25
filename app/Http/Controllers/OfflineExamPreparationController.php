<?php

namespace App\Http\Controllers;

use App\Models\Examination;
use App\Services\Examinations\ExaminationAccessService;
use App\Services\Examinations\OfflineExamPreparationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OfflineExamPreparationController extends Controller
{
    public function prepare(
        Request $request,
        Examination $examination,
        ExaminationAccessService $access,
        OfflineExamPreparationService $preparation,
    ): JsonResponse {
        $user = $request->user();

        if (! $access->canTake($user, $examination)) {
            return response()->json(['message' => $access->denyTakeReason($user, $examination)], 403);
        }

        $validated = $request->validate([
            'device_identifier' => ['required', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $preparation->prepare(
                $user,
                $examination,
                $validated['device_identifier'],
                $validated['device_name'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
