<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleClassroomCourseLink extends Model
{
    protected $fillable = [
        'user_id',
        'google_course_id',
        'course_name',
        'course_section',
        'instructor_name',
        'subject_offering_id',
        'match_confidence',
        'confirmed',
    ];

    protected function casts(): array
    {
        return [
            'confirmed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectOffering(): BelongsTo
    {
        return $this->belongsTo(SubjectOffering::class, 'subject_offering_id');
    }
}
