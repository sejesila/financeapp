@php
    $s = $loanStats;
    $cards = [
        ['Outstanding',        'KES '.number_format($s['totalOutstanding'], 0),        'Active loans only',                         'from-indigo-50 to-indigo-100 border-indigo-200'],
        ['Principal Disbursed','KES '.number_format($s['totalPrincipal'], 0),          'Loans given in period',                     'from-blue-50 to-blue-100 border-blue-200'],
        ['Cash Collected',     'KES '.number_format($s['totalReceivedAllTime'], 0),    'Incl. partial payments',                    'from-teal-50 to-teal-100 border-teal-200'],
        ['Repaid (Closed)',    'KES '.number_format($s['totalRepaid'], 0),             'Principal + interest, closed only',         'from-green-50 to-green-100 border-green-200'],
        ['Interest Earned',    'KES '.number_format($s['totalInterest'], 0),           'Incl. active rollovers',                    'from-purple-50 to-purple-100 border-purple-200'],
        ['Transaction Costs',  'KES '.number_format($s['totalTransactionCosts'], 0),   'Fees on disbursement',                      'from-orange-50 to-orange-100 border-orange-200'],
        ['Net Interest',       'KES '.number_format($s['netInterest'], 0),             'Interest − costs − referrer share owed (KES '.number_format($s['referrerCut'], 0).')', 'from-emerald-50 to-emerald-100 border-emerald-200'],
        ['Avg Interest Rate',  number_format($s['avgInterestRate'], 1).' %',           'Closed loans',                              'from-pink-50 to-pink-100 border-pink-200'],
        ['Repayment Rate',     number_format($s['repaymentRate'], 1).' %',             $s['repaidCount'].' of '.$s['disbursedCount'].' loans', 'from-yellow-50 to-yellow-100 border-yellow-200'],
    ];

    $periods = ['all' => 'All Time', 'this_month' => 'This Month', 'last_month' => 'Last Month',
                'this_year' => 'This Year', 'last_year' => 'Last Year', 'custom' => 'Custom'];
@endphp

<div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6" x-data="{ custom: {{ $statsPeriod === 'custom' ? 'true' : 'false' }} }">
    <div class="p-4 sm:p-6">
        <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-3">Loan Performance — {{ $statsLabel }}</h3>

        <div class="flex flex-wrap items-center gap-2 mb-4">
            @foreach($periods as $key => $label)
                @php $on = $statsPeriod === $key; @endphp
                @if($key === 'custom')
                    <button type="button" @click="custom = !custom"
                            class="px-3 py-1.5 rounded-full text-xs font-medium border {{ $on ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">{{ $label }}</button>
                @else
                    <a href="{{ request()->fullUrlWithQuery(['stats_period' => $key, 'stats_start' => null, 'stats_end' => null]) }}"
                       class="px-3 py-1.5 rounded-full text-xs font-medium border {{ $on ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">{{ $label }}</a>
                @endif
            @endforeach
        </div>

        <form x-show="custom" x-cloak method="GET" class="mb-4 flex flex-wrap items-center gap-2">
            <input type="hidden" name="stats_period" value="custom">
            <input type="date" name="stats_start" value="{{ $statsStart?->format('Y-m-d') }}" class="rounded-md border-gray-300 shadow-sm text-sm">
            <input type="date" name="stats_end" value="{{ $statsEnd?->format('Y-m-d') }}" class="rounded-md border-gray-300 shadow-sm text-sm">
            <button type="submit" class="px-3 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Apply</button>
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            @foreach($cards as [$title, $value, $sub, $classes])
                <div class="bg-gradient-to-br {{ $classes }} rounded-lg border p-3 sm:p-4">
                    <p class="text-xs sm:text-sm font-medium text-gray-500">{{ $title }}</p>
                    <p class="text-base sm:text-lg font-semibold text-gray-900">{{ $value }}</p>
                    <p class="text-[10px] sm:text-xs leading-tight text-gray-500">{{ $sub }}</p>
                </div>
            @endforeach

            @include('loans-given.partials.yield-card', ['loanYield' => $s['loanYield']])
        </div>
    </div>
</div>
