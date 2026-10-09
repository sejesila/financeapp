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

                @include('referrers.partials.ledger-table', ['rows' => $commission['rows'], 'balanceLabel' => 'Owed', 'empty' => 'No closed loans with commission yet.'])
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

                    <div class="grid grid-cols-4 gap-4 mb-4 text-sm">
                        <div class="bg-gray-50 rounded-lg p-3"><div class="text-gray-500">Opening</div>
                            <div class="font-semibold">KES {{ number_format($float['opening'], 0) }}</div></div>
                        <div class="bg-gray-50 rounded-lg p-3"><div class="text-gray-500">Total in</div>
                            <div class="font-semibold text-green-700">KES {{ number_format($float['total_credits'], 0) }}</div></div>
                        <div class="bg-gray-50 rounded-lg p-3"><div class="text-gray-500">Total out</div>
                            <div class="font-semibold text-red-700">KES {{ number_format($float['total_debits'], 0) }}</div></div>
                        <div class="bg-blue-50 rounded-lg p-3"><div class="text-gray-500">Balance now</div>
                            <div class="font-semibold">KES {{ number_format($float['current_balance'], 0) }}</div></div>
                    </div>

                    @include('referrers.partials.ledger-table', ['rows' => $float['rows'], 'balanceLabel' => 'Balance', 'empty' => 'No movements on the float account yet.'])
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
