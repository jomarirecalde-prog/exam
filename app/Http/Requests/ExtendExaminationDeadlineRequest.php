<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtendExaminationDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('examination')) ?? false;
    }

    public function rules(): array
    {
        return [
            'deadline_date' => ['required', 'date'],
            'deadline_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
