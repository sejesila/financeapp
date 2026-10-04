<x-app-layout>
    <div class="py-6 sm:py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                <div class="p-4 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                        <div>
                            <h2 class="text-xl sm:text-2xl font-semibold text-gray-800">{{ $referrer->name }}</h2>
                            <p class="text-sm text-gray-500 mt-1">
                                Default share: {{ number_format($referrer->default_share_percentage, 1) }}%
                                @if(!$referrer->is_active)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('referrers.statement', $referrer->id) }}"
                               class="inline-flex items-center px-3 sm:px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-[11px] sm:text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                View Statement
                            </a>
                            @if($referrer->floatAccount)
                                <a href="{{ route('referrers.float.index', $referrer->id) }}"
                                   class="inline-flex items-center px-3 sm:px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-[11px] sm:text-xs text-white uppercase tracking-widest hover:bg-teal-700 focus:bg-teal-700 active:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Reconcile Float
                                </a>
                            @endif
                            <a href="{{ route('referrer-payouts.create', $referrer->id) }}"
                               class="inline-flex items-center px-3 sm:px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-[11px] sm:text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Pay Out Referrer
                            </a>
                            <a href="{{ url()->previous() }}"
                               class="inline-flex items-center px-3 sm:px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-[11px] sm:text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Back
                            </a>
                        </div>
                    </div>

                    @php
                        // Eager-load payments so the "interest so far" figure on each
                        // active loan below (payments->sum('interest_portion')) doesn't
                        // run an extra query per row.
                        $referredLoans = $referrer->loans()->with('payments')->orderByDesc('disbursed_date')->get();
                        $paidLoans = $referredLoans->where('status', 'paid');

                        // Loans that are paid, not yet part of a payout batch,
                        // and not retained upfront before deposit. This is the
                        // single source of truth for "what's outstanding" —
                        // both the raw interest and the referrer's cut of it
                        // are derived from this same filtered set so they
                        // never drift apart.
                        $unpaidOutLoans = $paidLoans
                            ->whereNull('referrer_payout_id')
                            ->where('referrer_deducted_before_deposit', false);

                        $outstandingInterest = $unpaidOutLoans->sum('interest_amount');

                        $outstandingPayable = $unpaidOutLoans->sum(function ($loan) use ($referrer) {
                            $sharePct = $loan->referrer_share_percentage ?? $referrer->default_share_percentage;
                            return round($loan->interest_amount * ($sharePct / 100), 2);
                        });

                        $retainedTotal = $paidLoans->where('referrer_deducted_before_deposit', true)->sum('referrer_retained_amount');
                        $paidOutTotal = ($referrer->payouts ?? collect())->sum('amount_paid');

                        // YOUR money (principal + interest) she's collected on your
                        // behalf and is still holding, not her own commission.
                        $pendingFloatTotal = $referrer->pendingFloatInterestByLoan()->sum();
                    @endphp

                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4 mb-6">
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Referred Loans</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">{{ $referredLoans->count() }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg border border-slate-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Interest Not Yet Paid Out</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($outstandingInterest, 0) }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg border border-amber-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Owed Now</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($outstandingPayable, 0) }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Retained Before Deposit</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($retainedTotal, 0) }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg border border-green-200 p-3 sm:p-4">
                            <p class="text-xs sm:text-sm font-medium text-gray-500">Paid Out (Batches)</p>
                            <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($paidOutTotal, 0) }}</p>
                        </div>
                        @if($referrer->floatAccount)
                            <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-lg border border-teal-200 p-3 sm:p-4">
                                <p class="text-xs sm:text-sm font-medium text-gray-500">Pending in Float</p>
                                <p class="text-base sm:text-lg font-semibold text-gray-900">KES {{ number_format($pendingFloatTotal, 0) }}</p>
                                <p class="text-[10px] sm:text-xs leading-tight text-gray-500">Collected on your behalf, not yet reconciled</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Referred Loans -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                <div class="p-4 sm:p-6">
                    <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-4">Referred Loans</h3>

                    @if($referredLoans->isEmpty())
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">No loans referred by {{ $referrer->name }} yet.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Borrower</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Interest</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Referrer Status</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($referredLoans as $loan)
                                    <tr>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900">{{ $loan->borrower_name }}</td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm">
                                            @if($loan->status === 'active')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Active</span>
                                            @elseif($loan->status === 'paid')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Paid</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                            @php
                                                // A closed loan's interest_amount is the final, whole-loan
                                                // figure. An active loan has none yet — its only recognized
                                                // interest lives in its rollover payments' interest_portion.
                                                $interestSoFar = $loan->status === 'paid'
                                                    ? $loan->interest_amount
                                                    : $loan->payments->sum('interest_portion');
                                            @endphp
                                            @if($interestSoFar > 0)
                                                KES {{ number_format($interestSoFar, 0) }}
                                                @if($loan->status !== 'paid')
                                                    <span class="text-xs text-gray-400">(so far)</span>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                            @if($loan->status !== 'paid')
                                                <span class="text-gray-400">Not yet closed</span>
                                            @elseif($loan->referrer_deducted_before_deposit)
                                                <span class="text-blue-600">Retained upfront (KES {{ number_format($loan->referrer_retained_amount, 0) }})</span>
                                            @elseif($loan->referrer_payout_id)
                                                <span class="text-green-600">Paid out (#{{ $loan->referrer_payout_id }})</span>
                                            @else
                                                <span class="text-amber-600">Owed</span>
                                            @endif
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm">
                                            <a href="{{ route('loans-given.show', $loan->id) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Payout History -->
            @php
                $payouts = ($referrer->payouts ?? collect())
                    ->sortByDesc(fn ($p) => [$p->paid_date?->timestamp ?? 0, $p->id])
                    ->values();
            @endphp
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base sm:text-lg font-medium text-gray-900">Payout History</h3>
                        @if($payouts->isNotEmpty())
                            <span class="text-xs sm:text-sm text-gray-500">
                                {{ $payouts->count() }} payout{{ $payouts->count() > 1 ? 's' : '' }} ·
                                <span class="font-medium text-green-600">KES {{ number_format($payouts->sum('amount_paid'), 0) }}</span> total
                            </span>
                        @endif
                    </div>

                    @if($payouts->isEmpty())
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">No payout batches recorded yet.</p>
                        </div>
                    @else
                        <p class="text-xs text-gray-500 mb-3">Tap a payout to see the loans it covered.</p>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Payout</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Date</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Paid From</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Period Covered</th>
                                    <th class="px-3 sm:px-6 py-3 text-right text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Loans</th>
                                    <th class="px-3 sm:px-6 py-3 text-right text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Interest</th>
                                    <th class="px-3 sm:px-6 py-3 text-right text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</th>
                                </tr>
                                </thead>

                                @foreach($payouts as $payout)
                                    <tbody x-data="{ open: false }" class="bg-white border-t border-gray-200">
                                    <tr class="cursor-pointer hover:bg-gray-50" @click="open = !open">
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900">
                                            <svg class="inline w-3 h-3 mr-1 text-gray-400 transition-transform" :class="{ 'rotate-90': open }"
                                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                            #{{ $payout->id }}
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                            {{ \Carbon\Carbon::parse($payout->paid_date)->format('M d, Y') }}
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                            {{ $payout->account?->name ?? '—' }}
                                            @if($payout->account?->type === 'referrer_float')
                                                <span class="block text-[10px] sm:text-xs text-teal-600">her float</span>
                                            @endif
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                            {{ \Carbon\Carbon::parse($payout->period_start)->format('M d') }} &ndash; {{ \Carbon\Carbon::parse($payout->period_end)->format('M d, Y') }}
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 text-right">
                                            {{ $payout->loans->count() }}
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 text-right">
                                            KES {{ number_format($payout->total_interest, 0) }}
                                            <span class="block text-[10px] sm:text-xs text-gray-400">{{ number_format($payout->share_percentage, 1) }}% share</span>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-green-600 text-right">
                                            KES {{ number_format($payout->amount_paid, 0) }}
                                        </td>
                                    </tr>

                                    <tr x-show="open" x-cloak class="bg-gray-50">
                                        <td colspan="7" class="px-3 sm:px-6 py-3">
                                            @if($payout->loans->isEmpty())
                                                <p class="text-xs text-gray-500">No loans are linked to this payout.</p>
                                            @else
                                                <p class="text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Loans covered</p>
                                                <div class="space-y-1">
                                                    @foreach($payout->loans->sortBy('repaid_date') as $loan)
                                                        @php
                                                            $sharePct = $loan->referrer_share_percentage ?? $referrer->default_share_percentage;
                                                            $cut = round($loan->interest_amount * ($sharePct / 100), 2);
                                                        @endphp
                                                        <div class="flex items-center justify-between text-xs sm:text-sm">
                                                            <span class="text-gray-700">
                                                                <a href="{{ route('loans-given.show', $loan->id) }}" class="text-indigo-600 hover:text-indigo-900">{{ $loan->borrower_name }}</a>
                                                                <span class="text-gray-400">
                                                                    · {{ number_format($sharePct, 0) }}% of KES {{ number_format($loan->interest_amount, 0) }}
                                                                    @if($loan->repaid_date) · closed {{ $loan->repaid_date->format('M d') }} @endif
                                                                </span>
                                                            </span>
                                                            <span class="font-medium text-gray-900">KES {{ number_format($cut, 0) }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    </tbody>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
