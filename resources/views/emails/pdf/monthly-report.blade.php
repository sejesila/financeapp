<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Financial Report</title>
    <style>
        @page { margin: 15mm 20mm 20mm 20mm; size: A4 portrait; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.5; color: #333; background: white; }

        .watermark { position: fixed; top: 280px; left: 40px; transform: rotate(-45deg); font-size: 80px; color: rgba(139, 92, 246, 0.08); z-index: 0; font-weight: bold; pointer-events: none; }

        .header { text-align: center; padding: 20px 0; margin-bottom: 20px; background: linear-gradient(135deg, #8B5CF6 0%, #6366F1 100%); color: white; border-radius: 8px; page-break-after: avoid; }
        .header h1 { font-size: 24px; letter-spacing: 1px; font-weight: bold; }
        .header .period { font-size: 12px; margin-top: 5px; opacity: 0.95; }
        .header .user-info { font-size: 12px; margin-top: 8px; font-weight: 600; }

        .net-worth-banner { background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%); color: white; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; page-break-inside: avoid; page-break-after: avoid; }
        .net-worth-banner h3 { margin: 0 0 10px 0; font-size: 11px; opacity: 0.95; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .net-worth-banner .amount { font-size: 28px; font-weight: bold; margin-bottom: 8px; }
        .net-worth-banner .breakdown { font-size: 10px; opacity: 0.9; margin-top: 8px; }

        .summary-grid { display: flex; gap: 10px; margin: 20px 0; page-break-inside: avoid; page-break-after: avoid; }
        .summary-cell { flex: 1; padding: 18px; background: #FFFFFF; border: 2px solid #E5E7EB; border-radius: 8px; text-align: center; }
        .summary-cell.income  { border-left: 5px solid #10B981; }
        .summary-cell.expense { border-left: 5px solid #EF4444; }
        .summary-cell.savings { border-left: 5px solid #8B5CF6; }
        .summary-cell h3 { margin: 0 0 10px 0; font-size: 10px; color: #6B7280; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .summary-cell .amount { font-size: 20px; font-weight: bold; line-height: 1.2; }

        .stats-grid { display: flex; margin: 20px 0; background: #F9FAFB; padding: 12px; border-radius: 8px; page-break-inside: avoid; page-break-after: avoid; }
        .stat-cell { flex: 1; text-align: center; padding: 10px 5px; }
        .stat-label { font-size: 8px; color: #6B7280; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .stat-value { font-size: 14px; font-weight: bold; color: #1F2937; margin-top: 4px; }

        .section { margin: 20px 0; page-break-inside: avoid; }
        .section-title { font-size: 13px; font-weight: bold; color: #1F2937; margin-bottom: 12px; padding: 8px 12px; background: #F9FAFB; border-left: 4px solid #8B5CF6; border-radius: 4px; page-break-after: avoid; }

        .budget-item { background: #FAFAFA; padding: 12px; margin-bottom: 10px; border-left: 4px solid; border-radius: 4px; page-break-inside: avoid; }
        .budget-item.good    { border-color: #10B981; }
        .budget-item.warning { border-color: #F59E0B; }
        .budget-item.danger  { border-color: #EF4444; }
        .budget-header { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .budget-name { font-weight: 700; font-size: 11px; color: #1F2937; }
        .budget-percent { font-weight: bold; font-size: 11px; }
        .budget-bar { height: 6px; background: #E5E7EB; border-radius: 3px; overflow: hidden; margin: 6px 0; }
        .budget-fill { height: 100%; border-radius: 3px; }
        .budget-fill.good    { background: #10B981; }
        .budget-fill.warning { background: #F59E0B; }
        .budget-fill.danger  { background: #EF4444; }
        .budget-amounts { font-size: 9px; color: #6B7280; }

        .budget-grid { display: flex; flex-wrap: wrap; gap: 10px; page-break-inside: avoid; }
        .budget-grid .budget-item { flex: 1 1 calc(50% - 5px); min-width: 0; margin-bottom: 0; page-break-inside: avoid; }

        .insights-grid { display: flex; flex-wrap: wrap; gap: 10px; page-break-inside: avoid; }
        .insights-grid .insight-box { flex: 1 1 calc(50% - 5px); min-width: 0; margin: 0; page-break-inside: avoid; }

        table { width: 100%; border-collapse: collapse; margin: 12px 0; background: white; page-break-inside: avoid; }
        table th { background: #F3F4F6; padding: 10px 8px; text-align: left; font-size: 9px; color: #4B5563; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; border-bottom: 2px solid #E5E7EB; }
        table td { padding: 9px 8px; border-bottom: 1px solid #F3F4F6; font-size: 10px; }
        table tr.total-row { background: #F9FAFB; font-weight: bold; border-top: 2px solid #8B5CF6; }

        .alert { padding: 12px; border-radius: 6px; margin: 12px 0; border-left: 4px solid; page-break-inside: avoid; }
        .alert.warning { background: #FEF3C7; border-color: #F59E0B; color: #92400E; }
        .alert.info    { background: #DBEAFE; border-color: #3B82F6; color: #1E40AF; }
        .alert.success { background: #D1FAE5; border-color: #10B981; color: #065F46; }
        .alert-title { font-weight: bold; font-size: 10px; margin-bottom: 4px; }
        .alert-text  { font-size: 9px; line-height: 1.4; }

        .insight-box { background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #6366F1; page-break-inside: avoid; }
        .insight-box h4 { margin: 0 0 8px 0; font-size: 11px; color: #4338CA; font-weight: bold; }
        .insight-box p  { margin: 4px 0; font-size: 10px; color: #4B5563; line-height: 1.5; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #D1FAE5; color: #065F46; }
        .badge.danger  { background: #FEE2E2; color: #991B1B; }
        .badge.warning { background: #FEF3C7; color: #92400E; }
        .badge.neutral { background: #F3F4F6; color: #4B5563; }

        .trend-box { display: flex; margin: 0 0 16px 0; background: #F9FAFB; border-radius: 8px; padding: 12px; page-break-inside: avoid; }
        .trend-cell { flex: 1; text-align: center; padding: 8px; border-right: 1px solid #E5E7EB; }
        .trend-cell:last-child { border-right: none; }
        .trend-label   { font-size: 8px; color: #6B7280; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 4px; }
        .trend-value   { font-size: 16px; font-weight: bold; margin-top: 4px; }
        .trend-subtext { font-size: 9px; color: #6B7280; margin-top: 2px; }

        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #E5E7EB; text-align: center; font-size: 9px; color: #6B7280; page-break-before: avoid; }
        .footer .confidential { color: #DC2626; font-weight: bold; margin-bottom: 8px; }
    </style>
</head>
<body>

@php
    $currency    = 'KES';
    $income      = $data['income']              ?? 0;
    $expenses    = $data['expenses']            ?? 0;
    $netFlow     = $data['net_flow']            ?? 0;
    $savingsRate = $data['savings_rate']        ?? 0;
    $netWorth    = $data['net_worth']           ?? 0;
    $totalLoans  = $data['total_loans']         ?? 0;
    $totalBal    = $data['total_balance']       ?? 0;
    $priorIncome = $data['prior_period_income'] ?? 0;
    $incomeTrend = $data['income_trend']        ?? null;
    $budgetsOver  = $data['budgets_over']       ?? 0;
    $budgetsUnder = $data['budgets_under']      ?? 0;
    $budgetsTotal = $data['budgets_total']      ?? 0;
    $txCount      = $data['transaction_count']  ?? 0;

    $startDate = \Carbon\Carbon::parse($data['start_date']);
    $endDate   = \Carbon\Carbon::parse($data['end_date']);
    $days      = $startDate->diffInDays($endDate) + 1;
    $dailyAvg  = $days > 0 ? $expenses / $days : 0;
@endphp

<div class="watermark">CONFIDENTIAL</div>

<!-- Header -->
<div class="header">
    <h1>MONTHLY FINANCIAL REPORT</h1>
    <p class="period">{{ $startDate->format('F Y') }}</p>
    <p class="period" style="opacity: 0.8; font-size: 10px;">
        {{ $startDate->format('M j, Y') }} &mdash; {{ $endDate->format('M j, Y') }}
    </p>
    <p class="user-info">{{ $user->name }}</p>
</div>

<!-- Net Worth Banner -->
<div class="net-worth-banner">
    <h3>Your Net Worth</h3>
    <div class="amount">{{ $currency }} {{ number_format($netWorth) }}</div>
    <div class="breakdown">
        Assets: {{ $currency }} {{ number_format($netWorth) }}
        &bull; Liabilities: {{ $currency }} {{ number_format($totalLoans) }}
    </div>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
    <div class="summary-cell income">
        <h3>Total Income</h3>
        <div class="amount" style="color: #10B981;">{{ $currency }} {{ number_format($income) }}</div>
    </div>
    <div class="summary-cell expense">
        <h3>Total Expenses</h3>
        <div class="amount" style="color: #EF4444;">{{ $currency }} {{ number_format($expenses) }}</div>
    </div>
    <div class="summary-cell savings">
        <h3>Net Cash Flow</h3>
        <div class="amount" style="color: {{ $netFlow >= 0 ? '#10B981' : '#EF4444' }};">
            {{ $netFlow >= 0 ? '+' : '' }}{{ $currency }} {{ number_format($netFlow) }}
        </div>
    </div>
</div>

<!-- Monthly Stats -->
<div class="stats-grid">
    <div class="stat-cell">
        <div class="stat-label">Transactions</div>
        <div class="stat-value">{{ $txCount }}</div>
    </div>
    <div class="stat-cell">
        <div class="stat-label">Surplus Rate</div>
        <div class="stat-value" style="color: {{ $savingsRate >= 20 ? '#10B981' : ($savingsRate >= 10 ? '#F59E0B' : '#EF4444') }};">
            {{ number_format($savingsRate, 1) }}%
        </div>
    </div>
    <div class="stat-cell">
        <div class="stat-label">Daily Avg Spending</div>
        <div class="stat-value">{{ $currency }} {{ number_format($dailyAvg) }}</div>
    </div>
    <div class="stat-cell">
        <div class="stat-label">Accounts</div>
        <div class="stat-value">{{ $data['accounts']->count() }}</div>
    </div>
</div>

<!-- Trend Box -->
<div class="trend-box">
    <div class="trend-cell">
        <div class="trend-label">Income vs Last Month</div>
        @if($incomeTrend !== null)
            <div class="trend-value" style="color: {{ $incomeTrend >= 0 ? '#059669' : '#DC2626' }};">
                {{ $incomeTrend >= 0 ? '+' : '' }}{{ number_format($incomeTrend, 1) }}%
            </div>
            <div class="trend-subtext">Prior month: {{ $currency }} {{ number_format($priorIncome) }}</div>
        @else
            <div class="trend-value" style="color: #9CA3AF;">No prior data</div>
        @endif
    </div>
    <div class="trend-cell">
        <div class="trend-label">Budget Adherence</div>
        <div class="trend-value" style="color: {{ $budgetsOver === 0 ? '#059669' : '#F59E0B' }};">
            {{ $budgetsUnder }}/{{ $budgetsTotal }} on track
        </div>
        <div class="trend-subtext">{{ $budgetsOver }} {{ $budgetsOver === 1 ? 'category' : 'categories' }} over budget</div>
    </div>
</div>

<!-- Financial Health Alert -->
@if($savingsRate >= 20)
    <div class="alert success">
        <div class="alert-title">&#10003; Excellent Financial Health!</div>
        <div class="alert-text">Your surplus rate is {{ number_format($savingsRate, 1) }}% of income this month ({{ $currency }} {{ number_format($netFlow) }} net cash flow). You're on track for strong financial growth!</div>
    </div>
@elseif($savingsRate >= 10)
    <div class="alert info">
        <div class="alert-title">&#8505; Decent Surplus This Month</div>
        <div class="alert-text">Your surplus rate is {{ number_format($savingsRate, 1) }}% of income. Aim for 20%+ for stronger long-term results.</div>
    </div>
@else
    <div class="alert warning">
        <div class="alert-title">&#9888; Low Surplus Rate</div>
        <div class="alert-text">Your surplus rate of {{ number_format($savingsRate, 1) }}% is below the recommended 20%. Review your spending to find areas to cut back.</div>
    </div>
@endif

<!-- Budget Performance -->
@if(!empty($data['budget_performance']))
    <div class="section">
        <div class="section-title">Budget Performance Analysis</div>
        <div class="budget-grid">
            @foreach($data['budget_performance'] as $budget)
                @php
                    $pct         = min($budget['percentage'], 100);
                    $statusClass = $budget['percentage'] >= 100 ? 'danger' : ($budget['percentage'] >= 80 ? 'warning' : 'good');
                    $pctColor    = $budget['percentage'] >= 100 ? '#DC2626' : ($budget['percentage'] >= 80 ? '#D97706' : '#059669');
                @endphp
                <div class="budget-item {{ $statusClass }}">
                    <div class="budget-header">
                        <div class="budget-name">{{ $budget['category'] }}</div>
                        <div class="budget-percent" style="color: {{ $pctColor }};">{{ number_format($budget['percentage'], 1) }}%</div>
                    </div>
                    <div class="budget-bar">
                        <div class="budget-fill {{ $statusClass }}" style="width: {{ $pct }}%;"></div>
                    </div>
                    <div class="budget-amounts">
                        Spent: {{ $currency }} {{ number_format($budget['spent']) }}
                        of {{ $currency }} {{ number_format($budget['budgeted']) }}
                        ({{ $budget['is_new'] ? 'new category' : $budget['months_used'] . '-mo avg' }})
                        &bull; {{ $budget['remaining'] >= 0
        ? 'Remaining: ' . $currency . ' ' . number_format($budget['remaining'])
        : 'Over by: ' . $currency . ' ' . number_format(abs($budget['remaining'])) }}
                    </div>
                </div>
            @endforeach
        </div>
        <div class="insight-box">
            <h4>Budget Management</h4>
            @if($budgetsOver === 0)
                <p>Excellent job this month! All your budgets are on track with no overspending.</p>
            @else
                <p>You exceeded {{ $budgetsOver }} budget {{ $budgetsOver === 1 ? 'category' : 'categories' }} this month. Focus on those areas next month to bring spending back in line.</p>
            @endif
        </div>
    </div>
@endif

<!-- Account Balances -->
@if($data['accounts']->isNotEmpty())
    <div class="section">
        <div class="section-title">Account Overview</div>
        <table>
            <thead>
            <tr>
                <th>Account Name</th>
                <th style="text-align: center;">Status</th>
                <th style="text-align: right;">Current Balance</th>
                <th style="text-align: right;">% of Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['accounts'] as $account)
                @php
                    $pct         = $totalBal > 0 ? ($account->current_balance / $totalBal) * 100 : 0;
                    $healthClass = $account->current_balance > 0 ? 'success' : ($account->current_balance < 0 ? 'danger' : 'neutral');
                    $healthLabel = $account->current_balance > 0 ? 'Healthy' : ($account->current_balance < 0 ? 'Negative' : 'Zero');
                @endphp
                <tr>
                    <td style="font-weight: 600;">{{ $account->name }}</td>
                    <td style="text-align: center;"><span class="badge {{ $healthClass }}">{{ $healthLabel }}</span></td>
                    <td style="text-align: right; font-weight: bold;">{{ $currency }} {{ number_format($account->current_balance) }}</td>
                    <td style="text-align: right; color: #6B7280;">{{ number_format($pct, 1) }}%</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Total Assets</td>
                <td style="text-align: right; color: #10B981;">{{ $currency }} {{ number_format($totalBal) }}</td>
                <td style="text-align: right; color: #6B7280;">100%</td>
            </tr>
            </tbody>
        </table>
    </div>
@endif

<!-- Top Spending Categories -->
@if($data['top_categories']->isNotEmpty())
    <div class="section">
        <div class="section-title">Spending Breakdown by Category</div>
        <table>
            <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Category</th>
                <th style="text-align: center; width: 15%;">Transactions</th>
                <th style="text-align: right; width: 25%;">Total Amount</th>
                <th style="text-align: right; width: 15%;">% of Expenses</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['top_categories'] as $i => $cat)
                @php $catPct = $expenses > 0 ? ($cat['amount'] / $expenses) * 100 : 0; @endphp
                <tr>
                    <td style="color: #6B7280;">{{ $i + 1 }}</td>
                    <td style="font-weight: 600;">{{ $cat['category'] }}</td>
                    <td style="text-align: center; color: #6B7280;">{{ $cat['count'] }}</td>
                    <td style="text-align: right; font-weight: bold; color: #DC2626;">{{ $currency }} {{ number_format($cat['amount']) }}</td>
                    <td style="text-align: right; color: #6B7280;">{{ number_format($catPct, 1) }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @php $topCat = $data['top_categories']->first(); @endphp
        @if($topCat)
            <div class="insight-box">
                <h4>Spending Pattern Analysis</h4>
                @php $topPct = $expenses > 0 ? ($topCat['amount'] / $expenses) * 100 : 0; @endphp
                <p>Your dominant spending category was <strong>{{ $topCat['category'] }}</strong>, representing <strong>{{ number_format($topPct, 1) }}%</strong> of total expenses &mdash; {{ $currency }} {{ number_format($topCat['amount']) }} across {{ $topCat['count'] }} transactions.</p>
            </div>
        @endif
    </div>
@endif

<!-- Largest Transactions -->
@if($data['largest_transactions']->isNotEmpty())
    <div class="section">
        <div class="section-title">Largest Individual Expenses</div>
        <table>
            <thead>
            <tr>
                <th style="width: 15%;">Date</th>
                <th style="width: 40%;">Description</th>
                <th style="width: 25%;">Category</th>
                <th style="text-align: right; width: 20%;">Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['largest_transactions'] as $txn)
                <tr>
                    <td style="color: #6B7280;">{{ \Carbon\Carbon::parse($txn->period_date ?? $txn->date)->format('M j, Y') }}</td>
                    <td style="font-weight: 500;">{{ $txn->description }}</td>
                    <td style="color: #6B7280;">{{ $txn->category->name }}</td>
                    <td style="text-align: right; font-weight: bold; color: #DC2626;">-{{ $currency }} {{ number_format($txn->amount) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<!-- Loan Repayments This Month -->
@php
    $loansPaid   = $data['loans_paid_in_period']   ?? ['count' => 0, 'total' => 0, 'items' => []];
    $loansCleared = $data['loans_repaid_in_period'] ?? ['count' => 0, 'total' => 0, 'items' => []];
@endphp
@if($loansPaid['count'] > 0 || $loansCleared['count'] > 0)
    <div class="section">
        <div class="section-title">Loan Activity This Month</div>
        @if($loansPaid['count'] > 0)
            <div class="insight-box">
                <h4>&#128176; Repayments Made</h4>
                <p>You made <strong>{{ $loansPaid['count'] }} loan repayment{{ $loansPaid['count'] > 1 ? 's' : '' }}</strong> totalling <strong>{{ $currency }} {{ number_format($loansPaid['total']) }}</strong> this month.</p>
            </div>
        @endif
        @if($loansCleared['count'] > 0)
            <div class="insight-box" style="border-left-color: #10B981; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);">
                <h4 style="color: #065F46;">&#10003; Loans Fully Cleared</h4>
                <p>Congratulations! You fully repaid <strong>{{ $loansCleared['count'] }} loan{{ $loansCleared['count'] > 1 ? 's' : '' }}</strong> this month with a combined principal of <strong>{{ $currency }} {{ number_format($loansCleared['principal_total']) }}</strong>.</p>
            </div>
        @endif
    </div>
@endif

<!-- Key Insights -->
@if(!empty($data['insights']))
    <div class="section">
        <div class="section-title">Key Insights</div>
        <div class="insights-grid">
            @foreach($data['insights'] as $insight)
                <div class="insight-box">
                    <h4>{{ $insight['icon'] }} {{ $insight['title'] }} &mdash; {{ $insight['value'] }}</h4>
                    <p>{{ $insight['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endif

<!-- Active Loans -->
@if($data['active_loans']->isNotEmpty())
    <div class="section">
        <div class="section-title">Active Loans</div>
        <table>
            <thead>
            <tr>
                <th>Loan Source</th>
                <th style="text-align: right;">Principal</th>
                <th style="text-align: right;">Balance</th>
                <th style="text-align: center;">Due Date</th>
                <th style="text-align: center;">Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['active_loans'] as $loan)
                <tr>
                    <td style="font-weight: 600;">{{ $loan->source }}</td>
                    <td style="text-align: right;">{{ $currency }} {{ number_format($loan->principal_amount) }}</td>
                    <td style="text-align: right; color: #DC2626; font-weight: bold;">{{ $currency }} {{ number_format($loan->balance) }}</td>
                    <td style="text-align: center; color: #6B7280;">{{ \Carbon\Carbon::parse($loan->due_date)->format('M j, Y') }}</td>
                    <td style="text-align: center;"><span class="badge warning">{{ ucfirst($loan->status) }}</span></td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Total Loan Balance</td>
                <td style="text-align: right; color: #DC2626;">{{ $currency }} {{ number_format($totalLoans) }}</td>
                <td colspan="2"></td>
            </tr>
            </tbody>
        </table>
    </div>
@endif

<!-- Footer -->
<div class="footer">
    <p class="confidential">CONFIDENTIAL FINANCIAL DOCUMENT</p>
    <p>Generated on {{ now()->format('F j, Y') }}</p>
    <p>Report ID: MTH-{{ $startDate->format('Y-m') }}-{{ strtoupper(substr(md5($user->id . $data['start_date']), 0, 8)) }}</p>
    <p style="margin-top: 8px;">&#169; {{ now()->year }} Financial Report System. All rights reserved.</p>
    <p style="margin-top: 5px; font-size: 8px; color: #9CA3AF;">This document contains highly confidential financial information. Store securely and do not share with unauthorized parties.</p>
</div>

</body>
</html>
