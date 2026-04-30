<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Annual Financial Report</title>
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
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(99, 102, 241, 0.05);
            z-index: -1;
            font-weight: bold;
        }
        .header {
            text-align: center;
            padding: 15px 0;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #4338CA 0%, #6366F1 100%);
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
        .header .year-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 6px;
        }

        /* Net Worth Highlight */
        .net-worth-banner {
            background: linear-gradient(135deg, #4338CA 0%, #6366F1 100%);
            color: white;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
            border-radius: 8px;
        }
        .net-worth-banner h3 {
            margin: 0 0 10px 0;
            font-size: 11px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .net-worth-banner .amount {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .net-worth-banner .breakdown {
            font-size: 10px;
            opacity: 0.85;
            margin-top: 8px;
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
        .summary-cell.savings {
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
        .summary-cell .sub-label {
            font-size: 8px;
            color: #9CA3AF;
            margin-top: 4px;
        }

        /* Annual Stats */
        .stats-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
            background: #F9FAFB;
            padding: 12px;
            border-radius: 8px;
        }
        .stats-row {
            display: table-row;
        }
        .stat-cell {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 10px 5px;
        }
        .stat-label {
            font-size: 8px;
            color: #6B7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #1F2937;
            margin-top: 4px;
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
            border-left: 4px solid #6366F1;
            border-radius: 4px;
        }

        /* Monthly Breakdown Table */
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
        table tr.total-row {
            background: #F9FAFB;
            font-weight: bold;
            border-top: 2px solid #6366F1;
        }
        table tr.positive td.flow {
            color: #059669;
        }
        table tr.negative td.flow {
            color: #DC2626;
        }

        /* Budget Performance */
        .budget-item {
            background: #FAFAFA;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 4px solid;
            border-radius: 4px;
        }
        .budget-item.good { border-color: #10B981; }
        .budget-item.warning { border-color: #F59E0B; }
        .budget-item.danger { border-color: #EF4444; }
        .budget-header {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }
        .budget-name {
            display: table-cell;
            font-weight: 700;
            font-size: 11px;
            color: #1F2937;
        }
        .budget-percent {
            display: table-cell;
            text-align: right;
            font-weight: bold;
            font-size: 11px;
        }
        .budget-bar {
            height: 6px;
            background: #E5E7EB;
            border-radius: 3px;
            overflow: hidden;
            margin: 6px 0;
        }
        .budget-fill { height: 100%; border-radius: 3px; }
        .budget-fill.good { background: #10B981; }
        .budget-fill.warning { background: #F59E0B; }
        .budget-fill.danger { background: #EF4444; }
        .budget-amounts { font-size: 9px; color: #6B7280; }

        /* Alerts */
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin: 12px 0;
            border-left: 4px solid;
        }
        .alert.warning { background: #FEF3C7; border-color: #F59E0B; color: #92400E; }
        .alert.info    { background: #DBEAFE; border-color: #3B82F6; color: #1E40AF; }
        .alert.success { background: #D1FAE5; border-color: #10B981; color: #065F46; }
        .alert-title { font-weight: bold; font-size: 10px; margin-bottom: 4px; }
        .alert-text  { font-size: 9px; line-height: 1.4; }

        /* Insight Box */
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
        .badge.success { background: #D1FAE5; color: #065F46; }
        .badge.danger  { background: #FEE2E2; color: #991B1B; }
        .badge.warning { background: #FEF3C7; color: #92400E; }
        .badge.neutral { background: #F3F4F6; color: #4B5563; }

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

        .page-break { page-break-after: always; }
    </style>
</head>
<body>
<!-- Watermark -->
<div class="watermark">CONFIDENTIAL</div>

<!-- Header -->
<div class="header">
    <h1>📅 ANNUAL FINANCIAL REPORT</h1>
    <div class="year-badge">{{ $data['year'] }}</div>
    <p class="period">{{ $data['start_date'] }} — {{ $data['end_date'] }}</p>
    <p class="user-info">{{ $user->name }}</p>
</div>

<!-- Net Worth Banner -->
<div class="net-worth-banner">
    <h3>💎 Year-End Net Worth</h3>
    <div class="amount">KES {{ number_format($data['net_worth'], 0) }}</div>
    <div class="breakdown">
        Assets: KES {{ number_format($data['total_balance'], 0) }} •
        Liabilities: KES {{ number_format($data['total_loans'], 0) }}
    </div>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
    <div class="summary-row">
        <div class="summary-cell income">
            <h3>💰 Total Income</h3>
            <div class="amount" style="color: #10B981;">KES {{ number_format($data['income'], 0) }}</div>
            <div class="sub-label">Full Year {{ $data['year'] }}</div>
        </div>
        <div class="summary-cell expense">
            <h3>💸 Total Expenses</h3>
            <div class="amount" style="color: #EF4444;">KES {{ number_format($data['expenses'], 0) }}</div>
            <div class="sub-label">Full Year {{ $data['year'] }}</div>
        </div>
        <div class="summary-cell savings">
            <h3>💰 Net Savings</h3>
            <div class="amount" style="color: {{ $data['net_flow'] >= 0 ? '#10B981' : '#EF4444' }};">
                {{ $data['net_flow'] >= 0 ? '+' : '' }}KES {{ number_format($data['net_flow'], 0) }}
            </div>
            <div class="sub-label">Full Year {{ $data['year'] }}</div>
        </div>
    </div>
</div>

<!-- Annual Stats -->
<div class="stats-grid">
    <div class="stats-row">
        <div class="stat-cell">
            <div class="stat-label">Transactions</div>
            <div class="stat-value">{{ $data['transaction_count'] }}</div>
        </div>
        <div class="stat-cell">
            <div class="stat-label">Savings Rate</div>
            <div class="stat-value" style="color: {{ $data['income'] > 0 && (($data['net_flow'] / $data['income']) * 100) >= 20 ? '#10B981' : '#F59E0B' }};">
                {{ $data['income'] > 0 ? number_format(($data['net_flow'] / $data['income']) * 100, 1) : 0 }}%
            </div>
        </div>
        <div class="stat-cell">
            <div class="stat-label">Monthly Avg Spending</div>
            <div class="stat-value">KES {{ number_format($data['expenses'] / 12, 0) }}</div>
        </div>
        <div class="stat-cell">
            <div class="stat-label">Accounts</div>
            <div class="stat-value">{{ $data['accounts']->count() }}</div>
        </div>
    </div>
</div>

<!-- Financial Health Alert -->
@php
    $savingsRate = $data['income'] > 0 ? (($data['net_flow'] / $data['income']) * 100) : 0;
@endphp

@if($savingsRate >= 20)
    <div class="alert success">
        <div class="alert-title">✅ Excellent Annual Financial Health!</div>
        <div class="alert-text">You saved {{ number_format($savingsRate, 1) }}% of your total income in {{ $data['year'] }} (KES {{ number_format($data['net_flow'], 0) }}). Outstanding discipline — you're building serious long-term wealth!</div>
    </div>
@elseif($savingsRate > 0)
    <div class="alert info">
        <div class="alert-title">💡 Positive Year — Room to Grow</div>
        <div class="alert-text">You saved {{ number_format($savingsRate, 1) }}% of your income across {{ $data['year'] }}. Financial experts recommend aiming for a 20–30% savings rate for optimal wealth building. Consider reviewing recurring expenses heading into the new year.</div>
    </div>
@else
    <div class="alert warning">
        <div class="alert-title">⚠️ Action Required: Annual Deficit</div>
        <div class="alert-text">Your total expenses exceeded income by KES {{ number_format(abs($data['net_flow']), 0) }} ({{ number_format(abs($savingsRate), 1) }}%) over {{ $data['year'] }}. Review your budget strategy and set savings targets for the coming year.</div>
    </div>
@endif

<!-- Month-by-Month Breakdown -->
@if(isset($data['monthly_breakdown']) && count($data['monthly_breakdown']) > 0)
    <div class="section">
        <div class="section-title">📆 Month-by-Month Breakdown</div>
        <table>
            <thead>
            <tr>
                <th>Month</th>
                <th style="text-align: right;">Income</th>
                <th style="text-align: right;">Expenses</th>
                <th style="text-align: right;">Net Flow</th>
                <th style="text-align: center;">Savings Rate</th>
                <th style="text-align: center;">Transactions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['monthly_breakdown'] as $month)
                @php
                    $monthSavingsRate = $month['income'] > 0
                        ? ($month['net_flow'] / $month['income']) * 100
                        : 0;
                @endphp
                <tr class="{{ $month['net_flow'] >= 0 ? 'positive' : 'negative' }}">
                    <td style="font-weight: 600;">{{ $month['month'] }}</td>
                    <td style="text-align: right; color: #059669;">KES {{ number_format($month['income'], 0) }}</td>
                    <td style="text-align: right; color: #DC2626;">KES {{ number_format($month['expenses'], 0) }}</td>
                    <td class="flow" style="text-align: right; font-weight: bold;">
                        {{ $month['net_flow'] >= 0 ? '+' : '' }}KES {{ number_format($month['net_flow'], 0) }}
                    </td>
                    <td style="text-align: center; color: {{ $monthSavingsRate >= 20 ? '#059669' : ($monthSavingsRate > 0 ? '#D97706' : '#DC2626') }}; font-weight: bold;">
                        {{ number_format($monthSavingsRate, 1) }}%
                    </td>
                    <td style="text-align: center; color: #6B7280;">{{ $month['transaction_count'] }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>📊 Full Year Total</td>
                <td style="text-align: right; color: #059669;">KES {{ number_format($data['income'], 0) }}</td>
                <td style="text-align: right; color: #DC2626;">KES {{ number_format($data['expenses'], 0) }}</td>
                <td style="text-align: right; color: {{ $data['net_flow'] >= 0 ? '#059669' : '#DC2626' }}; font-size: 12px;">
                    {{ $data['net_flow'] >= 0 ? '+' : '' }}KES {{ number_format($data['net_flow'], 0) }}
                </td>
                <td style="text-align: center; font-weight: bold; color: {{ $savingsRate >= 20 ? '#059669' : ($savingsRate > 0 ? '#D97706' : '#DC2626') }};">
                    {{ number_format($savingsRate, 1) }}%
                </td>
                <td style="text-align: center;">{{ $data['transaction_count'] }}</td>
            </tr>
            </tbody>
        </table>

        @php
            $bestMonth = collect($data['monthly_breakdown'])->sortByDesc('net_flow')->first();
            $worstMonth = collect($data['monthly_breakdown'])->sortBy('net_flow')->first();
        @endphp
        <div class="insight-box">
            <h4>📈 Monthly Performance Insights</h4>
            <p>
                <strong>Best month:</strong> {{ $bestMonth['month'] }} with a net flow of
                {{ $bestMonth['net_flow'] >= 0 ? '+' : '' }}KES {{ number_format($bestMonth['net_flow'], 0) }}.
                <strong>Most challenging month:</strong> {{ $worstMonth['month'] }} with
                {{ $worstMonth['net_flow'] >= 0 ? '+' : '' }}KES {{ number_format($worstMonth['net_flow'], 0) }}.
                Use these patterns to plan smarter budgets for the year ahead.
            </p>
        </div>
    </div>
@endif

<!-- Budget Performance (Annual) -->
@if(count($data['budget_performance']) > 0)
    <div class="section">
        <div class="section-title">🎯 Annual Budget Performance</div>
        @foreach($data['budget_performance'] as $budget)
            @php
                $percentage = $budget['percentage'];
                $status = $percentage > 100 ? 'danger' : ($percentage > 80 ? 'warning' : 'good');
            @endphp
            <div class="budget-item {{ $status }}">
                <div class="budget-header">
                    <div class="budget-name">{{ $budget['category'] }}</div>
                    <div class="budget-percent" style="color: {{ $percentage > 100 ? '#DC2626' : ($percentage > 80 ? '#D97706' : '#059669') }};">
                        {{ number_format($percentage, 1) }}%
                    </div>
                </div>
                <div class="budget-bar">
                    <div class="budget-fill {{ $status }}" style="width: {{ min($percentage, 100) }}%;"></div>
                </div>
                <div class="budget-amounts">
                    Annual Spent: KES {{ number_format($budget['spent'], 0) }} of KES {{ number_format($budget['budgeted'], 0) }} •
                    @if($budget['remaining'] >= 0)
                        Remaining: KES {{ number_format($budget['remaining'], 0) }}
                    @else
                        <span style="color: #DC2626; font-weight: bold;">Over by KES {{ number_format(abs($budget['remaining']), 0) }}</span>
                    @endif
                </div>
            </div>
        @endforeach

        @php
            $overBudgetCount = collect($data['budget_performance'])->where('percentage', '>', 100)->count();
        @endphp
        @if($overBudgetCount > 0)
            <div class="insight-box">
                <h4>💡 Annual Budget Management Tip</h4>
                <p>
                    You exceeded {{ $overBudgetCount }} budget category(ies) over the course of {{ $data['year'] }}.
                    Consider reviewing these categories and setting more realistic annual limits for the coming year, or identifying specific months that drove overspending.
                </p>
            </div>
        @endif
    </div>
@endif

<!-- Account Balances -->
<div class="section">
    <div class="section-title">💰 Year-End Account Overview</div>
    <table>
        <thead>
        <tr>
            <th>Account Name</th>
            <th style="text-align: center;">Health Status</th>
            <th style="text-align: right;">Year-End Balance</th>
            <th style="text-align: right;">% of Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['accounts'] as $account)
            <tr>
                <td style="font-weight: 600;">{{ $account->name }}</td>
                <td style="text-align: center;">
                    <span class="badge {{ $account->current_balance > 50000 ? 'success' : ($account->current_balance > 10000 ? 'neutral' : 'warning') }}">
                        {{ $account->current_balance > 50000 ? 'Excellent' : ($account->current_balance > 10000 ? 'Good' : 'Low') }}
                    </span>
                </td>
                <td style="text-align: right; font-weight: bold; font-size: 11px;">
                    KES {{ number_format($account->current_balance, 0) }}
                </td>
                <td style="text-align: right; color: #6B7280; font-size: 10px;">
                    {{ $data['total_balance'] > 0 ? number_format(($account->current_balance / $data['total_balance']) * 100, 1) : 0 }}%
                </td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">💎 Total Year-End Assets</td>
            <td style="text-align: right; color: #10B981; font-size: 12px;">
                KES {{ number_format($data['total_balance'], 0) }}
            </td>
            <td style="text-align: right; color: #6B7280; font-size: 10px;">100%</td>
        </tr>
        </tbody>
    </table>
</div>

<!-- Top Spending Categories (Annual) -->
@if($data['top_categories']->count() > 0)
    <div class="section">
        <div class="section-title">📊 Annual Spending Breakdown by Category</div>
        <table>
            <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 35%;">Category</th>
                <th style="text-align: center; width: 15%;">Transactions</th>
                <th style="text-align: right; width: 25%;">Total Amount</th>
                <th style="text-align: right; width: 20%;">% of Expenses</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['top_categories'] as $index => $category)
                <tr>
                    <td style="color: #6B7280;">{{ $index + 1 }}</td>
                    <td style="font-weight: 600;">{{ $category['category'] }}</td>
                    <td style="text-align: center; color: #6B7280;">{{ $category['count'] }}</td>
                    <td style="text-align: right; font-weight: bold; color: #DC2626;">
                        KES {{ number_format($category['amount'], 0) }}
                    </td>
                    <td style="text-align: right; color: #6B7280;">
                        {{ $data['expenses'] > 0 ? number_format(($category['amount'] / $data['expenses']) * 100, 1) : 0 }}%
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        @php
            $topCategory = $data['top_categories']->first();
            $topPercentage = $data['expenses'] > 0 ? ($topCategory['amount'] / $data['expenses']) * 100 : 0;
        @endphp
        <div class="insight-box">
            <h4>📈 Annual Spending Pattern Analysis</h4>
            <p>
                Your dominant spending category across {{ $data['year'] }} was <strong>{{ $topCategory['category'] }}</strong>,
                representing <strong>{{ number_format($topPercentage, 1) }}%</strong> of total annual expenses.
                You spent KES {{ number_format($topCategory['amount'], 0) }} across {{ $topCategory['count'] }} transactions in this category.
                Consider whether this allocation aligns with your financial goals for the coming year.
            </p>
        </div>
    </div>
@endif

<!-- Page Break -->
<div class="page-break"></div>

<!-- Recent Transactions (Annual — show up to 50) -->
@if($data['transactions']->count() > 0)
    <div class="section">
        <div class="section-title">📝 Transaction History (Latest 50)</div>
        <table>
            <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 36%;">Description</th>
                <th style="width: 20%;">Category</th>
                <th style="width: 12%; text-align: center;">Type</th>
                <th style="width: 20%; text-align: right;">Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['transactions']->take(50) as $transaction)
                <tr>
                    <td style="white-space: nowrap; color: #6B7280; font-size: 9px;">{{ $transaction->date->format('M d, Y') }}</td>
                    <td style="font-weight: 500; font-size: 10px;">{{ Str::limit($transaction->description, 38) }}</td>
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
        @if($data['transaction_count'] > 50)
            <p style="text-align: center; color: #6B7280; font-size: 9px; margin-top: 10px; font-style: italic;">
                Showing 50 of {{ $data['transaction_count'] }} total transactions in {{ $data['year'] }}
            </p>
        @endif
    </div>
@endif

<!-- Active Loans -->
@if($data['active_loans']->count() > 0)
    <div class="section">
        <div class="section-title">💳 Year-End Debt Management Overview</div>
        <table>
            <thead>
            <tr>
                <th>Loan Source</th>
                <th style="text-align: center;">Disbursed</th>
                <th style="text-align: center;">Status</th>
                <th style="text-align: center;">Due Date</th>
                <th style="text-align: right;">Balance</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['active_loans'] as $loan)
                @php
                    $daysUntilDue = $loan->due_date ? now()->diffInDays($loan->due_date, false) : null;
                    $isOverdue  = $daysUntilDue !== null && $daysUntilDue < 0;
                    $isDueSoon  = $daysUntilDue !== null && $daysUntilDue <= 30 && $daysUntilDue >= 0;
                @endphp
                <tr>
                    <td style="font-weight: 600;">{{ $loan->source }}</td>
                    <td style="text-align: center; color: #6B7280; font-size: 9px;">
                        {{ \Carbon\Carbon::parse($loan->disbursed_date)->format('M d, Y') }}
                    </td>
                    <td style="text-align: center;">
                        <span class="badge {{ $isOverdue ? 'danger' : ($isDueSoon ? 'warning' : 'neutral') }}">
                            {{ $isOverdue ? 'Overdue' : ($isDueSoon ? 'Due Soon' : 'Active') }}
                        </span>
                    </td>
                    <td style="text-align: center; color: {{ $isOverdue ? '#DC2626' : '#6B7280' }}; font-size: 9px;">
                        {{ $loan->due_date ? $loan->due_date->format('M d, Y') : 'No due date' }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #DC2626; font-size: 11px;">
                        KES {{ number_format($loan->balance, 0) }}
                    </td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4">💰 Total Outstanding Debt</td>
                <td style="text-align: right; color: #DC2626; font-size: 12px;">
                    KES {{ number_format($data['total_loans'], 0) }}
                </td>
            </tr>
            </tbody>
        </table>

        @php
            $debtToAssetsRatio = $data['total_balance'] > 0 ? ($data['total_loans'] / $data['total_balance']) * 100 : 0;
        @endphp
        <div class="insight-box">
            <h4>💡 Year-End Debt-to-Assets Analysis</h4>
            <p>
                Your debt-to-assets ratio at end of {{ $data['year'] }} is <strong>{{ number_format($debtToAssetsRatio, 1) }}%</strong>
                (KES {{ number_format($data['total_loans'], 0) }} debt vs KES {{ number_format($data['total_balance'], 0) }} assets).
                @if($debtToAssetsRatio > 50)
                    Prioritise debt repayment as a key financial goal for {{ $data['year'] + 1 }}.
                @elseif($debtToAssetsRatio > 30)
                    Your debt is manageable — focused repayment in the coming year will strengthen your position considerably.
                @else
                    Your debt is well-managed relative to your assets. Excellent year-end financial health!
                @endif
            </p>
        </div>
    </div>
@endif

<!-- Annual Financial Summary Insight -->
<div class="section">
    <div class="insight-box">
        <h4>🌟 {{ $data['year'] }} Year in Review</h4>
        <p>
            Over the course of {{ $data['year'] }}, you recorded <strong>{{ $data['transaction_count'] }} transactions</strong>
            across <strong>{{ $data['accounts']->count() }} accounts</strong>.
            Your total income was <strong>KES {{ number_format($data['income'], 0) }}</strong>
            against total expenses of <strong>KES {{ number_format($data['expenses'], 0) }}</strong>,
            yielding an annual savings rate of <strong>{{ number_format($savingsRate, 1) }}%</strong>.
            Your year-end net worth stands at <strong>KES {{ number_format($data['net_worth'], 0) }}</strong>.
        </p>
        <p style="margin-top: 6px;">
            Use this report to set intentional financial targets for {{ $data['year'] + 1 }}.
            Small, consistent improvements in your savings rate compound significantly over time.
        </p>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <p class="confidential">🔒 CONFIDENTIAL FINANCIAL DOCUMENT</p>
    <p>This annual report was generated on {{ now()->format('l, F d, Y \a\t h:i A') }}</p>
    <p>Report ID: ANN-{{ $user->id }}-{{ $data['year'] }}-{{ now()->format('Ymd-His') }}</p>
    <p style="margin-top: 8px;">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    <p style="margin-top: 5px; font-size: 8px; color: #9CA3AF;">
        This document contains highly confidential financial information. Store securely and do not share with unauthorized parties.
    </p>
</div>
</body>
</html>
