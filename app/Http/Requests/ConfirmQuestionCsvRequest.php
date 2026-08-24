<?php

namespace App\Http\Requests;

use App\Models\Examination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmQuestionCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Examination::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],
            'import_mode' => ['sometimes', Rule::in(['append', 'replace', 'create', 'update', 'upsert'])],
        ];
    }
}
