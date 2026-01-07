<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Financial Report</title>
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
        .container {
            background: white;
            padding: 30px;
            border-radius: 5px;
        }
        .header {
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            color: #4F46E5;
            margin: 0;
            font-size: 22px;
        }
        .content {
            font-size: 14px;
            line-height: 1.8;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #4F46E5;
        }
        .info-box p {
            margin: 5px 0;
        }
        .password-notice {
            background: #FEF3C7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #F59E0B;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #666;
        }
        a {
            color: #4F46E5;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
    </div>

    <div class="content">
        <p>Dear {{ $user->name }},</p>

        <p>Attached, please find your <strong>Weekly Financial Report</strong>.</p>

        <div class="info-box">
            <p><strong>Report Period:</strong> {{ $data['start_date'] }} - {{ $data['end_date'] }}</p>
            <p><strong>Account Name:</strong> {{ $user->name }}</p>
            <p><strong>Total Accounts:</strong> {{ $data['accounts']->count() }}</p>
        </div>

        <div class="password-notice">
            <p><strong>📌 For your security, the PDF file is password protected.</strong></p>
            <p>Your password is: <strong>{{ substr(str_pad($user->id, 6, '0', STR_PAD_LEFT), -4) }}</strong></p>
            <p style="font-size: 12px; color: #666; margin-top: 10px;">
                This is the last 4 digits of your user ID number. Please keep this password confidential.
            </p>
            <p style="font-size: 12px; color: #666;">
                Please use Adobe Acrobat Reader version 6.0 or above to open the attachment.
            </p>
        </div>

        <p>This report includes:</p>
        <ul>
            <li>Income and expense summary</li>
            <li>Account balances</li>
            <li>Recent transactions</li>
            <li>Top spending categories</li>
            <li>Financial insights</li>
            @if($data['active_loans']->count() > 0)
                <li>Active loan information</li>
            @endif
        </ul>

        <p>Thank you for using {{ config('app.name') }} and for your continued trust in managing your finances with us.</p>

        <p>
            <a href="{{ route('email-preferences.edit') }}" style="color: #4F46E5;">Manage your email preferences</a> |
            <a href="{{ url('/dashboard') }}" style="color: #4F46E5;">View Dashboard</a>
        </p>
    </div>

    <div class="footer">
        <p><strong>Should you have any concerns, please contact our support team:</strong></p>
        <p>Email: {{ config('mail.from.address') }}</p>

        <p style="margin-top: 15px;">
            <em>This is an auto-generated email. Please do not reply to this email.</em>
        </p>

        <p style="margin-top: 15px;">
            Regards,<br>
            <strong>{{ config('app.name') }} Team</strong><br>
            Your Partner in Financial Management
        </p>

        <p style="margin-top: 20px; font-size: 11px;">
            © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </p>
    </div>
</div>
</body>
</html>
