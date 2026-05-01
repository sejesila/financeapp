<?php
// app/Services/MpesaSmsParser.php

namespace App\Services;

use Carbon\Carbon;

class MpesaSmsParser
{
    public static function parse(string $sms): ?array
    {
        $sms = trim($sms);

        // Normalise common issues
        $sms = str_replace(['Ksh', 'KSH', 'ksh'], 'KES', $sms);
        $sms = preg_replace('/\bconfirmed\b\.?/i', 'Confirmed.', $sms);
        $sms = preg_replace('/Confirmed\.(KES|Ksh)/i', 'Confirmed. KES', $sms);
        $sms = preg_replace('/^([A-Z0-9]+)\s+confirmed\./i', '$1 Confirmed.', $sms);

        // ─────────────────────────────────────────────────────────────────
        // I&M BANK PATTERNS
        // ─────────────────────────────────────────────────────────────────

        if (preg_match(
            '/Bank to M-PESA transfer of KES ([\d,]+\.?\d*)\s+to\s+[\d]+\s*-\s*(.+?)\s+successfully processed\.\s*Transaction Ref ID:\s*(\w+)\.\s*M-PESA Ref ID:\s*(\w+)/si',
            $sms, $m
        )) {
            return [
                'bank'        => 'im_bank',
                'type'        => 'expense',
                'subtype'     => 'bank_to_mpesa',
                'reference'   => $m[3],
                'mpesa_ref'   => $m[4],
                'amount'      => self::parseAmount($m[1]),
                'recipient'   => trim($m[2]),
                'date'        => now(),
                'balance'     => null,
                'fee'         => 0,
                'description' => 'Bank to Mpesa - ' . trim($m[2]),
            ];
        }

        if (preg_match(
            '/Dear\s+\w+,\s+you\s+withdrew\s+KES\s*([\d,]+\.?\d*)\s+on\s+([\d-]+)\s+([\d:]+)\s+at\s+(.+?)\s+using/si',
            $sms, $m
        )) {
            return [
                'bank'        => 'im_bank',
                'type'        => 'expense',
                'subtype'     => 'atm_withdrawal',
                'reference'   => 'ATM-' . date('YmdHis'),
                'amount'      => self::parseAmount($m[1]),
                'location'    => trim($m[4]),
                'date'        => self::parseBankDate($m[2], $m[3]),
                'balance'     => null,
                'fee'         => 0,
                'description' => 'ATM Withdrawal at ' . trim($m[4]),
            ];
        }

        // ─────────────────────────────────────────────────────────────────
        // MPESA PATTERNS — ORDER MATTERS (most specific first)
        // ─────────────────────────────────────────────────────────────────

        // 1. Inter-account received (Airtel Money → Mpesa)
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*You have received\s+KES\s*([\d,]+\.?\d*)\s+from\s+(AIRTEL MONEY|AIRTEL).+?on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\s+New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            return [
                'bank'               => 'mpesa',
                'type'               => 'transfer',
                'subtype'            => 'account_transfer',
                'reference'          => $m[1],
                'amount'             => self::parseAmount($m[2]),
                'sender'             => trim($m[3]),
                'date'               => self::parseDate($m[4], $m[5]),
                'balance'            => self::parseAmount($m[6]),
                'fee'                => 0,
                'description'        => 'Transfer received from ' . trim($m[3]),
                'from_account_hint'  => 'airtel money',
            ];
        }

        // 2. Inter-account sent (Mpesa → Airtel Money via paybill)
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+sent to\s+(AIRTEL MONEY|AIRTEL).+?for account\s+(\d+)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.?\s+New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            return [
                'bank'              => 'mpesa',
                'type'              => 'transfer',
                'subtype'           => 'account_transfer',
                'reference'         => $m[1],
                'amount'            => self::parseAmount($m[2]),
                'recipient'         => trim($m[3]),
                'paybill_account'   => $m[4],
                'to_account_hint'   => 'airtel money',
                'date'              => self::parseDate($m[5], $m[6]),
                'balance'           => self::parseAmount($m[7]),
                'fee'               => 0,
                'description'       => 'Transfer to ' . trim($m[3]),
            ];
        }

        // 3. Paybill transfers (Sanlam MMF, etc.) - must come BEFORE expense paybills
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+sent to\s+(.+?)\s+for account\s+(\d+)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.?\s+New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)\.\s*Transaction cost,\s*KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            $recipient   = trim($m[3]);
            $accountNo   = $m[4];

            // Check if this is a known transfer (Sanlam, etc.)
            $isTransfer = self::isKnownTransferAccount($recipient, $accountNo);

            if ($isTransfer) {
                return [
                    'bank'              => 'mpesa',
                    'type'              => 'transfer',
                    'subtype'           => 'account_transfer',
                    'reference'         => $m[1],
                    'amount'            => self::parseAmount($m[2]),
                    'recipient'         => $recipient,
                    'paybill_account'   => $accountNo,
                    'to_account_hint'   => $isTransfer,
                    'date'              => self::parseDate($m[5], $m[6]),
                    'balance'           => self::parseAmount($m[7]),
                    'fee'               => self::parseAmount($m[8]),
                    'description'       => 'Transfer to ' . $recipient,
                ];
            }

            // Regular expense paybill
            return [
                'bank'             => 'mpesa',
                'type'             => 'expense',
                'subtype'          => 'paybill',
                'reference'        => $m[1],
                'amount'           => self::parseAmount($m[2]),
                'recipient'        => $recipient,
                'paybill_account'  => $accountNo,
                'date'             => self::parseDate($m[5], $m[6]),
                'balance'          => self::parseAmount($m[7]),
                'fee'              => self::parseAmount($m[8]),
                'description'      => 'Paybill - ' . $recipient,
            ];
        }

        // 4. Send Money & Pochi la Biashara (combined pattern)
// - If phone number present -> send_money
// - If no phone number -> pochi
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+sent to\s+(.+?)\s+(?:on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.\s*New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)\.\s*Transaction cost,\s*KES\s*([\d,]+\.?\d*))/si',
            $sms, $m
        )) {
            $fullRecipient = trim($m[3]);

            // Check if there's a phone number (0 followed by 9 digits) at the end of recipient
            if (preg_match('/(.+?)\s+(0\d{9})$/', $fullRecipient, $phoneMatch)) {
                // Has phone number -> Send Money
                return [
                    'bank'        => 'mpesa',
                    'type'        => 'expense',
                    'subtype'     => 'send_money',
                    'reference'   => $m[1],
                    'amount'      => self::parseAmount($m[2]),
                    'recipient'   => trim($phoneMatch[1]), // Name without phone number
                    'date'        => self::parseDate($m[4], $m[5]),
                    'balance'     => self::parseAmount($m[6]),
                    'fee'         => self::parseAmount($m[7]),
                    'description' => 'Sent to ' . trim($phoneMatch[1]),
                ];
            } else {
                // No phone number -> Pochi la Biashara
                return [
                    'bank'        => 'mpesa',
                    'type'        => 'expense',
                    'subtype'     => 'pochi',
                    'reference'   => $m[1],
                    'amount'      => self::parseAmount($m[2]),
                    'recipient'   => $fullRecipient,
                    'date'        => self::parseDate($m[4], $m[5]),
                    'balance'     => self::parseAmount($m[6]),
                    'fee'         => self::parseAmount($m[7]),
                    'description' => 'Pochi - ' . $fullRecipient,
                ];
            }
        }

        // 6. Received money (regular P2P)
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*You have received\s+KES\s*([\d,]+\.?\d*)\s+from\s+(.+?)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.?\s*New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            return [
                'bank'        => 'mpesa',
                'type'        => 'income',
                'subtype'     => 'receive_money',
                'reference'   => $m[1],
                'amount'      => self::parseAmount($m[2]),
                'sender'      => trim($m[3]),
                'date'        => self::parseDate($m[4], $m[5]),
                'balance'     => self::parseAmount($m[6]),
                'fee'         => 0,
                'description' => 'Received from ' . trim($m[3]),
            ];
        }

        // 7. Till / Lipa Na M-PESA Till
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+paid to\s+(.+?)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.\s*New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)\.\s*Transaction cost,\s*KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            $recipient = trim(preg_replace('/[\s\d.]+$/', '', $m[3]));
            return [
                'bank'        => 'mpesa',
                'type'        => 'expense',
                'subtype'     => 'till',
                'reference'   => $m[1],
                'amount'      => self::parseAmount($m[2]),
                'recipient'   => $recipient,
                'date'        => self::parseDate($m[4], $m[5]),
                'balance'     => self::parseAmount($m[6]),
                'fee'         => self::parseAmount($m[7]),
                'description' => 'Till - ' . $recipient,
            ];
        }

        // 8. Mpesa Withdrawal (Agent)
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+withdrawn from\s+.+?\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.\s*New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)\.\s*Transaction cost,\s*KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            return [
                'bank'        => 'mpesa',
                'type'        => 'expense',
                'subtype'     => 'withdrawal',
                'reference'   => $m[1],
                'amount'      => self::parseAmount($m[2]),
                'date'        => self::parseDate($m[3], $m[4]),
                'balance'     => self::parseAmount($m[5]),
                'fee'         => self::parseAmount($m[6]),
                'description' => 'Mpesa Withdrawal',
            ];
        }

        // 9. Airtime purchase
        if (preg_match(
            '/^(\w+)\s+Confirmed\.?\s*You bought\s+KES\s*([\d,]+\.?\d*)\s+of airtime\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.\s*New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)\.?\s*Transaction cost,\s*KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            return [
                'bank'        => 'mpesa',
                'type'        => 'expense',
                'subtype'     => 'airtime',
                'reference'   => $m[1],
                'amount'      => self::parseAmount($m[2]),
                'date'        => self::parseDate($m[3], $m[4]),
                'balance'     => self::parseAmount($m[5]),
                'fee'         => self::parseAmount($m[6]),
                'description' => 'Airtime Purchase',
            ];
        }

        return null;
    }

    private static function isKnownTransferAccount(string $recipient, string $accountNo): ?string
    {
        $r = strtolower($recipient);

        if (str_contains($r, 'sanlam')) {
            return 'sanlam mmf';
        }

        return null;
    }

    private static function parseAmount(string $raw): float
    {
        return (float) str_replace(',', '', trim($raw));
    }

    private static function parseDate(string $date, string $time): Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/y g:i A', "$date $time");
        } catch (\Exception $e) {
            return now();
        }
    }

    private static function parseBankDate(string $date, string $time): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', "$date $time");
        } catch (\Exception $e) {
            return now();
        }
    }
}
