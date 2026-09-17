<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">Record Repayment</h2>
                        <a href="{{ route('loans-given.show', $loanGiven->id) }}"
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

                    <!-- Loan Summary -->
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Borrower:</span>
                                <span
                                    class="ml-2 text-sm font-semibold text-gray-900">{{ $loanGiven->borrower_name }}</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700">Principal:</span>
                                <span
                                    class="ml-2 text-sm font-semibold text-gray-900">KES {{ number_format($loanGiven->principal_amount, 0) }}</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700">Outstanding:</span>
                                <span
                                    class="ml-2 text-sm font-bold text-indigo-600">KES {{ number_format($loanGiven->remaining_principal, 0) }}</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-700">Received:</span>
                                <span
                                    class="ml-2 text-sm text-green-600 font-medium">KES {{ number_format($loanGiven->amount_paid, 0) }}</span>
                            </div>
                        </div>
                        @if($loanGiven->surplus_received > 0)
                            <div class="mt-2 pt-2 border-t border-blue-200">
                                <span class="text-sm font-medium text-gray-700">Received Above Principal So Far:</span>
                                <span
                                    class="ml-2 text-sm text-purple-600 font-medium">KES {{ number_format($loanGiven->surplus_received, 0) }}</span>
                            </div>
                        @endif

                    </div>

                    <!-- Referrer Reminder -->
                    @if($loanGiven->referrer)
                        <div class="bg-purple-50 border-l-4 border-purple-400 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-purple-400" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4"></path>
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
                                <label for="payment_account_id" class="block text-sm font-medium text-gray-700">Deposit
                                    Into Account <span class="text-red-600">*</span></label>
                                <select id="payment_account_id" name="payment_account_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('payment_account_id') border-red-500 @enderror"
                                        required>
                                    <option value="">Select Account</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}"
                                                data-type="{{ $account->type }}" {{ old('payment_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->name }} (KES {{ number_format($account->current_balance, 0) }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('payment_account_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="payment_amount" class="block text-sm font-medium text-gray-700">Amount
                                    Received (KES) <span class="text-red-600">*</span></label>
                                <input type="number" step="0.01" id="payment_amount" name="payment_amount"
                                       value="{{ old('payment_amount') }}"
                                       min="0.01"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('payment_amount') border-red-500 @enderror"
                                       required>
                                <div class="mt-1 text-xs text-gray-500">
                                    Outstanding principal: KES {{ number_format($loanGiven->remaining_principal, 0) }}
                                </div>
                                @error('payment_amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Interest portion of this payment — only relevant for a rollover
                                 (partial) payment, not a final close, so it's hidden when
                                 "close as fully repaid" is checked. See JS below. -->
                            <div id="interestPortionWrapper">
                                <label for="interest_portion" class="block text-sm font-medium text-gray-700">
                                    Interest Portion of This Payment (KES)
                                </label>
                                <input type="number" step="0.01" min="0" id="interest_portion" name="interest_portion"
                                       value="{{ old('interest_portion') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('interest_portion') border-red-500 @enderror">
                                <p class="mt-1 text-xs text-gray-500" id="principalPortionHint">
                                    e.g. borrowed KES 4,000, they pay KES 2,500 with KES 600 as interest — KES 1,900
                                    reduces the principal, leaving KES 2,100 outstanding. Leave blank if this
                                    payment is pure principal (no interest recognized yet). Any partial payment —
                                    with or without interest specified — pushes the due date 30 days out from
                                    this payment's date, starting a new period on the remaining principal.
                                </p>
                                @error('interest_portion')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date
                                    <span class="text-red-600">*</span></label>
                                <input type="date" id="payment_date" name="payment_date"
                                       value="{{ old('payment_date', now()->format('Y-m-d')) }}"
                                       max="{{ now()->format('Y-m-d') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('payment_date') border-red-500 @enderror"
                                       required>
                                @error('payment_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Interest Account Field — OPTIONAL. Only surfaced when this
                                 closing payment lands in a referrer float account, as a way
                                 to route the interest straight out immediately if you want
                                 to. Leaving it blank keeps principal + interest together in
                                 the float, which is fully trackable via the referrer's float
                                 reconciliation page — not a dead end. -->
                            <div id="interestAccountWrapper">
                                <label for="interest_account_id" class="block text-sm font-medium text-gray-700">
                                    Deposit Interest Into <span class="text-gray-400 font-normal">(optional)</span>
                                </label>
                                <select id="interest_account_id" name="interest_account_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('interest_account_id') border-red-500 @enderror">
                                    <option value="">Keep it in the float with the principal</option>
                                    @foreach($accounts as $account)
                                        @if($account->type !== 'referrer_float')
                                            <option
                                                value="{{ $account->id }}" {{ old('interest_account_id') == $account->id ? 'selected' : '' }}>
                                                {{ $account->name }}
                                                (KES {{ number_format($account->current_balance, 0) }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-600">
                                    This payment is landing in a referrer float. Pick an account here only if you
                                    want the interest routed out right away — otherwise it's fine to leave it, and
                                    it'll show as pending for this loan on the referrer's float reconciliation page.
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
                                <div
                                    class="border border-amber-200 bg-amber-50 rounded-lg p-4 h-full flex items-center">
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
                                                    The
                                                    referrer's {{ number_format($loanGiven->referrer_share_percentage ?? 0, 1) }}
                                                    % cut
                                                    of that interest will also be calculated at that point.
                                                @endif
                    </span>
                                        </label>
                                    </div>
                                    @if($loanGiven->referrer)
                                        <div class="flex items-start mt-3 pt-3 border-t border-amber-200">
                                            <input type="checkbox" id="referrer_deducted_before_deposit"
                                                   name="referrer_deducted_before_deposit" value="1"
                                                   class="mt-0.5 rounded border-gray-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50">
                                            <label for="referrer_deducted_before_deposit"
                                                   class="ml-2 block text-sm text-gray-800">
                                                <span class="font-medium">{{ $loanGiven->referrer->name }} already kept their {{ number_format($loanGiven->referrer_share_percentage ?? 0, 1) }}% before depositing this.</span>
                                                <span class="block text-xs text-gray-600 mt-1">
                                                    Check this if the amount above already excludes the referrer's cut. Nothing further will be
                                                    shown as owed to them for this loan — their retained amount is just recorded for your records.
                                                </span>
                                            </label>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M5 13l4 4L19 7"></path>
                                </svg>
                                Record Payment
                            </button>
                            <a href="{{ route('loans-given.show', $loanGiven->id) }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
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

            const interestPortionWrapper = document.getElementById('interestPortionWrapper');
            const interestPortionInput = document.getElementById('interest_portion');
            const paymentAmountInput = document.getElementById('payment_amount');
            const principalPortionHint = document.getElementById('principalPortionHint');
            const defaultHintText = principalPortionHint.textContent;

            function isFloatSelected() {
                const opt = paymentAccountSelect.options[paymentAccountSelect.selectedIndex];
                return opt && opt.dataset.type === 'referrer_float';
            }

            // Purely a visibility toggle — the field is never required. Leaving
            // it blank when a closing payment lands in the float just means the
            // interest stays there with the principal, which is fine.
            function refresh() {
                const show = closeLoanCheckbox.checked && isFloatSelected();

                if (show) {
                    wrapper.style.display = 'block';
                } else {
                    wrapper.style.display = 'none';
                    interestSelect.value = ''; // Clear selection when hidden
                }
            }

            // The interest-portion field only makes sense for a rollover (partial)
            // payment — a final close already computes total interest from the
            // full surplus automatically, so specifying it separately here would
            // double up. Hide and clear it whenever "close as final payment" is on.
            function refreshInterestPortion() {
                if (closeLoanCheckbox.checked) {
                    interestPortionWrapper.style.display = 'none';
                    interestPortionInput.value = '';
                } else {
                    interestPortionWrapper.style.display = 'block';
                }
                updatePrincipalPreview();
            }

            // Any partial payment (close_loan unchecked) pushes the due date 30
            // days out, whether or not an interest portion is specified. The
            // interest/principal breakdown line only differs based on whether
            // interest was entered.
            function updatePrincipalPreview() {
                const amount = parseFloat(paymentAmountInput.value) || 0;
                const interest = parseFloat(interestPortionInput.value) || 0;

                if (closeLoanCheckbox.checked || amount <= 0) {
                    principalPortionHint.textContent = defaultHintText;
                    return;
                }

                if (interest > 0) {
                    const principalPortion = Math.max(0, amount - interest);
                    principalPortionHint.textContent =
                        `KES ${principalPortion.toLocaleString('en-US', {maximumFractionDigits: 0})} of this payment reduces principal; ` +
                        `KES ${interest.toLocaleString('en-US', {maximumFractionDigits: 0})} is recorded as interest. Due date moves 30 days out from the payment date above.`;
                } else {
                    principalPortionHint.textContent =
                        `This entire payment reduces principal. Due date moves 30 days out from the payment date above.`;
                }
            }

            paymentAccountSelect.addEventListener('change', refresh);
            closeLoanCheckbox.addEventListener('change', refresh);
            closeLoanCheckbox.addEventListener('change', refreshInterestPortion);
            interestPortionInput.addEventListener('input', updatePrincipalPreview);
            paymentAmountInput.addEventListener('input', updatePrincipalPreview);

            refresh();
            refreshInterestPortion();
        })();
    </script>
</x-app-layout>
