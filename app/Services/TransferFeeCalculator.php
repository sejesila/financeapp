<?php

namespace App\Services;

use App\Models\Account;

/**
 * Calculates the transaction fee (if any) for a transfer between two accounts.
 *
 * Rules:
 *   - M-Pesa / Airtel Money → Cash  : withdrawal fee (tiered)
 *   - M-Pesa → Bank                 : PayBill fee (tiered, M-Pesa schedule)
 *   - Airtel Money → Bank           : PayBill fee (free — Airtel schedule)
 *   - Bank → Cash                   : flat ATM fee  (KES 33 + 15% excise = 37.95)
 *   - Everything else               : no fee
 */
class TransferFeeCalculator
{
    // ── Public entry point ────────────────────────────────────────────────────

    public function calculate(Account $from, Account $to, float $amount): TransferFee
    {
        $isMobileMoney = in_array($from->type, ['mpesa', 'airtel_money']);
        $isBankToCash  = $from->type === 'bank' && $to->type === 'cash';

        if ($isMobileMoney && $to->type === 'cash') {
            $fee  = $this->withdrawalFee($amount, $from->type);
            $desc = $this->feeDescription($from, $to, 'withdrawal');
            return new TransferFee($fee, 'withdrawal', $desc);
        }

        if ($isMobileMoney && $to->type === 'bank') {
            $fee  = $this->payBillFee($amount, $from->type);
            $desc = $this->feeDescription($from, $to, 'paybill');
            return new TransferFee($fee, 'paybill', $desc);
        }

        if ($isBankToCash) {
            $fee  = $this->atmFee();
            $desc = $this->feeDescription($from, $to, 'atm');
            return new TransferFee($fee, 'atm', $desc);
        }

        return new TransferFee(0, null, '');
    }

    // ── Fee schedules ─────────────────────────────────────────────────────────

    private function withdrawalFee(float $amount, string $accountType): float
    {
        $tiers = [
            ['min' => 50,    'max' => 100,    'cost' => 11],
            ['min' => 101,   'max' => 500,    'cost' => 29],
            ['min' => 501,   'max' => 1000,   'cost' => 29],
            ['min' => 1001,  'max' => 1500,   'cost' => 29],
            ['min' => 1501,  'max' => 2500,   'cost' => 29],
            ['min' => 2501,  'max' => 3500,   'cost' => 52],
            ['min' => 3501,  'max' => 5000,   'cost' => 69],
            ['min' => 5001,  'max' => 7500,   'cost' => 87],
            ['min' => 7501,  'max' => 10000,  'cost' => 115],
            ['min' => 10001, 'max' => 15000,  'cost' => 167],
            ['min' => 15001, 'max' => 20000,  'cost' => 185],
            ['min' => 20001, 'max' => 35000,  'cost' => 197],
            ['min' => 35001, 'max' => 50000,  'cost' => 278],
            ['min' => 50001, 'max' => 250000, 'cost' => 309],
        ];

        if (!in_array($accountType, ['mpesa', 'airtel_money'])) {
            return 0;
        }

        return $this->lookupTier($tiers, $amount);
    }

    private function payBillFee(float $amount, string $accountType): float
    {
        if ($accountType === 'mpesa') {
            $tiers = [
                ['min' => 1,     'max' => 49,     'cost' => 0],
                ['min' => 50,    'max' => 100,    'cost' => 0],
                ['min' => 101,   'max' => 500,    'cost' => 5],
                ['min' => 501,   'max' => 1000,   'cost' => 10],
                ['min' => 1001,  'max' => 1500,   'cost' => 15],
                ['min' => 1501,  'max' => 2500,   'cost' => 20],
                ['min' => 2501,  'max' => 3500,   'cost' => 25],
                ['min' => 3501,  'max' => 5000,   'cost' => 34],
                ['min' => 5001,  'max' => 7500,   'cost' => 42],
                ['min' => 7501,  'max' => 10000,  'cost' => 48],
                ['min' => 10001, 'max' => 15000,  'cost' => 57],
                ['min' => 15001, 'max' => 20000,  'cost' => 62],
                ['min' => 20001, 'max' => 25000,  'cost' => 67],
                ['min' => 25001, 'max' => 30000,  'cost' => 72],
                ['min' => 30001, 'max' => 35000,  'cost' => 83],
                ['min' => 35001, 'max' => 40000,  'cost' => 99],
                ['min' => 40001, 'max' => 45000,  'cost' => 103],
                ['min' => 45001, 'max' => 50000,  'cost' => 108],
                ['min' => 50001, 'max' => 250000, 'cost' => 108],
            ];
        } elseif ($accountType === 'airtel_money') {
            $tiers = [['min' => 1, 'max' => 150000, 'cost' => 0]];
        } else {
            return 0;
        }

        return $this->lookupTier($tiers, $amount);
    }

    /**
     * ATM withdrawal fee: KES 33 base + 15% excise duty = KES 37.95 flat.
     */
    private function atmFee(): float
    {
        $base = 33.00;
        return round($base + ($base * 0.15), 2);
    }

    private function lookupTier(array $tiers, float $amount): float
    {
        foreach ($tiers as $tier) {
            if ($amount >= $tier['min'] && $amount <= $tier['max']) {
                return $tier['cost'];
            }
        }
        return (float) (end($tiers)['cost'] ?? 0);
    }

    // ── Description ───────────────────────────────────────────────────────────

    private function feeDescription(Account $from, Account $to, string $feeType): string
    {
        $prefix = match ($feeType) {
            'withdrawal' => ($from->type === 'mpesa' ? 'M-Pesa' : 'Airtel Money') . ' withdrawal fee',
            'paybill'    => ($from->type === 'mpesa' ? 'M-Pesa' : 'Airtel Money') . ' PayBill fee',
            'atm'        => 'ATM withdrawal fee (KES 33 + 15% excise duty)',
            default      => 'Transaction fee',
        };

        return "{$prefix}: Transfer to {$to->name}";
    }
}
