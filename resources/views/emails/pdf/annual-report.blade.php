<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Annual Financial Report</title>
    <style>
        @page { margin: 15mm 20mm 20mm 20mm; size: A4 portrait; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.5; color: #333; background: white; }

        .watermark { position: fixed; top: 280px; left: 40px; transform: rotate(-45deg); font-size: 80px; color: rgba(99, 102, 241, 0.08); z-index: 0; font-weight: bold; pointer-events: none; }

        .header { text-align: center; padding: 20px 0; margin-bottom: 20px; background: linear-gradient(135deg, #4338CA 0%, #6366F1 100%); color: white; border-radius: 8px; page-break-after: avoid; }
        .header h1 { font-size: 24px; letter-spacing: 1px; font-weight: bold; }
        .header .period { font-size: 12px; margin-top: 5px; opacity: 0.95; }
        .header .user-info { font-size: 12px; margin-top: 8px; font-weight: 600; }
        .header .year-badge { display: inline-block; background: rgba(255,255,255,0.2); padding: 2px 12px; border-radius: 12px; font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin-top: 6px; }

        .net-worth-banner { background: linear-gradient(135deg, #4338CA 0%, #6366F1 100%); color: white; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; page-break-after: avoid; }
        .net-worth-banner h3 { margin: 0 0 10px 0; font-size: 11px; opacity: 0.95; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .net-worth-banner .amount { font-size: 28px; font-weight: bold; margin-bottom: 8px; }
        .net-worth-banner .breakdown { font-size: 10px; opacity: 0.9; margin-top: 8px; }

        .summary-grid { display: flex; gap: 10px; margin: 20px 0; page-break-after: avoid; }
        .summary-cell { flex: 1; padding: 18px; background: #FFFFFF; border: 2px solid #E5E7EB; border-radius: 8px; text-align: center; }
        .summary-cell.income  { border-left: 5px solid #10B981; }
        .summary-cell.expense { border-left: 5px solid #EF4444; }
        .summary-cell.savings { border-left: 5px solid #6366F1; }
        .summary-cell h3 { margin: 0 0 10px 0; font-size: 10px; color: #6B7280; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .summary-cell .amount { font-size: 20px; font-weight: bold; line-height: 1.2; }
        .summary-cell .sub-label { font-size: 8px; color: #9CA3AF; margin-top: 4px; }

        .stats-grid { display: flex; margin: 20px 0; background: #F9FAFB; padding: 12px; border-radius: 8px; page-break-after: avoid; }
        .stat-cell { flex: 1; text-align: center; padding: 10px 5px; }
        .stat-label { font-size: 8px; color: #6B7280; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .stat-value { font-size: 14px; font-weight: bold; color: #1F2937; margin-top: 4px; }

        .section { margin: 20px 0; page-break-inside: avoid; }
        .section-title { font-size: 13px; font-weight: bold; color: #1F2937; margin-bottom: 12px; padding: 8px 12px; background: #F9FAFB; border-left: 4px solid #6366F1; border-radius: 4px; page-break-after: avoid; }

        table { width: 100%; border-collapse: collapse; margin: 12px 0; background: white; page-break-inside: avoid; }
        table th { background: #F3F4F6; padding: 10px 8px; text-align: left; font-size: 9px; color: #4B5563; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; border-bottom: 2px solid #E5E7EB; }
        table td { padding: 9px 8px; border-bottom: 1px solid #F3F4F6; font-size: 10px; }
        table tr.total-row { background: #F9FAFB; font-weight: bold; border-top: 2px solid #6366F1; }

        .alert { padding: 12px; border-radius: 6px; margin: 12px 0; border-left: 4px solid; page-break-inside: avoid; }
        .alert.warning { background: #FEF3C7; border-color: #F59E0B; color: #92400E; }
        .alert.info    { background: #DBEAFE; border-color: #3B82F6; color: #1E40AF; }
        .alert.success { background: #D1FAE5; border-color: #10B981; color: #065F46; }
        .alert-title { font-weight: bold; font-size: 10px; margin-bottom: 4px; }
        .alert-text  { font-size: 9px; line-height: 1.4; }

        .insight-box { background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #6366F1; page-break-inside: avoid; }
        .insight-box h4 { margin: 0 0 8px 0; font-size: 11px; color: #4338CA; font-weight: bold; }
        .insight-box p  { margin: 4px 0; font-size: 10px; color: #4B5563; line-height: 1.5; }

        /* Two-column insights grid */
        .insights-grid { display: flex; flex-wrap: wrap; gap: 10px; margin: 0; }
        .insight-card { width: calc(50% - 5px); background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); padding: 12px 14px; border-radius: 8px; border-left: 4px solid #6366F1; page-break-inside: avoid; box-sizing: border-box; }
        .insight-card h4 { margin: 0 0 4px 0; font-size: 10px; color: #4338CA; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; }
        .insight-card .insight-value { font-size: 14px; font-weight: bold; color: #1F2937; margin: 2px 0 4px 0; }
        .insight-card p { margin: 0; font-size: 9px; color: #4B5563; line-height: 1.4; }

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

<div class="watermark">CONFIDENTIAL</div>

@php
    /*
    |--------------------------------------------------------------------------
    | Data comes from ReportDataService::generateAnnualReport()
    |
    | $data['year']                    — int  (prior full year)
    | $data['start_date']              — pre-formatted string e.g. "Jan 01, 2024"
    | $data['end_date']                — pre-formatted string e.g. "Dec 31, 2024"
    | $data['income']                  — float
    | $data['expenses']                — float
    | $data['net_flow']                — float
    | $data['savings_rate']            — float  percentage
    | $data['net_worth']               — float  (total_balance - total_loans - total_client_funds)
    | $data['total_loans']             — float  (active loans balance)
    | $data['total_balance']           — float  (sum of active account balances)
    | $data['transaction_count']       — int
    | $data['profitable_months']       — int    (months with net_flow > 0)
    | $data['prior_period_income']     — float  (prior year income)
    | $data['income_trend']            — float|null  percentage change vs prior year
    | $data['monthly_breakdown']       — array[] {month, month_short, income, expenses, net_flow, savings_rate, transaction_count}
    | $data['best_month']              — array|null  {month, net_flow, ...}  (highest net_flow month)
    | $data['worst_month']             — array|null  {month, net_flow, ...}  (lowest net_flow month)
    | $data['loans_paid_in_period']    — array  {count, total, items[]}   (repayment transactions during year)
    | $data['loans_repaid_in_period']  — array  {count, total, principal_total, items[]}  (fully cleared loans)
    | $data['accounts']                — Collection<Account>
    | $data['top_categories']          — Collection  {category, amount, count}
    | $data['largest_transactions']    — Collection<Transaction>
    | $data['active_loans']            — Collection<Loan>
    | $data['insights']                — array[]  {icon, title, value, description}
    |--------------------------------------------------------------------------
    */
    $currency        = 'KES';
    $year            = $data['year']            ?? now()->subYear()->year;
    $income          = $data['income']          ?? 0;
    $expenses        = $data['expenses']        ?? 0;
    $netFlow         = $data['net_flow']        ?? 0;
    $savingsRate     = $data['savings_rate']    ?? 0;
    $netWorth        = $data['net_worth']       ?? 0;
    $totalLoans      = $data['total_loans']     ?? 0;
    $totalBal        = $data['total_balance']   ?? 0;
    $txCount         = $data['transaction_count']  ?? 0;
    $profitMonths    = $data['profitable_months']  ?? 0;
    $priorIncome     = $data['prior_period_income'] ?? 0;
    $incomeTrend     = $data['income_trend']    ?? null;
    $monthlyBreakdown = $data['monthly_breakdown'] ?? [];
    $bestMonth       = $data['best_month']      ?? null;
    $worstMonth      = $data['worst_month']     ?? null;

    /*
     * loans_paid_in_period  — transactions where category = 'Loan Repayment'
     * loans_repaid_in_period — Loan records with status='paid' and repaid_date in period
     * These are two distinct datasets; the trend-box uses loans_paid_in_period.
     */
    $loansPaid    = $data['loans_paid_in_period']   ?? ['count' => 0, 'total' => 0, 'items' => []];
    $loansCleared = $data['loans_repaid_in_period']  ?? ['count' => 0, 'total' => 0, 'principal_total' => 0, 'items' => []];

    $avgMonthlySpend = $expenses > 0 ? $expenses / 12 : 0;
    $avgMonthlySave  = $netFlow / 12;
@endphp

    <!-- Header -->
<div class="header">
    <h1>ANNUAL FINANCIAL REPORT</h1>
    <div class="year-badge">Financial Year {{ $year }}</div>
    <p class="period" style="margin-top: 6px;">Jan 1, {{ $year }} &mdash; Dec 31, {{ $year }}</p>
    <p class="user-info">{{ $user->name }}</p>
</div>

<!-- Net Worth Banner -->
<div class="net-worth-banner">
    <h3>Year-End Net Worth</h3>
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
        <div class="sub-label">Full Year {{ $year }}</div>
    </div>
    <div class="summary-cell expense">
        <h3>Total Expenses</h3>
        <div class="amount" style="color: #EF4444;">{{ $currency }} {{ number_format($expenses) }}</div>
        <div class="sub-label">Full Year {{ $year }}</div>
    </div>
    <div class="summary-cell savings">
        <h3>Net Savings</h3>
        <div class="amount" style="color: {{ $netFlow >= 0 ? '#10B981' : '#EF4444' }};">
            {{ $netFlow >= 0 ? '+' : '' }}{{ $currency }} {{ number_format($netFlow) }}
        </div>
        <div class="sub-label">Full Year {{ $year }}</div>
    </div>
</div>

<!-- Annual Stats -->
<div class="stats-grid">
    <div class="stat-cell">
        <div class="stat-label">Transactions</div>
        {{-- transaction_count is the full untruncated count (service truncates ->transactions to 50 but counts all) --}}
        <div class="stat-value">{{ $txCount }}</div>
    </div>
    <div class="stat-cell">
        <div class="stat-label">Savings Rate</div>
        <div class="stat-value" style="color: {{ $savingsRate >= 20 ? '#10B981' : ($savingsRate >= 10 ? '#F59E0B' : '#EF4444') }};">
            {{ number_format($savingsRate, 1) }}%
        </div>
    </div>
    <div class="stat-cell">
        <div class="stat-label">Monthly Avg Spending</div>
        <div class="stat-value">{{ $currency }} {{ number_format($avgMonthlySpend) }}</div>
    </div>
    <div class="stat-cell">
        <div class="stat-label">Accounts</div>
        <div class="stat-value">{{ $data['accounts']->count() }}</div>
    </div>
</div>

<!-- Annual Performance Trend -->
<div class="trend-box">
    <div class="trend-cell">
        <div class="trend-label">Income vs Prior Year</div>
        @if($incomeTrend !== null)
            <div class="trend-value" style="color: {{ $incomeTrend >= 0 ? '#059669' : '#DC2626' }};">
                {{ $incomeTrend >= 0 ? '+' : '' }}{{ number_format($incomeTrend, 1) }}%
            </div>
            <div class="trend-subtext">Prior year: {{ $currency }} {{ number_format($priorIncome) }}</div>
        @else
            <div class="trend-value" style="color: #9CA3AF;">No prior data</div>
        @endif
    </div>
    <div class="trend-cell">
        <div class="trend-label">Profitable Months</div>
        <div class="trend-value" style="color: {{ $profitMonths >= 10 ? '#059669' : ($profitMonths >= 6 ? '#F59E0B' : '#DC2626') }};">
            {{ $profitMonths }}/12
        </div>
        <div class="trend-subtext">months with positive cash flow</div>
    </div>
    <div class="trend-cell">
        {{-- loans_paid_in_period = repayment transactions (count & total from getLoanPaymentsInPeriod) --}}
        <div class="trend-label">Loan Repayments</div>
        @if($loansPaid['count'] > 0)
            <div class="trend-value" style="color: #059669;">{{ $currency }} {{ number_format($loansPaid['total']) }}</div>
            <div class="trend-subtext">{{ $loansPaid['count'] }} repayment{{ $loansPaid['count'] > 1 ? 's' : '' }}</div>
        @else
            <div class="trend-value" style="color: #9CA3AF;">None recorded</div>
        @endif
    </div>
</div>

<!-- Financial Health Alert -->
@if($savingsRate >= 20)
    <div class="alert success">
        <div class="alert-title">Excellent Annual Financial Health</div>
        <div class="alert-text">You saved {{ number_format($savingsRate, 1) }}% of your total income in {{ $year }} ({{ $currency }} {{ number_format($netFlow) }}). Outstanding discipline — you're building serious long-term wealth.</div>
    </div>
@elseif($savingsRate >= 10)
    <div class="alert info">
        <div class="alert-title">Good Progress on Savings</div>
        <div class="alert-text">You saved {{ number_format($savingsRate, 1) }}% of your income this year. Aim to push this above 20% for stronger long-term growth.</div>
    </div>
@else
    <div class="alert warning">
        <div class="alert-title">Low Savings Rate</div>
        <div class="alert-text">Your savings rate of {{ number_format($savingsRate, 1) }}% is below the recommended 20%. Review your spending categories to find areas to reduce.</div>
    </div>
@endif

<!-- Annual Performance Overview -->
<div class="insight-box">
    <h4>Annual Performance Overview</h4>
    <p>Throughout {{ $year }}, you had positive cash flow in <strong>{{ $profitMonths }} of 12 months</strong>, with an average monthly savings of {{ $currency }} {{ number_format($avgMonthlySave) }}.
        {{-- best_month and worst_month are the first() items from sortByDesc/sortBy on net_flow --}}
        @if($bestMonth) Your best month was <strong>{{ $bestMonth['month'] }}</strong> (net: {{ $currency }} {{ number_format($bestMonth['net_flow']) }}). @endif
        @if($worstMonth) Your toughest month was <strong>{{ $worstMonth['month'] }}</strong> (net: {{ $currency }} {{ number_format($worstMonth['net_flow']) }}). @endif
    </p>
</div>

<!-- Loans Fully Cleared During Year -->
{{-- loans_repaid_in_period = Loan records where status='paid' AND repaid_date falls within the year --}}
@if($loansCleared['count'] > 0)
    <div class="insight-box" style="border-left-color: #10B981; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);">
        <h4 style="color: #065F46;">&#10003; Loans Fully Cleared in {{ $year }}</h4>
        <p>You completely paid off <strong>{{ $loansCleared['count'] }} loan{{ $loansCleared['count'] > 1 ? 's' : '' }}</strong> during {{ $year }},
            with a combined principal of <strong>{{ $currency }} {{ number_format($loansCleared['principal_total']) }}</strong>
            (total repaid including interest: <strong>{{ $currency }} {{ number_format($loansCleared['total']) }}</strong>).
        </p>
    </div>
@endif

<!-- Month-by-Month Breakdown -->
{{-- monthly_breakdown is a plain array built in generateAnnualReport() via a loop over 12 months --}}
@if(!empty($monthlyBreakdown))
    <div class="section">
        <div class="section-title">Month-by-Month Breakdown</div>
        <table>
            <thead>
            <tr>
                <th>Month</th>
                <th style="text-align: right;">Income</th>
                <th style="text-align: right;">Expenses</th>
                <th style="text-align: right;">Net Flow</th>
                <th style="text-align: right;">Savings Rate</th>
                <th style="text-align: center;">Txns</th>
            </tr>
            </thead>
            <tbody>
            @foreach($monthlyBreakdown as $i => $month)
                @php $rowNet = $month['net_flow']; @endphp
                <tr @if($i % 2 === 0) style="background: #F9FAFB;" @endif>
                    {{-- month_short is pre-formatted as 'M' (e.g. "Jan", "Feb") --}}
                    <td style="font-weight: 600;">{{ $month['month_short'] }}</td>
                    <td style="text-align: right; color: #059669;">{{ number_format($month['income']) }}</td>
                    <td style="text-align: right; color: #DC2626;">{{ number_format($month['expenses']) }}</td>
                    <td style="text-align: right; font-weight: bold; color: {{ $rowNet >= 0 ? '#059669' : '#DC2626' }};">
                        {{ $rowNet >= 0 ? '+' : '' }}{{ number_format($rowNet) }}
                    </td>
                    <td style="text-align: right; color: {{ $month['savings_rate'] >= 20 ? '#059669' : ($month['savings_rate'] >= 0 ? '#F59E0B' : '#DC2626') }};">
                        {{ number_format($month['savings_rate'], 1) }}%
                    </td>
                    <td style="text-align: center; color: #6B7280;">{{ $month['transaction_count'] }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Full Year</td>
                <td style="text-align: right; color: #059669;">{{ number_format($income) }}</td>
                <td style="text-align: right; color: #DC2626;">{{ number_format($expenses) }}</td>
                <td style="text-align: right; color: {{ $netFlow >= 0 ? '#059669' : '#DC2626' }};">
                    {{ $netFlow >= 0 ? '+' : '' }}{{ number_format($netFlow) }}
                </td>
                <td style="text-align: right;">{{ number_format($savingsRate, 1) }}%</td>
                <td style="text-align: center;">{{ $txCount }}</td>
            </tr>
            </tbody>
        </table>
    </div>
@endif

<!-- Account Balances -->
{{-- $data['accounts'] is a Collection<Account> — active accounts only (is_active = true) --}}
@if($data['accounts']->isNotEmpty())
    <div class="section">
        <div class="section-title">Year-End Account Overview</div>
        <table>
            <thead>
            <tr>
                <th>Account Name</th>
                <th style="text-align: center;">Status</th>
                <th style="text-align: right;">Year-End Balance</th>
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
                <td colspan="2">Total Year-End Assets</td>
                <td style="text-align: right; color: #10B981;">{{ $currency }} {{ number_format($totalBal) }}</td>
                <td style="text-align: right; color: #6B7280;">100%</td>
            </tr>
            </tbody>
        </table>
    </div>
@endif

<!-- Top Spending Categories -->
{{-- $data['top_categories'] is a Collection of arrays: {category, amount, count} — top 5 by amount --}}
@if($data['top_categories']->isNotEmpty())
    <div class="section">
        <div class="section-title">Annual Spending Breakdown by Category</div>
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
    </div>
@endif

<!-- Largest Transactions -->
{{--
    $data['largest_transactions'] is a Collection<Transaction> (top 5 expenses, sorted by amount desc)
    Uses COALESCE(period_date, date) in the query, so prefer period_date when set.
--}}
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

<!-- Active Loans -->
{{-- $data['active_loans'] = Loan::where('status','active') — only currently outstanding loans --}}
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

<!-- Key Insights — two-column grid -->
{{--
    $data['insights'] is a plain array from generateInsights():
    {icon (emoji), title, value, description}
    The annual blade uses insight-card style (title + value on separate lines) for better layout.
--}}
@if(!empty($data['insights']))
    <div class="section">
        <div class="section-title">Key Insights</div>
        <div class="insights-grid">
            @foreach($data['insights'] as $insight)
                <div class="insight-card">
                    {{-- icon is an emoji string present in all insight arrays from the service --}}
                    <h4>{{ $insight['icon'] }} {{ $insight['title'] }}</h4>
                    <div class="insight-value">{{ $insight['value'] }}</div>
                    <p>{{ $insight['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endif

<!-- Year in Review -->
<div class="section">
    <div class="insight-box">
        <h4>{{ $year }} Year in Review</h4>
        <p>Over the course of {{ $year }}, you recorded <strong>{{ $txCount }} transactions</strong> across <strong>{{ $data['accounts']->count() }} accounts</strong>. Your total income was <strong>{{ $currency }} {{ number_format($income) }}</strong> against total expenses of <strong>{{ $currency }} {{ number_format($expenses) }}</strong>, yielding an annual savings rate of <strong>{{ number_format($savingsRate, 1) }}%</strong>. Your year-end net worth stands at <strong>{{ $currency }} {{ number_format($netWorth) }}</strong>.</p>
        <p style="margin-top: 6px;">Use this report to set intentional financial targets for {{ $year + 1 }}. Small, consistent improvements in your savings rate compound significantly over time.</p>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <p class="confidential">CONFIDENTIAL FINANCIAL DOCUMENT</p>
    <p>Generated on {{ now()->format('F j, Y') }}</p>
    {{-- Report ID hashes user->id and the year integer, matching the service's data keys --}}
    <p>Report ID: ANN-{{ $year }}-{{ strtoupper(substr(md5($user->id . $year), 0, 8)) }}</p>
    <p style="margin-top: 8px;">© {{ now()->year }} Financial Report System. All rights reserved.</p>
    <p style="margin-top: 5px; font-size: 8px; color: #9CA3AF;">This document contains highly confidential financial information. Store securely and do not share with unauthorized parties.</p>
</div>

</body>
</html>
