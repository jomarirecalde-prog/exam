<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'units' => ['required', 'integer', 'min:1', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->input('is_active') === '1' || $this->boolean('is_active'),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'description' => filled($this->input('description')) ? $this->input('description') : null,
            'department_id' => filled($this->input('department_id')) ? $this->input('department_id') : null,
        ]);
    }
}
