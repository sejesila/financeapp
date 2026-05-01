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
        // I&M BANK PATTERNS (Process BEFORE M-PESA patterns to handle bank transfers)
        // ─────────────────────────────────────────────────────────────────

        // 1. I&M: Bank to M-PESA transfer
        // Example: "Bank to M-PESA transfer of KES 1,000.00 to 254708745191 - SILAS SEJE successfully processed. Transaction Ref ID: 4197QMGO4277. M-PESA Ref ID: UE1882QZ2G"
        if (preg_match(
            '/Bank to M-PESA transfer of KES ([\d,]+\.?\d*)\s+to\s+(\d+)\s*-\s*(.+?)\s+successfully processed\.\s*Transaction Ref ID:\s*(\w+)\.\s*M-PESA Ref ID:\s*(\w+)/si',
            $sms, $m
        )) {
            return [
                'bank'              => 'im_bank',
                'type'              => 'transfer',
                'subtype'           => 'account_transfer',
                'reference'         => $m[4],  // Transaction Ref ID
                'mpesa_ref'         => $m[5],  // M-PESA Ref ID (for duplicate detection)
                'amount'            => self::parseAmount($m[1]),
                'to_phone'          => $m[2],
                'recipient'         => trim($m[3]),
                'date'              => now(),
                'balance'           => null,
                'fee'               => 0,
                'description'       => 'Bank to M-PESA transfer to ' . trim($m[3]),
                'to_account_hint'   => 'mpesa',
            ];
        }

        // 2. I&M: Bank to Airtel Money transfer
        // Example: "Bank to Airtel Money Transfer of KES 250.00 to 254731609277 successfully processed. Transaction Ref ID:888660788069. Airtel Money Ref ID:Z3KVKUL3OKA."
        if (preg_match(
            '/Bank to Airtel Money Transfer of KES ([\d,]+\.?\d*)\s+to\s+(\d+)\s+successfully processed\.\s*Transaction Ref ID:\s*(\w+)\.\s*Airtel Money Ref ID:\s*(\w+)/si',
            $sms, $m
        )) {
            return [
                'bank'              => 'im_bank',
                'type'              => 'transfer',
                'subtype'           => 'account_transfer',
                'reference'         => $m[3],  // Transaction Ref ID
                'airtel_ref'        => $m[4],  // Airtel Money Ref ID
                'amount'            => self::parseAmount($m[1]),
                'to_phone'          => $m[2],
                'recipient'         => 'Airtel Money',
                'date'              => now(),
                'balance'           => null,
                'fee'               => 0,
                'description'       => 'Bank to Airtel Money transfer',
                'to_account_hint'   => 'airtel money',
            ];
        }

        // 3. I&M: ATM Withdrawal
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

        // Check for M-PESA confirmation of bank transfer (duplicate)
        // Example: "UE1882QZ2G Confirmed. You have received Ksh1,000.00 from IM BANK LIMITED- APP on 1/5/26 at 10:31 PM. New M-PESA balance is Ksh7,226.30."
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*You have received\s+KES\s*([\d,]+\.?\d*)\s+from\s+IM BANK/i',
            $sms, $m
        )) {
            // This is a duplicate of the bank transfer - return special marker
            return [
                'bank'          => 'mpesa',
                'type'          => 'duplicate',
                'subtype'       => 'bank_transfer_confirmation',
                'reference'     => $m[1],  // M-PESA ref ID that matches bank's M-PESA Ref ID
                'amount'        => self::parseAmount($m[2]),
                'is_duplicate'  => true,
                'description'   => 'M-PESA confirmation of bank transfer',
            ];
        }

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

        // 3. Paybill transfers (Sanlam MMF, etc.)
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+sent to\s+(.+?)\s+for account\s+(\d+)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.?\s+New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)\.\s*Transaction cost,\s*KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            $recipient   = trim($m[3]);
            $accountNo   = $m[4];
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
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+sent to\s+(.+?)\s+(?:on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.\s*New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)\.\s*Transaction cost,\s*KES\s*([\d,]+\.?\d*))/si',
            $sms, $m
        )) {
            $fullRecipient = trim($m[3]);

            if (preg_match('/(.+?)\s+(0\d{9})$/', $fullRecipient, $phoneMatch)) {
                return [
                    'bank'        => 'mpesa',
                    'type'        => 'expense',
                    'subtype'     => 'send_money',
                    'reference'   => $m[1],
                    'amount'      => self::parseAmount($m[2]),
                    'recipient'   => trim($phoneMatch[1]),
                    'date'        => self::parseDate($m[4], $m[5]),
                    'balance'     => self::parseAmount($m[6]),
                    'fee'         => self::parseAmount($m[7]),
                    'description' => 'Sent to ' . trim($phoneMatch[1]),
                ];
            } else {
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

        // 5. Received money (regular P2P)
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*You have received\s+KES\s*([\d,]+\.?\d*)\s+from\s+(.+?)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.?\s*New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            // Skip if this is from a bank (already handled)
            if (preg_match('/IM BANK|BANK/i', $m[3])) {
                return null;
            }

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

        // 6. Till / Lipa Na M-PESA Till
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

        // 7. Mpesa Withdrawal (Agent)
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

        // 8. Airtime purchase
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
