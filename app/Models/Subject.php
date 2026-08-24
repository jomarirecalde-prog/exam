<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
}
