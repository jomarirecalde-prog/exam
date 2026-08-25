<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamReactivationLog extends Model
{
    protected $fillable = [
        'examination_attempt_id',
        'reactivated_by',
        'reactivation_reason',
        'warning_mode',
        'previous_warning_count',
        'new_warning_count',
        'reactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'reactivated_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExaminationAttempt::class, 'examination_attempt_id');
    }

    public function reactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }
}
