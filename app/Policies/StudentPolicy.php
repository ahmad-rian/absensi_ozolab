<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin->value, UserRole::Guru->value, UserRole::OrangTua->value]);
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->hasAnyRole([UserRole::Admin->value, UserRole::Guru->value])) {
            return $user->school_id === $student->school_id;
        }

        return $user->parentProfile?->id === $student->parent_profile_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([UserRole::Admin->value]);
    }

    public function update(User $user, Student $student): bool
    {
        if (! $user->hasAnyRole([UserRole::Admin->value])) {
            return false;
        }

        return $user->school_id === $student->school_id;
    }

    public function delete(User $user, Student $student): bool
    {
        if (! $user->hasAnyRole([UserRole::Admin->value])) {
            return false;
        }

        return $user->school_id === $student->school_id;
    }

    public function restore(User $user, Student $student): bool
    {
        if (! $user->hasAnyRole([UserRole::Admin->value])) {
            return false;
        }

        return $user->school_id === $student->school_id;
    }

    public function forceDelete(User $user, Student $student): bool
    {
        return false;
    }
}
