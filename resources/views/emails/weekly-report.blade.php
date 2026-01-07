<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Financial Report</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px solid #4F46E5;
        }
        .header h1 {
            color: #4F46E5;
            margin: 0;
            font-size: 28px;
        }
        .period {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 30px 0;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #4F46E5;
        }
        .summary-card.positive {
            border-left-color: #10B981;
        }
        .summary-card.negative {
            border-left-color: #EF4444;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        .summary-card .amount {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .section {
            margin: 30px 0;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .category-name {
            font-weight: 500;
        }
        .category-amount {
            font-weight: bold;
            color: #EF4444;
        }
        .insight-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .insight-card h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .insight-card p {
            margin: 5px 0;
            opacity: 0.9;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            color: #666;
            font-size: 12px;
        }
        .button {
            display: inline-block;
            background: #4F46E5;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 500;
        }
        @media (max-width: 600px) {
            .summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>📊 Weekly Financial Report</h1>
        <p class="period">{{ $data['start_date'] }} - {{ $data['end_date'] }}</p>
    </div>

    <!-- Greeting -->
    <div style="margin: 30px 0;">
        <h2>Hello {{ $user->name }}! 👋</h2>
        <p>Here's your financial summary for the past week.</p>
    </div>

    <!-- Summary Cards -->
    <div class="summary">
        <div class="summary-card positive">
            <h3>Income</h3>
            <div class="amount">KES {{ number_format($data['income'], 0) }}</div>
        </div>
        <div class="summary-card negative">
            <h3>Expenses</h3>
            <div class="amount">KES {{ number_format($data['expenses'], 0) }}</div>
        </div>
        <div class="summary-card {{ $data['net_flow'] >= 0 ? 'positive' : 'negative' }}">
            <h3>Net Flow</h3>
            <div class="amount">KES {{ number_format($data['net_flow'], 0) }}</div>
        </div>
    </div>

    <!-- Account Balances -->
    <div class="section">
        <div class="section-title">💰 Account Balances</div>
        <table class="table">
            <thead>
            <tr>
                <th>Account</th>
                <th style="text-align: right;">Balance</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['accounts'] as $account)
                <tr>
                    <td>{{ $account->name }}</td>
                    <td style="text-align: right; font-weight: bold;">
                        KES {{ number_format($account->current_balance, 0) }}
                    </td>
                </tr>
            @endforeach
            <tr style="background: #f8f9fa; font-weight: bold;">
                <td>Total Balance</td>
                <td style="text-align: right; color: #10B981;">
                    KES {{ number_format($data['total_balance'], 0) }}
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <!-- Top Spending Categories -->
    @if($data['top_categories']->count() > 0)
        <div class="section">
            <div class="section-title">📈 Top Spending Categories</div>
            @foreach($data['top_categories'] as $category)
                <div class="category-item">
                    <div>
                        <div class="category-name">{{ $category['category'] }}</div>
                        <div style="font-size: 12px; color: #666;">{{ $category['count'] }} transaction(s)</div>
                    </div>
                    <div class="category-amount">KES {{ number_format($category['amount'], 0) }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Recent Transactions -->
    @if($data['transactions']->count() > 0)
        <div class="section">
            <div class="section-title">📝 Recent Transactions</div>
            <table class="table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
                </thead>
                <tbody>
                @foreach($data['transactions']->take(10) as $transaction)
                    <tr>
                        <td style="white-space: nowrap;">{{ $transaction->date->format('M d') }}</td>
                        <td>{{ Str::limit($transaction->description, 30) }}</td>
                        <td><span style="background: #e5e7eb; padding: 3px 8px; border-radius: 4px; font-size: 11px;">{{ $transaction->category->name }}</span></td>
                        <td style="text-align: right; font-weight: 500; color: {{ $transaction->category->type === 'expense' ? '#EF4444' : '#10B981' }};">
                            {{ $transaction->category->type === 'expense' ? '-' : '+' }}KES {{ number_format($transaction->amount, 0) }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if($data['transaction_count'] > 10)
                <p style="text-align: center; color: #666; font-size: 12px; margin-top: 10px;">
                    Showing 10 of {{ $data['transaction_count'] }} transactions
                </p>
            @endif
        </div>
    @endif

    <!-- Insights -->
    @if(count($data['insights']) > 0)
        <div class="section">
            <div class="section-title">💡 Insights & Tips</div>
            @foreach($data['insights'] as $insight)
                <div class="insight-card">
                    <h4>{{ $insight['icon'] }} {{ $insight['title'] }}</h4>
                    <p style="font-size: 18px; font-weight: bold;">{{ $insight['value'] }}</p>
                    <p>{{ $insight['description'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Active Loans -->
    @if($data['active_loans']->count() > 0)
        <div class="section">
            <div class="section-title">💳 Active Loans</div>
            @foreach($data['active_loans'] as $loan)
                <div class="category-item">
                    <div>
                        <div class="category-name">{{ $loan->source }}</div>
                        <div style="font-size: 12px; color: #666;">Due: {{ $loan->due_date ? $loan->due_date->format('M d, Y') : 'No due date' }}</div>
                    </div>
                    <div class="category-amount" style="color: #EF4444;">KES {{ number_format($loan->balance, 0) }}</div>
                </div>
            @endforeach
            <div style="margin-top: 15px; padding: 15px; background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 6px;">
                <strong>Total Loan Balance:</strong> KES {{ number_format($data['total_loans'], 0) }}
            </div>
        </div>
    @endif

    <!-- CTA Button -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ url('/dashboard') }}" class="button">View Full Dashboard</a>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This is an automated weekly report from {{ config('app.name') }}.</p>
        <p>
            <a href="{{ route('email-preferences.edit') }}" style="color: #4F46E5; text-decoration: none;">Manage Email Preferences</a>
        </p>
        <p style="margin-top: 10px;">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</div>
</body>
</html>
