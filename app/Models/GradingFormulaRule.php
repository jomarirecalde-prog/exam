<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class GradingFormulaRule extends Model
{
    protected $fillable = ['grading_formula_id', 'rule_type', 'config', 'priority'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(GradingFormula::class, 'grading_formula_id');
    }
}
