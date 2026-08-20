<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class GradingFormula extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(GradingFormulaRule::class);
    }
}
