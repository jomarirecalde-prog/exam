<?php

namespace App\Models;

use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Examination extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'code', 'subject_id', 'section_id', 'instructor_id',
        'academic_year_id', 'semester_id', 'examination_period', 'title',
        'description', 'instructions', 'duration_minutes', 'total_items',
        'passing_score', 'passing_percentage', 'examination_date',
        'start_time', 'end_time', 'status', 'needs_section_review', 'current_version',
    ];

    protected function casts(): array
    {
        return [
            'examination_period' => ExaminationPeriod::class,
            'status' => ExamStatus::class,
            'examination_date' => 'date',
            'passing_score' => 'decimal:2',
            'passing_percentage' => 'decimal:2',
            'needs_section_review' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Examination $examination) {
            $examination->uuid ??= (string) Str::uuid();
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'examination_sections')
            ->using(ExaminationSection::class)
            ->withTimestamps()
            ->orderBy('name');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(ExaminationSetting::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(ExamSchedule::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ExaminationVersion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExaminationAttempt::class);
    }

    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExaminationQuestion::class)->orderBy('order');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function statusKey(): string
    {
        $status = $this->status;

        return strtolower(is_object($status) ? $status->value : (string) $status);
    }

    public function periodLabel(): string
    {
        $period = $this->examination_period;

        return $period instanceof ExaminationPeriod
            ? $period->label()
            : (string) $period;
    }

    public function assignedSectionNames(): array
    {
        return $this->sections
            ->map(fn (Section $section) => $section->name ?: $section->code)
            ->filter()
            ->values()
            ->all();
    }

    public function scopeAssignedToSection(Builder $query, int $sectionId): Builder
    {
        return $query->whereHas('sections', function (Builder $query) use ($sectionId) {
            $query->where('sections.id', $sectionId);
        });
    }

    public function scopeAssignedToSections(Builder $query, array $sectionIds): Builder
    {
        $sectionIds = array_values(array_filter(array_map('intval', $sectionIds)));

        if ($sectionIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('sections', function (Builder $query) use ($sectionIds) {
            $query->whereIn('sections.id', $sectionIds);
        });
    }

    public function scopeVisibleToStudent(Builder $query, Student $student): Builder
    {
        return $query
            ->where('needs_section_review', false)
            ->whereIn('status', [
                ExamStatus::Published,
                ExamStatus::Scheduled,
                ExamStatus::Active,
            ])
            ->assignedToSections($student->accessibleSectionIds());
    }

    public function isAssignedToSection(int $sectionId): bool
    {
        if ($this->relationLoaded('sections')) {
            return $this->sections->contains('id', $sectionId);
        }

        return $this->sections()->where('sections.id', $sectionId)->exists();
    }
}
