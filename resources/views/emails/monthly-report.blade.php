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
        Your monthly financial report for <strong>{{ $data['start_date'] }} – {{ $data['end_date'] }}</strong>
        is attached. Please find a full breakdown of your income, expenses, and financial activity for the period.
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
