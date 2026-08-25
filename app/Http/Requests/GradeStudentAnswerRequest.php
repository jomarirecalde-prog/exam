<?php

namespace App\Http\Requests;

use App\Models\Examination;
use App\Models\StudentAnswer;
use App\Services\Grading\ManualGradingService;
use Illuminate\Foundation\Http\FormRequest;

class GradeStudentAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $examination = $this->route('examination');

        return $examination instanceof Examination
            && ($this->user()?->can('update', $examination) ?? false);
    }

    public function rules(): array
    {
        return [
            'points_earned' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $examination = $this->route('examination');
            $answer = $this->route('answer');

            if (! $examination instanceof Examination || ! $answer instanceof StudentAnswer) {
                return;
            }

            $answer->loadMissing(['attempt', 'question']);

            if (! $answer->attempt || (int) $answer->attempt->examination_id !== (int) $examination->id) {
                $validator->errors()->add('answer', 'Answer does not belong to this examination.');

                return;
            }

            if (! $answer->requires_manual_grading) {
                $validator->errors()->add('answer', 'This answer does not require manual grading.');

                return;
            }

            $maxPoints = app(ManualGradingService::class)->maxPointsForAnswer($answer, $answer->attempt);
            $points = (float) $this->input('points_earned');

            if ($points > $maxPoints) {
                $validator->errors()->add(
                    'points_earned',
                    "Points cannot exceed {$maxPoints} for this question.",
                );
            }
        });
    }
}
