<?php

namespace App\Http\Requests;

use App\Models\Examination;
use Illuminate\Foundation\Http\FormRequest;

class ImportQuestionCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Examination::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a CSV file to import.',
            'file.mimes' => 'The uploaded file must be a CSV.',
            'file.max' => 'The CSV file may not be larger than 2 MB.',
        ];
    }
}
