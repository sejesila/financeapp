<?php

namespace App\Services;

class MobileMoneyRates
{
    // ── Type labels ───────────────────────────────────────────────────────────

    public static function types(string $provider): array
    {
        return match ($provider) {
            'mpesa' => [
                'send_money'        => 'Send Money',
                'paybill'           => 'PayBill',
                'buy_goods'         => 'Buy Goods/Till Number',
                'pochi_la_biashara' => 'Pochi La Biashara',
            ],
            'airtel_money' => [
                'send_money' => 'Send Money',
                'paybill'    => 'PayBill',
                'buy_goods'  => 'Buy Goods/Till Number',
            ],
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }

    // ── Cost bands ────────────────────────────────────────────────────────────

    public static function costs(string $provider): array
    {
        return match ($provider) {
            'mpesa'        => self::mpesaCosts(),
            'airtel_money' => self::airtelCosts(),
            default        => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }

    /**
     * Resolve the transaction fee for a given provider, transaction type, and amount.
     * Returns 0 if no matching band is found.
     */
    public static function fee(string $provider, string $transactionType, float $amount): int
    {
        $bands = self::costs($provider)[$transactionType] ?? [];

        foreach ($bands as $band) {
            if ($amount >= $band['min'] && $amount <= $band['max']) {
                return $band['cost'];
            }
        }

        return 0;
    }

    // ── Private band definitions ──────────────────────────────────────────────

    private static function mpesaCosts(): array
    {
        $sendAndPochi = [
            ['min' => 1,      'max' => 100,    'cost' => 0],
            ['min' => 101,    'max' => 500,    'cost' => 7],
            ['min' => 501,    'max' => 1000,   'cost' => 13],
            ['min' => 1001,   'max' => 1500,   'cost' => 23],
            ['min' => 1501,   'max' => 2500,   'cost' => 33],
            ['min' => 2501,   'max' => 3500,   'cost' => 53],
            ['min' => 3501,   'max' => 5000,   'cost' => 57],
            ['min' => 5001,   'max' => 7500,   'cost' => 78],
            ['min' => 7501,   'max' => 10000,  'cost' => 90],
            ['min' => 10001,  'max' => 15000,  'cost' => 100],
            ['min' => 15001,  'max' => 20000,  'cost' => 105],
            ['min' => 20001,  'max' => 35000,  'cost' => 108],
            ['min' => 35001,  'max' => 50000,  'cost' => 110],
            ['min' => 50001,  'max' => 150000, 'cost' => 112],
            ['min' => 150001, 'max' => 250000, 'cost' => 115],
            ['min' => 250001, 'max' => 500000, 'cost' => 117],
        ];

        return [
            'send_money'        => $sendAndPochi,
            'pochi_la_biashara' => $sendAndPochi,
            'buy_goods'         => [['min' => 1, 'max' => 500000, 'cost' => 0]],
            'paybill'           => [
                ['min' => 1,     'max' => 100,    'cost' => 0],
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
                ['min' => 45001, 'max' => 250000, 'cost' => 108],
            ],
        ];
    }

    private static function airtelCosts(): array
    {
        return [
            'send_money' => [
                ['min' => 10,    'max' => 100,    'cost' => 0],
                ['min' => 101,   'max' => 500,    'cost' => 7],
                ['min' => 501,   'max' => 1000,   'cost' => 15],
                ['min' => 1001,  'max' => 1500,   'cost' => 25],
                ['min' => 1501,  'max' => 2500,   'cost' => 35],
                ['min' => 2501,  'max' => 3500,   'cost' => 55],
                ['min' => 3501,  'max' => 5000,   'cost' => 65],
                ['min' => 5001,  'max' => 7500,   'cost' => 80],
                ['min' => 7501,  'max' => 10000,  'cost' => 95],
                ['min' => 10001, 'max' => 15000,  'cost' => 105],
                ['min' => 15001, 'max' => 20000,  'cost' => 110],
                ['min' => 20001, 'max' => 35000,  'cost' => 115],
                ['min' => 35001, 'max' => 50000,  'cost' => 120],
                ['min' => 50001, 'max' => 70000,  'cost' => 125],
                ['min' => 70001, 'max' => 150000, 'cost' => 130],
            ],
            'paybill'   => [['min' => 1, 'max' => 150000, 'cost' => 0]],
            'buy_goods' => [['min' => 1, 'max' => 150000, 'cost' => 0]],
        ];
    }
}
