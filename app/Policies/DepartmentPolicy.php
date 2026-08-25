<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAcademicManagement;

class DepartmentPolicy
{
    use AuthorizesAcademicManagement;

    public function delete(User $user, Department $department): bool
    {
        return $this->canManageAcademicRecords($user);
    }
}
