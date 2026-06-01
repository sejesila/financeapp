<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $account->name }} – Annual Statement {{ $year }}</title>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            color: #1a1a1a;
            background: #fff;
        }

        .page {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 50px 60px;
            background: #fff;
        }

        /* ── Header ──────────────────────────────────────────────────── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 36px;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 20px;
        }

        .header-left .date            { font-size: 11pt; margin-bottom: 18px; }
        .header-left .recipient-name  { font-size: 12pt; font-weight: bold; }
        .header-left .account-number  { font-size: 11pt; color: #444; }

        .logo-block { text-align: right; }
        .logo-box {
            display: inline-flex; align-items: center; gap: 10px;
            background: #0d2b5e; color: #fff; padding: 10px 18px; border-radius: 6px;
        }
        .logo-box .logo-icon { font-size: 22pt; font-style: italic; font-weight: 900; letter-spacing: -1px; font-family: Georgia, serif; }
        .logo-box .logo-text { font-size: 14pt; font-weight: 700; letter-spacing: 1px; }
        .logo-box .logo-sub  { font-size: 7pt; letter-spacing: 2px; opacity: 0.75; display: block; margin-top: 2px; }

        /* ── Salutation / RE / Intro ──────────────────────────────────── */
        .salutation { margin-bottom: 16px; font-size: 11pt; }
        .re-line    { font-weight: bold; text-decoration: underline; font-size: 12pt; margin-bottom: 14px; }
        .intro      { font-size: 11pt; margin-bottom: 22px; line-height: 1.5; }

        /* ── Controls bar (screen only) ───────────────────────────────── */
        .controls-bar {
            display: flex; gap: 10px; align-items: flex-end;
            background: #f3f6fb; border: 1px solid #d0d9eb;
            border-radius: 8px; padding: 14px 18px; margin-bottom: 22px; flex-wrap: wrap;
        }
        .controls-bar label { font-size: 10pt; font-weight: 600; color: #444; display: block; margin-bottom: 4px; font-family: Arial, sans-serif; }
        .controls-bar select { font-size: 10pt; padding: 6px 10px; border: 1px solid #b0bcd0; border-radius: 5px; font-family: Arial, sans-serif; background: #fff; cursor: pointer; }
        .ctrl-btn { border: none; padding: 8px 20px; border-radius: 5px; font-size: 10pt; font-family: Arial, sans-serif; cursor: pointer; font-weight: 600; align-self: flex-end; }
        .ctrl-btn--navy  { background: #0d2b5e; color: #fff; }
        .ctrl-btn--navy:hover  { background: #1a4080; }
        .ctrl-btn--green { background: #1e7e4a; color: #fff; }
        .ctrl-btn--green:hover { background: #165e36; }

        /* ── Year summary table ───────────────────────────────────────── */
        table.year-summary { width: 100%; border-collapse: collapse; margin-bottom: 28px; font-size: 10.5pt; }
        table.year-summary thead tr { background: #0d2b5e; color: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.year-summary thead th { padding: 9px 10px; font-weight: 600; font-size: 10pt; text-align: right; }
        table.year-summary thead th:first-child { text-align: left; }
        table.year-summary tbody td { padding: 9px 10px; text-align: right; border-bottom: 1px solid #dde3ec; font-weight: 700; font-size: 11pt; }
        table.year-summary tbody td:first-child { text-align: left; font-weight: 400; font-style: italic; color: #444; }

        /* ── Shared amount colours ────────────────────────────────────── */
        .amount-inflow     { color: #1a5e2a; }
        .amount-withdrawal { color: #8b1a1a; }
        .amount-interest   { color: #1a3a6e; }
        .amount-balance    { font-weight: 700; }

        /* ── Month divider ────────────────────────────────────────────── */
        .month-divider { margin: 28px 0 10px; page-break-inside: avoid; page-break-before: auto; }
        .month-divider h3 { font-size: 11pt; font-weight: bold; color: #0d2b5e; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1.5px solid #0d2b5e; padding-bottom: 4px; }
        .month-divider .month-summary-line { font-size: 9.5pt; color: #555; font-style: italic; margin-top: 3px; font-family: Arial, sans-serif; }

        /* ── Per-month transaction table ──────────────────────────────── */
        table.txn-table { width: 100%; border-collapse: collapse; font-size: 10.5pt; margin-bottom: 6px; }
        table.txn-table thead { display: table-header-group; }
        table.txn-table thead tr { background: #e8edf5; color: #0d2b5e; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.txn-table thead th { padding: 7px 10px; text-align: left; font-weight: 600; font-size: 9.5pt; white-space: nowrap; }
        table.txn-table thead th:not(:first-child):not(:nth-child(2)) { text-align: right; }
        table.txn-table tbody tr { border-bottom: 1px solid #eef0f5; page-break-inside: avoid; }
        table.txn-table tbody tr:last-child { border-bottom: none; }
        table.txn-table tbody td { padding: 6px 10px; vertical-align: top; }
        table.txn-table tbody td:not(:first-child):not(:nth-child(2)) { text-align: right; }
        .narration-cell { max-width: 200px; font-size: 10pt; color: #333; }

        tr.pending-row td { color: #999; font-style: italic; background: #fffbf0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        tr.pending-row .amount-inflow { color: #b8860b; }
        tr.pending-row .pending-badge { display: inline-block; font-size: 7.5pt; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; border-radius: 3px; padding: 1px 5px; vertical-align: middle; margin-left: 4px; font-style: normal; }

        tr.month-totals { background: #f3f6fb; border-top: 1.5px solid #0d2b5e; font-weight: bold; font-size: 10pt; page-break-before: avoid; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        tr.month-totals td { padding: 7px 10px; }
        tr.month-totals td:not(:first-child):not(:nth-child(2)) { text-align: right; }

        .empty-month { font-size: 10pt; font-style: italic; color: #aaa; padding: 8px 10px 4px; }

        .future-month .month-divider h3   { color: #bbb; border-color: #ddd; }
        .future-month .month-summary-line { color: #ccc; }
        .future-month table.txn-table, .future-month .empty-month { opacity: 0.4; }

        /* ── Grand totals table ───────────────────────────────────────── */
        .grand-totals-wrap { margin-top: 30px; page-break-inside: avoid; }
        .grand-totals-wrap h3 { font-size: 11pt; font-weight: bold; color: #0d2b5e; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #0d2b5e; padding-bottom: 4px; margin-bottom: 0; }

        table.grand-table { width: 100%; border-collapse: collapse; font-size: 10.5pt; margin-bottom: 28px; }
        table.grand-table thead tr { background: #0d2b5e; color: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.grand-table thead th { padding: 9px 10px; font-weight: 600; font-size: 10pt; text-align: right; }
        table.grand-table thead th:first-child { text-align: left; }
        table.grand-table tbody tr { border-bottom: 1px solid #dde3ec; }
        table.grand-table tbody td { padding: 7px 10px; text-align: right; }
        table.grand-table tbody td:first-child { text-align: left; font-weight: 600; color: #1a1a1a; }
        table.grand-table tfoot tr { background: #e8edf5; border-top: 2px solid #0d2b5e; font-weight: bold; font-size: 10.5pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.grand-table tfoot td { padding: 9px 10px; text-align: right; }
        table.grand-table tfoot td:first-child { text-align: left; }

        /* ── Closing / Signature ──────────────────────────────────────── */
        .closing   { font-size: 11pt; margin-bottom: 30px; line-height: 1.6; page-break-inside: avoid; }
        .sig-block { margin-bottom: 40px; page-break-inside: avoid; }
        .sig-italic { font-family: Georgia, serif; font-style: italic; font-size: 18pt; color: #0d2b5e; margin: 8px 0 4px; }
        .sig-bold   { font-weight: bold; font-size: 11pt; text-decoration: underline; }

        /* ── Footer ──────────────────────────────────────────────────── */
        .footer { border-top: 1px solid #ccc; padding-top: 12px; font-size: 8.5pt; color: #666; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px; page-break-inside: avoid; }
        .footer a { color: #0d2b5e; text-decoration: none; }

        /* ── Print ───────────────────────────────────────────────────── */
        @media print {
            .controls-bar { display: none !important; }
            body { padding: 0; }
            .page { max-width: 100%; padding: 20px 30px 40px; }
        }
        @page { margin: 15mm 15mm 20mm; }
    </style>
</head>
<body>
<div class="page">

    {{-- Controls bar (screen only) --}}
    <form method="GET" action="{{ route('accounts.statement.annual', $account) }}" class="controls-bar">
        <div>
            <label for="year-sel">Year</label>
            <select id="year-sel" name="year" onchange="this.form.submit()">
                @foreach($availableYears as $y)
                <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <button type="button" class="ctrl-btn ctrl-btn--navy"
                onclick="window.location='{{ route('accounts.statement', $account) }}'">
            ← Monthly View
        </button>
        <button type="button" class="ctrl-btn ctrl-btn--green" onclick="window.print()">
            🖨 Print / Save PDF
        </button>
    </form>

    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            <p class="date">{{ now()->format('F d, Y') }}</p>
            <p class="recipient-name">{{ $user->name }}</p>
            <p class="account-number">Account Number: {{ $account->name }}</p>
        </div>
        <div class="logo-block">
            <div class="logo-box">
                <span class="logo-icon">E</span>
                <div>
                    <span class="logo-text">Etica<br>Capital</span>
                    <span class="logo-sub">imagine more</span>
                </div>
            </div>
        </div>
    </div>

    <p class="salutation">Dear {{ explode(' ', $user->name)[0] }},</p>
    <p class="re-line">RE: {{ strtoupper($account->name) }} - KES ANNUAL STATEMENT {{ $year }}</p>
    <p class="intro">
        Kindly find below your {{ $account->name }} annual statement for the period
        1 January {{ $year }} to 31 December {{ $year }},
        showing a month-by-month breakdown of all activity and interest earned.
    </p>

    {{-- Year summary --}}
    <table class="year-summary">
        <thead>
        <tr>
            <th></th>
            <th>Opening Balance</th>
            <th>Total Inflow</th>
            <th>Total Withdrawal</th>
            <th>Net Interest</th>
            <th>Closing Balance</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ $year }} Full Year</td>
            <td class="amount-balance">{{ number_format($annual['openingBalance'], 2) }}</td>
            <td class="amount-inflow">{{ $annual['totalInflow']     > 0 ? number_format($annual['totalInflow'],     2) : '—' }}</td>
            <td class="amount-withdrawal">{{ $annual['totalWithdrawal'] > 0 ? '(' . number_format($annual['totalWithdrawal'], 2) . ')' : '—' }}</td>
            <td class="amount-interest">{{ $annual['totalInterest']   > 0 ? number_format($annual['totalInterest'],   2) : '—' }}</td>
            <td class="amount-balance">{{ number_format($annual['closingBalance'], 2) }}</td>
        </tr>
        </tbody>
    </table>

    {{-- Month-by-month --}}
    @foreach($months as $month)
    <div class="{{ $month['isFuture'] ? 'future-month' : '' }}">

        <div class="month-divider">
            <h3>{{ $month['label'] }}</h3>
            <p class="month-summary-line">
                Opening: {{ number_format($month['openingBalance'], 2) }}
                @if($month['totalInflow']     > 0) &nbsp;·&nbsp; In: +{{ number_format($month['totalInflow'], 2) }} @endif
                @if($month['totalWithdrawal'] > 0) &nbsp;·&nbsp; Out: ({{ number_format($month['totalWithdrawal'], 2) }}) @endif
                @if($month['totalInterest']   > 0) &nbsp;·&nbsp; Interest: +{{ number_format($month['totalInterest'], 2) }} @endif
                &nbsp;·&nbsp; Closing: <strong>{{ number_format($month['closingBalance'], 2) }}</strong>
            </p>
        </div>

        @if($month['isFuture'])
        <p class="empty-month">Future month — no data available.</p>
        @elseif(count($month['rows']) === 0)
        <p class="empty-month">No transactions recorded for this month.</p>
        @else
        <table class="txn-table">
            <thead>
            <tr>
                <th>Date</th>
                <th>Narration</th>
                <th>Inflow</th>
                <th>Withdrawal</th>
                <th>Net Interest</th>
                <th>Running Balance</th>
            </tr>
            </thead>
            <tbody>
            @foreach($month['rows'] as $row)
            <tr class="{{ ($row['pending'] ?? false) ? 'pending-row' : '' }}">
                <td style="white-space:nowrap">{{ $row['date'] }}</td>
                <td class="narration-cell">
                    {{ $row['narration'] }}
                    @if($row['pending'] ?? false)
                    <span class="pending-badge">Pending</span>
                    @endif
                </td>
                <td class="amount-inflow">
                    @if($row['inflow'] !== null)
                    {{ number_format($row['inflow'], 2) }}
                    @elseif(($row['pending_amount'] ?? null) !== null)
                    {{ number_format($row['pending_amount'], 2) }}
                    @endif
                </td>
                <td class="amount-withdrawal">
                    {{ $row['withdrawal'] !== null ? '(' . number_format($row['withdrawal'], 2) . ')' : '' }}
                </td>
                <td class="amount-interest">
                    {{ $row['net_interest'] !== null ? number_format($row['net_interest'], 2) : '' }}
                </td>
                <td class="amount-balance">{{ number_format($row['running_balance'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="month-totals">
                <td style="white-space:nowrap">{{ $month['to']->format('M d, Y') }}</td>
                <td>Month Total (KES)</td>
                <td class="amount-inflow">{{ $month['totalInflow']     > 0 ? number_format($month['totalInflow'],     2) : '' }}</td>
                <td class="amount-withdrawal">{{ $month['totalWithdrawal'] > 0 ? '(' . number_format($month['totalWithdrawal'], 2) . ')' : '' }}</td>
                <td class="amount-interest">{{ $month['totalInterest']   > 0 ? number_format($month['totalInterest'],   2) : '' }}</td>
                <td class="amount-balance">{{ number_format($month['closingBalance'], 2) }}</td>
            </tr>
            </tbody>
        </table>
        @endif

    </div>
    @endforeach

    {{-- Grand summary table --}}
    <div class="grand-totals-wrap">
        <h3>Annual Grand Total – {{ $year }}</h3>
    </div>
    <table class="grand-table">
        <thead>
        <tr>
            <th>Month</th>
            <th>Inflow (KES)</th>
            <th>Withdrawal (KES)</th>
            <th>Net Interest (KES)</th>
            <th>Closing Balance (KES)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($months as $month)
        @if(!$month['isFuture'] && ($month['totalInflow'] + $month['totalWithdrawal'] + $month['totalInterest']) > 0)
        <tr>
            <td>{{ $month['label'] }}</td>
            <td class="amount-inflow">{{ $month['totalInflow']     > 0 ? number_format($month['totalInflow'],     2) : '—' }}</td>
            <td class="amount-withdrawal">{{ $month['totalWithdrawal'] > 0 ? '(' . number_format($month['totalWithdrawal'], 2) . ')' : '—' }}</td>
            <td class="amount-interest">{{ $month['totalInterest']   > 0 ? number_format($month['totalInterest'],   2) : '—' }}</td>
            <td class="amount-balance">{{ number_format($month['closingBalance'], 2) }}</td>
        </tr>
        @endif
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td>Total / Closing (KES)</td>
            <td class="amount-inflow">{{ $annual['totalInflow']     > 0 ? number_format($annual['totalInflow'],     2) : '—' }}</td>
            <td class="amount-withdrawal">{{ $annual['totalWithdrawal'] > 0 ? '(' . number_format($annual['totalWithdrawal'], 2) . ')' : '—' }}</td>
            <td class="amount-interest">{{ $annual['totalInterest']   > 0 ? number_format($annual['totalInterest'],   2) : '—' }}</td>
            <td class="amount-balance">{{ number_format($annual['closingBalance'], 2) }}</td>
        </tr>
        </tfoot>
    </table>

    {{-- Closing --}}
    <div class="closing">
        <p>Thank you for investing with us.</p>
        <br>
        <p>Yours Faithfully,</p>
    </div>

    <div class="sig-block">
        <p><strong>For: {{ $account->name }}</strong></p>
        <p class="sig-italic">Etica</p>
        <p class="sig-bold">Etica Capital.</p>
    </div>

    <div class="footer">
        <div>
            Etica Capital<br>
            Suite 4A and 4E, Allamano Centre, Consolata Shrine, Westlands, Nairobi.<br>
            P.O. Box 3245, 00621, Nairobi. Telephone +254 769 999 666
        </div>
        <div style="text-align:right">
            <a href="mailto:fundops@eticacap.com">fundops@eticacap.com</a><br>
            <a href="https://www.eticacap.com">www.eticacap.com</a>
        </div>
    </div>

</div>
</body>
</html>
