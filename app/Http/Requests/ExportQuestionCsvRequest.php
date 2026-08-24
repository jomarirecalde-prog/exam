<?php

namespace App\Http\Requests;

use App\Models\Examination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportQuestionCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Examination::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['all', 'filtered', 'selected'])],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'type' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'exists:questions,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedFilters(): array
    {
        $scope = $this->input('scope', 'filtered');
        $filters = [];

        if ($scope === 'selected') {
            $filters['ids'] = array_values(array_filter((array) $this->input('ids', [])));

            return $filters;
        }

        if ($scope === 'all') {
            return $filters;
        }

        if ($this->filled('subject_id')) {
            $filters['subject_id'] = (int) $this->input('subject_id');
        }

        if ($this->filled('difficulty')) {
            $filters['difficulty'] = strtolower((string) $this->input('difficulty'));
        }

        if ($this->filled('type')) {
            $filters['type'] = (string) $this->input('type');
        }

        if ($this->filled('search')) {
            $filters['search'] = (string) $this->input('search');
        }

        return $filters;
    }
}
