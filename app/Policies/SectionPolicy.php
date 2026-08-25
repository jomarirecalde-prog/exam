<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAcademicManagement;

class SectionPolicy
{
    use AuthorizesAcademicManagement;

    public function delete(User $user, Section $section): bool
    {
        return $this->canManageAcademicRecords($user);
    }
}
