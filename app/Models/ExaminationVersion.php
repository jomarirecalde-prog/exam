<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ExaminationVersion extends Model
{
    protected $fillable = ['examination_id', 'version_number', 'snapshot', 'created_by'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExaminationQuestion::class);
    }
}
