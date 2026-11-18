<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Receivable;
use App\Models\User;

class ReceivablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Receivable $receivable): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Receivable $receivable): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Receivable $receivable): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function markAsReceived(User $user, Receivable $receivable): bool
    {
        return $user->role === UserRole::Admin;
    }
}
