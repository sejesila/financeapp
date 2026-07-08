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

        .header { text-align: center; padding: 16px 0; margin-bottom: 14px; background: #6366F1; color: white; border-radius: 8px; page-break-after: avoid; }
        .header h1 { font-size: 20px; letter-spacing: 1px; font-weight: bold; }
        .header .period { font-size: 11px; margin-top: 4px; opacity: 0.95; }
        .header .user-info { font-size: 11px; margin-top: 6px; font-weight: 600; }

        .net-worth-banner { background: #6366F1; color: white; padding: 14px; text-align: center; margin: 14px 0; border-radius: 8px; page-break-after: avoid; }
        .net-worth-banner h3 { margin: 0 0 6px 0; font-size: 10px; opacity: 0.95; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .net-worth-banner .amount { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .net-worth-banner .breakdown { font-size: 9px; opacity: 0.9; margin-top: 4px; }

        .section { margin: 14px 0; page-break-inside: avoid; }
        .section-title { font-size: 12px; font-weight: bold; color: #1F2937; margin-bottom: 8px; padding: 6px 10px; background: #F9FAFB; border-left: 4px solid #8B5CF6; border-radius: 4px; page-break-after: avoid; }

        table { width: 100%; border-collapse: collapse; margin: 8px 0; background: white; }
        table th { background: #F3F4F6; padding: 7px 8px; text-align: left; font-size: 8px; color: #4B5563; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; border-bottom: 2px solid #E5E7EB; }
        table td { padding: 6px 8px; border-bottom: 1px solid #F3F4F6; font-size: 10px; }
        table tr.total-row { background: #F9FAFB; font-weight: bold; border-top: 2px solid #8B5CF6; }

        /* Dense label/value stat table — replaces card grids, stat grids, trend boxes */
        .stat-table td { padding: 6px 10px; font-size: 10px; border-bottom: 1px solid #F3F4F6; }
        .stat-table td.label { width: 25%; color: #6B7280; text-transform: uppercase; font-weight: 700; font-size: 8px; letter-spacing: 0.4px; }
        .stat-table td.value { width: 25%; font-weight: bold; color: #1F2937; }

        .alert { padding: 9px 12px; border-radius: 6px; margin: 10px 0; border-left: 4px solid; font-size: 10px; page-break-inside: avoid; }
        .alert.warning { background: #FEF3C7; border-color: #F59E0B; color: #92400E; }
        .alert.info    { background: #DBEAFE; border-color: #3B82F6; color: #1E40AF; }
        .alert.success { background: #D1FAE5; border-color: #10B981; color: #065F46; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #D1FAE5; color: #065F46; }
        .badge.danger  { background: #FEE2E2; color: #991B1B; }
        .badge.warning { background: #FEF3C7; color: #92400E; }
        .badge.neutral { background: #F3F4F6; color: #4B5563; }

        .footer { margin-top: 22px; padding-top: 12px; border-top: 2px solid #E5E7EB; text-align: center; font-size: 9px; color: #6B7280; page-break-before: avoid; }
        .footer .confidential { color: #DC2626; font-weight: bold; margin-bottom: 6px; }
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

    $budgetPerformance = collect($data['budget_performance'] ?? [])->take(10);
@endphp

<div class="watermark">CONFIDENTIAL</div>

<!-- Header -->
<div class="header">
    <h1>MONTHLY FINANCIAL REPORT</h1>
    <p class="period">{{ $startDate->format('F Y') }}</p>
    <p class="period" style="opacity: 0.8; font-size: 9px;">
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

<!-- Key Metrics -->
<table class="stat-table">
    <tr>
        <td class="label">Total Income</td>
        <td class="value" style="color: #10B981;">{{ $currency }} {{ number_format($income) }}</td>
        <td class="label">Total Expenses</td>
        <td class="value" style="color: #EF4444;">{{ $currency }} {{ number_format($expenses) }}</td>
    </tr>
    <tr>
        <td class="label">Net Cash Flow</td>
        <td class="value" style="color: {{ $netFlow >= 0 ? '#059669' : '#DC2626' }};">{{ $netFlow >= 0 ? '+' : '' }}{{ $currency }} {{ number_format($netFlow) }}</td>
        <td class="label">Surplus Rate</td>
        <td class="value" style="color: {{ $savingsRate >= 20 ? '#059669' : ($savingsRate >= 10 ? '#D97706' : '#DC2626') }};">{{ number_format($savingsRate, 1) }}%</td>
    </tr>
    <tr>
        <td class="label">Transactions</td>
        <td class="value">{{ $txCount }}</td>
        <td class="label">Daily Avg Spending</td>
        <td class="value">{{ $currency }} {{ number_format($dailyAvg) }}</td>
    </tr>
    <tr>
        <td class="label">Accounts</td>
        <td class="value">{{ $data['accounts']->count() }}</td>
        <td class="label">Income vs Last Month</td>
        <td class="value" style="color: {{ $incomeTrend === null ? '#9CA3AF' : ($incomeTrend >= 0 ? '#059669' : '#DC2626') }};">
            {{ $incomeTrend !== null ? ($incomeTrend >= 0 ? '+' : '') . number_format($incomeTrend, 1) . '%' : 'No prior data' }}
        </td>
    </tr>
    <tr>
        <td class="label">Budget Adherence</td>
        <td class="value" style="color: {{ $budgetsOver === 0 ? '#059669' : '#D97706' }};">{{ $budgetsUnder }}/{{ $budgetsTotal }} on track</td>
        <td class="label">Investment Income</td>
        <td class="value" style="color: #059669;">{{ $currency }} {{ number_format($investmentIncome['total']) }}</td>
    </tr>
</table>

<!-- Financial Health Alert -->
@if($savingsRate >= 20)
    <div class="alert success"><strong>&#10003; Excellent financial health</strong> &mdash; {{ number_format($savingsRate, 1) }}% surplus rate this month.</div>
@elseif($savingsRate >= 10)
    <div class="alert info"><strong>&#8505; Decent surplus</strong> &mdash; {{ number_format($savingsRate, 1) }}% this month. Aim for 20%+.</div>
@else
    <div class="alert warning"><strong>&#9888; Low surplus rate</strong> &mdash; {{ number_format($savingsRate, 1) }}%, below the recommended 20%.</div>
@endif

<!-- Budget Performance -->
@if($budgetPerformance->isNotEmpty())
    <div class="section">
        <div class="section-title">Budget Performance</div>
        <table>
            <thead>
            <tr>
                <th>Category</th>
                <th style="text-align: right;">Budgeted</th>
                <th style="text-align: right;">Spent</th>
                <th style="text-align: right;">Remaining</th>
                <th style="text-align: right;">%</th>
            </tr>
            </thead>
            <tbody>
            @foreach($budgetPerformance as $budget)
                @php
                    $pctColor = $budget['percentage'] >= 100 ? '#DC2626' : ($budget['percentage'] >= 80 ? '#D97706' : '#059669');
                @endphp
                <tr>
                    <td style="font-weight: 600;">{{ $budget['category'] }}
                        <span style="color:#9CA3AF; font-size:8px;">({{ $budget['has_budget'] ? 'set' : ($budget['is_new'] ? 'new' : $budget['months_used'] . 'mo avg') }})</span>
                    </td>
                    <td style="text-align: right;">{{ $currency }} {{ number_format($budget['budgeted']) }}</td>
                    <td style="text-align: right;">{{ $currency }} {{ number_format($budget['spent']) }}</td>
                    <td style="text-align: right; color: {{ $budget['remaining'] >= 0 ? '#059669' : '#DC2626' }};">
                        {{ $budget['remaining'] >= 0 ? '' : '-' }}{{ $currency }} {{ number_format(abs($budget['remaining'])) }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: {{ $pctColor }};">{{ number_format($budget['percentage'], 1) }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<!-- Account Balances -->
@if($data['accounts']->isNotEmpty())
    @php
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

<!-- Investment Income -->
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
                <td>Total</td>
                <td style="text-align: right; color: #059669;">{{ $currency }} {{ number_format($investmentIncome['total']) }}</td>
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
    </div>
@endif

<!-- Loan Activity This Month -->
@php
    $loansPaid   = $data['loans_paid_in_period']   ?? ['count' => 0, 'total' => 0, 'items' => []];
    $loansCleared = $data['loans_repaid_in_period'] ?? ['count' => 0, 'total' => 0, 'principal_total' => 0, 'items' => []];
@endphp
@if($loansPaid['count'] > 0 || $loansCleared['count'] > 0)
    <div class="section">
        <div class="section-title">Loan Activity This Month</div>
        <table class="stat-table">
            <tr>
                <td class="label">Repayments Made</td>
                <td class="value">{{ $loansPaid['count'] }}</td>
                <td class="label">Repayments Total</td>
                <td class="value" style="color: #059669;">{{ $currency }} {{ number_format($loansPaid['total']) }}</td>
            </tr>
            @if($loansCleared['count'] > 0)
                <tr>
                    <td class="label">Loans Fully Cleared</td>
                    <td class="value">{{ $loansCleared['count'] }}</td>
                    <td class="label">Principal Cleared</td>
                    <td class="value" style="color: #059669;">{{ $currency }} {{ number_format($loansCleared['principal_total']) }}</td>
                </tr>
            @endif
        </table>
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
    <p style="margin-top: 6px;">&#169; {{ now()->year }} Financial Report System. All rights reserved.</p>
    <p style="margin-top: 4px; font-size: 8px; color: #9CA3AF;">This document contains highly confidential financial information. Store securely and do not share with unauthorized parties.</p>
</div>

</body>
</html>
