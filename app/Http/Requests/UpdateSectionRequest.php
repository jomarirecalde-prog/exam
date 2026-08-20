<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'program_id' => ['required', 'exists:programs,id'],
            'year_level_id' => [
                'required',
                Rule::exists('year_levels', 'id')->where('program_id', $this->integer('program_id')),
            ],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'semester_id' => [
                'required',
                Rule::exists('semesters', 'id')->where('academic_year_id', $this->integer('academic_year_id')),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'year_level_id.exists' => 'Select a year level that belongs to the chosen program.',
            'semester_id.exists' => 'Select a semester that belongs to the chosen academic year.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->input('is_active') === '1' || $this->boolean('is_active'),
            'code' => filled($this->input('code')) ? $this->input('code') : null,
        ]);
    }
}
