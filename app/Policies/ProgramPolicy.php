<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAcademicManagement;

class ProgramPolicy
{
    use AuthorizesAcademicManagement;

    public function delete(User $user, Program $program): bool
    {
        return $this->canManageAcademicRecords($user);
    }
}
