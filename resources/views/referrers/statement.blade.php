<x-app-layout>
    @php
        // Colour key for the float and "everything" tables. Full class names are
        // spelled out so Tailwind picks them up.
        $kindStyles = [
            'principal'   => ['label' => 'Principal',        'row' => 'bg-indigo-50',  'border' => 'border-indigo-400',  'badge' => 'bg-indigo-100 text-indigo-800'],
            'interest'    => ['label' => 'Interest',         'row' => 'bg-green-50',   'border' => 'border-green-400',   'badge' => 'bg-green-100 text-green-800'],
            'transfer_in' => ['label' => 'Transfer in',      'row' => 'bg-sky-50',     'border' => 'border-sky-400',     'badge' => 'bg-sky-100 text-sky-800'],
            'remitted'    => ['label' => 'Sent back to you', 'row' => 'bg-orange-50',  'border' => 'border-orange-400',  'badge' => 'bg-orange-100 text-orange-800'],
            'loan_given'  => ['label' => 'Loan given',       'row' => 'bg-rose-50',    'border' => 'border-rose-400',    'badge' => 'bg-rose-100 text-rose-800'],
            'commission'  => ['label' => 'Commission',       'row' => 'bg-purple-50',  'border' => 'border-purple-400',  'badge' => 'bg-purple-100 text-purple-800'],
            'fee'         => ['label' => 'Fee',              'row' => 'bg-gray-50',    'border' => 'border-gray-300',    'badge' => 'bg-gray-200 text-gray-700'],
        ];

        // Commission ledger and write-off recovery ledger use their own kinds.
        $commissionStyles = [
            'earned'    => ['label' => 'Earned',      'row' => 'bg-green-50',  'border' => 'border-green-400',  'badge' => 'bg-green-100 text-green-800'],
            'paid'      => ['label' => 'Paid out',    'row' => 'bg-orange-50', 'border' => 'border-orange-400', 'badge' => 'bg-orange-100 text-orange-800'],
            'kept'      => ['label' => 'Kept by her', 'row' => 'bg-blue-50',   'border' => 'border-blue-400',   'badge' => 'bg-blue-100 text-blue-800'],
            'charged'   => ['label' => 'Written off', 'row' => 'bg-rose-50',   'border' => 'border-rose-400',   'badge' => 'bg-rose-100 text-rose-800'],
            'recovered' => ['label' => 'Recovered',   'row' => 'bg-teal-50',   'border' => 'border-teal-400',   'badge' => 'bg-teal-100 text-teal-800'],
        ];

        // How many months to render (newest first). ?months=3|6|12|all — default 6.
        $monthsParam = request('months');
        $limit = $monthsParam === 'all'
            ? null
            : (is_numeric($monthsParam) ? max(1, (int) $monthsParam) : 6);

        $recovery = $commission['recovery'] ?? null;
    @endphp

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800"> Statement </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Every credit and debit (going out), in order.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Range: how much history to render. Balances stay correct either way. --}}
                    <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs">
                        @foreach(['3' => '3 mo', '6' => '6 mo', '12' => '12 mo', 'all' => 'All'] as $value => $label)
                            @php $active = ($value === 'all' && $limit === null) || ($limit !== null && (string) $limit === (string) $value); @endphp
                            <a href="{{ request()->fullUrlWithQuery(['months' => $value]) }}"
                               class="px-3 py-1.5 {{ $active ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ route('referrers.show', $referrer->id) }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                        Back
                    </a>
                </div>
            </div>

            {{-- ───────────── Commission ───────────── --}}
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800">Commission earned</h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4 mt-3 text-sm">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-gray-500">Earned</div>
                        <div class="text-lg font-semibold text-green-700">KES {{ number_format($commission['total_earned'], 0) }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-gray-500">Paid or kept</div>
                        <div class="text-lg font-semibold text-red-700">KES {{ number_format($commission['total_settled'], 0) }}</div>
                    </div>
                    <div class="bg-indigo-50 rounded-lg p-3">
                        <div class="text-gray-500">You still owe her</div>
                        <div class="text-lg font-semibold text-indigo-800">KES {{ number_format($commission['still_owed'], 0) }}</div>
                    </div>
                </div>

                @if($recovery && $recovery['has'])
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4 text-sm">
                        <div class="bg-rose-50 rounded-lg p-3">
                            <div class="text-gray-500">She owes you (write-offs)</div>
                            <div class="text-lg font-semibold text-rose-800">KES {{ number_format($recovery['outstanding'], 0) }}</div>
                            <div class="text-xs text-gray-400 mt-1">
                                charged {{ number_format($recovery['charged'], 0) }} · recovered {{ number_format($recovery['recovered'], 0) }}
                            </div>
                        </div>
                        <div class="bg-indigo-50 rounded-lg p-3">
                            <div class="text-gray-500">
                                {{ $commission['net_owed'] >= 0 ? 'Net you owe her' : 'Net she owes you' }}
                            </div>
                            <div class="text-lg font-semibold text-indigo-800">KES {{ number_format(abs($commission['net_owed']), 0) }}</div>
                            <div class="text-xs text-gray-400 mt-1">commission owed less write-off recovery</div>
                        </div>
                    </div>
                @endif

                @include('referrers.partials.ledger', [
                    'months'       => $commission['months'],
                    'mode'         => 'balance',
                    'balanceLabel' => 'Owed',
                    'creditLabel'  => 'Earned',
                    'debitLabel'   => 'Paid / kept',
                    'kindStyles'   => $commissionStyles,
                    'limit'        => $limit,
                    'empty'        => 'No commission yet.',
                ])

                @if($recovery && $recovery['has'])
                    <h4 class="text-md font-semibold text-gray-800 mt-8">Write-off recoveries</h4>
                    <p class="text-sm text-gray-500 mb-2">
                        Loans written off and charged to {{ $referrer->name }}, and what each payout has deducted so far.
                    </p>
                    @include('referrers.partials.ledger', [
                        'months'       => $recovery['months'],
                        'mode'         => 'balance',
                        'balanceLabel' => 'She owes',
                        'creditLabel'  => 'Charged',
                        'debitLabel'   => 'Recovered',
                        'kindStyles'   => $commissionStyles,
                        'limit'        => $limit,
                        'empty'        => 'No write-offs charged.',
                    ])
                @endif
            </div>

            {{-- ───────────── Float ───────────── --}}
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800">Float Account</h3>

                @if(!$float['account'])
                    <p class="text-sm text-gray-500 mt-2">{{ $referrer->name }} has no float account set up.</p>
                @else

                    @if(abs($float['difference']) > 0.5)
                        <div class="mb-4 bg-amber-50 border-l-4 border-amber-400 p-3 text-sm text-amber-800">
                            This statement adds up to KES {{ number_format($float['closing'], 0) }}, but the account balance shows
                            KES {{ number_format($float['current_balance'], 0) }} (difference KES {{ number_format($float['difference'], 0) }}).
                            Usually that means an opening balance or a transfer isn't being picked up.
                        </div>
                    @endif

                    @php
                        $principal = $float['by_component']['principal'];
                        $interest  = $float['by_component']['interest'];

                        // What's physically in the float besides interest. She re-lends
                        // repayments, so this can't be derived by adding up principal
                        // received; it's the account balance less the interest in it.
                        $principalInFloat = (float) $float['current_balance'] - (float) $interest['closing'];
                    @endphp

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm mt-3">
                        <div class="bg-indigo-50 rounded-lg p-3">
                            <div class="text-gray-500">Principal in the float now</div>
                            <div class="text-lg font-semibold text-gray-900">KES {{ number_format($principalInFloat, 0) }}</div>
                            <div class="text-xs text-gray-400 mt-1">account balance less interest</div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3">
                            <div class="text-gray-500">Interest held</div>
                            <div class="text-lg font-semibold text-gray-900">KES {{ number_format($interest['closing'], 0) }}</div>
                            <div class="text-xs text-gray-400 mt-1">
                                in {{ number_format($interest['credits'], 0) }} · out {{ number_format($interest['debits'], 0) }}
                            </div>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-3">
                            <div class="text-gray-500">Float account balance</div>
                            <div class="text-lg font-semibold text-gray-900">KES {{ number_format($float['current_balance'], 0) }}</div>
                            <div class="text-xs text-gray-400 mt-1">everything in the account</div>
                        </div>
                    </div>

                    <h4 class="text-md font-semibold text-gray-800 mt-6">Principal collected</h4>
                    @include('referrers.partials.ledger', [
                        'months'       => $principal['months'],
                        'mode'         => 'balance',
                        'balanceLabel' => 'Net collected',
                        'creditLabel'  => 'In',
                        'debitLabel'   => 'Out',
                        'kindStyles'   => $kindStyles,
                        'limit'        => $limit,
                        'empty'        => 'No principal received yet.',
                    ])

                    <h4 class="text-md font-semibold text-gray-800 mt-8">Interest</h4>
                    @include('referrers.partials.ledger', [
                        'months'       => $interest['months'],
                        'mode'         => 'balance',
                        'balanceLabel' => 'Interest held',
                        'creditLabel'  => 'In',
                        'debitLabel'   => 'Out',
                        'kindStyles'   => $kindStyles,
                        'limit'        => $limit,
                        'empty'        => 'No interest movements yet.',
                    ])

                    <details class="mt-8">
                        <summary class="cursor-pointer text-md font-semibold text-gray-800">
                            Full account activity
                            <span class="text-sm font-normal text-gray-500">(every movement, unfiltered)</span>
                        </summary>
                        <div class="mt-3">
                            @include('referrers.partials.ledger', [
                                'months'       => $float['months'],
                                'mode'         => 'balance',
                                'balanceLabel' => 'Balance',
                                'creditLabel'  => 'In',
                                'debitLabel'   => 'Out',
                                'kindStyles'   => $kindStyles,
                                'limit'        => $limit,
                                'empty'        => 'No movements on the float account yet.',
                            ])
                        </div>
                    </details>
                @endif
            </div>

            {{-- ───────────── Everything involving her ───────────── --}}
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                <details>
                    <summary class="cursor-pointer text-lg font-semibold text-gray-800">
                        Everything
                        <span class="text-sm font-normal text-gray-500">({{ $movements['count'] }} movements, on every account)</span>
                    </summary>
                    <div class="mt-3">
                        @include('referrers.partials.ledger', [
                            'months'     => $movements['months'],
                            'mode'       => 'movements',
                            'kindStyles' => $kindStyles,
                            'limit'      => $limit,
                            'empty'      => 'No movements found.',
                        ])
                    </div>
                </details>
            </div>
        </div>
    </div>
</x-app-layout>
