<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAcademicManagement;

class StudentPolicy
{
    use AuthorizesAcademicManagement;

    public function delete(User $user, Student $student): bool
    {
        return $this->canManageAcademicRecords($user);
    }

    public function restore(User $user, Student $student): bool
    {
        return $this->canRestoreAcademicRecords($user);
    }

    public function forceDelete(User $user, Student $student): bool
    {
        return $this->canRestoreAcademicRecords($user);
    }
}
