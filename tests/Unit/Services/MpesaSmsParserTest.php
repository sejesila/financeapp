<?php
// tests/Unit/Services/MpesaSmsParserTest.php

namespace Tests\Unit\Services;

use App\Services\MpesaSmsParser;
use PHPUnit\Framework\TestCase;

class MpesaSmsParserTest extends TestCase
{
    // ── Send Money (P2P) ──────────────────────────────────────────────────────

    public function test_send_money_with_recipient_number_and_fee()
    {
        $sms = 'UDU882LSDZ Confirmed. KES530.00 sent to OGACHI DAVID 0719685465 on 30/4/26 at 6:34 PM. New M-PESA balance is KES5,790.30. Transaction cost, KES13.00. Amount you can transact within the day is 471,355.00. Earn interest daily on Ziidi MMF,Dial *334#';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('send_money', $result['subtype']);
        $this->assertEquals('UDU882LSDZ', $result['reference']);
        $this->assertEquals(530.00, $result['amount']);
        $this->assertEquals('OGACHI DAVID', $result['recipient']);
        $this->assertEquals(5790.30, $result['balance']);
        $this->assertEquals(13.00, $result['fee']);
    }

    public function test_send_money_no_fee()
    {
        $sms = 'UDU882LQXI Confirmed. KES30.00 sent to DAVID CHOKAI NAROROI 0720110265 on 30/4/26 at 6:35 PM. New M-PESA balance is KES5,760.30. Transaction cost, KES0.00. Amount you can transact within the day is 471,325.00. Earn interest daily on Ziidi MMF,Dial *334#';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('send_money', $result['subtype']);
        $this->assertEquals('UDU882LQXI', $result['reference']);
        $this->assertEquals(30.00, $result['amount']);
        $this->assertEquals('DAVID CHOKAI NAROROI', $result['recipient']);
        $this->assertEquals(5760.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    // ── Till / Lipa Na M-PESA ─────────────────────────────────────────────────

    public function test_till_lipa_na_mpesa()
    {
        $sms = 'UDT882GV6I Confirmed. KES450.00 paid to VUNO VENTURES. on 29/4/26 at 3:14 PM. New M-PESA balance is KES19,505.30. Transaction cost, KES0.00. Amount you can transact within the day is 492,305.00. Save frequent Tills for quick payment on M-PESA app https://bit.ly/mpesalnk';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('till', $result['subtype']);
        $this->assertEquals('UDT882GV6I', $result['reference']);
        $this->assertEquals(450.00, $result['amount']);
        $this->assertEquals('VUNO VENTURES', $result['recipient']);
        $this->assertEquals(19505.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
        $this->assertStringContainsString('Till', $result['description']);
    }

    public function test_till_with_merchant_reference()
    {
        $sms = 'UDT882GNBT Confirmed. KES850.00 paid to JEREMIAH MUTUKU MWANTHI 7. on 29/4/26 at 3:07 PM. New M-PESA balance is KES19,955.30. Transaction cost, KES0.00. Amount you can transact within the day is 492,755.00. Save frequent Tills for quick payment on M-PESA app https://bit.ly/mpesalnk';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('till', $result['subtype']);
        $this->assertEquals('UDT882GNBT', $result['reference']);
        $this->assertEquals(850.00, $result['amount']);
        $this->assertEquals('JEREMIAH MUTUKU MWANTHI', $result['recipient']); // trailing "7." stripped
        $this->assertEquals(19955.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    // ── Paybill — regular expense ─────────────────────────────────────────────

    public function test_regular_paybill_is_expense()
    {
        $sms = 'UDU882IZYJ Confirmed. KES30.00 sent to WINAS SACCO for account 76586 on 30/4/26 at 6:19 AM New M-PESA balance is KES19,475.30. Transaction cost, KES0.00. Amount you can transact within the day is 499,970.00. Save frequent paybills for quick payment on M-PESA app https://bit.ly/mpesalnk';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('paybill', $result['subtype']);
        $this->assertEquals('UDU882IZYJ', $result['reference']);
        $this->assertEquals(30.00, $result['amount']);
        $this->assertEquals('WINAS SACCO', $result['recipient']);
        $this->assertEquals(19475.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    public function test_paybill_with_fee()
    {
        $sms = 'UDQ882420Z Confirmed. KES250.00 sent to Equity Paybill Account for account 510666 on 26/4/26 at 1:47 PM New M-PESA balance is KES37,360.30. Transaction cost, KES5.00. Amount you can transact within the day is 497,670.00. Save frequent paybills for quick payment on M-PESA app https://bit.ly/mpesalnk';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('paybill', $result['subtype']);
        $this->assertEquals('UDQ882420Z', $result['reference']);
        $this->assertEquals(250.00, $result['amount']);
        $this->assertEquals(37360.30, $result['balance']);
        $this->assertEquals(5.00, $result['fee']);
    }

    // ── Paybill — transfer (account hint set) ─────────────────────────────────

    public function test_sanlam_paybill_is_transfer()
    {
        $sms = 'UDR88272FR Confirmed. KES5,000.00 sent to SANLAM UNIT TRUST for account 14468 on 27/4/26 at 9:58 AM New M-PESA balance is KES25,476.30. Transaction cost, KES0.00.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('account_transfer', $result['subtype']);
        $this->assertEquals('UDR88272FR', $result['reference']);
        $this->assertEquals(5000.00, $result['amount']);
        $this->assertEquals('SANLAM UNIT TRUST', $result['recipient']);
        $this->assertEquals('14468', $result['paybill_account']);
        $this->assertEquals('sanlam mmf', $result['to_account_hint']);
        $this->assertEquals(25476.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    public function test_airtel_money_paybill_is_transfer()
    {
        $sms = 'UDT882G8GD confirmed. KES100.00 sent to AIRTEL MONEY for account 254731609277 on 29/4/26 at 12:58 PM New M-PESA balance is KES23,955.30. Transaction cost, KES0.00.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('account_transfer', $result['subtype']);
        $this->assertEquals('UDT882G8GD', $result['reference']);
        $this->assertEquals(100.00, $result['amount']);
        $this->assertEquals('AIRTEL MONEY', $result['recipient']);
        $this->assertEquals('254731609277', $result['paybill_account']);
        $this->assertEquals('airtel money', $result['to_account_hint']);
        $this->assertEquals(23955.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    public function test_unknown_account_number_paybill_has_no_hint()
    {
        // A paybill with an account number that doesn't match any known hint
        $sms = 'PAY123ABCD Confirmed. KES500.00 sent to RANDOM COMPANY for account 99999 on 1/5/26 at 9:00 AM New M-PESA balance is KES10,000.00. Transaction cost, KES0.00.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('paybill', $result['subtype']);
    }

    // ── Pochi la Biashara ─────────────────────────────────────────────────────

    public function test_pochi_la_biashara()
    {
        $sms = 'UDU882J1IZ Confirmed. KES50.00 sent to MAGDALENE WAMBUI on 30/4/26 at 6:34 AM. New M-PESA balance is KES19,425.30. Transaction cost, KES0.00. Amount you can transact within the day is 499,920.00. Sign up for Lipa Na M-PESA Till online https://m-pesaforbusiness.co.ke';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('pochi', $result['subtype']);
        $this->assertEquals('UDU882J1IZ', $result['reference']);
        $this->assertEquals(50.00, $result['amount']);
        $this->assertEquals('MAGDALENE WAMBUI', $result['recipient']);
        $this->assertEquals(19425.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
        $this->assertStringContainsString('Pochi', $result['description']);
    }

    public function test_pochi_mixed_case_merchant()
    {
        $sms = 'UDT882FTMN Confirmed. KES80.00 sent to Dorcas gatibaru on 29/4/26 at 10:50 AM. New M-PESA balance is KES25,750.30. Transaction cost, KES0.00. Amount you can transact within the day is 498,490.00. Sign up for Lipa Na M-PESA Till online https://m-pesaforbusiness.co.ke';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('pochi', $result['subtype']);
        $this->assertEquals('UDT882FTMN', $result['reference']);
        $this->assertEquals(80.00, $result['amount']);
        $this->assertEquals('Dorcas gatibaru', $result['recipient']);
        $this->assertEquals(25750.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    // ── Airtime ───────────────────────────────────────────────────────────────

    public function test_airtime_purchase()
    {
        $sms = 'UDU882MWE1 confirmed.You bought KES50.00 of airtime on 30/4/26 at 10:17 PM.New M-PESA balance is KES6,716.30. Transaction cost, KES0.00.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('airtime', $result['subtype']);
        $this->assertEquals(50.00, $result['amount']);
        $this->assertEquals(6716.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    // ── Mpesa Withdrawal (Agent) ──────────────────────────────────────────────

    public function test_mpesa_withdrawal()
    {
        $sms = 'UDU882LSDZ Confirmed. KES500.00 withdrawn from AGENT on 30/4/26 at 6:34 PM. New M-PESA balance is KES5,790.30. Transaction cost, KES13.00.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('withdrawal', $result['subtype']);
        $this->assertEquals(500.00, $result['amount']);
        $this->assertEquals(5790.30, $result['balance']);
        $this->assertEquals(13.00, $result['fee']);
    }

    // ── Inter-account transfer (Airtel → Mpesa) ───────────────────────────────

    public function test_inter_account_transfer_from_airtel()
    {
        $sms = 'UE18820MJ1 Confirmed. You have received KES10.00 from AIRTEL MONEY - Silas Seje 731609277 on 1/5/26 at 1:12 PM New M-PESA balance is KES7,448.30.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('account_transfer', $result['subtype']);
        $this->assertEquals('UE18820MJ1', $result['reference']);
        $this->assertEquals(10.00, $result['amount']);
        $this->assertEquals('AIRTEL MONEY', $result['sender']);
        $this->assertEquals('airtel money', $result['from_account_hint']);
        $this->assertEquals(7448.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    // ── I&M: Bank to M-PESA transfer ──────────────────────────────────────────

    public function test_im_bank_to_mpesa_transfer_self()
    {
        $sms = 'Bank to M-PESA transfer of KES 600.00 to 254708745191 - SILAS SEJE successfully processed. Transaction Ref ID: 4006DMKD1032. M-PESA Ref ID: UD9O205UFV';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('im_bank', $result['bank']);
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('bank_to_mpesa_self', $result['subtype']);
        $this->assertEquals('UD9O205UFV', $result['reference']); // Uses M-PESA ref for dedup
        $this->assertEquals('UD9O205UFV', $result['mpesa_ref']);
        $this->assertEquals(600.00, $result['amount']);
    }

    public function test_im_bank_to_mpesa_transfer_external()
    {
        $sms = 'Bank to M-PESA transfer of KES 600.00 to 254719685465 - JOHN DOE successfully processed. Transaction Ref ID: 4006DMKD1032. M-PESA Ref ID: UD9O205UFV';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('im_bank', $result['bank']);
        // External transfers should be expense, not transfer
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('bank_to_mpesa', $result['subtype']);
    }

    public function test_im_bank_to_airtel_transfer_self()
    {
        $sms = 'Bank to Airtel Money Transfer of KES 250.00 to 254731609277 successfully processed. Transaction Ref ID:888660788069. Airtel Money Ref ID:Z3KVKUL3OKA.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('im_bank', $result['bank']);
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('bank_to_airtel_self', $result['subtype']);
        $this->assertEquals('Z3KVKUL3OKA', $result['reference']); // Uses Airtel ref for dedup
        $this->assertEquals(250.00, $result['amount']);
    }

    // ── I&M: Airtel received SMS (second leg of bank→airtel self transfer) ─────

    public function test_im_airtel_received_sms_self_transfer()
    {
        $sms = 'You\'ve received KES 250.00 from SILAS OUNO SE JE. Airtel Ref:Z3KVKUL3OKA.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('im_bank', $result['bank']);
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('bank_to_airtel_self', $result['subtype']);
        $this->assertEquals('Z3KVKUL3OKA', $result['reference']);
        $this->assertEquals(250.00, $result['amount']);
    }

    public function test_im_airtel_received_sms_external_ignored()
    {
        // Airtel SMS from external source should be ignored (return null)
        $sms = 'You\'ve received KES 100.00 from EXTERNAL PERSON. Airtel Ref:SOMEREF.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNull($result);
    }

    // ── I&M: ATM Withdrawal ───────────────────────────────────────────────────

    public function test_im_atm_withdrawal()
    {
        $sms = 'Dear SILAS, you withdrew KES 30,000.00 on 2026-04-25 09:59:05 at I&M BANK KENYATTA KE 2 using I&M 5477********9433.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('im_bank', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('atm_withdrawal', $result['subtype']);
        $this->assertEquals(30000.00, $result['amount']);
        $this->assertStringContainsString('I&M BANK KENYATTA', $result['location']);
    }

    // ── Mpesa: Received from IM BANK (bank→mpesa self transfer second leg) ──────

    public function test_mpesa_received_from_im_bank_self_transfer()
    {
        $sms = 'UE1882QZ2G Confirmed. You have received KES1,000.00 from IM BANK LIMITED- APP on 1/5/26 at 10:31 PM. New M-PESA balance is KES7,226.30. Buy goods with M-PESA.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('bank_to_mpesa_self', $result['subtype']);
        $this->assertEquals('UE1882QZ2G', $result['reference']);
        $this->assertEquals(1000.00, $result['amount']);
    }

    public function test_regular_received_money_not_self_transfer()
    {
        // Regular received money (not from IM BANK) should be receive_money
        $sms = 'UE18820MJ1 Confirmed. You have received KES10.00 from JANE DOE 0722123456 on 1/5/26 at 1:12 PM New M-PESA balance is KES7,448.30.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('income', $result['type']);
        $this->assertEquals('receive_money', $result['subtype']);
    }

    // ── Edge cases ────────────────────────────────────────────────────────────

    public function test_invalid_sms_returns_null()
    {
        $this->assertNull(MpesaSmsParser::parse('This is not a valid M-PESA SMS'));
        $this->assertNull(MpesaSmsParser::parse('Your OTP is 123456'));
        $this->assertNull(MpesaSmsParser::parse(''));
    }

    public function test_amount_parsing_with_commas()
    {
        $sms = 'UDT882GV6I Confirmed. KES450.00 paid to VUNO VENTURES. on 29/4/26 at 3:14 PM. New M-PESA balance is KES19,505.30. Transaction cost, KES0.00.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertEquals(19505.30, $result['balance']);
        $this->assertEquals(450.00, $result['amount']);
    }

    public function test_ksh_kes_variants_all_normalised()
    {
        // KES variant
        $sms = 'UDR88272FR Confirmed. KES5,000.00 sent to SANLAM UNIT TRUST for account 14468 on 27/4/26 at 9:58 AM New M-PESA balance is KES25,476.30. Transaction cost, KES0.00.';
        $result = MpesaSmsParser::parse($sms);
        $this->assertNotNull($result);
        $this->assertEquals(5000.00, $result['amount']);

        // KSH variant
        $sms2 = 'UDT882GV6I Confirmed. KSH450.00 paid to VUNO VENTURES. on 29/4/26 at 3:14 PM. New M-PESA balance is KSH19,505.30. Transaction cost, KSH0.00.';
        $result2 = MpesaSmsParser::parse($sms2);
        $this->assertNotNull($result2);
        $this->assertEquals(450.00, $result2['amount']);

        // Ksh variant
        $sms3 = 'UDT882GV6I Confirmed. Ksh450.00 paid to VUNO VENTURES. on 29/4/26 at 3:14 PM. New M-PESA balance is Ksh19,505.30. Transaction cost, Ksh0.00.';
        $result3 = MpesaSmsParser::parse($sms3);
        $this->assertNotNull($result3);
        $this->assertEquals(450.00, $result3['amount']);
    }

    public function test_received_money_without_number()
    {
        $sms = 'UDG880ZSXQ Confirmed. You have received KES500.00 from JANE DOE on 16/4/26 at 2:00 PM. New M-PESA balance is KES6,000.00.';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('income', $result['type']);
        $this->assertEquals('receive_money', $result['subtype']);
        $this->assertEquals(500.00, $result['amount']);
        $this->assertEquals('JANE DOE', $result['sender']);
        $this->assertEquals(6000.00, $result['balance']);
    }

    public function test_send_money_distinguishes_from_pochi()
    {
        // Send Money has a phone number
        $sms = 'UDU882LSDZ Confirmed. KES530.00 sent to OGACHI DAVID 0719685465 on 30/4/26 at 6:34 PM. New M-PESA balance is KES5,790.30. Transaction cost, KES13.00.';
        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('send_money', $result['subtype']);

        // Pochi has NO phone number
        $sms2 = 'UDU882J1IZ Confirmed. KES50.00 sent to MAGDALENE WAMBUI on 30/4/26 at 6:34 AM. New M-PESA balance is KES19,425.30. Transaction cost, KES0.00.';
        $result2 = MpesaSmsParser::parse($sms2);

        $this->assertNotNull($result2);
        $this->assertEquals('pochi', $result2['subtype']);
    }
    // ── M-Shwari deposit (Mpesa → M-Shwari) ──────────────────────────────────────

    public function test_mshwari_deposit_is_transfer()
    {
        $sms = 'UD788BW2M5 Confirmed.Ksh4,000.00 transferred to M-Shwari account on 7/4/26 at 5:14 PM. M-PESA balance is Ksh431.30 .New M-Shwari saving account balance is Ksh4,000.94. Transaction cost Ksh.0.00';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('account_transfer', $result['subtype']);
        $this->assertEquals('UD788BW2M5', $result['reference']);
        $this->assertEquals(4000.00, $result['amount']);
        $this->assertEquals('mshwari', $result['to_account_hint']);
        $this->assertArrayNotHasKey('from_account_hint', $result);
        $this->assertEquals(431.30, $result['balance']);
        $this->assertEquals(0, $result['fee']);
    }

// ── M-Shwari withdrawal (M-Shwari → Mpesa) ───────────────────────────────────

    public function test_mshwari_withdrawal_is_transfer()
    {
        $sms = 'UD98808BZN Confirmed.Ksh1,000.00 transferred from M-Shwari account on 9/4/26 at 7:04 PM. M-Shwari balance is Ksh3,000.94 .M-PESA balance is Ksh1,266.30 .Transaction cost Ksh.0.00';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('mpesa', $result['bank']);
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('account_transfer', $result['subtype']);
        $this->assertEquals('UD98808BZN', $result['reference']);
        $this->assertEquals(1000.00, $result['amount']);
        $this->assertEquals('mshwari', $result['from_account_hint']);
        $this->assertArrayNotHasKey('to_account_hint', $result);
        $this->assertEquals(1266.30, $result['balance']); // M-PESA balance (destination)
        $this->assertEquals(0, $result['fee']);
    }

// ── KES. normalization (Transaction cost Ksh.0.00) ───────────────────────────

    public function test_kes_dot_amount_normalised_correctly()
    {
        // Both M-Shwari SMS formats use "Transaction cost Ksh.0.00" — no space, dot before digits
        $sms = 'UD788BW2M5 Confirmed.Ksh4,000.00 transferred to M-Shwari account on 7/4/26 at 5:14 PM. M-PESA balance is Ksh431.30 .New M-Shwari saving account balance is Ksh4,000.94. Transaction cost Ksh.0.00';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        // Fee must be 0.00, not garbage from a failed parse
        $this->assertEquals(0, $result['fee']);
    }

// ── Till: trailing number + dot stripped from recipient ───────────────────────

    public function test_till_trailing_number_stripped_from_recipient()
    {
        // Merchant reference number + dot appears after the name (e.g. "JEREMIAH MUTUKU MWANTHI 7.")
        $sms = 'UDT882GNBT Confirmed. KES850.00 paid to JEREMIAH MUTUKU MWANTHI 7. on 29/4/26 at 3:07 PM. New M-PESA balance is KES19,955.30. Transaction cost, KES0.00. Amount you can transact within the day is 492,755.00. Save frequent Tills for quick payment on M-PESA app https://bit.ly/mpesalnk';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('till', $result['subtype']);
        $this->assertEquals(850.00, $result['amount']);
        // Trailing " 7." must be stripped — only the name remains
        $this->assertEquals('JEREMIAH MUTUKU MWANTHI', $result['recipient']);
        $this->assertEquals(19955.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    public function test_till_double_dot_does_not_corrupt_fee()
    {
        // Original bug: double dot before "New M-PESA balance" caused fee to capture wrong group
        $sms = 'UE3882WEM2 Confirmed. Ksh308.00 paid to Waeconmatt Limited -Tassia Branch.. on 3/5/26 at 11:44 AM.New M-PESA balance is Ksh5,723.30. Transaction cost, Ksh0.00. Amount you can transact within the day is 499,592.00. Save frequent Tills for quick payment on M-PESA app https://bit.ly/mpesalnk';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('till', $result['subtype']);
        $this->assertEquals(308.00, $result['amount']);
        $this->assertEquals('Waeconmatt Limited -Tassia Branch', $result['recipient']);
        $this->assertEquals(5723.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']); // Was incorrectly KES 7 before the fix
    }

    public function test_till_naivas_resolves_to_groceries()
    {
        $sms = 'UDQ88254RC Confirmed. Ksh6,774.00 paid to NAIVAS DEVELOPMENT HOUSE. on 26/4/26 at 6:16 PM. New M-PESA balance is Ksh30,586.30. Transaction cost, Ksh0.00. Amount you can transact within the day is 490,896.00. Save frequent Tills for quick payment on M-PESA app https://bit.ly/mpesalnk';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('till', $result['subtype']);
        $this->assertEquals(6774.00, $result['amount']);
        $this->assertEquals('NAIVAS DEVELOPMENT HOUSE', $result['recipient']);
        $this->assertEquals(30586.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }

    public function test_till_quick_mart_multi_word_name()
    {
        $sms = 'UDT882FVA5 Confirmed. Ksh1,595.00 paid to Quick Mart Tom Mboya. on 29/4/26 at 12:15 PM. New M-PESA balance is Ksh24,155.30. Transaction cost, Ksh0.00. Amount you can transact within the day is 496,895.00. Save frequent Tills for quick payment on M-PESA app https://bit.ly/mpesalnk';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('till', $result['subtype']);
        $this->assertEquals(1595.00, $result['amount']);
        // Multi-word name with location suffix must be preserved intact
        $this->assertEquals('Quick Mart Tom Mboya', $result['recipient']);
        $this->assertEquals(24155.30, $result['balance']);
        $this->assertEquals(0.00, $result['fee']);
    }
}
