{{-- resources/views/emails/annual-report.blade.php --}}
    <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annual Financial Report</title>
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
        .header { border-bottom: 2px solid #6366F1; padding-bottom: 15px; margin-bottom: 25px; }
        .header h1 { color: #6366F1; margin: 0; font-size: 22px; }
        .header .year-badge {
            display: inline-block;
            background: #EEF2FF;
            color: #4338CA;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            margin-top: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .attachment-note {
            background: #EEF2FF;
            border: 1px solid #C7D2FE;
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
        a { color: #6366F1; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <span class="year-badge">{{ $data['year'] }} Annual Report</span>
    </div>

    <p style="font-size:14px;">Dear <strong>{{ $user->name }}</strong>,</p>

    <p style="font-size:13px; color:#4B5563;">
        Your annual financial report for <strong>{{ $data['year'] }}</strong> is attached.
        It covers a full year of your financial activity, including income, expenses, savings,
        and account performance from
        <strong>{{ $data['start_date'] }} – {{ $data['end_date'] }}</strong>.
    </p>

    {{-- $hasEtica is passed from AnnualReportMail::content() — no DB query here --}}
    <div class="attachment-note">
        <strong>📎 Attachments in this email:</strong>
        <ul>
            <li>Annual Financial Report — {{ $data['year'] }}.pdf</li>
            @if($hasEtica)
                <li>
                    Etica Fixed Income Statement — {{ $data['year'] }}.pdf
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
