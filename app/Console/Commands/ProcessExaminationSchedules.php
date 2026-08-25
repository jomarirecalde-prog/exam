<?php

namespace App\Console\Commands;

use App\Services\Examinations\ExaminationEndService;
use App\Services\Examinations\ExaminationScheduleService;
use Illuminate\Console\Command;

class ProcessExaminationSchedules extends Command
{
    protected $signature = 'examinations:process-schedules';

    protected $description = 'Activate scheduled examinations and enforce examination deadlines';

    public function handle(
        ExaminationScheduleService $schedule,
        ExaminationEndService $end,
    ): int {
        $activated = $schedule->activateScheduledExaminations();
        $endedAttempts = $end->processDueDeadlines();

        $this->info("Activated {$activated} scheduled examination(s).");
        $this->info("Processed deadline closures affecting {$endedAttempts} active attempt(s).");

        return self::SUCCESS;
    }
}
