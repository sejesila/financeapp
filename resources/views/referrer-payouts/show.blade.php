<x-app-layout>
    <div class="py-12">
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
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-800">{{ $referrer->name }}</h2>
                            <p class="text-sm text-gray-500 mt-1">
                                Default share: {{ number_format($referrer->default_share_percentage, 1) }}%
                                @if(!$referrer->is_active)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="{{ route('referrer-payouts.create', $referrer->id) }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Pay Out Referrer
                            </a>
                            <a href="{{ route('loans-given.show') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Back
                            </a>
                        </div>
                    </div>

                    @php
                        $referredLoans = $referrer->loans()->orderByDesc('disbursed_date')->get();
                        $paidLoans = $referredLoans->where('status', 'paid');
                        $outstandingPayable = $paidLoans
                            ->whereNull('referrer_payout_id')
                            ->where('referrer_deducted_before_deposit', false)
                            ->sum(function ($loan) use ($referrer) {
                                $sharePct = $loan->referrer_share_percentage ?? $referrer->default_share_percentage;
                                return round($loan->interest_amount * ($sharePct / 100), 2);
                            });
                        $retainedTotal = $paidLoans->where('referrer_deducted_before_deposit', true)->sum('referrer_retained_amount');
                        $paidOutTotal = optional($referrer->payouts ?? collect())->sum('amount_paid') ?? 0;
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200 p-4">
                            <p class="text-sm font-medium text-gray-500">Referred Loans</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $referredLoans->count() }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg border border-amber-200 p-4">
                            <p class="text-sm font-medium text-gray-500">Owed Now</p>
                            <p class="text-lg font-semibold text-gray-900">KES {{ number_format($outstandingPayable, 0) }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200 p-4">
                            <p class="text-sm font-medium text-gray-500">Retained Before Deposit</p>
                            <p class="text-lg font-semibold text-gray-900">KES {{ number_format($retainedTotal, 0) }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg border border-green-200 p-4">
                            <p class="text-sm font-medium text-gray-500">Paid Out (Batches)</p>
                            <p class="text-lg font-semibold text-gray-900">KES {{ number_format($paidOutTotal, 0) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Referred Loans -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Referred Loans</h3>

                    @if($referredLoans->isEmpty())
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">No loans referred by {{ $referrer->name }} yet.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Borrower</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interest</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referrer Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($referredLoans as $loan)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $loan->borrower_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($loan->status === 'active')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Active</span>
                                            @elseif($loan->status === 'paid')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Paid</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $loan->interest_amount > 0 ? 'KES ' . number_format($loan->interest_amount, 0) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
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
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Payout History</h3>

                    @if(($referrer->payouts ?? collect())->isEmpty())
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">No payout batches recorded yet.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Interest</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective Share %</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Date</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($referrer->payouts->sortByDesc('paid_date') as $payout)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ \Carbon\Carbon::parse($payout->period_start)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($payout->period_end)->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">KES {{ number_format($payout->total_interest, 0) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($payout->share_percentage, 1) }}%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">KES {{ number_format($payout->amount_paid, 0) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ \Carbon\Carbon::parse($payout->paid_date)->format('M d, Y') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
