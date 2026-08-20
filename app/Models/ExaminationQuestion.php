<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ExaminationQuestion extends Model
{
    protected $fillable = [
        'examination_id', 'examination_version_id', 'question_id', 'order', 'points_override',
    ];

    protected function casts(): array
    {
        return ['points_override' => 'decimal:2'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
