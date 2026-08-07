<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\ClientFund;
use App\Models\ClientFundTransaction;
use App\Models\Transaction;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates a money transfer between two accounts.
 *
 * Responsibilities:
 *   - Enforce transfer-rule validation (account-type constraints)
 *   - Calculate and record any applicable transaction fee
 *   - Create the Transfer record
 *   - Auto-reconcile transfers out of savings that dip into money still
 *     owed to clients, by recording the shortfall as "borrowed" against
 *     the relevant ClientFund(s)
 *   - Trigger balance recalculation on both accounts
 *
 * Throws ValidationException so the controller can let Laravel's normal
 * redirect-with-errors flow handle the response — no HTTP coupling here.
 */
readonly class TransferService
{
    public function __construct(
        private TransferFeeCalculator $feeCalculator,
    )
    {
    }

    // ── Public entry point ────────────────────────────────────────────────────

    /**
     * Execute a transfer.
     *
     * @param Account $from
     * @param Account $to
     * @param float $amount
     * @param string $date
     * @param string|null $description
     * @param float|null $manualFee User-supplied fee override (null = use calculator)
     * @param bool $isClientFund Withdrawal is someone else's money, not personal spend
     * @param bool $isLending Withdrawal is earmarked to lend out, not personal spend
     * @return TransferFee  The fee that was charged (amount may be 0).
     *
     * @throws ValidationException
     */
    public function execute(
        Account $from,
        Account $to,
        float   $amount,
        string  $date,
        ?string $description = null,
        ?float  $manualFee = null,
        bool    $isClientFund = false,
        bool    $isLending = false,
        ?int    $clientFundId = null,
    ): TransferFee
    {
        $this->enforceTransferRules($from, $to, $amount);

        $fee = $this->feeCalculator->calculate($from, $to, $amount);

        if ($manualFee !== null) {
            $fee = $fee->withAmount($manualFee);
        }

        $this->enforceBalanceCheck($from, $amount, $fee);

        $needsReconciliation = ! $isClientFund && $this->hasOutstandingClientFunds($from);

        // Savings accounts often pool the user's own money alongside money
        // still owed to clients (tracked via ClientFund). If this transfer
        // isn't itself flagged as a client fund movement but the amount
        // (plus fee) exceeds what the user actually owns in that account,
        // the shortfall is being funded by client money. Rather than just
        // logging a warning, record it as borrowed against the oldest
        // outstanding client fund(s) so it's reflected in that fund's
        // balance and stays auditable.
        //
        // enforceBalanceCheck() above guarantees amount + fee <= current
        // balance, and current balance = ownedBalance + outstandingTotal,
        // so the shortfall can never exceed outstandingTotal here.
        $outstandingFunds = collect();
        $borrowShortfall  = 0.0;

        if ($from->type === 'savings' && ! $isClientFund) {
            $outstandingFunds = $this->getOutstandingClientFunds($from);
            $outstandingTotal = (float) $outstandingFunds->sum(fn($f) => (float) $f->balance);

            if ($outstandingTotal > 0) {
                $ownedBalance    = (float) $from->current_balance - $outstandingTotal;
                $totalDeduction  = $amount + $fee->amount;
                $borrowShortfall = min(max(0, $totalDeduction - $ownedBalance), $outstandingTotal);
            }
        }

        DB::transaction(function () use (
            $from, $to, $amount, $date, $description, $fee,
            $isClientFund, $isLending, $clientFundId, $needsReconciliation,
            $outstandingFunds, $borrowShortfall,
        ) {
            $isInterestGated = $to->type === 'savings'
                && stripos($to->name, 'etica') !== false;

            $valueDate = $isInterestGated
                ? KenyanBusinessDays::nextBusinessDay(Carbon::parse($date))->format('Y-m-d')
                : null;

            $transfer = Transfer::create([
                'from_account_id'       => $from->id,
                'to_account_id'         => $to->id,
                'amount'                => $amount,
                'date'                  => $date,
                'value_date'            => $valueDate,
                'description'           => $description,
                'user_id'               => Auth::id(),
                'is_client_fund'        => $isClientFund,
                'is_lending'            => $isLending,
                'client_fund_id'        => $clientFundId,
                // A savings shortfall is auto-resolved by the borrow logic
                // below, so it never needs manual reconciliation.
                'needs_reconciliation'  => $from->type === 'savings' ? false : $needsReconciliation,
            ]);

            if ($fee->isCharged()) {
                $this->recordFeeTransaction($from, $to, $date, $fee, $description, $transfer);
            }

            if ($borrowShortfall > 0) {
                $this->recordBorrowedFromClientFunds(
                    $outstandingFunds, $borrowShortfall, $date, $to, $description, $transfer
                );
            }

            $from->updateBalance();
            $to->updateBalance();
        });

        if ($from->type !== 'savings' && $needsReconciliation) {
            Log::warning('TransferService: transfer moved money out of an account with outstanding client funds — not flagged as client fund, needs manual reconciliation', [
                'user_id'         => Auth::id(),
                'from_account_id' => $from->id,
                'amount'          => $amount,
            ]);
        } elseif ($borrowShortfall > 0) {
            Log::info('TransferService: transfer partially funded by borrowing against outstanding client fund(s)', [
                'user_id'          => Auth::id(),
                'from_account_id'  => $from->id,
                'amount'           => $amount,
                'amount_borrowed'  => $borrowShortfall,
            ]);
        }

        return $fee;
    }

    // ── Client fund safety check ────────────────────────────────────────────

    /**
     * Mirrors TransferRecorder::attemptClientFundAutoMatch()'s outstanding-
     * balance check for the manual transfer form. Doesn't block the transfer
     * — same warn-and-allow behavior as the webhook path — just flags it so
     * it surfaces for review rather than silently drifting the way the
     * Sanlam MMF client funds did.
     */
    private function hasOutstandingClientFunds(Account $from): bool
    {
        return ClientFund::where('user_id', Auth::id())
            ->where('account_id', $from->id)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['cancelled'])
            ->exists();
    }

    /**
     * Outstanding (unfinished) client funds currently sitting in this
     * account, oldest first — used both to size a potential borrow and to
     * decide which fund(s) absorb it (FIFO).
     */
    private function getOutstandingClientFunds(Account $from): Collection
    {
        return ClientFund::where('user_id', Auth::id())
            ->where('account_id', $from->id)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('received_date')
            ->get();
    }

    /**
     * Apply a shortfall against outstanding client fund(s), oldest first,
     * recording each portion as a "borrowed" ClientFundTransaction. This
     * reduces the fund's balance exactly like a real expense would (so
     * reporting/reconciliation stays accurate) but is tagged is_borrowed
     * and linked to the Transfer rather than a Transaction, since the
     * money already left the account via the transfer itself.
     */
    private function recordBorrowedFromClientFunds(
        Collection $outstandingFunds,
        float      $shortfall,
        string     $date,
        Account    $to,
        ?string    $description,
        Transfer   $transfer,
    ): void
    {
        $remaining = $shortfall;

        foreach ($outstandingFunds as $fund) {
            if ($remaining <= 0) {
                break;
            }

            $portion = min($remaining, (float) $fund->balance);
            if ($portion <= 0) {
                continue;
            }

            $fund->amount_spent += $portion;
            $fund->updateBalance();

            ClientFundTransaction::create([
                'client_fund_id' => $fund->id,
                'transaction_id' => null,
                'transfer_id'    => $transfer->id,
                'type'           => 'expense',
                'is_borrowed'    => true,
                'amount'         => $portion,
                'date'           => $date,
                'description'    => "Borrowed for personal transfer to {$to->name}"
                    . ($description ? " ({$description})" : ''),
            ]);

            $remaining -= $portion;
        }
    }

    // ── Transfer rule validation ───────────────────────────────────────────────

    /**
     * Enforce account-type transfer rules.
     *
     * Rules:
     *   1. Cash → Savings  : blocked (go through mobile money or bank first)
     *   2. Savings → *     : only Cash, M-Pesa, Airtel Money, or Bank allowed
     *   3. Bank → Savings  : allowed (direct bank-to-savings deposit)
     *   4. M-Pesa minimum withdrawal enforced
     */
    private function enforceTransferRules(Account $from, Account $to, float $amount): void
    {
        // Rule: Cash cannot transfer directly to Savings
        if ($from->type === 'cash' && $to->type === 'savings') {
            throw ValidationException::withMessages([
                'to_account_id' => 'Direct transfers from cash to savings accounts are not allowed. Please transfer to M-Pesa, Airtel Money, or Bank first.',
            ]);
        }

        // Rule: Savings can only transfer to Cash, mobile money, or Bank
        if ($from->type === 'savings') {
            $allowed = ['cash', 'mpesa', 'airtel_money', 'bank','referrer_float'];
            if (!in_array($to->type, $allowed)) {
                throw ValidationException::withMessages([
                    'to_account_id' => 'Savings accounts can only transfer to Cash, M-Pesa, Airtel Money, or Bank accounts.',
                ]);
            }
        }

        // Rule: M-Pesa minimum withdrawal
        if ($from->type === 'mpesa' && $to->type === 'cash' && $amount < 50) {
            throw ValidationException::withMessages([
                'amount' => 'Minimum M-Pesa withdrawal amount is KES 50.',
            ]);
        }

        // Bank → Savings is explicitly allowed — no rule needed, falls through cleanly.
    }

    // ── Balance check ─────────────────────────────────────────────────────────

    private function enforceBalanceCheck(Account $from, float $amount, TransferFee $fee): void
    {
        $total = $amount + $fee->amount;

        if ($from->current_balance < $total) {
            throw ValidationException::withMessages([
                'amount' => "Insufficient balance in {$from->name}. "
                    . "Current balance: " . number_format($from->current_balance, 0, '.', ',')
                    . ", Required: " . number_format($total, 2, '.', ',')
                    . " (Transfer: " . number_format($amount, 0, '.', ',')
                    . " + Fee: " . number_format($fee->amount, 2, '.', ',') . ")",
            ]);
        }
    }
    // ── Client fund safety check ────────────────────────────────────────────

    /**
     * Block transfers out of an account holding outstanding client funds
     * unless the transfer is explicitly flagged as a client fund movement.
     * Without this, money can silently leave an account that ClientFund
     * records still believe is holding someone else's cash — the exact
     * drift that later requires manual reconciliation in reports (see
     * ReportDataService::getClientFundsBalanceAsAt(), which can only trace
     * a fund's location through Transfer rows explicitly tagged to it).
     *
     * Currently unused for savings accounts (the borrow logic above
     * auto-resolves the shortfall instead of blocking); left available for
     * account types where you'd rather hard-stop than auto-reconcile.
     */
    private function enforceClientFundSafety(Account $from, bool $isClientFund): void
    {
        if ($isClientFund) {
            return; // user already told us this transfer is a client fund movement
        }

        $hasOutstanding = ClientFund::where('user_id', Auth::id())
            ->where('account_id', $from->id)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($hasOutstanding) {
            throw ValidationException::withMessages([
                'is_client_fund' => "{$from->name} is holding outstanding client funds. "
                    . "Confirm whether this transfer includes client money by checking "
                    . "'This is a client fund' before continuing — otherwise the client "
                    . "fund balance will drift out of sync with this account.",
            ]);
        }
    }

    // ── Fee transaction ───────────────────────────────────────────────────────

    private function recordFeeTransaction(
        Account     $from,
        Account     $to,
        string      $date,
        TransferFee $fee,
        ?string     $userDescription,
        Transfer    $transfer,
    ): void
    {
        $feeCategory = Category::firstOrCreate(
            ['user_id' => Auth::id(), 'name' => 'Transaction Fees', 'parent_id' => null],
            ['type' => 'expense', 'icon' => '💸', 'is_active' => true],
        );

        Transaction::create([
            'user_id' => Auth::id(),
            'date' => $date,
            'description' => $userDescription
                ? "{$from->name} to {$to->name} fee: {$userDescription}"
                : "{$from->name} to {$to->name} fee",
            'amount' => $fee->amount,
            'category_id' => $feeCategory->id,
            'account_id' => $from->id,
            'payment_method' => match ($from->type) {
                'mpesa' => 'Mpesa',
                'airtel_money' => 'Airtel Money',
                'bank' => 'Bank',
                default => 'Cash',
            },
            'is_transaction_fee' => true,
            'transfer_id' => $transfer->id,
        ]);
    }
}
