<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;

class UserLoginGuard
{
    public static function webLoginError(User $user): ?string
    {
        if ($user->role === 'staff') {
            return 'Staff accounts must use the POS app.';
        }

        if ($user->role === 'manager' && !self::managerHasAssignedBranch($user)) {
            return 'Your manager account is not assigned to a branch yet. Contact an administrator.';
        }

        return null;
    }

    public static function apiLoginError(User $user): ?string
    {
        if ($user->role === 'manager' && !self::managerHasAssignedBranch($user)) {
            return 'Your manager account is not assigned to a branch yet. Contact an administrator.';
        }

        return null;
    }

    public static function managerHasAssignedBranch(User $user): bool
    {
        return Branch::query()
            ->where('manager_user_id', $user->id)
            ->exists();
    }
}
