<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any loans.
     */
    public function viewAny(User $user)
    {
        return true; // All authenticated users can view their own loans list
    }

    /**
     * Determine whether the user can view the loan.
     */
    public function view(User $user, Loan $loan)
    {
        return $user->id === $loan->user_id;
    }

    /**
     * Determine whether the user can create loans.
     */
    public function create(User $user)
    {
        return true; // All authenticated users can create loans
    }

    /**
     * Determine whether the user can update the loan.
     */
    public function update(User $user, Loan $loan)
    {
        return $user->id === $loan->user_id && $loan->status === 'active';
    }

    /**
     * Determine whether the user can delete the loan.
     */
    public function delete(User $user, Loan $loan)
    {
        return $user->id === $loan->user_id
            && $loan->status === 'active'
            && $loan->amount_paid == 0;
    }

    /**
     * Determine whether the user can make payments on the loan.
     */
    public function makePayment(User $user, Loan $loan)
    {
        return $user->id === $loan->user_id && $loan->status === 'active';
    }
}
