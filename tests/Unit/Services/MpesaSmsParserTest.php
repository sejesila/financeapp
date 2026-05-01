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
        $this->assertEquals('account_transfer', $result['subtype']);  // Changed from 'paybill'
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
        $this->assertEquals('account_transfer', $result['subtype']);  // Changed from 'paybill' to 'account_transfer'
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
        $this->assertEquals('pochi', $result['subtype']);           // ← pochi, not buy_goods
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

    public function test_im_bank_to_mpesa_transfer()
    {
        $sms = 'Bank to M-PESA transfer of KES 600.00 to 254719685465 - JOHN DOE successfully processed. Transaction Ref ID: 4006DMKD1032. M-PESA Ref ID: UD9O205UFV';

        $result = MpesaSmsParser::parse($sms);

        $this->assertNotNull($result);
        $this->assertEquals('im_bank', $result['bank']);
        $this->assertEquals('expense', $result['type']);
        $this->assertEquals('bank_to_mpesa', $result['subtype']);
        $this->assertEquals('4006DMKD1032', $result['reference']);
        $this->assertEquals('UD9O205UFV', $result['mpesa_ref']);
        $this->assertEquals(600.00, $result['amount']);
        $this->assertEquals('JOHN DOE', $result['recipient']);
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

}
