<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payable;
use App\Models\User;

class PayablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Payable $payable): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Payable $payable): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Payable $payable): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function markAsPaid(User $user, Payable $payable): bool
    {
        return $user->role === UserRole::Admin;
    }
}
