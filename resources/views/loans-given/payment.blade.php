<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">Record Repayment</h2>
                        <a href="{{ route('loans-given.show', $loanGiven->id) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
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

                    <!-- Loan Summary -->
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Borrower:</span>
                                <span class="ml-2 text-sm font-semibold text-gray-900">{{ $loanGiven->borrower_name }}</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700">Principal:</span>
                                <span class="ml-2 text-sm font-semibold text-gray-900">KES {{ number_format($loanGiven->principal_amount, 0) }}</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700">Outstanding:</span>
                                <span class="ml-2 text-sm font-bold text-indigo-600">KES {{ number_format($loanGiven->remaining_principal, 0) }}</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700">Received:</span>
                                <span class="ml-2 text-sm text-green-600 font-medium">KES {{ number_format($loanGiven->amount_paid, 0) }}</span>
                            </div>
                        </div>
                        @if($loanGiven->surplus_received > 0)
                            <div class="mt-2 pt-2 border-t border-blue-200">
                                <span class="text-sm font-medium text-gray-700">Received Above Principal So Far:</span>
                                <span class="ml-2 text-sm text-purple-600 font-medium">KES {{ number_format($loanGiven->surplus_received, 0) }}</span>
                            </div>
                        @endif

                    </div>

                    <!-- Referrer Reminder -->
                    @if($loanGiven->referrer)
                        <div class="bg-purple-50 border-l-4 border-purple-400 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-purple-800">
                                        This loan was referred by
                                        <span class="font-medium">{{ $loanGiven->referrer->name }}</span>,
                                        who's owed
                                        <span class="font-medium">{{ number_format($loanGiven->referrer_share_percentage ?? 0, 1) }}%</span>
                                        of whatever interest ends up being earned. Their cut is only calculated
                                        (and shown) once you close this loan as fully repaid — nothing to do
                                        differently on this payment.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('loans-given.payment', $loanGiven->id) }}" class="space-y-6">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="payment_account_id" class="block text-sm font-medium text-gray-700">Deposit Into Account <span class="text-red-600">*</span></label>
                                <select id="payment_account_id" name="payment_account_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('payment_account_id') border-red-500 @enderror" required>
                                    <option value="">Select Account</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" data-type="{{ $account->type }}" {{ old('payment_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->name }} (KES {{ number_format($account->current_balance, 0) }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('payment_account_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="payment_amount" class="block text-sm font-medium text-gray-700">Amount Received (KES) <span class="text-red-600">*</span></label>
                                <input type="number" step="0.01" id="payment_amount" name="payment_amount" value="{{ old('payment_amount') }}"
                                       min="0.01"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('payment_amount') border-red-500 @enderror" required>
                                <div class="mt-1 text-xs text-gray-500">
                                    Outstanding principal: KES {{ number_format($loanGiven->remaining_principal, 0) }}
                                </div>
                                @error('payment_amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date <span class="text-red-600">*</span></label>
                                <input type="date" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}"
                                       max="{{ now()->format('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('payment_date') border-red-500 @enderror" required>
                                @error('payment_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Interest Account Field - Now in the grid -->
                            <div id="interestAccountWrapper">
                                <label for="interest_account_id" class="block text-sm font-medium text-gray-700">
                                    Deposit Interest Into <span class="text-red-600">*</span>
                                </label>
                                <select id="interest_account_id" name="interest_account_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('interest_account_id') border-red-500 @enderror">
                                    <option value="">Select Account</option>
                                    @foreach($accounts as $account)
                                        @if($account->type !== 'referrer_float')
                                            <option value="{{ $account->id }}" {{ old('interest_account_id') == $account->id ? 'selected' : '' }}>
                                                {{ $account->name }} (KES {{ number_format($account->current_balance, 0) }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-600">
                                    This payment is landing in a referrer float — the referrer's cut comes out of there, but your interest shouldn't. Pick where it lands.
                                </p>
                                @error('interest_account_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Notes -->
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                <textarea id="notes" name="notes" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('notes') border-red-500 @enderror">{{ old('notes') }}</textarea>
                                @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Close loan option -->
                            <div>
                                <div class="border border-amber-200 bg-amber-50 rounded-lg p-4 h-full flex items-center">
                                    <div class="flex items-start">
                                        <input type="checkbox" id="close_loan" name="close_loan" value="1"
                                               checked
                                               class="mt-0.5 rounded border-gray-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50">
                                        <label for="close_loan" class="ml-2 block text-sm text-gray-800">
                                            <span class="font-medium">This is the final payment — close the loan as fully repaid.</span>
                                            <span class="block text-xs text-gray-600 mt-1">
                        The interest and rate will be calculated automatically from the total
                        received (including this payment) vs. the principal, and no further
                        payments will be accepted on this loan.
                        @if($loanGiven->referrer)
                                                    The referrer's {{ number_format($loanGiven->referrer_share_percentage ?? 0, 1) }}% cut
                                                    of that interest will also be calculated at that point.
                                                @endif
                    </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Record Payment
                            </button>
                            <a href="{{ route('loans-given.show', $loanGiven->id) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const paymentAccountSelect = document.getElementById('payment_account_id');
            const closeLoanCheckbox = document.getElementById('close_loan');
            const wrapper = document.getElementById('interestAccountWrapper');
            const interestSelect = document.getElementById('interest_account_id');

            function isFloatSelected() {
                const opt = paymentAccountSelect.options[paymentAccountSelect.selectedIndex];
                return opt && opt.dataset.type === 'referrer_float';
            }

            function refresh() {
                const show = closeLoanCheckbox.checked && isFloatSelected();

                if (show) {
                    wrapper.style.display = 'block';
                    interestSelect.required = true;
                } else {
                    wrapper.style.display = 'none';
                    interestSelect.required = false;
                    interestSelect.value = ''; // Clear selection when hidden
                }
            }

            paymentAccountSelect.addEventListener('change', refresh);
            closeLoanCheckbox.addEventListener('change', refresh);
            refresh();
        })();
    </script>
</x-app-layout>
