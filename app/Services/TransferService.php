<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates a money transfer between two accounts.
 *
 * Responsibilities:
 *   - Enforce transfer-rule validation (account-type constraints)
 *   - Calculate and record any applicable transaction fee
 *   - Create the Transfer record
 *   - Trigger balance recalculation on both accounts
 *
 * Throws ValidationException so the controller can let Laravel's normal
 * redirect-with-errors flow handle the response — no HTTP coupling here.
 */
readonly class TransferService
{
    public function __construct(
        private TransferFeeCalculator $feeCalculator,
    ) {}

    // ── Public entry point ────────────────────────────────────────────────────

    /**
     * Execute a transfer.
     *
     * @param  Account $from
     * @param  Account $to
     * @param  float   $amount
     * @param  string  $date
     * @param  string|null $description
     * @return TransferFee   The fee that was charged (amount may be 0).
     *
     * @throws ValidationException
     */
    public function execute(
        Account $from,
        Account $to,
        float   $amount,
        string  $date,
        ?string $description = null,
    ): TransferFee {
        $this->enforceTransferRules($from, $to, $amount);

        $fee = $this->feeCalculator->calculate($from, $to, $amount);

        $this->enforceBalanceCheck($from, $amount, $fee);

        DB::transaction(function () use ($from, $to, $amount, $date, $description, $fee) {
            Transfer::create([
                'from_account_id' => $from->id,
                'to_account_id'   => $to->id,
                'amount'          => $amount,
                'date'            => $date,
                'description'     => $description,
                'user_id'         => Auth::id(),
            ]);

            if ($fee->isCharged()) {
                $this->recordFeeTransaction($from, $date, $fee, $description);
            }

            $from->updateBalance();
            $to->updateBalance();
        });

        return $fee;
    }

    // ── Transfer rule validation ───────────────────────────────────────────────

    /**
     * Enforce account-type transfer rules.
     *
     * Rules:
     *   1. Cash → Savings : blocked (go through mobile money first)
     *   2. Savings → Bank or Savings : blocked
     *
     * Throws ValidationException with the appropriate field/message pair.
     */
    private function enforceTransferRules(Account $from, Account $to, float $amount): void
    {
        // Rule: Cash cannot transfer directly to Savings
        if ($from->type === 'cash' && $to->type === 'savings') {
            throw ValidationException::withMessages([
                'to_account_id' => 'Direct transfers from cash to savings accounts are not allowed. Please transfer to M-Pesa or Airtel Money first.',
            ]);
        }

        // Rule: Savings can only transfer to Cash / mobile money
        if ($from->type === 'savings') {
            $allowed = ['cash', 'mpesa', 'airtel_money'];
            if (!in_array($to->type, $allowed)) {
                throw ValidationException::withMessages([
                    'to_account_id' => 'Savings accounts can only transfer to Cash, M-Pesa, or Airtel Money accounts.',
                ]);
            }
        }

        // Rule: M-Pesa minimum withdrawal
        if ($from->type === 'mpesa' && $to->type === 'cash' && $amount < 50) {
            throw ValidationException::withMessages([
                'amount' => 'Minimum M-Pesa withdrawal amount is KES 50.',
            ]);
        }
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

    // ── Fee transaction ───────────────────────────────────────────────────────

    private function recordFeeTransaction(
        Account    $from,
        string     $date,
        TransferFee $fee,
        ?string    $userDescription,
    ): void {
        $feeCategory = Category::firstOrCreate(
            ['user_id' => Auth::id(), 'name' => 'Transaction Fees', 'parent_id' => null],
            ['type' => 'expense', 'icon' => '💸', 'is_active' => true],
        );

        Transaction::create([
            'user_id'            => Auth::id(),
            'date'               => $date,
            'description'        => $userDescription
                ? "{$fee->description}: {$userDescription}"
                : $fee->description,
            'amount'             => $fee->amount,
            'category_id'        => $feeCategory->id,
            'account_id'         => $from->id,
            'payment_method'     => match ($from->type) {
                'mpesa'          => 'Mpesa',
                'airtel_money'   => 'Airtel Money',
                'bank'           => 'Bank',
                default          => 'Cash',
            },
            'is_transaction_fee' => true,
        ]);
    }
}
