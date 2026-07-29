<?php

namespace App\Policies;

use App\Models\LoanGiven;
use App\Models\User;

class LoanGivenPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LoanGiven $loanGiven): bool
    {
        return $user->id === $loanGiven->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function makePayment(User $user, LoanGiven $loanGiven): bool
    {
        return $user->id === $loanGiven->user_id;
    }

    public function delete(User $user, LoanGiven $loanGiven): bool
    {
        return $user->id === $loanGiven->user_id;
    }
}
