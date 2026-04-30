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
        .container {
            background: white;
            padding: 30px;
            border-radius: 5px;
        }
        .header {
            border-bottom: 2px solid #6366F1;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            color: #6366F1;
            margin: 0;
            font-size: 22px;
        }
        .header .year-badge {
            display: inline-block;
            background: #EEF2FF;
            color: #4338CA;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            margin-top: 8px;
            letter-spacing: 1px;
            text-transform: uppercase;
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
            border-left: 4px solid #6366F1;
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
        .highlight-stat {
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
            padding: 15px 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        .highlight-stat .label {
            font-size: 11px;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        .highlight-stat .value {
            font-size: 26px;
            font-weight: bold;
            color: #4338CA;
            margin: 4px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #666;
        }
        a {
            color: #6366F1;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <span class="year-badge">{{ $data['year'] }} Annual Report</span>
    </div>

    <div class="content">
        <p>Dear {{ $user->name }},</p>

        <p>Attached, please find your <strong>Annual Financial Report</strong> for the year <strong>{{ $data['year'] }}</strong>. This report provides a full-year overview of your financial activity, growth, and trends.</p>

        <div class="highlight-stat">
            <div class="label">💎 Year-End Net Worth</div>
            <div class="value">KES {{ number_format($data['net_worth'], 0) }}</div>
        </div>

        <div class="info-box">
            <p><strong>Report Period:</strong> {{ $data['start_date'] }} - {{ $data['end_date'] }}</p>
            <p><strong>Account Name:</strong> {{ $user->name }}</p>
            <p><strong>Total Accounts:</strong> {{ $data['accounts']->count() }}</p>
            <p><strong>Total Transactions:</strong> {{ $data['transaction_count'] }}</p>
            @if($data['net_flow'] >= 0)
                <p><strong>Annual Net Savings:</strong> KES {{ number_format($data['net_flow'], 0) }}</p>
            @else
                <p><strong>Annual Net Deficit:</strong> KES {{ number_format(abs($data['net_flow']), 0) }}</p>
            @endif
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

        <p>This comprehensive annual report includes:</p>
        <ul>
            <li>Full-year income and expense summary</li>
            <li>Month-by-month financial breakdown</li>
            <li>Year-end net worth calculation</li>
            <li>Annual savings rate analysis</li>
            @if(count($data['budget_performance']) > 0)
                <li>Yearly budget performance analysis</li>
            @endif
            <li>Account balances and year-over-year trends</li>
            <li>Top spending categories for the year</li>
            <li>Complete transaction history</li>
            <li>Annual financial insights and recommendations</li>
            @if($data['active_loans']->count() > 0)
                <li>Loan status and debt management overview</li>
            @endif
        </ul>

        <p>Thank you for using {{ config('app.name') }} to manage your finances throughout {{ $data['year'] }}. We look forward to helping you reach even greater financial milestones in the year ahead.</p>

        <p>
            <a href="{{ route('email-preferences.edit') }}">Manage your email preferences</a> |
            <a href="{{ url('/dashboard') }}">View Dashboard</a>
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
