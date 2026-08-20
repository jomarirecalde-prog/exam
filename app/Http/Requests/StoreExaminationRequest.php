<?php

namespace App\Http\Requests;

use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use App\Models\Examination;
use App\Services\Examinations\ExaminationSectionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Examination::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'semester_id' => [
                'required',
                Rule::exists('semesters', 'id')->where('academic_year_id', $this->integer('academic_year_id')),
            ],
            'subject_id' => ['required', 'exists:subjects,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'year_level_id' => [
                'required',
                Rule::exists('year_levels', 'id')->where('program_id', $this->integer('program_id')),
            ],
            'section_ids' => ['required', 'array', 'min:1'],
            'section_ids.*' => ['required', 'integer', 'distinct', 'exists:sections,id'],
            'examination_period' => ['required', Rule::enum(ExaminationPeriod::class)],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'passing_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
            'examination_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'randomize_questions' => ['sometimes', 'boolean'],
            'allow_back_navigation' => ['sometimes', 'boolean'],
            'auto_submit_on_expire' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in([ExamStatus::Draft->value, ExamStatus::Published->value, ExamStatus::Scheduled->value])],
        ];
    }

    public function messages(): array
    {
        return [
            'section_ids.required' => 'Please select at least one section before continuing.',
            'section_ids.min' => 'Please select at least one section before continuing.',
            'section_ids.*.exists' => 'One or more selected sections could not be found.',
            'section_ids.*.distinct' => 'Duplicate section assignments are not allowed.',
            'semester_id.exists' => 'Select a semester that belongs to the chosen academic year.',
            'year_level_id.exists' => 'Select a year level that belongs to the chosen program.',
            'end_time.after' => 'The end time must be after the start time.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            app(ExaminationSectionService::class)->assertAssignable(
                $this->user(),
                $this->input('section_ids', []),
                $this->integer('academic_year_id'),
                $this->integer('semester_id'),
                $this->integer('subject_id'),
                $this->integer('program_id'),
                $this->integer('year_level_id'),
            );
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'section_ids' => array_values(array_unique(array_filter((array) $this->input('section_ids', [])))),
            'randomize_questions' => $this->boolean('randomize_questions'),
            'allow_back_navigation' => $this->boolean('allow_back_navigation'),
            'auto_submit_on_expire' => $this->boolean('auto_submit_on_expire'),
            'start_time' => filled($this->input('start_time')) ? substr((string) $this->input('start_time'), 0, 5) : null,
            'end_time' => filled($this->input('end_time')) ? substr((string) $this->input('end_time'), 0, 5) : null,
            'examination_date' => filled($this->input('examination_date')) ? $this->input('examination_date') : null,
            'status' => strtoupper((string) $this->input('status', ExamStatus::Draft->value)),
        ]);
    }
}
