<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    /**
     * Allow any authenticated user to view their accounts list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Allow a user to view an account if they own it.
     */
    public function view(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    /**
     * Allow a user to create accounts.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Allow a user to update their own account.
     */
    public function update(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    /**
     * Allow a user to delete their own account.
     */
    public function delete(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function restore(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function forceDelete(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }
}
