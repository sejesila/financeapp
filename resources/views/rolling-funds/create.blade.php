<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                    Record Rolling Funds Investment
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Record your investment now, update outcome later
                </p>
            </div>
            <a href="{{ route('rolling-funds.index') }}"
               class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                ← Back
            </a>
        </div>
    </x-slot>

    <div class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">

            @if ($errors->any())
                <div class="bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-4 rounded-lg mb-6">
                    <p class="font-medium mb-2">Please fix the following errors:</p>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                <form action="{{ route('rolling-funds.store') }}" method="POST" id="rollingFundForm">
                    @csrf

                    <div class="p-6 space-y-6">
                        <!-- Date -->
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date"
                                   name="date"
                                   id="date"
                                   value="{{ old('date', date('Y-m-d')) }}"
                                   max="{{ date('Y-m-d') }}"
                                   required
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Account Selection -->
                        <div>
                            <label for="account_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Source Account <span class="text-red-500">*</span>
                            </label>
                            <select name="account_id"
                                    id="account_id"
                                    required
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select account</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}"
                                            data-balance="{{ $account->current_balance }}"
                                            data-type="{{ $account->type }}"
                                        {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                        {{ $account->name }} (KES {{ number_format($account->current_balance, 0) }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Select the account you'll use for the investment
                            </p>
                        </div>


                        <!-- Stake Amount -->
                        <div>
                            <label for="stake_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                               Amount <span class="text-red-500">*</span>
                            </label>
                            <input type="number"
                                   name="stake_amount"
                                   id="stake_amount"
                                   value="{{ old('stake_amount') }}"
                                   step="0.01"
                                   min="0.01"
                                   required
                                   placeholder="0.00"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                            <div id="fee-info" class="mt-2 text-xs text-gray-600 dark:text-gray-400 hidden">
                                <span class="font-medium">Transaction Fee:</span> KES <span id="fee-amount">0</span>
                                <br>
                                <span class="font-medium">Total Deduction:</span> KES <span id="total-deduction">0</span>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Notes
                            </label>
                            <textarea name="notes"
                                      id="notes"
                                      rows="3"
                                      placeholder="Add any additional details about this investment..."
                                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-600 flex justify-between items-center">
                        <a href="{{ route('rolling-funds.index') }}"
                           class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 text-sm font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                                class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 font-medium text-sm">
                            Record Investment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            const paybillCosts = @json($paybillCosts);

            function calculatePaybillFee(amount) {
                for (let tier of paybillCosts) {
                    if (amount >= tier.min && amount <= tier.max) {
                        return tier.cost;
                    }
                }
                return paybillCosts[paybillCosts.length - 1].cost || 0;
            }

            function updateFeeInfo() {
                const accountSelect = document.getElementById('account_id');
                const stakeInput = document.getElementById('stake_amount');
                const feeInfo = document.getElementById('fee-info');
                const feeAmountSpan = document.getElementById('fee-amount');
                const totalDeductionSpan = document.getElementById('total-deduction');

                const selectedOption = accountSelect.options[accountSelect.selectedIndex];
                const accountType = selectedOption.dataset.type;
                const accountBalance = parseFloat(selectedOption.dataset.balance) || 0;
                const stakeAmount = parseFloat(stakeInput.value) || 0;

                if (accountType === 'mpesa' && stakeAmount > 0) {
                    const fee = calculatePaybillFee(stakeAmount);
                    const total = stakeAmount + fee;

                    feeAmountSpan.textContent = fee.toFixed(2);
                    totalDeductionSpan.textContent = total.toFixed(2);
                    feeInfo.classList.remove('hidden');

                    if (accountBalance < total) {
                        feeInfo.classList.add('text-red-600', 'dark:text-red-400');
                        feeInfo.innerHTML = `
                        <span class="font-medium">⚠️ Insufficient Balance!</span><br>
                        Transaction Fee: KES ${fee.toFixed(2)}<br>
                        Total Required: KES ${total.toFixed(2)}<br>
                        Available: KES ${accountBalance.toFixed(2)}
                    `;
                    } else {
                        feeInfo.classList.remove('text-red-600', 'dark:text-red-400');
                    }
                } else {
                    feeInfo.classList.add('hidden');
                }
            }

            document.getElementById('account_id').addEventListener('change', updateFeeInfo);
            document.getElementById('stake_amount').addEventListener('input', updateFeeInfo);

            document.addEventListener('DOMContentLoaded', updateFeeInfo);
        </script>
    @endpush
</x-app-layout>
