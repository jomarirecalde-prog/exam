<?php

namespace App\Http\Requests;

use App\Models\Instructor;
use App\Models\Semester;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'instructor_id' => ['nullable', 'exists:instructors,id'],
            'academic_year_id' => ['nullable', 'required_with:instructor_id', 'exists:academic_years,id'],
            'semester_id' => ['nullable', 'required_with:instructor_id', 'exists:semesters,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('instructor_id') && $this->filled('department_id')) {
                $instructor = Instructor::query()->find($this->input('instructor_id'));

                if ($instructor?->department_id && (int) $instructor->department_id !== (int) $this->input('department_id')) {
                    $validator->errors()->add('instructor_id', 'The selected instructor does not belong to the chosen department.');
                }
            }

            if ($this->filled('semester_id') && $this->filled('academic_year_id')) {
                $semester = Semester::query()->find($this->input('semester_id'));

                if ($semester && (int) $semester->academic_year_id !== (int) $this->input('academic_year_id')) {
                    $validator->errors()->add('semester_id', 'The selected semester does not belong to the chosen academic year.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->input('is_active') === '1' || $this->boolean('is_active'),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'description' => filled($this->input('description')) ? $this->input('description') : null,
            'department_id' => filled($this->input('department_id')) ? $this->input('department_id') : null,
            'instructor_id' => filled($this->input('instructor_id')) ? $this->input('instructor_id') : null,
            'academic_year_id' => filled($this->input('academic_year_id')) ? $this->input('academic_year_id') : null,
            'semester_id' => filled($this->input('semester_id')) ? $this->input('semester_id') : null,
            'section_id' => filled($this->input('section_id')) ? $this->input('section_id') : null,
        ]);
    }
}
