<?php

namespace App\Support;

use App\Models\Program;
use App\Models\YearLevel;

class YearLevelDefaults
{
    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return [
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
        ];
    }

    public static function ensureForProgram(Program $program): void
    {
        foreach (self::labels() as $level => $name) {
            YearLevel::query()->updateOrCreate(
                [
                    'program_id' => $program->id,
                    'level' => $level,
                ],
                [
                    'name' => $name,
                    'is_active' => true,
                ]
            );
        }
    }

    public static function ensureForAllPrograms(): void
    {
        Program::query()->each(fn (Program $program) => self::ensureForProgram($program));
    }
}
