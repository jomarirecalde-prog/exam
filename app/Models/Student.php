<?php

namespace App\Models;

use App\Enums\StudentRegistrationStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'student_id',
        'phone',
        'sex',
        'date_of_birth',
        'home_address',
        'program_id',
        'year_level_id',
        'section_id',
        'is_active',
        'registration_status',
        'registered_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'date_of_birth' => 'date',
            'registered_at' => 'datetime',
            'approved_at' => 'datetime',
            'registration_status' => StudentRegistrationStatus::class,
        ];
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function displayName(): string
    {
        return $this->user?->fullName() ?: $this->user?->name ?: $this->student_id;
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'student_sections')
            ->withPivot(['academic_year_id', 'semester_id'])
            ->withTimestamps();
    }

    public function subjectEnrollments(): HasMany
    {
        return $this->hasMany(StudentSubject::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'student_subjects')
            ->withPivot(['academic_year_id', 'semester_id', 'status', 'verified_at', 'verified_by', 'rejection_reason'])
            ->withTimestamps();
    }

    public function subjectChangeRequests(): HasMany
    {
        return $this->hasMany(StudentSubjectChangeRequest::class);
    }

    public function isRegistrationPending(): bool
    {
        return $this->registration_status === StudentRegistrationStatus::Pending;
    }

    public function isRegistrationApproved(): bool
    {
        return $this->registration_status === StudentRegistrationStatus::Approved;
    }

    public function isRegistrationRejected(): bool
    {
        return $this->registration_status === StudentRegistrationStatus::Rejected;
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
