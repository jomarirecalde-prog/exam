<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateInstructorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    public function rules(): array
    {
        $instructor = $this->route('instructor');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($instructor?->user_id),
            ],
            'employee_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('instructors', 'employee_id')->ignore($instructor?->id),
            ],
            'department_id' => ['required', 'exists:departments,id'],
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
}
