<?php

use App\Support\YearLevelDefaults;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        YearLevelDefaults::ensureForAllPrograms();
    }

    public function down(): void
    {
        // Year levels may already be referenced by sections and students.
    }
};
