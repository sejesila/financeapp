<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weekly Financial Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 3px solid #4F46E5;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #4F46E5;
            margin: 0;
            font-size: 24px;
        }
        .period {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        .summary-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }
        .summary-row {
            display: table-row;
        }
        .summary-cell {
            display: table-cell;
            width: 33.33%;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .summary-cell h3 {
            margin: 0 0 10px 0;
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        .summary-cell .amount {
            font-size: 18px;
            font-weight: bold;
        }
        .section {
            margin: 30px 0;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            border-bottom: 1px solid #e5e7eb;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
<!-- Header -->
<div class="header">
    <h1>Weekly Financial Report</h1>
    <p class="period">{{ $data['start_date'] }} - {{ $data['end_date'] }}</p>
    <p style="margin-top: 10px;"><strong>{{ $user->name }}</strong></p>
</div>

<!-- Summary -->
<div class="summary-grid">
    <div class="summary-row">
        <div class="summary-cell">
            <h3>Income</h3>
            <div class="amount" style="color: #10B981;">KES {{ number_format($data['income'], 0) }}</div>
        </div>
        <div class="summary-cell">
            <h3>Expenses</h3>
            <div class="amount" style="color: #EF4444;">KES {{ number_format($data['expenses'], 0) }}</div>
        </div>
        <div class="summary-cell">
            <h3>Net Flow</h3>
            <div class="amount" style="color: {{ $data['net_flow'] >= 0 ? '#10B981' : '#EF4444' }};">
                KES {{ number_format($data['net_flow'], 0) }}
            </div>
        </div>
    </div>
</div>

<!-- Account Balances -->
<div class="section">
    <div class="section-title">Account Balances</div>
    <table>
        <thead>
        <tr>
            <th>Account Name</th>
            <th style="text-align: right;">Current Balance</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['accounts'] as $account)
            <tr>
                <td>{{ $account->name }}</td>
                <td style="text-align: right; font-weight: bold;">KES {{ number_format($account->current_balance, 0) }}</td>
            </tr>
        @endforeach
        <tr style="background: #f8f9fa; font-weight: bold;">
            <td>Total Balance</td>
            <td style="text-align: right;">KES {{ number_format($data['total_balance'], 0) }}</td>
        </tr>
        </tbody>
    </table>
</div>

<!-- Top Spending Categories -->
@if($data['top_categories']->count() > 0)
    <div class="section">
        <div class="section-title">Top Spending Categories</div>
        <table>
            <thead>
            <tr>
                <th>Category</th>
                <th style="text-align: center;">Transactions</th>
                <th style="text-align: right;">Total Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['top_categories'] as $category)
                <tr>
                    <td>{{ $category['category'] }}</td>
                    <td style="text-align: center;">{{ $category['count'] }}</td>
                    <td style="text-align: right; font-weight: bold;">KES {{ number_format($category['amount'], 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<!-- Page Break -->
<div class="page-break"></div>

<!-- Transactions -->
@if($data['transactions']->count() > 0)
    <div class="section">
        <div class="section-title">Transactions (Latest 20)</div>
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Category</th>
                <th style="text-align: right;">Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['transactions']->take(20) as $transaction)
                <tr>
                    <td style="white-space: nowrap;">{{ $transaction->date->format('M d, Y') }}</td>
                    <td>{{ Str::limit($transaction->description, 40) }}</td>
                    <td>{{ $transaction->category->name }}</td>
                    <td style="text-align: right; font-weight: bold;">
                        {{ $transaction->category->type === 'expense' ? '-' : '+' }}KES {{ number_format($transaction->amount, 0) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if($data['transaction_count'] > 20)
            <p style="text-align: center; color: #666; font-size: 10px; margin-top: 10px;">
                Showing 20 of {{ $data['transaction_count'] }} total transactions
            </p>
        @endif
    </div>
@endif

<!-- Active Loans -->
@if($data['active_loans']->count() > 0)
    <div class="section">
        <div class="section-title">Active Loans</div>
        <table>
            <thead>
            <tr>
                <th>Loan Source</th>
                <th>Due Date</th>
                <th style="text-align: right;">Balance</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['active_loans'] as $loan)
                <tr>
                    <td>{{ $loan->source }}</td>
                    <td>{{ $loan->due_date ? $loan->due_date->format('M d, Y') : 'No due date' }}</td>
                    <td style="text-align: right; font-weight: bold; color: #EF4444;">KES {{ number_format($loan->balance, 0) }}</td>
                </tr>
            @endforeach
            <tr style="background: #f8f9fa; font-weight: bold;">
                <td colspan="2">Total Loan Balance</td>
                <td style="text-align: right;">KES {{ number_format($data['total_loans'], 0) }}</td>
            </tr>
            </tbody>
        </table>
    </div>
@endif

<!-- Footer -->
<div class="footer">
    <p>Generated on {{ now()->format('F d, Y \a\t h:i A') }}</p>
    <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
</div>
</body>
</html>
