<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Financial Report</title>
    <style>
        @page { margin: 15mm 20mm 20mm 20mm; size: A4 portrait; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.5; color: #333; background: white; }

        .watermark { position: absolute; top: 280px; left: 40px; transform: rotate(-45deg); font-size: 80px; color: rgba(139, 92, 246, 0.06); z-index: 0; font-weight: bold; pointer-events: none; }

        .header { text-align: center; padding: 20px 0; margin-bottom: 20px; background: #6366F1; color: white; border-radius: 8px; page-break-after: avoid; }
        .header h1 { font-size: 24px; letter-spacing: 1px; font-weight: bold; }
        .header .period { font-size: 12px; margin-top: 5px; opacity: 0.95; }
        .header .user-info { font-size: 12px; margin-top: 8px; font-weight: 600; }

        .net-worth-banner { background: #6366F1; color: white; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; page-break-after: avoid; }
        .net-worth-banner h3 { margin: 0 0 10px 0; font-size: 11px; opacity: 0.95; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .net-worth-banner .amount { font-size: 28px; font-weight: bold; margin-bottom: 8px; }
        .net-worth-banner .breakdown { font-size: 10px; opacity: 0.9; margin-top: 8px; }

        /* ── Summary cards: table layout instead of flex (cheaper for the PDF renderer) ── */
        .summary-grid { display: table; width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 10px 0; margin: 20px 0 20px -10px; width: calc(100% + 10px); page-break-after: avoid; }
        .summary-grid .summary-row { display: table-row; }
        .summary-cell { display: table-cell; padding: 18px; background: #FFFFFF; border: 2px solid #E5E7EB; border-radius: 8px; text-align: center; vertical-align: top; }
        .summary-cell.income  { border-left: 5px solid #10B981; }
        .summary-cell.expense { border-left: 5px solid #EF4444; }
        .summary-cell.savings { border-left: 5px solid #8B5CF6; }
        .summary-cell h3 { margin: 0 0 10px 0; font-size: 10px; color: #6B7280; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .summary-cell .amount { font-size: 20px; font-weight: bold; line-height: 1.2; }

        /* ── Stat cells: table layout instead of flex ── */
        .stats-grid { display: table; width: 100%; table-layout: fixed; margin: 20px 0; background: #F9FAFB; padding: 12px; border-radius: 8px; page-break-after: avoid; }
        .stats-grid .stats-row { display: table-row; }
        .stat-cell { display: table-cell; text-align: center; padding: 10px 5px; vertical-align: top; }
        .stat-label { font-size: 8px; color: #6B7280; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .stat-value { font-size: 14px; font-weight: bold; color: #1F2937; margin-top: 4px; }

        .section { margin: 20px 0; page-break-inside: avoid; }
        .section-title { font-size: 13px; font-weight: bold; color: #1F2937; margin-bottom: 12px; padding: 8px 12px; background: #F9FAFB; border-left: 4px solid #8B5CF6; border-radius: 4px; page-break-after: avoid; }

        .budget-item { background: #FAFAFA; padding: 12px; border-left: 4px solid; border-radius: 4px; }
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

        /* Budget grid rendered as a CSS table instead of flex-wrap for cheaper layout */
        .budget-grid { display: table; width: 100%; border-collapse: separate; border-spacing: 0 10px; table-layout: fixed; }
        .budget-grid .budget-row { display: table-row; }
        .budget-grid .budget-cell { display: table-cell; width: 50%; padding-right: 10px; vertical-align: top; }
        .budget-grid .budget-cell:last-child { padding-right: 0; }

        .insights-grid { display: table; width: 100%; border-collapse: separate; border-spacing: 0 10px; table-layout: fixed; }
        .insights-grid .insight-row { display: table-row; }
        .insights-grid .insight-cell { display: table-cell; width: 50%; padding-right: 10px; vertical-align: top; }
        .insights-grid .insight-cell:last-child { padding-right: 0; }

        table { width: 100%; border-collapse: collapse; margin: 12px 0; background: white; }
        table th { background: #F3F4F6; padding: 10px 8px; text-align: left; font-size: 9px; color: #4B5563; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; border-bottom: 2px solid #E5E7EB; }
        table td { padding: 9px 8px; border-bottom: 1px solid #F3F4F6; font-size: 10px; }
        table tr.total-row { background: #F9FAFB; font-weight: bold; border-top: 2px solid #8B5CF6; }

        .alert { padding: 12px; border-radius: 6px; margin: 12px 0; border-left: 4px solid; }
        .alert.warning { background: #FEF3C7; border-color: #F59E0B; color: #92400E; }
        .alert.info    { background: #DBEAFE; border-color: #3B82F6; color: #1E40AF; }
        .alert.success { background: #D1FAE5; border-color: #10B981; color: #065F46; }
        .alert-title { font-weight: bold; font-size: 10px; margin-bottom: 4px; }
        .alert-text  { font-size: 9px; line-height: 1.4; }

        .insight-box { background: #EEF2FF; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #6366F1; }
        .insight-box h4 { margin: 0 0 8px 0; font-size: 11px; color: #4338CA; font-weight: bold; }
        .insight-box p  { margin: 4px 0; font-size: 10px; color: #4B5563; line-height: 1.5; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #D1FAE5; color: #065F46; }
        .badge.danger  { background: #FEE2E2; color: #991B1B; }
        .badge.warning { background: #FEF3C7; color: #92400E; }
        .badge.neutral { background: #F3F4F6; color: #4B5563; }

        /* ── Trend cells: table layout instead of flex ── */
        .trend-box { display: table; width: 100%; margin: 0 0 16px 0; background: #F9FAFB; border-radius: 8px; padding: 12px; table-layout: fixed; page-break-after: avoid; }
        .trend-box .trend-row { display: table-row; }
        .trend-cell { display: table-cell; text-align: center; padding: 8px; border-right: 1px solid #E5E7EB; vertical-align: top; }
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
    $savingsBalance = $data['savings_balance'] ?? 0;
    $investmentIncome = $data['investment_income'] ?? ['total' => 0, 'accounts' => []];

    $startDate = \Carbon\Carbon::parse($data['start_date']);
    $endDate   = \Carbon\Carbon::parse($data['end_date']);
    $days      = $startDate->diffInDays($endDate) + 1;
    $dailyAvg  = $days > 0 ? $expenses / $days : 0;

    // Cap budget items shown to keep DOM size (and render time) down
    $budgetPerformance = collect($data['budget_performance'] ?? [])->take(10);
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
        Savings Accounts: {{ $currency }} {{ number_format($savingsBalance) }}
        &bull; Active Loans: {{ $currency }} {{ number_format($totalLoans) }}
    </div>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
    <div class="summary-row">
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
</div>

<!-- Monthly Stats -->
<div class="stats-grid">
    <div class="stats-row">
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
</div>

<!-- Trend Box -->
<div class="trend-box">
    <div class="trend-row">
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
@if($budgetPerformance->isNotEmpty())
    <div class="section">
        <div class="section-title">Budget Performance Analysis</div>
        <div class="budget-grid">
            @foreach($budgetPerformance->chunk(2) as $pair)
                <div class="budget-row">
                    @foreach($pair as $budget)
                        @php
                            $pct         = min($budget['percentage'], 100);
                            $statusClass = $budget['percentage'] >= 100 ? 'danger' : ($budget['percentage'] >= 80 ? 'warning' : 'good');
                            $pctColor    = $budget['percentage'] >= 100 ? '#DC2626' : ($budget['percentage'] >= 80 ? '#D97706' : '#059669');
                        @endphp
                        <div class="budget-cell">
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
                                    ({{ $budget['has_budget'] ? 'set budget' : ($budget['is_new'] ? 'new category' : $budget['months_used'] . '-mo avg') }})
                                    &bull; {{ $budget['remaining'] >= 0
                ? 'Remaining: ' . $currency . ' ' . number_format($budget['remaining'])
                : 'Over by: ' . $currency . ' ' . number_format(abs($budget['remaining'])) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if($pair->count() === 1)
                        <div class="budget-cell"></div>
                    @endif
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
    @php
        // Substitute the net-worth-adjusted figure for Etica's raw balance everywhere it's used
        $adjustedAccounts = $data['accounts']->where('current_balance', '!=', 0)->map(function ($account) use ($netWorth) {
            $isEtica = strtolower($account->name) === 'etica';
            return (object) [
                'id'              => $account->id,
                'name'            => $account->name,
                'display_balance' => $isEtica ? $netWorth : $account->balance_as_at,
            ];
        });
        $adjustedTotal = $adjustedAccounts->sum('display_balance');
    @endphp
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
            @foreach($adjustedAccounts as $account)
                @php
                    $pct         = $adjustedTotal > 0 ? ($account->display_balance / $adjustedTotal) * 100 : 0;
                    $healthClass = $account->display_balance > 0 ? 'success' : ($account->display_balance < 0 ? 'danger' : 'neutral');
                    $healthLabel = $account->display_balance > 0 ? 'Healthy' : ($account->display_balance < 0 ? 'Negative' : 'Zero');
                @endphp
                <tr>
                    <td style="font-weight: 600;">{{ $account->name }}</td>
                    <td style="text-align: center;"><span class="badge {{ $healthClass }}">{{ $healthLabel }}</span></td>
                    <td style="text-align: right; font-weight: bold;">{{ $currency }} {{ number_format($account->display_balance) }}</td>
                    <td style="text-align: right; color: #6B7280;">{{ number_format($pct, 1) }}%</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Total Assets</td>
                <td style="text-align: right; color: #10B981;">{{ $currency }} {{ number_format($adjustedTotal) }}</td>
                <td style="text-align: right; color: #6B7280;">100%</td>
            </tr>
            </tbody>
        </table>
    </div>
@endif

<!-- Investment Income (Savings Interest) -->
@if($investmentIncome['total'] > 0)
    <div class="section">
        <div class="section-title">Investment Income (Savings Interest)</div>
        <table>
            <thead>
            <tr>
                <th>Savings Account</th>
                <th style="text-align: right;">Interest Earned</th>
            </tr>
            </thead>
            <tbody>
            @foreach($investmentIncome['accounts'] as $acct)
                <tr>
                    <td style="font-weight: 600;">{{ $acct['name'] }}</td>
                    <td style="text-align: right; color: #059669; font-weight: bold;">{{ $currency }} {{ number_format($acct['amount']) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total Investment Income</td>
                <td style="text-align: right; color: #059669;">{{ $currency }} {{ number_format($investmentIncome['total']) }}</td>
            </tr>
            </tbody>
        </table>
        <div class="insight-box">
            <h4>&#128176; Investment Income</h4>
            <p>Your savings accounts earned <strong>{{ $currency }} {{ number_format($investmentIncome['total']) }}</strong> in interest this month.</p>
        </div>
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
            <div class="insight-box" style="border-left-color: #10B981; background: #ECFDF5;">
                <h4 style="color: #065F46;">&#10003; Loans Fully Cleared</h4>
                <p>Congratulations! You fully repaid <strong>{{ $loansCleared['count'] }} loan{{ $loansCleared['count'] > 1 ? 's' : '' }}</strong> this month with a combined principal of <strong>{{ $currency }} {{ number_format($loansCleared['principal_total']) }}</strong>.</p>
            </div>
        @endif
    </div>
@endif
{{-- Salary → Savings Rate --}}
@php
    $salarySavings = $data['salary_savings_rate'] ?? [];
@endphp
@if(!empty($salarySavings))
    <div class="section">
        <div class="section-title">Salary Saved to Savings (within 48 hours)</div>
        <table>
            <thead>
            <tr>
                <th style="width: 25%;">Salary Date</th>
                <th style="text-align: right; width: 25%;">Salary Received</th>
                <th style="text-align: right; width: 25%;">Moved to Savings</th>
                <th style="text-align: right; width: 25%;">% Saved</th>
            </tr>
            </thead>
            <tbody>
            @foreach($salarySavings as $entry)
                <tr>
                    <td>{{ $entry['salary_date'] }}</td>
                    <td style="text-align: right;">{{ $currency }} {{ number_format($entry['salary_amount']) }}</td>
                    <td style="text-align: right; color: #059669; font-weight: bold;">{{ $currency }} {{ number_format($entry['saved_amount']) }}</td>
                    <td style="text-align: right; font-weight: bold;
                        color: {{ $entry['savings_percentage'] >= 20 ? '#059669' : ($entry['savings_percentage'] >= 10 ? '#D97706' : '#DC2626') }};">
                        {{ $entry['savings_percentage'] }}%
                    </td>
                </tr>
            @endforeach
            @if(count($salarySavings) > 1)
                @php $avgPct = round(collect($salarySavings)->avg('savings_percentage'), 1); @endphp
                <tr class="total-row">
                    <td colspan="3">Average Saved</td>
                    <td style="text-align: right; color: {{ $avgPct >= 20 ? '#059669' : '#D97706' }};">{{ $avgPct }}%</td>
                </tr>
            @endif
            </tbody>
        </table>
        @php $first = $salarySavings[0]; @endphp
        <div class="insight-box">
            <h4>&#128176; Salary Savings Discipline</h4>
            @if($first['saved_amount'] > 0)
                <p>You moved <strong>{{ $currency }} {{ number_format($first['saved_amount']) }}</strong>
                    (<strong>{{ $first['savings_percentage'] }}%</strong> of your salary) to savings
                    within 48 hours of receiving it. {{ $first['savings_percentage'] >= 20
                       ? 'Excellent habit — paying yourself first is the foundation of wealth building.'
                       : 'Consider increasing this to at least 20% of your salary for stronger long-term growth.' }}
                </p>
            @else
                <p>No transfers to savings were recorded within 48 hours of your salary this month.
                    Consider automating a savings transfer immediately after salary arrives.</p>
            @endif
        </div>
    </div>
@endif

<!-- Key Insights -->
@if(!empty($data['insights']))
    <div class="section">
        <div class="section-title">Key Insights</div>
        <div class="insights-grid">
            @foreach(collect($data['insights'])->chunk(2) as $pair)
                <div class="insight-row">
                    @foreach($pair as $insight)
                        <div class="insight-cell">
                            <div class="insight-box" style="margin: 0;">
                                <h4>{{ $insight['icon'] }} {{ $insight['title'] }} &mdash; {{ $insight['value'] }}</h4>
                                <p>{{ $insight['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                    @if($pair->count() === 1)
                        <div class="insight-cell"></div>
                    @endif
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
