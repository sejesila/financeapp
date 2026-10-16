<x-app-layout>
    @php
        $money = fn ($v) => $v > 0 ? number_format($v, 0) : '';

        // Colour key shared by every table below. Full class names are spelled out
        // here so Tailwind picks them up.
        $kindStyles = [
            'principal'   => ['label' => 'Principal',        'row' => 'bg-indigo-50',  'border' => 'border-indigo-400',  'badge' => 'bg-indigo-100 text-indigo-800'],
            'interest'    => ['label' => 'Interest',         'row' => 'bg-green-50',   'border' => 'border-green-400',   'badge' => 'bg-green-100 text-green-800'],
            'transfer_in' => ['label' => 'Transfer in',      'row' => 'bg-sky-50',     'border' => 'border-sky-400',     'badge' => 'bg-sky-100 text-sky-800'],
            'remitted'    => ['label' => 'Sent back to you', 'row' => 'bg-orange-50',  'border' => 'border-orange-400',  'badge' => 'bg-orange-100 text-orange-800'],
            'loan_given'  => ['label' => 'Loan given',       'row' => 'bg-rose-50',    'border' => 'border-rose-400',    'badge' => 'bg-rose-100 text-rose-800'],
            'commission'  => ['label' => 'Commission',       'row' => 'bg-purple-50',  'border' => 'border-purple-400',  'badge' => 'bg-purple-100 text-purple-800'],
            'fee'         => ['label' => 'Fee',              'row' => 'bg-gray-50',    'border' => 'border-gray-300',    'badge' => 'bg-gray-200 text-gray-700'],
        ];
    @endphp

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800">{{ $referrer->name }} — Statement</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Every credit (money or commission coming in) and debit (going out), in order.
                    </p>
                </div>
                <a href="{{ route('referrers.show', $referrer->id) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    Back
                </a>
            </div>

            {{-- ───────────── Commission ───────────── --}}
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800">Commission she has earned</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Credit = commission earned on a closed loan. Debit = paid to her, or kept by her before depositing.
                    The balance is what you still owe her.
                </p>

                <div class="grid grid-cols-3 gap-4 mb-4 text-sm">
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

                @include('referrers.partials.ledger-table', ['months' => $commission['months'], 'balanceLabel' => 'Owed', 'empty' => 'No closed loans with commission yet.'])
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

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div class="bg-indigo-50 rounded-lg p-3">
                            <div class="text-gray-500">Principal in her float now</div>
                            <div class="text-lg font-semibold text-gray-900">KES {{ number_format($principalInFloat, 0) }}</div>
                            <div class="text-xs text-gray-400 mt-1">account balance less interest</div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3">
                            <div class="text-gray-500">Interest she holds for you</div>
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
                    <p class="text-xs text-gray-400 mt-2 mb-6">
                        She re-lends repayments, so the principal in her float is what's left of the account after interest. It reflects
                        the capital you put in, loans she gave out and fees. Every movement is in the full activity at the bottom.
                    </p>

                    <h4 class="text-md font-semibold text-gray-800 mt-6">Principal collected</h4>
                    <p class="text-sm text-gray-500 mb-2">
                        Principal received on fully or partly paid loans that landed in her float, less principal she has sent back to you.
                        Collected KES {{ number_format($principal['credits'], 0) }} · sent back KES {{ number_format($principal['debits'], 0) }}.
                        This is a running tally of what came in and went back, not what she holds.
                    </p>
                    @include('referrers.partials.ledger-table', [
                        'months'       => $principal['months'],
                        'balanceLabel' => 'Net collected',
                        'kindStyles'   => $kindStyles,
                        'empty'        => 'No principal received yet.',
                    ])

                    <h4 class="text-md font-semibold text-gray-800 mt-8">Interest</h4>
                    <p class="text-sm text-gray-500 mb-2">
                        Interest earned on partially or fully paid loans, less her commission and any interest she has sent back.
                    </p>
                    @include('referrers.partials.ledger-table', [
                        'months'       => $interest['months'],
                        'balanceLabel' => 'Interest held',
                        'kindStyles'   => $kindStyles,
                        'empty'        => 'No interest movements yet.',
                    ])

                    <details class="mt-8">
                        <summary class="cursor-pointer text-md font-semibold text-gray-800">
                            Full account activity
                            <span class="text-sm font-normal text-gray-500">(every movement, unfiltered)</span>
                        </summary>
                        <div class="mt-3">
                            <div class="flex flex-wrap gap-2 text-xs mb-3">
                                @foreach($kindStyles as $s)
                                    <span class="rounded px-2 py-0.5 {{ $s['badge'] }}">{{ $s['label'] }}</span>
                                @endforeach
                            </div>
                            @include('referrers.partials.ledger-table', [
                                'months'       => $float['months'],
                                'kindStyles'   => $kindStyles,
                                'balanceLabel' => 'Balance',
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
                        Everything involving {{ $referrer->name }}
                        <span class="text-sm font-normal text-gray-500">({{ $movements['count'] }} movements, on every account)</span>
                    </summary>
                    <p class="text-sm text-gray-500 mt-2 mb-4">
                        Her float account, transfers in and out of it, and every disbursement, repayment, interest and commission
                        tied to loans she referred, on whichever account it landed. In and Out are from the point of view of the
                        account shown, so they don't add up to one balance. Use the filter to see only what never passed through her float.
                    </p>
                    <div class="flex flex-wrap gap-2 text-xs mb-3">
                        @foreach($kindStyles as $s)
                            <span class="rounded px-2 py-0.5 {{ $s['badge'] }}">{{ $s['label'] }}</span>
                        @endforeach
                    </div>
                    @include('referrers.partials.movements-table', [
                        'kindStyles' => $kindStyles,
                        'months' => $movements['months'],
                        'empty'  => 'No movements found.',
                    ])
                </details>
            </div>
        </div>
    </div>
</x-app-layout>
