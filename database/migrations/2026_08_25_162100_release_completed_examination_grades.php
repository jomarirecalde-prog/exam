<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('grades')
            ->where('is_released', false)
            ->whereIn('status', ['PASSED', 'FAILED'])
            ->update([
                'is_released' => true,
                'released_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Release state cannot be reliably restored.
    }
};
