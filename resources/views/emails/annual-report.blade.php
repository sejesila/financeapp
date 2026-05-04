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
        .password-notice {
            background: #FEF3C7;
            padding: 13px 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #F59E0B;
            font-size: 13px;
        }
        .password-notice p { margin: 4px 0; }
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
        and account performance from <strong>{{ $data['start_date'] }} – {{ $data['end_date'] }}</strong>.
    </p>

    <div class="password-notice">
        <p><strong>🔒 The attached PDF is password protected.</strong></p>
        <p>Your password: <strong>{{ substr(str_pad($user->id, 6, '0', STR_PAD_LEFT), -4) }}</strong>
            <span style="font-size:11px; color:#78716C;"> (last 4 digits of your user ID)</span></p>
        <p style="font-size:11px; color:#78716C; margin-top:6px;">Use Adobe Acrobat Reader 6.0+ to open the attachment.</p>
    </div>

    <p style="font-size:13px; color:#4B5563;">
        If you have any questions about your report, feel free to reach out to us at
        <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
    </p>

    <p style="font-size:13px;">
        <a href="{{ route('email-preferences.edit') }}">Manage email preferences</a> &nbsp;·&nbsp;
        <a href="{{ url('/dashboard') }}">Go to Dashboard</a>
    </p>

    <div class="footer">
        <p><em>This is an auto-generated email. Please do not reply directly to this message.</em></p>
        <p style="margin-top:12px;">
            Regards,<br>
            <strong>{{ config('app.name') }} Team</strong>
        </p>
        <p style="margin-top:16px; font-size:11px;">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>

</div>
</body>
</html>
