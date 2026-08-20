<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Student extends Model
{
    protected $fillable = [
        'user_id', 'student_id', 'program_id', 'year_level_id', 'section_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function yearLevel(): BelongsTo
    {
        return $this->belongsTo(YearLevel::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'student_sections')
            ->withPivot(['academic_year_id', 'semester_id'])
            ->withTimestamps();
    }

    public function accessibleSectionIds(?int $academicYearId = null, ?int $semesterId = null): array
    {
        $ids = [];

        if ($this->section_id) {
            $ids[] = (int) $this->section_id;
        }

        $query = DB::table('student_sections')->where('student_id', $this->id);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return array_values(array_unique(array_merge(
            $ids,
            $query->pluck('section_id')->map(fn ($id) => (int) $id)->all()
        )));
    }
}
