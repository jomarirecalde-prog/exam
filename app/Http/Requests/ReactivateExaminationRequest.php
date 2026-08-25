<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReactivateExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('examination')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
            'deadline_date' => ['nullable', 'date'],
            'deadline_time' => ['nullable', 'date_format:H:i'],
        ];
    }
}
