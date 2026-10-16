<x-app-layout>
    @php
        $money = fn ($v) => $v > 0 ? number_format($v, 0) : '';
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
                    @endphp

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div class="bg-indigo-50 rounded-lg p-3">
                            <div class="text-gray-500">Principal she holds for you</div>
                            <div class="text-lg font-semibold text-gray-900">KES {{ number_format($principal['closing'], 0) }}</div>
                            <div class="text-xs text-gray-400 mt-1">
                                in {{ number_format($principal['credits'], 0) }} · sent back {{ number_format($principal['debits'], 0) }}
                            </div>
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
                        The account balance also includes the capital you put in, loans she gave out and fees,
                        which is why it isn't simply principal + interest. Those are in the full activity at the bottom.
                    </p>

                    <h4 class="text-md font-semibold text-gray-800 mt-6">Principal</h4>
                    <p class="text-sm text-gray-500 mb-2">
                        Principal received on fully or partly paid loans that landed in her float, less principal she has sent back to you.
                    </p>
                    @include('referrers.partials.ledger-table', [
                        'months'       => $principal['months'],
                        'balanceLabel' => 'Principal held',
                        'empty'        => 'No principal received yet.',
                    ])

                    <h4 class="text-md font-semibold text-gray-800 mt-8">Interest</h4>
                    <p class="text-sm text-gray-500 mb-2">
                        Interest earned on partially or fully paid loans, less her commission and any interest she has sent back.
                    </p>
                    @include('referrers.partials.ledger-table', [
                        'months'       => $interest['months'],
                        'balanceLabel' => 'Interest held',
                        'empty'        => 'No interest movements yet.',
                    ])

                    <details class="mt-8">
                        <summary class="cursor-pointer text-md font-semibold text-gray-800">
                            Full account activity
                            <span class="text-sm font-normal text-gray-500">(every movement, unfiltered)</span>
                        </summary>
                        <div class="mt-3">
                            @include('referrers.partials.ledger-table', [
                                'months'       => $float['months'],
                                'balanceLabel' => 'Balance',
                                'empty'        => 'No movements on the float account yet.',
                            ])
                        </div>
                    </details>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
