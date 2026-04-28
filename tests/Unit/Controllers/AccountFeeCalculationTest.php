<?php

namespace Tests\Unit\Controllers;

use App\Services\TransferFeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helper: call private method via Reflection ───────────────────────────────

function callPrivate(string $method, mixed ...$args): mixed
{
    $calculator = app(TransferFeeCalculator::class);
    $ref        = new \ReflectionMethod(TransferFeeCalculator::class, $method);
    $ref->setAccessible(true);
    return $ref->invoke($calculator, ...$args);
}

// ─── Withdrawal Fees ──────────────────────────────────────────────────────────

dataset('withdrawal_fee_tiers', [
    'minimum withdrawal (50 KES)'    => [50,    'mpesa',        11],
    'low tier (300 KES)'             => [300,   'mpesa',        29],
    'mid tier (3000 KES)'            => [3000,  'mpesa',        52],
    'high tier (7501 KES)'           => [7501,  'mpesa',       115],
    'very high (20001 KES)'          => [20001, 'mpesa',       197],
    'max tier (50001 KES)'           => [50001, 'mpesa',       309],
    'airtel same tiers (50 KES)'     => [50,    'airtel_money', 11],
    'airtel mid (3000 KES)'          => [3000,  'airtel_money', 52],
]);

it('calculates correct withdrawal fee', function (float $amount, string $type, float $expected) {
    expect(callPrivate('withdrawalFee', $amount, $type))->toBe($expected);
})->with('withdrawal_fee_tiers');

it('returns 0 withdrawal fee for non-mobile account types', function () {
    expect(callPrivate('withdrawalFee', 5000, 'bank'))->toBe(0.0)
        ->and(callPrivate('withdrawalFee', 5000, 'cash'))->toBe(0.0);
});

// ─── PayBill Fees ─────────────────────────────────────────────────────────────

dataset('mpesa_paybill_tiers', [
    'free tier (50 KES)'        => [50,   'mpesa', 0],
    '101-500 tier (200 KES)'    => [200,  'mpesa', 5],
    '501-1000 tier (1000 KES)'  => [1000, 'mpesa', 10],
    '1001-1500 tier (1500 KES)' => [1500, 'mpesa', 15],
    '5001-7500 tier (6000 KES)' => [6000, 'mpesa', 42],
]);

it('calculates correct M-Pesa paybill fee', function (float $amount, string $type, float $expected) {
    expect(callPrivate('payBillFee', $amount, $type))->toBe($expected);
})->with('mpesa_paybill_tiers');

it('charges zero paybill fee for Airtel Money', function () {
    expect(callPrivate('payBillFee', 5000, 'airtel_money'))->toBe(0.0);
});

it('returns 0 paybill fee for unknown account type', function () {
    expect(callPrivate('payBillFee', 5000, 'cash'))->toBe(0.0);
});

// ─── ATM Fee ──────────────────────────────────────────────────────────────────

it('calculates ATM fee as KES 33 + 15% excise duty = 37.95', function () {
    expect(callPrivate('atmFee'))->toBe(37.95);
});

// ─── Edge Cases ───────────────────────────────────────────────────────────────

it('returns the last tier fee for amounts above all defined tiers on withdrawal', function () {
    expect(callPrivate('withdrawalFee', 999999, 'mpesa'))->toBe(309.0);
});
