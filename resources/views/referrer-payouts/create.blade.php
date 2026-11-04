<x-app-layout>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">Pay Out {{ $referrer->name }}</h2>
                        <a href="{{ route('referrers.show', $referrer->id) }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back
                        </a>
                    </div>

                    @if ($errors->any())
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                            <p class="text-sm text-red-700 font-medium mb-1">Please fix the following:</p>
                            <ul class="list-disc pl-5 space-y-1 text-sm text-red-700">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                            <p class="text-sm text-red-700">{{ session('error') }}</p>
                        </div>
                    @endif

                    @if($unpaidLoans->isEmpty())
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <p class="text-sm text-blue-700">
                                No unpaid commission for {{ $referrer->name }} — either nothing's been earned yet,
                                or everything's already been paid out or retained before depositing.
                            </p>
                        </div>

                        @if($recoveryOwed > 0)
                            <div class="mt-4 bg-rose-50 border-l-4 border-rose-400 p-4">
                                <p class="text-sm text-rose-800">
                                    {{ $referrer->name }} still owes KES {{ number_format($recoveryOwed, 0) }} from written-off
                                    loans. It will be deducted from her commission as soon as some is earned.
                                </p>
                            </div>
                        @endif
                    @else
                        <!-- Unpaid commission reference table -->
                        <div class="bg-purple-50 border-l-4 border-purple-400 p-4 mb-6">
                            <p class="text-sm text-purple-800 mb-3">
                                {{ $unpaidLoans->count() }} loan{{ $unpaidLoans->count() > 1 ? 's' : '' }}
                                referred by
                                {{ $referrer->name }} with commission not yet paid out or retained upfront.
                                Total interest across all of them: <span
                                    class="font-medium">KES {{ number_format($totalInterest, 0) }}</span>
                                &mdash; their combined cut at each loan's own share rate: <span
                                    class="font-medium">KES {{ number_format($totalCut, 0) }}</span>.
                            </p>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-purple-200 text-sm">
                                    <thead>
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-purple-700">Borrower</th>
                                        <th class="px-3 py-2 text-left font-medium text-purple-700">Unpaid Interest</th>
                                        <th class="px-3 py-2 text-left font-medium text-purple-700">Share %</th>
                                        <th class="px-3 py-2 text-left font-medium text-purple-700">Cut</th>
                                        <th class="px-3 py-2 text-left font-medium text-purple-700">First Payment</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-purple-100">
                                    @foreach($unpaidLoans as $loan)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-800">{{ $loan->borrower_name }}</td>
                                            <td class="px-3 py-2 text-gray-800">
                                                KES {{ number_format($loan->unpaid_interest, 0) }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ number_format($loan->computed_share_percentage, 1) }}
                                                %
                                            </td>
                                            <td class="px-3 py-2 text-gray-800 font-medium">
                                                KES {{ number_format($loan->computed_cut, 0) }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ $loan->unpaid_payments->min('payment_date')?->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-xs text-purple-700">
                                Only payments dated inside the period you pick below will actually be included
                                in this payout.
                                Each loan's cut is computed at its own share percentage, so the payout total may not
                                equal a single flat rate applied to the interest total.
                            </p>
                        </div>

                        {{-- Write-off recovery preview. $recoveryOwed / $recoveryDeduction / $netPayable
                             come from ReferrerPayoutController@create and are based on ALL unpaid
                             commission; the real figures are recalculated for the chosen period. --}}
                        @if($recoveryOwed > 0)
                            <div class="bg-rose-50 border-l-4 border-rose-400 p-4 mb-6">
                                <p class="text-sm font-medium text-rose-800 mb-2">
                                    Written-off loans to recover from {{ $referrer->name }}
                                </p>
                                <dl class="text-sm space-y-1 max-w-md">
                                    <div class="flex justify-between">
                                        <dt class="text-gray-600">Still owed from write-offs</dt>
                                        <dd class="font-medium text-gray-900">KES {{ number_format($recoveryOwed, 0) }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-600">Commission (all unpaid)</dt>
                                        <dd class="font-medium text-gray-900">KES {{ number_format($totalCut, 0) }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-600">Less: write-off recovery</dt>
                                        <dd class="font-medium text-rose-700">&minus; KES {{ number_format($recoveryDeduction, 0) }}</dd>
                                    </div>
                                    <div class="flex justify-between pt-1 border-t border-rose-200">
                                        <dt class="font-medium text-gray-800">Net paid in cash</dt>
                                        <dd class="font-bold text-gray-900">KES {{ number_format($netPayable, 0) }}</dd>
                                    </div>
                                </dl>
                                <p class="mt-2 text-xs text-rose-700">
                                    @if($recoveryOwed > $totalCut)
                                        Her commission doesn't cover the full amount, so KES {{ number_format($recoveryOwed - $recoveryDeduction, 0) }}
                                        carries forward and comes off future commissions.
                                    @else
                                        Her commission covers the full amount owed.
                                    @endif
                                    Figures are for all unpaid commission; the actual deduction is recalculated for the
                                    period you pick below.
                                </p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('referrer-payouts.store', $referrer->id) }}"
                              class="space-y-6">
                            @csrf

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="period_start" class="block text-sm font-medium text-gray-700">Period
                                        Start <span class="text-red-600">*</span></label>
                                    <input type="date" id="period_start" name="period_start"
                                           value="{{ old('period_start') }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('period_start') border-red-500 @enderror"
                                           required>
                                    @error('period_start')<p
                                        class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="period_end" class="block text-sm font-medium text-gray-700">Period End
                                        <span class="text-red-600">*</span></label>
                                    <input type="date" id="period_end" name="period_end"
                                           value="{{ old('period_end', now()->format('Y-m-d')) }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('period_end') border-red-500 @enderror"
                                           required>
                                    @error('period_end')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>


                                <div x-data="{ accountId: '{{ old('account_id') }}', floatId: '{{ $floatAccount?->id }}' }">
                                    <label for="account_id" class="block text-sm font-medium text-gray-700">Pay From
                                        Account <span class="text-red-600">*</span></label>
                                    <select id="account_id" name="account_id" x-model="accountId"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('account_id') border-red-500 @enderror"
                                            required>
                                        <option value="">Select Account</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                                {{ $account->name }}
                                                (KES {{ number_format($account->current_balance, 0) }})
                                                @if($floatAccount && $floatAccount->id === $account->id) — her float @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('account_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                                    @if($floatAccount)
                                        <template x-if="accountId === floatId">
                                            <div class="mt-2 bg-teal-50 border-l-4 border-teal-400 p-3">
                                                <p class="text-xs text-teal-800">
                                                    Paying from {{ $referrer->name }}'s float (currently
                                                    KES {{ number_format($floatAccount->current_balance, 0) }}). Her commission comes out of
                                                    money she's holding for you, so each loan's cut is also marked as settled on the
                                                    Reconcile Float page. What she still owes you drops by the same amount.
                                                    @if($recoveryDeduction > 0)
                                                        Only the net cash amount leaves her float; the part deducted for written-off
                                                        loans stays in the float and becomes yours to reconcile.
                                                    @endif
                                                </p>
                                            </div>
                                        </template>
                                    @endif
                                </div>

                                <div>
                                    <label for="paid_date" class="block text-sm font-medium text-gray-700">Paid Date
                                        <span class="text-red-600">*</span></label>
                                    <input type="date" id="paid_date" name="paid_date"
                                           value="{{ old('paid_date', now()->format('Y-m-d')) }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('paid_date') border-red-500 @enderror"
                                           required>
                                    @error('paid_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="bg-amber-50 border-l-4 border-amber-400 p-4">
                                <p class="text-sm text-amber-800">
                                    ⚠️ The final payout amount is recalculated from payments dated
                                    between
                                    the two dates above, each at its own share rate — not a flat total from the
                                    reference
                                    table shown earlier. @if($recoveryOwed > 0) Any write-off recovery is deducted from that
                                    total before the cash is paid out. @endif
                                    Double-check the period covers the payments you intend to pay for.
                                </p>
                            </div>

                            <div class="flex items-center space-x-4">
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Confirm Payout
                                </button>
                                <a href="{{ route('referrers.show', $referrer->id) }}"
                                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
