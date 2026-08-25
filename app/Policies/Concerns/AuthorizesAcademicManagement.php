<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesAcademicManagement
{
    protected function canManageAcademicRecords(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(['superadmin', 'admin']);
    }

    protected function canRestoreAcademicRecords(User $user): bool
    {
        return $user->is_active && $user->hasRole('superadmin');
    }
}
