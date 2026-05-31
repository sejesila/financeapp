{{-- resources/views/emails/etica-statement.blade.php --}}
    <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $account->name }} Statement – {{ $period }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container { background: white; padding: 30px; border-radius: 8px; }

        .header {
            border-bottom: 2px solid #0d2b5e;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 { color: #0d2b5e; margin: 0; font-size: 22px; }
        .header .subtitle { font-size: 13px; color: #6B7280; margin-top: 4px; }


        .summary-box {
            background: #f0f4ff;
            border: 1px solid #c7d4f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .summary-box table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .summary-box td { padding: 5px 8px; }
        .summary-box td:last-child { text-align: right; font-weight: 600; }
        .label-cell { color: #555; }
        .amount-green  { color: #1a5e2a; }
        .amount-red    { color: #8b1a1a; }
        .amount-blue   { color: #1a3a6e; }
        .amount-bold   { color: #0d2b5e; font-size: 14px; }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
            font-size: 12px;
            color: #666;
        }
        a { color: #0d2b5e; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">

    {{-- Header --}}
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <div class="subtitle">
            {{ $account->name }} Statement &middot; {{ $period }}
        </div>
    </div>

    <p style="font-size:14px;">Dear <strong>{{ explode(' ', $user->name)[0] }}</strong>,</p>

    <p style="font-size:13px; color:#4B5563;">
        Please find attached your <strong>{{ $account->name }}</strong> statement
        for the period <strong>{{ $data['from']->format('M d, Y') }} – {{ $data['to']->format('M d, Y') }}</strong>.
    </p>

    {{-- Mini summary --}}
    <div class="summary-box">
        <table>
            <tr>
                <td class="label-cell">Opening Balance</td>
                <td>KES {{ number_format($data['openingBalance'], 2) }}</td>
            </tr>
            @if($data['totalInflow'] > 0)
                <tr>
                    <td class="label-cell">Total Inflows</td>
                    <td class="amount-green">+ KES {{ number_format($data['totalInflow'], 2) }}</td>
                </tr>
            @endif
            @if($data['totalWithdrawal'] > 0)
                <tr>
                    <td class="label-cell">Total Withdrawals</td>
                    <td class="amount-red">(KES {{ number_format($data['totalWithdrawal'], 2) }})</td>
                </tr>
            @endif
            @if($data['totalInterest'] > 0)
                <tr>
                    <td class="label-cell">Net Interest Earned</td>
                    <td class="amount-blue">KES {{ number_format($data['totalInterest'], 2) }}</td>
                </tr>
            @endif
            <tr style="border-top: 1px solid #c7d4f0;">
                <td class="label-cell"><strong>Closing Balance</strong></td>
                <td class="amount-bold">KES {{ number_format($data['closingBalance'], 2) }}</td>
            </tr>
        </table>
    </div>

    <p style="font-size:13px; color:#4B5563;">
        The full statement with all transaction details is in the attached PDF.
    </p>

    <p style="font-size:13px;">
        <a href="{{ route('accounts.statement', $account) }}">View statement online</a>
        &nbsp;&middot;&nbsp;
        <a href="{{ route('email-preferences.edit') }}">Manage email preferences</a>
        &nbsp;&middot;&nbsp;
        <a href="{{ url('/dashboard') }}">Dashboard</a>
    </p>

    <div class="footer">
        <p><em>This is an auto-generated email. Please do not reply directly to this message.</em></p>
        <p style="margin-top:12px;">
            Regards,<br>
            <strong>Etica Capital / {{ config('app.name') }} Team</strong>
        </p>
        <p style="margin-top:16px; font-size:11px;">
            © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </p>
    </div>

</div>
</body>
</html>
