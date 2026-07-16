<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

/**
 * Handles top-up category resolution and top-up transaction creation.
 *
 * Extracted from AccountController to keep business rules out of HTTP layer.
 */
class TopUpService
{
    // System-reserved category names that can never be used for a manual top-up.
    private const EXCLUDED = [
        'Loan Receipt', 'Loan Repayment', 'Excise Duty',
        'Loan Fees Refund', 'Facility Fee Refund',
        'Balance Adjustment', 'Rolling Funds',
    ];

    private const EXCLUDED_PARENTS = ['Income', 'Loans'];

    // Income categories allowed for bank account top-ups.
    private const BANK_ALLOWED_INCOME = ['Salary', 'Side Income'];

    // ── Category resolution ───────────────────────────────────────────────────

    /**
     * Returns [Collection $categories, bool $showSaccoDividends].
     */
    public function getCategories(string $accountType): array
    {
        $excluded = self::EXCLUDED;

        $query = Category::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotIn('name', $excluded)
            ->whereNotIn('name', self::EXCLUDED_PARENTS)
            ->whereNotNull('parent_id');

        $categories = match ($accountType) {
            'bank'         => $query->where('type', 'income')
                ->whereIn('name', self::BANK_ALLOWED_INCOME)
                ->orderBy('name')
                ->get(),
            'savings'      => $query->where('type', 'income')->orderBy('name')->get(),
            'airtel_money' => $query->where('type', 'income')->where('name', '!=', 'Salary')->orderBy('name')->get(),
            'mpesa'        => $query->where(function ($q) {
                $q->where(function ($s) {
                    $s->where('type', 'income')->where('name', '!=', 'Salary');
                })->orWhere('type', 'liability');
            })->orderBy('name')->get(),
            default        => $query->whereIn('type', ['income', 'liability'])->orderBy('name')->get(),
        };

        return $categories;
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Validate that the chosen category is allowed for this account and user.
     *
     * Returns null on success, or an error string that the controller should
     * pass to session('error').
     */
    public function validateCategory(Account $account, Category $category): ?string
    {
        // Must belong to authenticated user
        if ($category->user_id !== Auth::id()) {
            return 'Please select a valid category.';
        }

        // System-reserved guard
        if (in_array($category->name, self::EXCLUDED)) {
            return 'This category is reserved for system use only.';
        }


        // Bank accounts: only allow explicitly permitted income categories
        if ($account->type === 'bank'
            && $category->type === 'income'
            && !in_array($category->name, self::BANK_ALLOWED_INCOME)
        ) {
            $allowed = implode(' or ', self::BANK_ALLOWED_INCOME);
            return "Only {$allowed} income is allowed for bank accounts.";
        }

        return null; // all good
    }

}
