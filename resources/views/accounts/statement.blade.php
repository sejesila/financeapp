<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $account->name }} – Statement {{ $from->format('M d') }}–{{ $to->format('M d, Y') }}</title>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            color: #1a1a1a;
            background: #fff;
            padding: 0;
        }

        .page {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 50px 60px;
            background: #fff;
        }

        /* ── Print button (screen only) ──────────────────────────────── */
        .print-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 24px;
        }

        .print-btn {
            background: #1e7e4a;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            font-size: 10pt;
            font-family: Arial, sans-serif;
            cursor: pointer;
            font-weight: 600;
        }
        .print-btn:hover { background: #165e36; }

        /* ── Header ──────────────────────────────────────────────────── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 36px;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 20px;
        }

        .header-left .date          { font-size: 11pt; margin-bottom: 18px; }
        .header-left .recipient-name { font-size: 12pt; font-weight: bold; }
        .header-left .account-number { font-size: 11pt; color: #444; }

        .logo-block { text-align: right; }

        .logo-box {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #0d2b5e;
            color: #fff;
            padding: 10px 18px;
            border-radius: 6px;
        }

        .logo-box .logo-icon {
            font-size: 22pt; font-style: italic; font-weight: 900;
            letter-spacing: -1px; font-family: Georgia, serif;
        }
        .logo-box .logo-text { font-size: 14pt; font-weight: 700; letter-spacing: 1px; }
        .logo-box .logo-sub  { font-size: 7pt; letter-spacing: 2px; opacity: .75; display: block; margin-top: 2px; }

        .salutation { margin-bottom: 16px; font-size: 11pt; }

        .re-line {
            font-weight: bold; text-decoration: underline;
            font-size: 12pt; margin-bottom: 14px;
        }

        .intro { font-size: 11pt; margin-bottom: 22px; line-height: 1.5; }

        /* ── Table ───────────────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5pt;
            margin-bottom: 28px;
        }

        thead { display: table-header-group; }
        tfoot  { display: table-row-group; }

        thead tr {
            background: #0d2b5e;
            color: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        thead th {
            padding: 9px 10px;
            font-weight: 600;
            font-size: 10pt;
            white-space: nowrap;
            text-align: left;
        }

        /* Numeric columns right-aligned (3rd col onwards, skipping Narration) */
        thead th:nth-child(n+3) { text-align: right; }

        tbody tr { border-bottom: 1px solid #dde3ec; page-break-inside: avoid; }
        tbody tr:last-child { border-bottom: none; }

        tbody td { padding: 7px 10px; vertical-align: top; }

        /* Same rule for body cells */
        tbody td:nth-child(n+3) { text-align: right; }

        .narration-cell { max-width: 200px; font-size: 10pt; color: #333; }

        .amount-inflow     { color: #1a5e2a; }
        .amount-withdrawal { color: #8b1a1a; }
        .amount-interest   { color: #1a3a6e; }
        .amount-balance    { font-weight: 600; }

        tr.opening-row td {
            font-style: italic; color: #555;
            background: #f7f9fb; border-bottom: 1px solid #c8d3e6;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }

        tr.pending-row td {
            color: #999; font-style: italic; background: #fffbf0;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }

        tr.pending-row .amount-inflow { color: #b8860b; }

        tr.pending-row .pending-badge {
            display: inline-block; font-size: 7.5pt;
            background: #fef3c7; color: #92400e;
            border: 1px solid #fcd34d; border-radius: 3px;
            padding: 1px 5px; vertical-align: middle;
            margin-left: 4px; font-style: normal;
        }

        tr.totals-row {
            background: #e8edf5;
            border-top: 2px solid #0d2b5e;
            font-weight: bold; font-size: 10.5pt;
            page-break-before: avoid;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }

        tr.totals-row td { padding: 9px 10px; }
        tr.totals-row td:nth-child(n+3) { text-align: right; }

        /* ── Closing ─────────────────────────────────────────────────── */
        .closing { font-size: 11pt; margin-bottom: 30px; line-height: 1.6; page-break-inside: avoid; }

        .sig-block { margin-bottom: 40px; page-break-inside: avoid; }

        .sig-italic {
            font-family: Georgia, serif; font-style: italic;
            font-size: 18pt; color: #0d2b5e; margin: 8px 0 4px;
        }

        .sig-bold { font-weight: bold; font-size: 11pt; text-decoration: underline; }

        /* ── Footer ──────────────────────────────────────────────────── */
        .footer {
            border-top: 1px solid #ccc; padding-top: 12px;
            font-size: 8.5pt; color: #666;
            display: flex; justify-content: space-between;
            align-items: flex-start; flex-wrap: wrap; gap: 8px;
            page-break-inside: avoid;
        }

        .footer a { color: #0d2b5e; text-decoration: none; }

        /* ── Print overrides ─────────────────────────────────────────── */
        @media print {
            .print-bar { display: none !important; }
            body { padding: 0; }
            .page { max-width: 100%; padding: 20px 30px 40px; }
            table { font-size: 10pt; }
        }

        @page { margin: 15mm 15mm 20mm; }
    </style>
</head>
<body>
<div class="page">

    {{-- ── Print button (screen only) ────────────────────────────────── --}}
    <div class="print-bar">
        <button class="print-btn" onclick="window.print()">🖨 Print / Save PDF</button>
    </div>

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="header">
        <div class="header-left">
            <p class="date">{{ $to->format('F d, Y') }}</p>
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

    {{-- ── Salutation ──────────────────────────────────────────────────── --}}
    <p class="salutation">Dear {{ explode(' ', $user->name)[0] }},</p>

    {{-- ── RE Line ─────────────────────────────────────────────────────── --}}
    <p class="re-line">RE: {{ strtoupper($account->name) }} - KES STATEMENT</p>

    {{-- ── Intro ───────────────────────────────────────────────────────── --}}
    <p class="intro">
        Kindly see below your {{ $account->name }} statement showing account status
        as at {{ $to->format('F d, Y') }}.
    </p>

    {{-- ── Statement Table ─────────────────────────────────────────────── --}}
    <table>
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
        <tr class="opening-row">
            <td>{{ $from->format('M d, Y') }}</td>
            <td class="narration-cell">Opening Balance</td>
            <td></td><td></td><td></td>
            <td class="amount-balance">{{ number_format($openingBalance, 2) }}</td>
        </tr>

        @forelse($rows as $row)
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
                <td class="amount-balance">
                    {{ number_format($row['running_balance'], 2) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="text-align:center; padding:20px; color:#888; font-style:italic;">
                    No transactions in this period.
                </td>
            </tr>
        @endforelse

        <tr class="totals-row">
            <td>{{ $to->format('M d, Y') }}</td>
            <td>Total (KES)</td>
            <td class="amount-inflow">
                {{ $totalInflow > 0 ? number_format($totalInflow, 2) : '' }}
            </td>
            <td class="amount-withdrawal">
                {{ $totalWithdrawal > 0 ? '(' . number_format($totalWithdrawal, 2) . ')' : '' }}
            </td>
            <td class="amount-interest">
                {{ $totalInterest > 0 ? number_format($totalInterest, 2) : '' }}
            </td>
            <td class="amount-balance">{{ number_format($closingBalance, 2) }}</td>
        </tr>
        </tbody>
    </table>

    {{-- ── Closing ──────────────────────────────────────────────────────── --}}
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

    {{-- ── Footer ──────────────────────────────────────────────────────── --}}
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
