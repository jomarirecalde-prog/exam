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
            'file' => self::fileRules(),
            'subject_id' => ['nullable', 'exists:subjects,id'],
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function fileRules(): array
    {
        return ['required', 'file', 'max:2048', self::csvFileRule()];
    }

    /**
     * Accept CSV uploads by extension. MIME detection is unreliable on Windows
     * (Excel exports are often reported as text/plain or application/vnd.ms-excel).
     */
    protected static function csvFileRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! $value instanceof \Illuminate\Http\UploadedFile) {
                return;
            }

            $extension = strtolower($value->getClientOriginalExtension());

            if (! in_array($extension, ['csv', 'txt'], true)) {
                $fail('The uploaded file must be a CSV.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a CSV file to import.',
            'file.max' => 'The CSV file may not be larger than 2 MB.',
        ];
    }
}
