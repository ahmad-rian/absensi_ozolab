<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin->value, UserRole::Guru->value, UserRole::OrangTua->value]);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->hasAnyRole([UserRole::Admin->value, UserRole::Guru->value])) {
            return $user->school_id === $attendance->school_id;
        }

        return $user->parentProfile?->id === $attendance->student->parent_profile_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin->value, UserRole::Guru->value]);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->hasAnyRole([UserRole::Admin->value]);
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->hasAnyRole([UserRole::Admin->value]);
    }

    public function export(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin->value, UserRole::Guru->value]);
    }

    public function restore(User $user, Attendance $attendance): bool
    {
        return false;
    }

    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return false;
    }
}
