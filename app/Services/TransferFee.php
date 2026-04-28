<?php

namespace App\Services;

/**
 * Value object returned by TransferFeeCalculator.
 * Carries the fee amount, type label, and a human-readable success suffix.
 */
readonly class TransferFee
{
    public function __construct(
        public float   $amount,
        public ?string $type,        // 'withdrawal' | 'paybill' | 'atm' | null
        public string  $description,
    ) {}

    public function isCharged(): bool
    {
        return $this->amount > 0;
    }

    public function successSuffix(): string
    {
        if (!$this->isCharged()) {
            return '';
        }

        $label = match ($this->type) {
            'withdrawal' => 'Withdrawal',
            'paybill'    => 'PayBill',
            'atm'        => 'ATM Withdrawal',
            default      => 'Transaction',
        };

        return " ({$label} fee: KES " . number_format($this->amount, 2, '.', ',') . ')';
    }
}
