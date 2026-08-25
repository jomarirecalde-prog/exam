<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAcademicManagement;

class SubjectPolicy
{
    use AuthorizesAcademicManagement;

    public function delete(User $user, Subject $subject): bool
    {
        return $this->canManageAcademicRecords($user);
    }
}
