<?php

namespace App\Http\Requests;

use App\Services\Students\AcademicLookupService;
use App\Services\Students\StudentSubjectEnrollmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreStudentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'home_address' => ['nullable', 'string', 'max:500'],
            'student_id' => ['required', 'string', 'max:50', 'unique:students,student_id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'year_level_id' => ['required', 'integer', 'exists:year_levels,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.unique' => 'An account using this Student ID already exists.',
            'email.unique' => 'An account using this email address already exists. Please sign in or recover your account.',
            'subject_ids.required' => 'Please select at least one enrolled subject.',
            'subject_ids.min' => 'Please select at least one enrolled subject.',
            'subject_ids.*.exists' => 'One or more selected subjects are invalid or unavailable.',
        ];
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

            try {
                app(StudentSubjectEnrollmentService::class)->validateSubjectIds($this->input('subject_ids', []));
            } catch (\Illuminate\Validation\ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject_ids' => array_values(array_unique(array_filter(array_map('intval', (array) $this->input('subject_ids', []))))),
        ]);
    }
}
