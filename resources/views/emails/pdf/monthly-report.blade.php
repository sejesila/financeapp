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

        .header { text-align: center; padding: 20px 0; margin-bottom: 20px; background: linear-gradient(135deg, #8B5CF6 0%, #6366F1 100%); color: white; border-radius: 8px; }
        .header h1 { font-size: 24px; letter-spacing: 1px; font-weight: bold; }
        .header .period { font-size: 12px; margin-top: 5px; opacity: 0.95; }
        .header .user-info { font-size: 12px; margin-top: 8px; font-weight: 600; }

        .net-worth-banner { background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%); color: white; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
        .net-worth-banner h3 { margin: 0 0 10px 0; font-size: 11px; opacity: 0.95; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .net-worth-banner .amount { font-size: 28px; font-weight: bold; margin-bottom: 8px; }
        .net-worth-banner .breakdown { font-size: 10px; opacity: 0.9; margin-top: 8px; }

        .summary-grid { display: flex; gap: 10px; margin: 20px 0; }
        .summary-cell { flex: 1; padding: 18px; background: #FFFFFF; border: 2px solid #E5E7EB; border-radius: 8px; text-align: center; }
        .summary-cell.income  { border-left: 5px solid #10B981; }
        .summary-cell.expense { border-left: 5px solid #EF4444; }
        .summary-cell.savings { border-left: 5px solid #8B5CF6; }
        .summary-cell h3 { margin: 0 0 10px 0; font-size: 10px; color: #6B7280; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .summary-cell .amount { font-size: 20px; font-weight: bold; line-height: 1.2; }

        .stats-grid { display: flex; margin: 20px 0; background: #F9FAFB; padding: 12px; border-radius: 8px; }
        .stat-cell { flex: 1; text-align: center; padding: 10px 5px; }
        .stat-label { font-size: 8px; color: #6B7280; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .stat-value { font-size: 14px; font-weight: bold; color: #1F2937; margin-top: 4px; }

        .section { margin: 20px 0; }
        .section-title { font-size: 13px; font-weight: bold; color: #1F2937; margin-bottom: 12px; padding: 8px 12px; background: #F9FAFB; border-left: 4px solid #8B5CF6; border-radius: 4px; }

        .budget-item { background: #FAFAFA; padding: 12px; margin-bottom: 10px; border-left: 4px solid; border-radius: 4px; }
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

        .budget-grid { display: flex; flex-wrap: wrap; gap: 10px; }
        .budget-grid .budget-item { flex: 1 1 calc(50% - 5px); min-width: 0; margin-bottom: 0; }

        .insights-grid { display: flex; flex-wrap: wrap; gap: 10px; }
        .insights-grid .insight-box { flex: 1 1 calc(50% - 5px); min-width: 0; margin: 0; }

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

        .insight-box { background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #6366F1; }
        .insight-box h4 { margin: 0 0 8px 0; font-size: 11px; color: #4338CA; font-weight: bold; }
        .insight-box p  { margin: 4px 0; font-size: 10px; color: #4B5563; line-height: 1.5; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.success { background: #D1FAE5; color: #065F46; }
        .badge.danger  { background: #FEE2E2; color: #991B1B; }
        .badge.warning { background: #FEF3C7; color: #92400E; }
        .badge.neutral { background: #F3F4F6; color: #4B5563; }

        .trend-box { display: flex; margin: 0 0 16px 0; background: #F9FAFB; border-radius: 8px; padding: 12px; }
        .trend-cell { flex: 1; text-align: center; padding: 8px; border-right: 1px solid #E5E7EB; }
        .trend-cell:last-child { border-right: none; }
        .trend-label   { font-size: 8px; color: #6B7280; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 4px; }
        .trend-value   { font-size: 16px; font-weight: bold; margin-top: 4px; }
        .trend-subtext { font-size: 9px; color: #6B7280; margin-top: 2px; }

        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #E5E7EB; text-align: center; font-size: 9px; color: #6B7280; }
        .footer .confidential { color: #DC2626; font-weight: bold; margin-bottom: 8px; }
    </style>
</head>
<body>

<div class="watermark">CONFIDENTIAL</div>

<!-- Header -->
<div class="header">
    <h1>MONTHLY FINANCIAL REPORT</h1>
    <p class="period">May 2026</p>
    <p class="period" style="opacity: 0.8; font-size: 10px;">May 1, 2026 — May 31, 2026</p>
    <p class="user-info">Seje</p>
</div>

<!-- Net Worth Banner -->
<div class="net-worth-banner">
    <h3>Your Net Worth</h3>
    <div class="amount">KES 152,128</div>
    <div class="breakdown">Assets: KES 160,560 &bull; Liabilities: KES 8,432</div>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
    <div class="summary-cell income">
        <h3>Total Income</h3>
        <div class="amount" style="color: #10B981;">KES 11,978</div>
    </div>
    <div class="summary-cell expense">
        <h3>Total Expenses</h3>
        <div class="amount" style="color: #EF4444;">KES 8,147</div>
    </div>
    <div class="summary-cell savings">
        <h3>Net Savings</h3>
        <div class="amount" style="color: #10B981;">+KES 3,831</div>
    </div>
</div>

<!-- Monthly Stats -->
<div class="stats-grid">
    <div class="stat-cell">
        <div class="stat-label">Transactions</div>
        <div class="stat-value">24</div>
    </div>
    <div class="stat-cell">
        <div class="stat-label">Savings Rate</div>
        <div class="stat-value" style="color: #10B981;">32.0%</div>
    </div>
    <div class="stat-cell">
        <div class="stat-label">Daily Avg Spending</div>
        <div class="stat-value">KES 263</div>
    </div>
    <div class="stat-cell">
        <div class="stat-label">Accounts</div>
        <div class="stat-value">4</div>
    </div>
</div>

<!-- Trend Box -->
<div class="trend-box">
    <div class="trend-cell">
        <div class="trend-label">Income vs Last Month</div>
        <div class="trend-value" style="color: #DC2626;">-23.3%</div>
        <div class="trend-subtext">Prior month: KES 15,612</div>
    </div>
    <div class="trend-cell">
        <div class="trend-label">Budget Adherence</div>
        <div class="trend-value" style="color: #F59E0B;">5/7 on track</div>
        <div class="trend-subtext">2 categories over budget</div>
    </div>
</div>

<!-- Financial Health Alert -->
<div class="alert success">
    <div class="alert-title">&#10003; Excellent Financial Health!</div>
    <div class="alert-text">You saved 32.0% of your income this month (KES 3,831). You're on track for strong financial growth!</div>
</div>

<!-- Budget Performance -->
<div class="section">
    <div class="section-title">Budget Performance Analysis</div>
    <div class="budget-grid">
        <div class="budget-item danger">
            <div class="budget-header">
                <div class="budget-name">Food &amp; Dining</div>
                <div class="budget-percent" style="color: #DC2626;">177.1%</div>
            </div>
            <div class="budget-bar"><div class="budget-fill danger" style="width: 100%;"></div></div>
            <div class="budget-amounts">Spent: KES 1,960 of KES 1,107 &bull; Remaining: KES 0</div>
        </div>
        <div class="budget-item danger">
            <div class="budget-header">
                <div class="budget-name">Rent</div>
                <div class="budget-percent" style="color: #DC2626;">103.3%</div>
            </div>
            <div class="budget-bar"><div class="budget-fill danger" style="width: 100%;"></div></div>
            <div class="budget-amounts">Spent: KES 1,400 of KES 1,355 &bull; Remaining: KES 0</div>
        </div>
        <div class="budget-item warning">
            <div class="budget-header">
                <div class="budget-name">Entertainment</div>
                <div class="budget-percent" style="color: #D97706;">75.5%</div>
            </div>
            <div class="budget-bar"><div class="budget-fill warning" style="width: 75.5%;"></div></div>
            <div class="budget-amounts">Spent: KES 330 of KES 437 &bull; Remaining: KES 107</div>
        </div>
        <div class="budget-item warning">
            <div class="budget-header">
                <div class="budget-name">Transport</div>
                <div class="budget-percent" style="color: #D97706;">74.3%</div>
            </div>
            <div class="budget-bar"><div class="budget-fill warning" style="width: 74.3%;"></div></div>
            <div class="budget-amounts">Spent: KES 666 of KES 896 &bull; Remaining: KES 230</div>
        </div>
        <div class="budget-item good">
            <div class="budget-header">
                <div class="budget-name">Airtime &amp; Data</div>
                <div class="budget-percent" style="color: #059669;">68.0%</div>
            </div>
            <div class="budget-bar"><div class="budget-fill good" style="width: 68%;"></div></div>
            <div class="budget-amounts">Spent: KES 253 of KES 372 &bull; Remaining: KES 119</div>
        </div>
        <div class="budget-item good">
            <div class="budget-header">
                <div class="budget-name">Groceries</div>
                <div class="budget-percent" style="color: #059669;">48.7%</div>
            </div>
            <div class="budget-bar"><div class="budget-fill good" style="width: 48.7%;"></div></div>
            <div class="budget-amounts">Spent: KES 819 of KES 1,681 &bull; Remaining: KES 862</div>
        </div>
        <div class="budget-item good">
            <div class="budget-header">
                <div class="budget-name">Utilities</div>
                <div class="budget-percent" style="color: #059669;">46.3%</div>
            </div>
            <div class="budget-bar"><div class="budget-fill good" style="width: 46.3%;"></div></div>
            <div class="budget-amounts">Spent: KES 314 of KES 678 &bull; Remaining: KES 364</div>
        </div>
    </div>
    <div class="insight-box">
        <h4>Budget Management</h4>
        <p>You exceeded 2 budget categories this month. Focus on those areas next month to bring spending back in line.</p>
    </div>
</div>

<!-- Account Balances -->
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
        <tr>
            <td style="font-weight: 600;">Mpesa</td>
            <td style="text-align: center;"><span class="badge danger">Negative</span></td>
            <td style="text-align: right; font-weight: bold;">KES -38,641</td>
            <td style="text-align: right; color: #6B7280;">-24.1%</td>
        </tr>
        <tr>
            <td style="font-weight: 600;">I&amp;M Bank</td>
            <td style="text-align: center;"><span class="badge success">Healthy</span></td>
            <td style="text-align: right; font-weight: bold;">KES 285,563</td>
            <td style="text-align: right; color: #6B7280;">177.9%</td>
        </tr>
        <tr>
            <td style="font-weight: 600;">Cash</td>
            <td style="text-align: center;"><span class="badge danger">Negative</span></td>
            <td style="text-align: right; font-weight: bold;">KES -86,362</td>
            <td style="text-align: right; color: #6B7280;">-53.8%</td>
        </tr>
        <tr>
            <td style="font-weight: 600;">Airtel Money</td>
            <td style="text-align: center;"><span class="badge neutral">Zero</span></td>
            <td style="text-align: right; font-weight: bold;">KES 0</td>
            <td style="text-align: right; color: #6B7280;">0.0%</td>
        </tr>
        <tr class="total-row">
            <td colspan="2">Total Assets</td>
            <td style="text-align: right; color: #10B981;">KES 160,560</td>
            <td style="text-align: right; color: #6B7280;">100%</td>
        </tr>
        </tbody>
    </table>
</div>

<!-- Top Spending Categories -->
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
        <tr>
            <td style="color: #6B7280;">1</td>
            <td style="font-weight: 600;">Food &amp; Dining</td>
            <td style="text-align: center; color: #6B7280;">3</td>
            <td style="text-align: right; font-weight: bold; color: #DC2626;">KES 1,960</td>
            <td style="text-align: right; color: #6B7280;">24.1%</td>
        </tr>
        <tr>
            <td style="color: #6B7280;">2</td>
            <td style="font-weight: 600;">Rent</td>
            <td style="text-align: center; color: #6B7280;">1</td>
            <td style="text-align: right; font-weight: bold; color: #DC2626;">KES 1,400</td>
            <td style="text-align: right; color: #6B7280;">17.2%</td>
        </tr>
        <tr>
            <td style="color: #6B7280;">3</td>
            <td style="font-weight: 600;">Savings</td>
            <td style="text-align: center; color: #6B7280;">1</td>
            <td style="text-align: right; font-weight: bold; color: #DC2626;">KES 1,053</td>
            <td style="text-align: right; color: #6B7280;">12.9%</td>
        </tr>
        <tr>
            <td style="color: #6B7280;">4</td>
            <td style="font-weight: 600;">Groceries</td>
            <td style="text-align: center; color: #6B7280;">2</td>
            <td style="text-align: right; font-weight: bold; color: #DC2626;">KES 819</td>
            <td style="text-align: right; color: #6B7280;">10.1%</td>
        </tr>
        <tr>
            <td style="color: #6B7280;">5</td>
            <td style="font-weight: 600;">Clothing</td>
            <td style="text-align: center; color: #6B7280;">1</td>
            <td style="text-align: right; font-weight: bold; color: #DC2626;">KES 812</td>
            <td style="text-align: right; color: #6B7280;">10.0%</td>
        </tr>
        </tbody>
    </table>
    <div class="insight-box">
        <h4>Spending Pattern Analysis</h4>
        <p>Your dominant spending category was <strong>Food &amp; Dining</strong>, representing <strong>24.1%</strong> of total expenses — KES 1,960 across 3 transactions.</p>
    </div>
</div>

<!-- Key Insights -->
<div class="section">
    <div class="section-title">Key Insights</div>
    <div class="insights-grid">
        <div class="insight-box">
            <h4>&#128202; Average Daily Spending — KES 255</h4>
            <p>You spent an average of KES 255 per day</p>
        </div>
        <div class="insight-box">
            <h4>&#128201; Spending Decreased — -19.4%</h4>
            <p>You spent KES 1,967 less than last month</p>
        </div>
        <div class="insight-box">
            <h4>&#128184; Biggest Expense — KES 1,400</h4>
            <p>Monthly Rent Payment (Rent)</p>
        </div>
        <div class="insight-box">
            <h4>&#127919; Savings Rate — 32.0%</h4>
            <p>Great! You're saving well</p>
        </div>
    </div>
</div>

<!-- Active Loans -->
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
        <tr>
            <td style="font-weight: 600;">KCB Mpesa Loan</td>
            <td style="text-align: right;">KES 5,000</td>
            <td style="text-align: right; color: #DC2626; font-weight: bold;">KES 3,432</td>
            <td style="text-align: center; color: #6B7280;">Aug 4, 2026</td>
            <td style="text-align: center;"><span class="badge warning">Active</span></td>
        </tr>
        <tr>
            <td style="font-weight: 600;">Fuliza</td>
            <td style="text-align: right;">KES 5,000</td>
            <td style="text-align: right; color: #DC2626; font-weight: bold;">KES 5,000</td>
            <td style="text-align: center; color: #6B7280;">May 18, 2026</td>
            <td style="text-align: center;"><span class="badge warning">Active</span></td>
        </tr>
        <tr class="total-row">
            <td colspan="2">Total Loan Balance</td>
            <td style="text-align: right; color: #DC2626;">KES 8,432</td>
            <td colspan="2"></td>
        </tr>
        </tbody>
    </table>
</div>

<!-- Footer -->
<div class="footer">
    <p class="confidential">&#128274; CONFIDENTIAL FINANCIAL DOCUMENT</p>
    <p>Generated on May 4, 2026</p>
    <p>Report ID: MTH-2026-05-797341CA</p>
    <p style="margin-top: 8px;">&#169; 2026 Financial Report System. All rights reserved.</p>
    <p style="margin-top: 5px; font-size: 8px; color: #9CA3AF;">This document contains highly confidential financial information. Store securely and do not share with unauthorized parties.</p>
</div>

</body>
</html>
