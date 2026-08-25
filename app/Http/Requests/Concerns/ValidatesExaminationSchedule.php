<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;
use Illuminate\Validation\Validator;

trait ValidatesExaminationSchedule
{
    protected function validateExaminationSchedule(Validator $validator): void
    {
        $publishing = in_array(strtoupper((string) $this->input('status', 'DRAFT')), ['PUBLISHED', 'ACTIVE'], true);
        $immediate = $this->boolean('availability_immediate', true);

        if ($publishing && ! $this->filled('deadline_date')) {
            $validator->errors()->add('deadline_date', 'Please set an examination deadline before publishing.');
        }

        if ($publishing && ! $this->filled('deadline_policy')) {
            $validator->errors()->add('deadline_policy', 'Please select what happens when the examination deadline is reached.');
        }

        if (! $immediate && ! $this->filled('available_from_date')) {
            $validator->errors()->add('available_from_date', 'Please set when the examination becomes available.');
        }

        if (! $immediate && ! $this->filled('available_from_time')) {
            $validator->errors()->add('available_from_time', 'Please set the availability start time.');
        }

        $availableFrom = $immediate
            ? now()
            : ($this->filled('available_from_date')
                ? Carbon::parse($this->input('available_from_date').' '.($this->input('available_from_time') ?: '00:00'))
                : null);

        if ($this->filled('deadline_date')) {
            $deadline = Carbon::parse($this->input('deadline_date').' '.($this->input('deadline_time') ?: '23:59'));

            if ($availableFrom && $deadline->lessThanOrEqualTo($availableFrom)) {
                $validator->errors()->add('deadline_date', 'The examination deadline must be after the availability start date and time.');
            }
        }
    }
}
