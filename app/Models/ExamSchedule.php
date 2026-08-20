<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    protected $fillable = [
        'examination_id',
        'available_from',
        'available_until',
        'published_at',
        'activated_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'published_at' => 'datetime',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }
}
