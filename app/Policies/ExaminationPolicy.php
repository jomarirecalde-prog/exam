<?php

namespace App\Policies;

use App\Models\Examination;
use App\Models\User;
use App\Services\Examinations\ExaminationAccessService;

class ExaminationPolicy
{
    public function __construct(protected ExaminationAccessService $access)
    {
    }

    public function create(User $user): bool
    {
        return $this->access->canManage($user);
    }

    public function update(User $user, Examination $examination): bool
    {
        return $this->access->canManage($user, $examination);
    }

    public function take(User $user, Examination $examination): bool
    {
        return $this->access->canTake($user, $examination);
    }

    public function viewResult(User $user, Examination $examination): bool
    {
        return $this->access->canViewResult($user, $examination);
    }
}
