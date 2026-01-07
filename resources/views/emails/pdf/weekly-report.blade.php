<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weekly Financial Report</title>
    <style>
        @page {
            margin: 20mm;
            @bottom-right {
                content: "Page " counter(page) " of " counter(pages);
                font-size: 9px;
                color: #666;
            }
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
        }
        .header {
            text-align: center;
            padding: 15px 0;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: white;
            border-radius: 8px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            letter-spacing: 1px;
        }
        .header .period {
            font-size: 11px;
            margin-top: 5px;
            opacity: 0.9;
        }
        .header .user-info {
            font-size: 12px;
            margin-top: 8px;
            font-weight: 600;
        }

        /* Quick Stats Bar */
        .quick-stats {
            background: #F3F4F6;
            padding: 12px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #4F46E5;
        }
        .quick-stats-grid {
            display: table;
            width: 100%;
        }
        .quick-stat {
            display: table-cell;
            text-align: center;
            padding: 8px;
        }
        .quick-stat-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .quick-stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #1F2937;
            margin-top: 3px;
        }

        /* Summary Cards */
        .summary-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
            border-spacing: 8px;
        }
        .summary-row {
            display: table-row;
        }
        .summary-cell {
            display: table-cell;
            width: 33.33%;
            padding: 18px;
            background: #FFFFFF;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            text-align: center;
        }
        .summary-cell.income {
            border-left: 5px solid #10B981;
        }
        .summary-cell.expense {
            border-left: 5px solid #EF4444;
        }
        .summary-cell.net {
            border-left: 5px solid #6366F1;
        }
        .summary-cell h3 {
            margin: 0 0 10px 0;
            font-size: 10px;
            color: #6B7280;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .summary-cell .amount {
            font-size: 20px;
            font-weight: bold;
            line-height: 1.2;
        }
        .summary-cell .change {
            font-size: 8px;
            margin-top: 5px;
            color: #6B7280;
        }

        /* Section Styling */
        .section {
            margin: 25px 0;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1F2937;
            margin-bottom: 12px;
            padding: 8px 12px;
            background: #F9FAFB;
            border-left: 4px solid #4F46E5;
            border-radius: 4px;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            background: white;
        }
        table th {
            background: #F3F4F6;
            padding: 10px 8px;
            text-align: left;
            font-size: 9px;
            color: #4B5563;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #E5E7EB;
        }
        table td {
            padding: 9px 8px;
            border-bottom: 1px solid #F3F4F6;
            font-size: 10px;
        }
        table tr:hover {
            background: #FAFAFA;
        }
        table tr.total-row {
            background: #F9FAFB;
            font-weight: bold;
            border-top: 2px solid #4F46E5;
        }

        /* Category Items */
        .category-list {
            margin: 12px 0;
        }
        .category-item {
            display: table;
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #FAFAFA;
            border-left: 4px solid #4F46E5;
            border-radius: 4px;
        }
        .category-item .row {
            display: table-row;
        }
        .category-item .cell {
            display: table-cell;
            padding: 2px 0;
        }
        .category-item .cell.right {
            text-align: right;
            font-weight: bold;
        }
        .category-name {
            font-weight: 600;
            font-size: 11px;
            color: #1F2937;
        }
        .category-meta {
            font-size: 9px;
            color: #6B7280;
            margin-top: 2px;
        }
        .category-amount {
            font-size: 13px;
            font-weight: bold;
            color: #DC2626;
        }
        .category-percent {
            font-size: 9px;
            color: #6B7280;
            margin-top: 2px;
        }

        /* Insights Box */
        .insight-box {
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #6366F1;
        }
        .insight-box h4 {
            margin: 0 0 8px 0;
            font-size: 11px;
            color: #4338CA;
            font-weight: bold;
        }
        .insight-box p {
            margin: 4px 0;
            font-size: 10px;
            color: #4B5563;
            line-height: 1.5;
        }
        .insight-highlight {
            font-weight: bold;
            color: #1F2937;
        }

        /* Alert Boxes */
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin: 12px 0;
            border-left: 4px solid;
        }
        .alert.warning {
            background: #FEF3C7;
            border-color: #F59E0B;
            color: #92400E;
        }
        .alert.info {
            background: #DBEAFE;
            border-color: #3B82F6;
            color: #1E40AF;
        }
        .alert.success {
            background: #D1FAE5;
            border-color: #10B981;
            color: #065F46;
        }
        .alert-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 4px;
        }
        .alert-text {
            font-size: 9px;
            line-height: 1.4;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 2px solid #E5E7EB;
            text-align: center;
            font-size: 9px;
            color: #6B7280;
        }
        .footer .confidential {
            color: #DC2626;
            font-weight: bold;
            margin-bottom: 8px;
        }

        /* Page Break */
        .page-break {
            page-break-after: always;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge.success {
            background: #D1FAE5;
            color: #065F46;
        }
        .badge.danger {
            background: #FEE2E2;
            color: #991B1B;
        }
        .badge.neutral {
            background: #F3F4F6;
            color: #4B5563;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <h1>📊 WEEKLY FINANCIAL REPORT</h1>
    <p class="period">{{ $data['start_date'] }} - {{ $data['end_date'] }}</p>
    <p class="user-info">{{ $user->name }}</p>
</div>

<!-- Quick Stats Bar -->
<div class="quick-stats">
    <div class="quick-stats-grid">
        <div class="quick-stat">
            <div class="quick-stat-label">Total Transactions</div>
            <div class="quick-stat-value">{{ $data['transaction_count'] }}</div>
        </div>
        <div class="quick-stat">
            <div class="quick-stat-label">Accounts</div>
            <div class="quick-stat-value">{{ $data['accounts']->count() }}</div>
        </div>
        <div class="quick-stat">
            <div class="quick-stat-label">Avg Transaction</div>
            <div class="quick-stat-value">KES {{ $data['transaction_count'] > 0 ? number_format(($data['income'] + $data['expenses']) / $data['transaction_count'], 0) : 0 }}</div>
        </div>
        @if($data['active_loans']->count() > 0)
            <div class="quick-stat">
                <div class="quick-stat-label">Active Loans</div>
                <div class="quick-stat-value">{{ $data['active_loans']->count() }}</div>
            </div>
        @endif
    </div>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
    <div class="summary-row">
        <div class="summary-cell income">
            <h3>💰 Total Income</h3>
            <div class="amount" style="color: #10B981;">KES {{ number_format($data['income'], 0) }}</div>
        </div>
        <div class="summary-cell expense">
            <h3>💸 Total Expenses</h3>
            <div class="amount" style="color: #EF4444;">KES {{ number_format($data['expenses'], 0) }}</div>
        </div>
        <div class="summary-cell net">
            <h3>📈 Net Cash Flow</h3>
            <div class="amount" style="color: {{ $data['net_flow'] >= 0 ? '#10B981' : '#EF4444' }};">
                {{ $data['net_flow'] >= 0 ? '+' : '' }}KES {{ number_format($data['net_flow'], 0) }}
            </div>
        </div>
    </div>
</div>

<!-- Financial Health Insights -->
@php
    $savingsRate = $data['income'] > 0 ? (($data['net_flow'] / $data['income']) * 100) : 0;
@endphp

@if($savingsRate > 20)
    <div class="alert success">
        <div class="alert-title">✅ Excellent Savings Performance!</div>
        <div class="alert-text">You saved {{ number_format($savingsRate, 1) }}% of your income this week. Keep up the great work!</div>
    </div>
@elseif($savingsRate > 0)
    <div class="alert info">
        <div class="alert-title">💡 Good Progress</div>
        <div class="alert-text">You saved {{ number_format($savingsRate, 1) }}% of your income. Try to aim for 20% or more for optimal financial health.</div>
    </div>
@else
    <div class="alert warning">
        <div class="alert-title">⚠️ Spending Exceeded Income</div>
        <div class="alert-text">Your expenses were {{ number_format(abs($savingsRate), 1) }}% higher than your income this week. Consider reviewing your spending patterns.</div>
    </div>
@endif

<!-- Account Balances -->
<div class="section">
    <div class="section-title">💰 Account Balances</div>
    <table>
        <thead>
        <tr>
            <th>Account Name</th>
            <th style="text-align: center;">Status</th>
            <th style="text-align: right;">Current Balance</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['accounts'] as $account)
            <tr>
                <td style="font-weight: 600;">{{ $account->name }}</td>
                <td style="text-align: center;">
                    <span class="badge {{ $account->current_balance > 10000 ? 'success' : ($account->current_balance > 0 ? 'neutral' : 'danger') }}">
                        {{ $account->current_balance > 10000 ? 'Healthy' : ($account->current_balance > 0 ? 'Active' : 'Low') }}
                    </span>
                </td>
                <td style="text-align: right; font-weight: bold; font-size: 11px;">
                    KES {{ number_format($account->current_balance, 0) }}
                </td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">💎 Total Net Worth</td>
            <td style="text-align: right; color: #10B981; font-size: 12px;">
                KES {{ number_format($data['total_balance'], 0) }}
            </td>
        </tr>
        </tbody>
    </table>
</div>

<!-- Top Spending Categories -->
@if($data['top_categories']->count() > 0)
    <div class="section">
        <div class="section-title">📊 Top Spending Categories</div>
        <div class="category-list">
            @foreach($data['top_categories'] as $index => $category)
                <div class="category-item">
                    <div class="row">
                        <div class="cell" style="width: 70%;">
                            <div class="category-name">{{ $index + 1 }}. {{ $category['category'] }}</div>
                            <div class="category-meta">{{ $category['count'] }} transaction(s)</div>
                        </div>
                        <div class="cell right" style="width: 30%;">
                            <div class="category-amount">KES {{ number_format($category['amount'], 0) }}</div>
                            <div class="category-percent">{{ $data['expenses'] > 0 ? number_format(($category['amount'] / $data['expenses']) * 100, 1) : 0 }}% of total</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<!-- Spending Insight -->
@if($data['top_categories']->count() > 0)
    @php
        $topCategory = $data['top_categories']->first();
        $topPercentage = $data['expenses'] > 0 ? ($topCategory['amount'] / $data['expenses']) * 100 : 0;
    @endphp
    <div class="insight-box">
        <h4>💡 Spending Pattern Insight</h4>
        <p>
            Your highest spending category was <span class="insight-highlight">{{ $topCategory['category'] }}</span>,
            accounting for <span class="insight-highlight">{{ number_format($topPercentage, 1) }}%</span> of your total expenses
            (KES {{ number_format($topCategory['amount'], 0) }} across {{ $topCategory['count'] }} transactions).
        </p>
    </div>
@endif

<!-- Page Break -->
<div class="page-break"></div>

<!-- Transactions -->
@if($data['transactions']->count() > 0)
    <div class="section">
        <div class="section-title">📝 Transaction History (Latest 25)</div>
        <table>
            <thead>
            <tr>
                <th style="width: 15%;">Date</th>
                <th style="width: 40%;">Description</th>
                <th style="width: 20%;">Category</th>
                <th style="width: 10%; text-align: center;">Type</th>
                <th style="width: 15%; text-align: right;">Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['transactions']->take(25) as $transaction)
                <tr>
                    <td style="white-space: nowrap; color: #6B7280;">{{ $transaction->date->format('M d, Y') }}</td>
                    <td style="font-weight: 500;">{{ Str::limit($transaction->description, 40) }}</td>
                    <td style="font-size: 9px; color: #6B7280;">{{ $transaction->category->name }}</td>
                    <td style="text-align: center;">
                        <span class="badge {{ $transaction->category->type === 'expense' ? 'danger' : 'success' }}">
                            {{ $transaction->category->type }}
                        </span>
                    </td>
                    <td style="text-align: right; font-weight: bold; color: {{ $transaction->category->type === 'expense' ? '#DC2626' : '#059669' }};">
                        {{ $transaction->category->type === 'expense' ? '-' : '+' }}KES {{ number_format($transaction->amount, 0) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if($data['transaction_count'] > 25)
            <p style="text-align: center; color: #6B7280; font-size: 9px; margin-top: 10px; font-style: italic;">
                Showing 25 of {{ $data['transaction_count'] }} total transactions this week
            </p>
        @endif
    </div>
@endif

<!-- Active Loans -->
@if($data['active_loans']->count() > 0)
    <div class="section">
        <div class="section-title">💳 Active Loan Summary</div>
        <table>
            <thead>
            <tr>
                <th>Loan Source</th>
                <th style="text-align: center;">Status</th>
                <th style="text-align: center;">Due Date</th>
                <th style="text-align: right;">Outstanding Balance</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['active_loans'] as $loan)
                @php
                    $daysUntilDue = $loan->due_date ? now()->diffInDays($loan->due_date, false) : null;
                    $isOverdue = $daysUntilDue !== null && $daysUntilDue < 0;
                    $isDueSoon = $daysUntilDue !== null && $daysUntilDue <= 7 && $daysUntilDue >= 0;
                @endphp
                <tr>
                    <td style="font-weight: 600;">{{ $loan->source }}</td>
                    <td style="text-align: center;">
                        <span class="badge {{ $isOverdue ? 'danger' : ($isDueSoon ? 'warning' : 'neutral') }}">
                            {{ $isOverdue ? 'Overdue' : ($isDueSoon ? 'Due Soon' : 'Active') }}
                        </span>
                    </td>
                    <td style="text-align: center; color: {{ $isOverdue ? '#DC2626' : '#6B7280' }};">
                        {{ $loan->due_date ? $loan->due_date->format('M d, Y') : 'No due date' }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #DC2626; font-size: 11px;">
                        KES {{ number_format($loan->balance, 0) }}
                    </td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">💰 Total Loan Liability</td>
                <td style="text-align: right; color: #DC2626; font-size: 12px;">
                    KES {{ number_format($data['total_loans'], 0) }}
                </td>
            </tr>
            </tbody>
        </table>

        @if($data['total_loans'] > 0)
            <div class="alert warning">
                <div class="alert-title">💡 Loan Repayment Priority</div>
                <div class="alert-text">
                    You have KES {{ number_format($data['total_loans'], 0) }} in outstanding loans.
                    Consider allocating {{ number_format(($data['total_loans'] / $data['total_balance']) * 100, 1) }}% of your net worth toward debt reduction.
                </div>
            </div>
        @endif
    </div>
@endif

<!-- Footer -->
<div class="footer">
    <p class="confidential">🔒 CONFIDENTIAL FINANCIAL DOCUMENT</p>
    <p>This report was generated on {{ now()->format('l, F d, Y \a\t h:i A') }}</p>
    <p>Report ID: WK-{{ $user->id }}-{{ now()->format('Ymd-His') }}</p>
    <p style="margin-top: 8px;">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    <p style="margin-top: 5px; font-size: 8px; color: #9CA3AF;">
        This document contains confidential financial information. Please store securely and do not share with unauthorized parties.
    </p>
</div>
</body>
</html>
