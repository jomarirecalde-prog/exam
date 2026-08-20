<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = [
        'program_id', 'year_level_id', 'academic_year_id', 'semester_id',
        'name', 'code', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function yearLevel(): BelongsTo
    {
        return $this->belongsTo(YearLevel::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function examinations(): BelongsToMany
    {
        return $this->belongsToMany(Examination::class, 'examination_sections')
            ->using(ExaminationSection::class)
            ->withTimestamps();
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_section')
            ->withPivot(['academic_year_id', 'semester_id'])
            ->withTimestamps();
    }

    public function displayName(): string
    {
        return $this->name ?: (string) $this->code;
    }
}
