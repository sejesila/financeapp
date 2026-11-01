@php
    $sacco      = $data['sacco'] ?? null;
    $retirement = $data['retirement'] ?? null;
    $cur        = $currency ?? 'KES';
@endphp

@if($sacco)
    <div class="section">
        <div class="section-title">Sacco — {{ $sacco['name'] }}</div>

        <table class="stat-table">
            <tr>
                <td class="label">Share Capital</td>
                <td class="value">{{ $cur }} {{ number_format($sacco['ledgers']['shares']['balance']) }}</td>
                <td class="label">Deposits</td>
                <td class="value">{{ $cur }} {{ number_format($sacco['ledgers']['deposits']['balance']) }}</td>
            </tr>
            <tr>
                <td class="label">Mobile Wallet</td>
                <td class="value">{{ $cur }} {{ number_format($sacco['ledgers']['wallet']['balance']) }}</td>
                <td class="label">Loans Outstanding</td>
                <td class="value" style="color: #DC2626;">{{ $cur }} {{ number_format($sacco['loans_outstanding']) }}</td>
            </tr>
            <tr>
                <td class="label">Net Position</td>
                <td class="value" style="color: {{ $sacco['net_position'] >= 0 ? '#059669' : '#DC2626' }};">
                    {{ $sacco['net_position'] < 0 ? '-' : '' }}{{ $cur }} {{ number_format(abs($sacco['net_position'])) }}
                </td>
                <td class="label">Borrowing Power</td>
                <td class="value">{{ $cur }} {{ number_format($sacco['borrowing_power']) }}</td>
            </tr>
            <tr>
                <td class="label">Deposits Added ({{ $periodLabel ?? 'Period' }})</td>
                <td class="value" style="color: #059669;">{{ $cur }} {{ number_format($sacco['period']['deposits_added']) }}</td>
                <td class="label">Dividends ({{ $periodLabel ?? 'Period' }})</td>
                <td class="value" style="color: #059669;">{{ $cur }} {{ number_format($sacco['period']['dividends']) }}</td>
            </tr>
            <tr>
                <td class="label">Loan Repaid ({{ $periodLabel ?? 'Period' }})</td>
                <td class="value">
                    {{ $cur }} {{ number_format($sacco['period']['loan_repaid']) }}
                    <span style="display:block; font-size:8px; font-weight:normal; color:#9CA3AF;">
                        interest {{ $cur }} {{ number_format($sacco['period']['loan_interest']) }}
                        &bull; principal {{ $cur }} {{ number_format($sacco['period']['loan_principal']) }}
                    </span>
                </td>
                <td class="label">Wallet Withdrawals ({{ $periodLabel ?? 'Period' }})</td>
                <td class="value">{{ $cur }} {{ number_format($sacco['period']['withdrawals']) }}</td>
            </tr>
        </table>

        @if(!empty($sacco['loans']))
            <table>
                <thead>
                <tr>
                    <th>Sacco Loan</th>
                    <th style="text-align: right;">Principal</th>
                    <th style="text-align: right;">Instalment</th>
                    <th style="text-align: center;">Paid</th>
                    <th style="text-align: right;">Outstanding</th>
                </tr>
                </thead>
                <tbody>
                @foreach($sacco['loans'] as $l)
                    <tr>
                        <td style="font-weight: 600;">{{ $l['name'] }}</td>
                        <td style="text-align: right;">{{ $cur }} {{ number_format($l['principal']) }}</td>
                        <td style="text-align: right;">{{ $cur }} {{ number_format($l['instalment']) }}</td>
                        <td style="text-align: center; color: #6B7280;">{{ $l['paid_count'] }}/{{ $l['term_months'] }}</td>
                        <td style="text-align: right; color: #DC2626; font-weight: bold;">{{ $cur }} {{ number_format($l['outstanding']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif

@if($retirement)
    <div class="section">
        <div class="section-title">Retirement Funds</div>
        <table>
            <thead>
            <tr>
                <th>Fund</th>
                <th style="text-align: right;">Employee</th>
                <th style="text-align: right;">Employer</th>
                <th style="text-align: right;">Interest</th>
                <th style="text-align: right;">Balance</th>
            </tr>
            </thead>
            <tbody>
            @foreach($retirement['funds'] as $f)
                <tr>
                    <td style="font-weight: 600;">{{ $f['name'] }}
                        <span style="color:#9CA3AF; font-size:8px;">({{ $f['type'] }})</span></td>
                    <td style="text-align: right;">{{ $cur }} {{ number_format($f['employee'] + $f['voluntary']) }}</td>
                    <td style="text-align: right;">{{ $cur }} {{ number_format($f['employer']) }}</td>
                    <td style="text-align: right; color: #059669;">{{ $cur }} {{ number_format($f['interest']) }}</td>
                    <td style="text-align: right; font-weight: bold;">{{ $cur }} {{ number_format($f['balance']) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total ({{ $periodLabel ?? 'Period' }} contributions)</td>
                <td style="text-align: right;">{{ $cur }} {{ number_format($retirement['total']['employee'] + $retirement['total']['voluntary']) }}</td>
                <td style="text-align: right;">{{ $cur }} {{ number_format($retirement['total']['employer']) }}</td>
                <td style="text-align: right; color: #059669;">{{ $cur }} {{ number_format($retirement['total']['interest']) }}</td>
                <td style="text-align: right; color: #059669;">{{ $cur }} {{ number_format($retirement['total']['balance']) }}</td>
            </tr>
            </tbody>
        </table>
        <p style="font-size: 8px; color: #9CA3AF; margin-top: 4px;">
            Contribution columns show the period only. Balance is as at the end of the period.
        </p>
    </div>
@endif
