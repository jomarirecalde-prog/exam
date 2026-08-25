<?php

namespace App\Http\Requests;

use App\Enums\ExaminationAccessMode;
use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use App\Models\Examination;
use App\Services\Examinations\ExaminationSectionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $examination = $this->route('examination');

        return $examination instanceof Examination
            && ($this->user()?->can('update', $examination) ?? false);
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
            'subject_offering_id' => ['nullable', 'integer', 'exists:subject_instructor,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'year_level_id' => [
                'required',
                Rule::exists('year_levels', 'id')->where('program_id', $this->integer('program_id')),
            ],
            'section_ids' => ['nullable', 'array'],
            'section_ids.*' => ['required', 'integer', 'distinct', 'exists:sections,id'],
            'access_mode' => ['required', Rule::enum(ExaminationAccessMode::class)],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'examination_period' => ['required', Rule::enum(ExaminationPeriod::class)],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'passing_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
            'randomize_questions' => ['sometimes', 'boolean'],
            'allow_back_navigation' => ['sometimes', 'boolean'],
            'auto_submit_on_expire' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in([
                ExamStatus::Draft->value,
                ExamStatus::Published->value,
                ExamStatus::Active->value,
            ])],
            'questions' => ['sometimes', 'array'],
            'questions.*.type' => ['required_with:questions', 'string', Rule::in(['multiple_choice', 'true_false', 'identification', 'essay'])],
            'questions.*.text' => ['required_with:questions', 'string'],
            'questions.*.points' => ['nullable', 'integer', 'min:1'],
            'questions.*.difficulty' => ['nullable', 'string', 'max:50'],
            'questions.*.topic' => ['nullable', 'string', 'max:255'],
            'questions.*.correctAnswer' => ['nullable', 'string'],
            'questions.*.sampleAnswer' => ['nullable', 'string'],
            'questions.*.choices' => ['nullable', 'array'],
            'questions.*.choices.*.id' => ['nullable', 'string', 'max:10'],
            'questions.*.choices.*.text' => ['nullable', 'string'],
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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $service = app(ExaminationSectionService::class);
            $accessMode = $this->input('access_mode', ExaminationAccessMode::SubjectAndSections->value);
            $sectionIds = $this->input('section_ids', []);
            $studentIds = $this->input('student_ids', []);

            if ($accessMode === ExaminationAccessMode::SubjectAndSections->value && $sectionIds === []) {
                $validator->errors()->add('section_ids', 'Please select at least one section for this access mode.');
            }

            if ($accessMode === ExaminationAccessMode::SpecificStudents->value && $studentIds === []) {
                $validator->errors()->add('student_ids', 'Please select at least one student for this access mode.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($sectionIds !== []) {
                $service->assertAssignable(
                    $this->user(),
                    $sectionIds,
                    $this->integer('academic_year_id'),
                    $this->integer('semester_id'),
                    $this->integer('subject_id'),
                    $this->integer('program_id'),
                    $this->integer('year_level_id'),
                );
            }

            $examination = $this->route('examination');

            if (! $examination instanceof Examination) {
                return;
            }

            $blocked = $service->protectedSectionIds($examination, $this->input('section_ids', []));

            if ($blocked !== []) {
                $names = \App\Models\Section::query()
                    ->whereIn('id', $blocked)
                    ->orderBy('name')
                    ->pluck('name')
                    ->filter()
                    ->implode(', ');

                $validator->errors()->add(
                    'section_ids',
                    $names !== ''
                        ? "Cannot remove {$names} because students from this section have already started or submitted the examination."
                        : 'Cannot remove a section that already has examination attempts.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'section_ids' => array_values(array_unique(array_filter((array) $this->input('section_ids', [])))),
            'student_ids' => array_values(array_unique(array_filter(array_map('intval', (array) $this->input('student_ids', []))))),
            'access_mode' => $this->input('access_mode', ExaminationAccessMode::SubjectAndSections->value),
            'randomize_questions' => $this->boolean('randomize_questions'),
            'allow_back_navigation' => $this->boolean('allow_back_navigation'),
            'auto_submit_on_expire' => $this->boolean('auto_submit_on_expire'),
        ]);

        if ($this->filled('status')) {
            $this->merge([
                'status' => strtoupper((string) $this->input('status')),
            ]);
        }
    }
}
