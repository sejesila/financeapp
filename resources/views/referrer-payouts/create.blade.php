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
                                No unpaid referred loans for {{ $referrer->name }} — either nothing's closed yet,
                                or everything's already been paid out or retained before depositing.
                            </p>
                        </div>
                    @else
                        <!-- Unpaid loans reference table -->
                        <div class="bg-purple-50 border-l-4 border-purple-400 p-4 mb-6">
                            <p class="text-sm text-purple-800 mb-3">
                                {{ $unpaidLoans->count() }} closed loan{{ $unpaidLoans->count() > 1 ? 's' : '' }}
                                referred by
                                {{ $referrer->name }} with interest not yet paid out or retained upfront.
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
                                        <th class="px-3 py-2 text-left font-medium text-purple-700">Interest</th>
                                        <th class="px-3 py-2 text-left font-medium text-purple-700">Share %</th>
                                        <th class="px-3 py-2 text-left font-medium text-purple-700">Cut</th>
                                        <th class="px-3 py-2 text-left font-medium text-purple-700">Repaid Date</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-purple-100">
                                    @foreach($unpaidLoans as $loan)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-800">{{ $loan->borrower_name }}</td>
                                            <td class="px-3 py-2 text-gray-800">
                                                KES {{ number_format($loan->interest_amount, 0) }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ number_format($loan->computed_share_percentage, 1) }}
                                                %
                                            </td>
                                            <td class="px-3 py-2 text-gray-800 font-medium">
                                                KES {{ number_format($loan->computed_cut, 0) }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ $loan->repaid_date?->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-2 text-xs text-purple-700">
                                Only loans with a repaid date inside the period you pick below will actually be included
                                in this payout.
                                Each loan's cut is computed at its own share percentage, so the payout total may not
                                equal a single flat rate applied to the interest total.

                            </p>
                        </div>

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


                                <div>
                                    <label for="account_id" class="block text-sm font-medium text-gray-700">Pay From
                                        Account <span class="text-red-600">*</span></label>
                                    <select id="account_id" name="account_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('account_id') border-red-500 @enderror"
                                            required>
                                        <option value="">Select Account</option>
                                        @foreach($accounts as $account)
                                            <option
                                                value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                                {{ $account->name }}
                                                (KES {{ number_format($account->current_balance, 0) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('account_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
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
                                    ⚠️ The final payout amount is recalculated from loans whose repaid date falls
                                    between
                                    the two dates above, each at its own share rate — not a flat total from the
                                    reference
                                    table shown earlier. Double-check the period covers the loans you intend to pay for.
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
