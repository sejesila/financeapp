<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Make Loan Payment') }}
            </h2>
            <a href="{{ route('loans.show', $loan) }}" class="mt-2 sm:mt-0 text-indigo-600 hover:text-indigo-800 text-sm">
                ← Back to Loan
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        @php
            $totalPrincipalPaid = $loan->payments->sum('principal_portion');
            $totalInterestPaid = $loan->payments->sum('interest_portion');
            $remainingPrincipal = $loan->principal_amount - $totalPrincipalPaid;
            $remainingInterest = ($loan->interest_amount ?? 0) - $totalInterestPaid;

            if ($loan->interest_amount === null || $loan->interest_amount == 0) {
                $facilitationFee = $loan->principal_amount * 0.09;
                $remainingInterest = $facilitationFee - $totalInterestPaid;
            }
        @endphp

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        @if($accounts->isEmpty())
            <div class="bg-yellow-100 text-yellow-800 p-4 rounded mb-6">
                <p class="font-semibold">⚠️ No Eligible Accounts Found</p>
                <p class="text-sm mt-2">
                    You need an account with at least KES {{ number_format($minRequiredBalance ?? 0, 0) }}
                    (25% of outstanding balance) to make a payment.
                </p>
                <p class="text-sm mt-1">
                    Note: Savings accounts cannot be used for loan payments.
                </p>
            </div>
        @endif

        <!-- Loan Summary Card -->
        <div class="bg-white shadow rounded-lg p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <p class="text-sm text-gray-600 uppercase">Loan Source</p>
                    <p class="text-lg font-bold text-gray-900">{{ $loan->source }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 uppercase">Total Due</p>
                    <p class="text-lg font-bold text-gray-900">
                        KES {{ number_format($loan->total_amount, 0, '.', ',') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 uppercase">Amount Paid</p>
                    <p class="text-lg font-bold text-green-600">
                        KES {{ number_format($loan->amount_paid, 0, '.', ',') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 uppercase">Balance</p>
                    <p class="text-lg font-bold text-red-600">KES {{ number_format($loan->balance, 0, '.', ',') }}</p>
                </div>
            </div>

            <!-- Outstanding Breakdown -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div>
                    <p class="text-xs text-gray-600 uppercase">Remaining Principal</p>
                    <p class="text-base font-bold text-gray-900">
                        KES {{ number_format($remainingPrincipal, 0, '.', ',') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Remaining Interest/Fees</p>
                    <p class="text-base font-bold text-gray-900">
                        KES {{ number_format(max(0, $remainingInterest), 0, '.', ',') }}</p>
                </div>
            </div>

            <!-- Progress Bar -->
            @if($loan->total_amount > 0)
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        @php
                            $percentage = ($loan->amount_paid / $loan->total_amount) * 100;
                        @endphp
                        <div class="bg-green-500 h-3 rounded-full" style="width: {{ min($percentage, 100) }}%"></div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2 text-center">
                        {{ number_format($percentage, 1) }}% repaid
                    </p>
                </div>
            @endif
        </div>

        <!-- Payment Form -->
        <form method="POST" action="{{ route('loans.payment.store', $loan) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf

            <input type="hidden" id="remaining_interest" value="{{ max(0, $remainingInterest) }}">
            <input type="hidden" id="remaining_principal" value="{{ $remainingPrincipal }}">

            <!-- Payment Account Selection -->
            <div>
                <label for="payment_account_id" class="block text-gray-700 font-semibold mb-2">
                    Pay From Account <span class="text-red-500">*</span>
                </label>
                <select
                    name="payment_account_id"
                    id="payment_account_id"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base @error('payment_account_id') border-red-500 @enderror"
                    required
                    onchange="updateAccountBalance()"
                    {{ $accounts->isEmpty() ? 'disabled' : '' }}>
                    <option value="">Select account to pay from</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}"
                                data-balance="{{ $account->current_balance }}"
                            {{ old('payment_account_id', $loan->account_id) == $account->id ? 'selected' : '' }}>
                            {{ $account->name }}
                            (Balance: KES {{ number_format($account->current_balance, 0, '.', ',') }})
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-2" id="selected_account_balance">
                    @if($accounts->isNotEmpty())
                        @if($loan->account && $accounts->contains('id', $loan->account_id))
                            Available balance: KES {{ number_format($loan->account->current_balance, 0, '.', ',') }}
                        @else
                            Select an account to see available balance
                        @endif
                    @else
                        Top up an account to at least KES {{ number_format($minRequiredBalance ?? 0, 0) }} to proceed
                    @endif
                </p>
                <p class="text-xs text-blue-600 mt-1">
                    💡 Only accounts with at least 25% of the payment amount are shown (savings accounts excluded)
                </p>
                @error('payment_account_id')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Payment Amount -->
            <div>
                <label for="payment_amount" class="block text-gray-700 font-semibold mb-2">
                    Payment Amount <span class="text-red-500">*</span>
                </label>
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 w-full">
                    <span class="text-gray-700 font-semibold">KES</span>
                    <input
                        type="number"
                        name="payment_amount"
                        id="payment_amount"
                        step="0.01"
                        min="0.01"
                        max="{{ $loan->balance }}"
                        value="{{ old('payment_amount') }}"
                        class="flex-1 w-full px-4 py-3 border border-gray-300 rounded-lg text-lg @error('payment_amount') border-red-500 @enderror"
                        placeholder="Enter payment amount"
                        required
                        {{ $accounts->isEmpty() ? 'disabled' : '' }}
                    >
                </div>
                <p class="text-xs text-gray-500 mt-2">Maximum: KES {{ number_format($loan->balance, 0, '.', ',') }}</p>
                @error('payment_amount')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror

                <!-- Quick Amount Buttons -->
                <div class="mt-3 flex flex-col sm:flex-row gap-2 w-full">
                    <button type="button" onclick="setPaymentAmount({{ $remainingInterest }})"
                            class="w-full sm:w-auto px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                        {{ $accounts->isEmpty() ? 'disabled' : '' }}>
                        Interest Only (KES {{ number_format(max(0, $remainingInterest), 0) }})
                    </button>
                    <button type="button" onclick="setPaymentAmount({{ $loan->balance }})"
                            class="w-full sm:w-auto px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"
                        {{ $accounts->isEmpty() ? 'disabled' : '' }}>
                        Full Balance (KES {{ number_format($loan->balance, 0) }})
                    </button>
                </div>
            </div>

            <!-- Payment Breakdown -->
            <div class="p-4 bg-blue-50 rounded-lg border border-blue-200 space-y-4">
                <p class="text-sm font-semibold text-gray-700 mb-4">Payment Allocation (Auto-calculated)</p>

                <div class="space-y-4">
                    <div>
                        <label for="interest_portion" class="block text-sm font-medium text-gray-700 mb-1">
                            Interest/Fees Portion
                        </label>
                        <div class="flex items-center gap-2 w-full">
                            <span class="text-gray-600">KES</span>
                            <input
                                type="number"
                                name="interest_portion"
                                id="interest_portion"
                                step="0.01"
                                min="0"
                                value="{{ old('interest_portion', '0') }}"
                                class="flex-1 w-full px-4 py-2 border border-gray-300 rounded bg-gray-50 @error('interest_portion') border-red-500 @enderror"
                                placeholder="0"
                                readonly
                            >
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Remaining interest/fees: KES {{ number_format(max(0, $remainingInterest), 0) }}</p>
                        @error('interest_portion')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="principal_portion" class="block text-sm font-medium text-gray-700 mb-1">
                            Principal Portion
                        </label>
                        <div class="flex items-center gap-2 w-full">
                            <span class="text-gray-600">KES</span>
                            <input
                                type="number"
                                name="principal_portion"
                                id="principal_portion"
                                step="0.01"
                                min="0"
                                value="{{ old('principal_portion', '0') }}"
                                class="flex-1 w-full px-4 py-2 border border-gray-300 rounded bg-gray-50 @error('principal_portion') border-red-500 @enderror"
                                placeholder="0"
                                readonly
                            >
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Remaining principal: KES {{ number_format($remainingPrincipal, 0) }}</p>
                        @error('principal_portion')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-4 border-t-2 border-blue-300">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700 font-semibold">Total Payment:</span>
                            <span class="font-bold text-xl text-gray-900" id="total_display">
                                KES 0
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-100 rounded">
                    <p class="text-xs text-blue-800 font-semibold mb-2">📋 Auto-allocation Logic:</p>
                    <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                        <li><strong>Interest/Fees First:</strong> Payment covers remaining interest/fees</li>
                        <li><strong>Principal Second:</strong> Any remaining amount goes to principal</li>
                        <li>If you want custom allocation, you can manually override these fields</li>
                    </ol>
                </div>

                <div class="mt-3 flex items-center">
                    <input type="checkbox" id="manual_override" class="mr-2" {{ $accounts->isEmpty() ? 'disabled' : '' }}>
                    <label for="manual_override" class="text-sm text-gray-700 cursor-pointer">
                        Enable manual allocation (unlock fields)
                    </label>
                </div>
            </div>

            <!-- Payment Date -->
            <div>
                <label for="payment_date" class="block text-gray-700 font-semibold mb-2">
                    Payment Date <span class="text-red-500">*</span>
                </label>
                <input
                    type="date"
                    name="payment_date"
                    id="payment_date"
                    value="{{ old('payment_date', date('Y-m-d')) }}"
                    max="{{ date('Y-m-d') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded @error('payment_date') border-red-500 @enderror"
                    required
                    {{ $accounts->isEmpty() ? 'disabled' : '' }}
                >
                @error('payment_date')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-gray-700 font-semibold mb-2">
                    Notes <span class="text-gray-500 text-sm font-normal">(Optional)</span>
                </label>
                <textarea
                    name="notes"
                    id="notes"
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded"
                    placeholder="e.g., Partial payment, reference number, etc."
                    {{ $accounts->isEmpty() ? 'disabled' : '' }}
                >{{ old('notes') }}</textarea>
            </div>

            <!-- Submit Buttons -->
            <div class="flex flex-col sm:flex-row gap-4">
                <button type="submit"
                        {{ $accounts->isEmpty() ? 'disabled' : '' }}
                        class="w-full sm:flex-1 {{ $accounts->isEmpty() ? 'bg-gray-400 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700' }} text-white px-6 py-3 rounded font-semibold text-lg">
                    {{ $accounts->isEmpty() ? 'No Eligible Accounts' : 'Record Payment' }}
                </button>
                <a href="{{ route('loans.show', $loan) }}"
                   class="w-full sm:flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded hover:bg-gray-400 font-semibold text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        // Update account balance display when account is selected
        function updateAccountBalance() {
            const accountSelect = document.getElementById('payment_account_id');
            const selectedOption = accountSelect.options[accountSelect.selectedIndex];
            const balance = selectedOption.getAttribute('data-balance');
            const balanceDisplay = document.getElementById('selected_account_balance');

            if (balance) {
                const formattedBalance = parseFloat(balance).toLocaleString('en-US', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });
                balanceDisplay.textContent = `Available balance: KES ${formattedBalance}`;
                balanceDisplay.classList.remove('text-red-500');
                balanceDisplay.classList.add('text-gray-500');
            } else {
                balanceDisplay.textContent = 'Select an account to see available balance';
                balanceDisplay.classList.remove('text-red-500', 'text-gray-500');
            }
        }

        // Set payment amount from quick buttons
        function setPaymentAmount(amount) {
            document.getElementById('payment_amount').value = amount.toFixed(2);
            calculatePaymentAllocation();
        }

        // Calculate payment allocation
        function calculatePaymentAllocation() {
            const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
            const remainingInterest = parseFloat(document.getElementById('remaining_interest').value) || 0;
            const remainingPrincipal = parseFloat(document.getElementById('remaining_principal').value) || 0;

            let interestPortion = Math.min(paymentAmount, remainingInterest);
            let principalPortion = Math.max(0, paymentAmount - interestPortion);

            // Cap principal portion to remaining principal
            principalPortion = Math.min(principalPortion, remainingPrincipal);

            document.getElementById('interest_portion').value = interestPortion.toFixed(2);
            document.getElementById('principal_portion').value = principalPortion.toFixed(2);

            const total = interestPortion + principalPortion;
            document.getElementById('total_display').textContent = `KES ${total.toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            })}`;
        }

        // Enable manual override
        document.getElementById('manual_override').addEventListener('change', function() {
            const interestInput = document.getElementById('interest_portion');
            const principalInput = document.getElementById('principal_portion');

            if (this.checked) {
                interestInput.removeAttribute('readonly');
                principalInput.removeAttribute('readonly');
                interestInput.classList.remove('bg-gray-50');
                principalInput.classList.remove('bg-gray-50');
                interestInput.classList.add('bg-white');
                principalInput.classList.add('bg-white');
            } else {
                interestInput.setAttribute('readonly', true);
                principalInput.setAttribute('readonly', true);
                interestInput.classList.add('bg-gray-50');
                principalInput.classList.add('bg-gray-50');
                interestInput.classList.remove('bg-white');
                principalInput.classList.remove('bg-white');
                calculatePaymentAllocation();
            }
        });

        // Auto-calculate on payment amount change
        document.getElementById('payment_amount').addEventListener('input', function() {
            if (!document.getElementById('manual_override').checked) {
                calculatePaymentAllocation();
            }
        });

        // Manual allocation change updates total
        document.getElementById('interest_portion').addEventListener('input', updateTotal);
        document.getElementById('principal_portion').addEventListener('input', updateTotal);

        function updateTotal() {
            const interestPortion = parseFloat(document.getElementById('interest_portion').value) || 0;
            const principalPortion = parseFloat(document.getElementById('principal_portion').value) || 0;
            const total = interestPortion + principalPortion;

            document.getElementById('total_display').textContent = `KES ${total.toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            })}`;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateAccountBalance();
            calculatePaymentAllocation();
        });
    </script>
</x-app-layout>
