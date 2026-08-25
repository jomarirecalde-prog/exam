<?php

namespace App\Http\Requests;

use App\Enums\ExamEndPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EndExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('examination')) ?? false;
    }

    public function rules(): array
    {
        return [
            'end_policy' => ['required', Rule::enum(ExamEndPolicy::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
