<?php

namespace App\Http\Controllers;

use App\Http\Requests\GradeStudentAnswerRequest;
use App\Models\Examination;
use App\Models\StudentAnswer;
use App\Services\Grading\ManualGradingService;
use Illuminate\Http\RedirectResponse;

class ExaminationGradingController extends Controller
{
    public function store(
        GradeStudentAnswerRequest $request,
        Examination $examination,
        StudentAnswer $answer,
        ManualGradingService $grading,
    ): RedirectResponse {
        $grade = $grading->gradeAnswer(
            $examination,
            $answer,
            (float) $request->validated('points_earned'),
            $request->validated('feedback'),
            $request->user(),
        );

        return redirect()
            ->route('examinations.result', [
                'examination' => $examination,
                'student' => $grade->student_id,
            ])
            ->with('status', 'Answer graded successfully.');
    }
}
