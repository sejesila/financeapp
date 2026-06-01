{{-- resources/views/emails/monthly-report.blade.php --}}
    <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Financial Report</title>
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
        .header { border-bottom: 2px solid #8B5CF6; padding-bottom: 15px; margin-bottom: 25px; }
        .header h1 { color: #8B5CF6; margin: 0; font-size: 22px; }
        .header .subtitle { font-size: 13px; color: #6B7280; margin-top: 4px; }

        .attachment-note {
            background: #F5F3FF;
            border: 1px solid #DDD6FE;
            border-radius: 6px;
            padding: 12px 16px;
            margin: 18px 0;
            font-size: 12.5px;
            color: #4B5563;
        }
        .attachment-note ul {
            margin: 8px 0 0 0;
            padding-left: 18px;
        }
        .attachment-note li { margin-bottom: 4px; }
        .attachment-note .etica-tag {
            display: inline-block;
            background: #0d2b5e;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 3px;
            margin-left: 4px;
            vertical-align: middle;
            letter-spacing: 0.4px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E5E7EB;
            font-size: 12px;
            color: #666;
        }
        a { color: #8B5CF6; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <div class="subtitle">Monthly Financial Report · {{ now()->subMonth()->format('F Y') }}</div>
    </div>

    <p style="font-size:14px;">Dear <strong>{{ $user->name }}</strong>,</p>

    <p style="font-size:13px; color:#4B5563;">
        Your monthly financial report for
        <strong>{{ $data['start_date'] }} – {{ $data['end_date'] }}</strong>
        is attached. Please find a full breakdown of your income, expenses, and financial
        activity for the period.
    </p>

    {{-- $hasEtica is passed from MonthlyReportMail::content() — no DB query here --}}
    <div class="attachment-note">
        <strong>📎 Attachments in this email:</strong>
        <ul>
            <li>Monthly Financial Report — {{ now()->subMonth()->format('F Y') }}.pdf</li>
            @if($hasEtica)
                <li>
                    Etica Fixed Income Statement — {{ now()->subMonth()->format('F Y') }}.pdf
                    <span class="etica-tag">Etica Capital</span>
                </li>
            @endif
        </ul>
    </div>

    <p style="font-size:13px; color:#4B5563;">
        If you have any questions about your report, feel free to reach out to us at
        <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
    </p>

    <p style="font-size:13px;">
        <a href="{{ route('email-preferences.edit') }}">Manage email preferences</a>
        &nbsp;·&nbsp;
        <a href="{{ url('/dashboard') }}">Go to Dashboard</a>
    </p>

    <div class="footer">
        <p><em>This is an auto-generated email. Please do not reply directly to this message.</em></p>
        <p style="margin-top:12px;">
            Regards,<br>
            <strong>{{ config('app.name') }} Team</strong>
        </p>
        <p style="margin-top:16px; font-size:11px;">
            © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </p>
    </div>
</div>
</body>
</html>
