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

        // Bank to M-PESA transfer.
        // Captures phone number and recipient name separately to detect self-transfers.
        // Uses M-PESA Ref ID (m[5]) as reference so it matches the Mpesa confirmation SMS.
        if (preg_match(
            '/Bank to M-PESA transfer of KES ([\d,]+\.?\d*)\s+to\s+([\d]+)\s*-\s*(.+?)\s+successfully processed\.\s*Transaction Ref ID:\s*(\w+)\.\s*M-PESA Ref ID:\s*(\w+)/si',
            $sms, $m
        )) {
            $phoneNumber    = trim($m[2]);
            $recipientName  = trim($m[3]);
            $isSelfTransfer = str_contains($phoneNumber, '254708745191')
                || stripos($recipientName, 'SILAS SEJE') !== false;

            return [
                'bank'        => 'im_bank',
                'type'        => $isSelfTransfer ? 'transfer' : 'expense',
                'subtype'     => $isSelfTransfer ? 'bank_to_mpesa_self' : 'bank_to_mpesa',
                'reference'   => $m[5],  // M-PESA Ref ID — matches the Mpesa SMS reference for dedup
                'mpesa_ref'   => $m[5],
                'amount'      => self::parseAmount($m[1]),
                'recipient'   => $recipientName,
                'date'        => now(),
                'balance'     => null,
                'fee'         => 0,
                'description' => $isSelfTransfer
                    ? 'Bank to Mpesa (self transfer)'
                    : 'Bank to Mpesa - ' . $recipientName,
            ];
        }

        // Bank to Airtel Money transfer (outgoing leg from bank).
        // Uses Airtel Money Ref ID (m[4]) as reference so it matches the received SMS.
        if (preg_match(
            '/Bank to Airtel Money Transfer of KES ([\d,]+\.?\d*)\s+to\s+([\d]+)\s+successfully processed\.\s*Transaction Ref ID:\s*(\w+)\.\s*Airtel Money Ref ID:\s*(\w+)/si',
            $sms, $m
        )) {
            $phoneNumber    = trim($m[2]);
            $isSelfTransfer = str_contains($phoneNumber, '254731609277');

            return [
                'bank'        => 'im_bank',
                'type'        => $isSelfTransfer ? 'transfer' : 'expense',
                'subtype'     => $isSelfTransfer ? 'bank_to_airtel_self' : 'bank_to_airtel',
                'reference'   => $m[4],  // Airtel Money Ref ID — matches the received SMS for dedup
                'amount'      => self::parseAmount($m[1]),
                'date'        => now(),
                'balance'     => null,
                'fee'         => 0,
                'description' => 'Bank to Airtel Money transfer',
            ];
        }

        // Airtel received SMS from own bank — second leg of bank→airtel self transfer.
        // "You've received KES X from SILAS OUNO SE JE. Airtel Ref:XXXXX."
        // No date in this SMS format so we use now().
        if (preg_match(
            '/You\'ve received\s+KES\s*([\d,]+\.?\d*)\s+from\s+(.+?)\.\s*Airtel Ref:\s*(\w+)/si',
            $sms, $m
        )) {
            $sender         = trim($m[2]);
            $isSelfTransfer = stripos($sender, 'SILAS OUNO SE JE') !== false
                || stripos($sender, 'SILAS SEJE') !== false;

            if (!$isSelfTransfer) {
                return null; // Not our transaction — ignore
            }

            return [
                'bank'        => 'im_bank',
                'type'        => 'transfer',
                'subtype'     => 'bank_to_airtel_self',
                'reference'   => $m[3],  // Airtel Ref — matches outgoing SMS reference for dedup
                'amount'      => self::parseAmount($m[1]),
                'sender'      => $sender,
                'date'        => now(),
                'balance'     => null,
                'fee'         => 0,
                'description' => 'Bank to Airtel Money transfer',
            ];
        }

        // ATM withdrawal
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

        // 0. Received from own bank (IM BANK LIMITED) — self transfer second leg.
        //    Must come BEFORE the generic receive_money pattern (pattern 6).
        //    The reference (transaction ID at start of Mpesa SMS) is the M-PESA Ref ID,
        //    which matches what the bank SMS stores as reference — enabling dedup.
        //    Regex: "IM BANK LIMITED[^.]*on" skips "- APP" or similar suffixes safely.
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*You have received\s+KES\s*([\d,]+\.?\d*)\s+from\s+IM BANK LIMITED[^.]*on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))/si',
            $sms, $m
        )) {
            return [
                'bank'        => 'mpesa',
                'type'        => 'transfer',
                'subtype'     => 'bank_to_mpesa_self',
                'reference'   => $m[1],  // M-PESA Ref ID — matches bank SMS reference
                'amount'      => self::parseAmount($m[2]),
                'sender'      => 'IM BANK LIMITED',
                'date'        => self::parseDate($m[3], $m[4]),
                'balance'     => null,
                'fee'         => 0,
                'description' => 'Bank to Mpesa transfer',
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

        // 2a. Inter-account sent (Mpesa → Airtel Money via paybill)
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
        // 2b. M-Shwari deposit — Mpesa → M-Shwari
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+transferred to M-Shwari account\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.\s*M-PESA balance is\s+KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            return [
                'bank'            => 'mpesa',
                'type'            => 'transfer',
                'subtype'         => 'account_transfer',
                'reference'       => $m[1],
                'amount'          => self::parseAmount($m[2]),
                'date'            => self::parseDate($m[3], $m[4]),
                'balance'         => self::parseAmount($m[5]),
                'fee'             => 0,
                'to_account_hint' => 'm-shwari',
                'description'     => 'Transfer to M-Shwari',
            ];
        }

        // 2c. M-Shwari withdrawal — M-Shwari → Mpesa
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+transferred from M-Shwari account\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.\s*M-Shwari balance is\s+KES\s*([\d,]+\.?\d*).*?M-PESA balance is\s+KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            return [
                'bank'              => 'mpesa',
                'type'              => 'transfer',
                'subtype'           => 'account_transfer',
                'reference'         => $m[1],
                'amount'            => self::parseAmount($m[2]),
                'date'              => self::parseDate($m[3], $m[4]),
                'balance'           => self::parseAmount($m[6]), // M-PESA balance (destination)
                'fee'               => 0,
                'from_account_hint' => 'm-shwari',
                'description'       => 'Transfer from M-Shwari',
            ];
        }

        // 3. Paybill transfers (Sanlam MMF, Etica, etc.) - must come BEFORE expense paybills
        // FIXED: Changed to accept both KES and Ksh, and account codes with letters (e.g., 357892M)
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*(?:KES|Ksh)\s*([\d,]+\.?\d*)\s+sent to\s+(.+?)\s+for account\s+(\S+)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\s+New M-PESA balance is\s+(?:KES|Ksh)\s*([\d,]+\.?\d*)\.\s*Transaction cost,\s*(?:KES|Ksh)\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            $recipient   = trim($m[3]);
            $accountNo   = $m[4];

            // Check if this is a known transfer (Sanlam, Etica, etc.)
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

        // 5. Received money (regular P2P)
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

        // 6. Till / Lipa Na M-PESA Till
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*KES\s*([\d,]+\.?\d*)\s+paid to\s+(.+?)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.+\s*New M-PESA balance is\s+KES\s*([\d,]+\.?\d*)\.\s*Transaction cost,\s*KES\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            $recipient = trim(preg_replace('/[\s\d]*\.+$|[\s\d]+$/', '', trim($m[3]))); // strip trailing dots from recipient
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

        if (str_contains($r, 'sanlam'))  return 'sanlam';
        if (str_contains($r, 'mshwari') || str_contains($r, 'm-shwari')) return 'mshwari';
        if (str_contains($r, 'etica'))   return 'etica';

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
