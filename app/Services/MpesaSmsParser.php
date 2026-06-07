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

        // PesaLink transfer (bank → savings account at another bank, e.g. Etica/Equity)
        if (preg_match(
            '/Pesalink transfer of KES ([\d,]+\.?\d*)\s+to\s+(.+?)\s+A\/c\s+(\S+)\s+on\s+([\d\/]+)\s+([\d:]+)\s+processed successfully\.\s*Transaction Ref ID:\s*(\w+)/si',
            $sms, $m
        )) {
            $bankName  = trim($m[2]);
            $accountNo = trim($m[3]);

            $isEtica = stripos($bankName, 'equity') !== false
                && str_contains($accountNo, '0180283951027');

            return [
                'bank'        => 'im_bank',
                'type'        => $isEtica ? 'transfer' : 'expense',
                'subtype'     => $isEtica ? 'pesalink_to_savings' : 'pesalink_outgoing',
                'reference'   => $m[6],
                'amount'      => self::parseAmount($m[1]),
                'recipient'   => $bankName,
                'account_no'  => $accountNo,
                'date'        => self::parsePesaLinkDate($m[4], $m[5]),
                'balance'     => null,
                'fee'         => self::getPesaLinkFee(self::parseAmount($m[1])),
                'description' => $isEtica
                    ? 'PesaLink to Etica Savings'
                    : 'PesaLink to ' . $bankName . ' ' . $accountNo,
            ];
        }

        // Bank to M-PESA transfer
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
                'reference'   => $m[5],
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

        // Bank to Airtel Money transfer (outgoing leg from bank)
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
                'reference'   => $m[4],
                'amount'      => self::parseAmount($m[1]),
                'date'        => now(),
                'balance'     => null,
                'fee'         => 0,
                'description' => 'Bank to Airtel Money transfer',
            ];
        }

        // Airtel received SMS from own bank — second leg of bank→airtel self transfer
        if (preg_match(
            '/You\'ve received\s+KES\s*([\d,]+\.?\d*)\s+from\s+(.+?)\.\s*Airtel Ref:\s*(\w+)/si',
            $sms, $m
        )) {
            $sender         = trim($m[2]);
            $isSelfTransfer = stripos($sender, 'SILAS OUNO SE JE') !== false
                || stripos($sender, 'SILAS SEJE') !== false;

            if (!$isSelfTransfer) {
                return null;
            }

            return [
                'bank'        => 'im_bank',
                'type'        => 'transfer',
                'subtype'     => 'bank_to_airtel_self',
                'reference'   => $m[3],
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

        // 0. Received from own bank (IM BANK LIMITED) — self transfer second leg
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*You have received\s+KES\s*([\d,]+\.?\d*)\s+from\s+IM BANK LIMITED[^.]*on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))/si',
            $sms, $m
        )) {
            return [
                'bank'        => 'mpesa',
                'type'        => 'transfer',
                'subtype'     => 'bank_to_mpesa_self',
                'reference'   => $m[1],
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
                'balance'           => self::parseAmount($m[6]),
                'fee'               => 0,
                'from_account_hint' => 'm-shwari',
                'description'       => 'Transfer from M-Shwari',
            ];
        }

        // 3. Paybill transfers (Sanlam MMF, Etica, etc.) - must come BEFORE expense paybills
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*(?:KES|Ksh)\s*([\d,]+\.?\d*)\s+sent to\s+(.+?)\s+for account\s+(\S+)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.?\s+New M-PESA balance is\s+(?:KES|Ksh)\s*([\d,]+\.?\d*)\.\s*Transaction cost,\s*(?:KES|Ksh)\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            $recipient = trim($m[3]);
            $accountNo = $m[4];

            $isTransfer = self::isKnownTransferAccount($recipient, $accountNo);

            if ($isTransfer) {
                return [
                    'bank'            => 'mpesa',
                    'type'            => 'transfer',
                    'subtype'         => 'account_transfer',
                    'reference'       => $m[1],
                    'amount'          => self::parseAmount($m[2]),
                    'recipient'       => $recipient,
                    'paybill_account' => $accountNo,
                    'to_account_hint' => $isTransfer,
                    'date'            => self::parseDate($m[5], $m[6]),
                    'balance'         => self::parseAmount($m[7]),
                    'fee'             => self::parseAmount($m[8]),
                    'description'     => 'Transfer to ' . $recipient,
                ];
            }

            return [
                'bank'            => 'mpesa',
                'type'            => 'expense',
                'subtype'         => 'paybill',
                'reference'       => $m[1],
                'amount'          => self::parseAmount($m[2]),
                'recipient'       => $recipient,
                'paybill_account' => $accountNo,
                'date'            => self::parseDate($m[5], $m[6]),
                'balance'         => self::parseAmount($m[7]),
                'fee'             => self::parseAmount($m[8]),
                'description'     => 'Paybill - ' . $recipient,
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
            $recipient = trim(preg_replace('/[\s\d]*\.+$|[\s\d]+$/', '', trim($m[3])));
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

        // ─────────────────────────────────────────────────────────────────
        // AIRTEL MONEY PATTERNS
        // ─────────────────────────────────────────────────────────────────

        // Airtel → Paybill
        // "S3MHML58P3E Confirmed. Ksh 200 successfully paid to Kenya Power and Lighting Co Ltd on 17/05/26 at 09:17 AM. Fee: Ksh 4. Bal: Ksh 97.0."
        if (preg_match(
            '/^(\w+)\s+Confirmed\.\s*Ksh\s*([\d,]+\.?\d*)\s+successfully paid to\s+(.+?)\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.\s*Fee:\s*Ksh\s*([\d,]+\.?\d*)\.\s*Bal:\s*Ksh\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            return [
                'bank'        => 'airtel',
                'type'        => 'expense',
                'subtype'     => 'paybill',
                'reference'   => $m[1],
                'amount'      => self::parseAmount($m[2]),
                'recipient'   => trim($m[3]),
                'date'        => self::parseAirtelDate($m[4], $m[5]),
                'balance'     => self::parseAmount($m[7]),
                'fee'         => self::parseAmount($m[6]),
                'description' => 'Paybill - ' . trim($m[3]),
            ];
        }

        // Airtel → Pochi la Biashara
        if (preg_match(
            '/^(\w+)\.\s*Ksh\s*([\d,]+\.?\d*)\s+sent to\s+(.+?)\s+(\d{10,12})\s+on\s+([\d\/]+)\s+at\s+([\d:]+\s*(?:AM|PM))\.\s*Fee:\s*Ksh\s*([\d,]+\.?\d*)\.\s*Bal:\s*Ksh\s*([\d,]+\.?\d*)\.\s*MPESA ID:\s*(\w+)/si',
            $sms, $m
        )) {
            $phone = $m[4];

            // Self-transfer to own Mpesa — already handled by the Mpesa confirmation SMS.
            // Return null so the webhook ignores this Airtel leg entirely.
            if (str_contains($phone, '254708745191')) {
                return null;
            }

            return [
                'bank'        => 'airtel',
                'type'        => 'expense',
                'subtype'     => 'pochi',
                'reference'   => $m[9],
                'airtel_ref'  => $m[1],
                'amount'      => self::parseAmount($m[2]),
                'recipient'   => trim($m[3]),
                'phone'       => $phone,
                'date'        => self::parseAirtelDate($m[5], $m[6]),
                'balance'     => self::parseAmount($m[8]),
                'fee'         => self::parseAmount($m[7]),
                'description' => 'Pochi - ' . trim($m[3]),
            ];
        }

        // Airtel → Airtime purchase
        // "37779704577 Successful. Airtime top up for line 731609277 of Ksh 250 is successful. 25% bonus airtime received. Bal: Ksh 12.0."
        if (preg_match(
            '/^(\w+)\s+Successful\.\s*Airtime top up for line\s+(\d+)\s+of\s+Ksh\s*([\d,]+\.?\d*)\s+is successful.*?Bal:\s*Ksh\s*([\d,]+\.?\d*)/si',
            $sms, $m
        )) {
            return [
                'bank'        => 'airtel',
                'type'        => 'expense',
                'subtype'     => 'airtime',
                'reference'   => $m[1],
                'amount'      => self::parseAmount($m[3]),
                'recipient'   => $m[2],
                'date'        => now(),
                'balance'     => self::parseAmount($m[4]),
                'fee'         => 0,
                'description' => 'Airtel Airtime - ' . $m[2],
            ];
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // PesaLink fee schedule (I&M Bank)
    // ─────────────────────────────────────────────────────────────────────

    public static function getPesaLinkFee(float $amount): float
    {
        $baseFee = match (true) {
            $amount <= 500     => 0,
            $amount <= 10_000  => 44,
            $amount <= 50_000  => 66,
            $amount <= 100_000 => 87,
            $amount <= 200_000 => 109,
            $amount <= 500_000 => 131,
            default            => 163,
        };

        return round($baseFee * 1.15, 2);
    }

    private static function isKnownTransferAccount(string $recipient, string $accountNo): ?string
    {
        $r = strtolower($recipient);

        if (str_contains($r, 'sanlam'))                                    return 'sanlam';
        if (str_contains($r, 'mshwari') || str_contains($r, 'm-shwari'))  return 'mshwari';
        if (str_contains($r, 'etica'))                                     return 'etica';

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

    // PesaLink date format: "29/05/2026 12:38"
    private static function parsePesaLinkDate(string $date, string $time): Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y H:i', "$date $time");
        } catch (\Exception $e) {
            return now();
        }
    }

    // Airtel date format: "07/06/26 09:59 AM"
    private static function parseAirtelDate(string $date, string $time): Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/y g:i A', "$date $time");
        } catch (\Exception $e) {
            return now();
        }
    }
}
