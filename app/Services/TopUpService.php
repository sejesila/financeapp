<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    // ── Category resolution ───────────────────────────────────────────────────

    /**
     * Returns [Collection $categories, bool $showSaccoDividends].
     */
    public function getCategories(string $accountType): array
    {
        $excluded = self::EXCLUDED;

        $showSaccoDividends = $this->resolveSaccoDividends();

        if (!$showSaccoDividends) {
            $excluded[] = 'Sacco Dividends';
        }

        $query = Category::where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotIn('name', $excluded)
            ->whereNotIn('name', self::EXCLUDED_PARENTS)
            ->whereNotNull('parent_id');

        $categories = match ($accountType) {
            'bank'         => $query->where('type', 'income')->where('name', 'Salary')->orderBy('name')->get(),
            'savings'      => $query->where('type', 'income')->orderBy('name')->get(),
            'airtel_money' => $query->where('type', 'income')->where('name', '!=', 'Salary')->orderBy('name')->get(),
            'mpesa'        => $query->where(function ($q) {
                $q->where(function ($s) {
                    $s->where('type', 'income')->where('name', '!=', 'Salary');
                })->orWhere('type', 'liability');
            })->orderBy('name')->get(),
            default        => $query->whereIn('type', ['income', 'liability'])->orderBy('name')->get(),
        };

        return [$categories, $showSaccoDividends];
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Validate that the chosen category is allowed for this account and user.
     * Throws ValidationException (rendered as redirect-with-errors by Laravel)
     * or returns a session-error string for cases the controller handles via
     * redirect()->back()->with('error', ...).
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

        // Sacco Dividends window + once-per-year guard
        if ($category->name === 'Sacco Dividends') {
            if (!$this->inSaccoWindow()) {
                return 'Sacco Dividends can only be recorded between 10 April and 10 May.';
            }
            if ($this->saccoAlreadyUsed()) {
                return 'Sacco Dividends have already been recorded for this year.';
            }
        }

        // Bank accounts: income must be Salary only
        if ($account->type === 'bank' && $category->type === 'income' && $category->name !== 'Salary') {
            return 'Only Salary income is allowed for bank accounts.';
        }

        return null; // all good
    }

    // ── Sacco Dividends helpers ───────────────────────────────────────────────

    /**
     * Resolves Sacco Dividends visibility:
     * true  = within the 10 Apr – 10 May window AND not yet used this year.
     * false = outside the window, or already used.
     */
    private function resolveSaccoDividends(): bool
    {
        return $this->inSaccoWindow() && !$this->saccoAlreadyUsed();
    }

    private function inSaccoWindow(): bool
    {
        $today = now();
        $start = $today->copy()->setDate($today->year, 4, 10);
        $end   = $today->copy()->setDate($today->year, 5, 10);
        return $today->between($start, $end);
    }

    private function saccoAlreadyUsed(): bool
    {
        $ids = Category::where('user_id', Auth::id())
            ->where('name', 'Sacco Dividends')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return false;
        }

        return DB::table('transactions')
            ->where('user_id', Auth::id())
            ->whereIn('category_id', $ids)
            ->whereYear('date', now()->year)
            ->whereNull('deleted_at')
            ->exists();
    }
}
