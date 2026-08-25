<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['department_id', 'code', 'name', 'description', 'units', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'subject_section')
            ->withPivot(['academic_year_id', 'semester_id'])
            ->withTimestamps();
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(Instructor::class, 'subject_instructor')
            ->withPivot(['section_id', 'academic_year_id', 'semester_id'])
            ->withTimestamps();
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentSubject::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_subjects')
            ->withPivot(['academic_year_id', 'semester_id', 'status', 'verified_at', 'verified_by', 'rejection_reason'])
            ->withTimestamps();
    }
}
