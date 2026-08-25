<?php

namespace App\Http\Requests;

use App\Services\Students\AcademicLookupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($student?->user_id),
            ],
            'home_address' => ['nullable', 'string', 'max:500'],
            'student_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'student_id')->ignore($student?->id),
            ],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'year_level_id' => ['required', 'integer', 'exists:year_levels,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->input('is_active') === '1' || $this->boolean('is_active'),
            'password' => filled($this->input('password')) ? $this->input('password') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lookup = app(AcademicLookupService::class);
            $departmentId = (int) $this->input('department_id');
            $programId = (int) $this->input('program_id');
            $yearLevelId = (int) $this->input('year_level_id');
            $sectionId = (int) $this->input('section_id');

            if (! $lookup->programBelongsToDepartment($programId, $departmentId)) {
                $validator->errors()->add('program_id', 'The selected program does not belong to the chosen department.');
            }

            if (! $lookup->yearLevelBelongsToProgram($yearLevelId, $programId)) {
                $validator->errors()->add('year_level_id', 'The selected year level does not belong to the chosen program.');
            }

            if (! $lookup->sectionBelongsToProgramAndYearLevel($sectionId, $programId, $yearLevelId)) {
                $validator->errors()->add('section_id', 'The selected section is not valid for the chosen program and year level.');
            }
        });
    }
}
