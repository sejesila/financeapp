<?php

namespace App\Policies;

use App\Models\Referrer;
use App\Models\User;

class ReferrerPolicy
{
    /**
     * Referrers aren't user-owned (no user_id column, unlike Account/LoanGiven —
     * the create() query in LoanGivenController pulls Referrer::where('is_active', true)
     * with no user scoping at all). So "view" here just means "any authenticated
     * user may reference any active referrer" — there's no per-user ownership to check.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Referrer $referrer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Referrer $referrer): bool
    {
        return true;
    }

    public function delete(User $user, Referrer $referrer): bool
    {
        return true;
    }
}
