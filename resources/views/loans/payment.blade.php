@extends('layout')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold">Make Loan Payment</h1>
            <a href="{{ route('loans.show', $loan) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">
                ← Back to Loan
            </a>
        </div>

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        <!-- Loan Summary Card -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <p class="text-sm text-gray-600 uppercase">Loan Source</p>
                    <p class="text-lg font-bold text-gray-900">{{ $loan->source }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 uppercase">Total Due</p>
                    <p class="text-lg font-bold text-gray-900">KES {{ number_format($loan->total_amount, 0, '.', ',') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 uppercase">Amount Paid</p>
                    <p class="text-lg font-bold text-green-600">KES {{ number_format($loan->amount_paid, 0, '.', ',') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 uppercase">Balance</p>
                    <p class="text-lg font-bold text-red-600">KES {{ number_format($loan->balance, 0, '.', ',') }}</p>
                </div>
            </div>

            <!-- Outstanding Breakdown -->
            @php
                // Calculate remaining principal and interest
                $totalPrincipalPaid = $loan->payments->sum('principal_portion');
                $totalInterestPaid = $loan->payments->sum('interest_portion');
                $remainingPrincipal = $loan->principal_amount - $totalPrincipalPaid;
                $remainingInterest = ($loan->interest_amount ?? 0) - $totalInterestPaid;

                // Calculate interest from facilitation fee (7.5% of principal)
                if ($loan->interest_amount === null || $loan->interest_amount == 0) {
                    // M-Shwari style: facilitation fee = 7.5% of principal
                    $facilitationFee = $loan->principal_amount * 0.09;
                    $remainingInterest = $facilitationFee - $totalInterestPaid;
                }
            @endphp

            <div class="grid grid-cols-2 gap-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div>
                    <p class="text-xs text-gray-600 uppercase">Remaining Principal</p>
                    <p class="text-base font-bold text-gray-900">KES {{ number_format($remainingPrincipal, 0, '.', ',') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-600 uppercase">Remaining Interest/Fees</p>
                    <p class="text-base font-bold text-gray-900">KES {{ number_format(max(0, $remainingInterest), 0, '.', ',') }}</p>
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
        <form method="POST" action="{{ route('loans.payment.store', $loan) }}" class="bg-white shadow rounded-lg p-6">
            @csrf

            <!-- Hidden fields for remaining amounts -->
            <input type="hidden" id="remaining_interest" value="{{ max(0, $remainingInterest) }}">
            <input type="hidden" id="remaining_principal" value="{{ $remainingPrincipal }}">

            <!-- Payment Amount -->
            <div class="mb-6">
                <label for="payment_amount" class="block text-gray-700 font-semibold mb-2">
                    Payment Amount <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center">
                    <span class="text-gray-700 font-semibold mr-3">KES</span>
                    <input
                        type="number"
                        name="payment_amount"
                        id="payment_amount"
                        step="0.01"
                        min="0.01"
                        max="{{ $loan->balance }}"
                        value="{{ old('payment_amount') }}"
                        class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-lg @error('payment_amount') border-red-500 @enderror"
                        placeholder="Enter payment amount"
                        required
                    >
                </div>
                <p class="text-xs text-gray-500 mt-2">Maximum: KES {{ number_format($loan->balance, 0, '.', ',') }}</p>
                @error('payment_amount')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror

                <!-- Quick Amount Buttons -->
                <div class="mt-3 flex gap-2">
                    <button type="button" onclick="setPaymentAmount({{ $remainingInterest }})"
                            class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                        Interest Only (KES {{ number_format(max(0, $remainingInterest), 0) }})
                    </button>
                    <button type="button" onclick="setPaymentAmount({{ $loan->balance }})"
                            class="px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200">
                        Full Balance (KES {{ number_format($loan->balance, 0) }})
                    </button>
                </div>
            </div>

            <!-- Payment Breakdown -->
            <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <p class="text-sm font-semibold text-gray-700 mb-4">Payment Allocation (Auto-calculated)</p>

                <div class="space-y-4">
                    <div>
                        <label for="interest_portion" class="block text-sm font-medium text-gray-700 mb-1">
                            Interest/Fees Portion
                        </label>
                        <div class="flex items-center">
                            <span class="text-gray-600 mr-2">KES</span>
                            <input
                                type="number"
                                name="interest_portion"
                                id="interest_portion"
                                step="0.01"
                                min="0"
                                value="{{ old('interest_portion', '0') }}"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded bg-gray-50 @error('interest_portion') border-red-500 @enderror"
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
                        <div class="flex items-center">
                            <span class="text-gray-600 mr-2">KES</span>
                            <input
                                type="number"
                                name="principal_portion"
                                id="principal_portion"
                                step="0.01"
                                min="0"
                                value="{{ old('principal_portion', '0') }}"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded bg-gray-50 @error('principal_portion') border-red-500 @enderror"
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
                    <input type="checkbox" id="manual_override" class="mr-2">
                    <label for="manual_override" class="text-sm text-gray-700 cursor-pointer">
                        Enable manual allocation (unlock fields)
                    </label>
                </div>
            </div>

            <!-- Payment Date -->
            <div class="mb-6">
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
                >
                @error('payment_date')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Notes -->
            <div class="mb-6">
                <label for="notes" class="block text-gray-700 font-semibold mb-2">
                    Notes <span class="text-gray-500 text-sm font-normal">(Optional)</span>
                </label>
                <textarea
                    name="notes"
                    id="notes"
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded"
                    placeholder="e.g., Partial payment, reference number, etc."
                >{{ old('notes') }}</textarea>
            </div>

            <!-- Submit Buttons -->
            <div class="flex gap-4">
                <button type="submit" class="flex-1 bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700 font-semibold text-lg">
                    Record Payment
                </button>
                <a href="{{ route('loans.show', $loan) }}" class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded hover:bg-gray-400 font-semibold text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        const amountInput = document.getElementById('payment_amount');
        const principalInput = document.getElementById('principal_portion');
        const interestInput = document.getElementById('interest_portion');
        const totalDisplay = document.getElementById('total_display');
        const manualOverride = document.getElementById('manual_override');

        const remainingInterest = parseFloat(document.getElementById('remaining_interest').value) || 0;
        const remainingPrincipal = parseFloat(document.getElementById('remaining_principal').value) || 0;

        // Format number as currency
        function formatCurrency(num) {
            return num.toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        }

        // Set payment amount from quick buttons
        function setPaymentAmount(amount) {
            amountInput.value = amount.toFixed(2);
            autoAllocatePayment();
        }

        // Auto-allocate payment: Interest first, then Principal
        function autoAllocatePayment() {
            const paymentAmount = parseFloat(amountInput.value) || 0;

            if (paymentAmount <= 0) {
                interestInput.value = '0';
                principalInput.value = '0';
                updateTotalDisplay();
                return;
            }

            // Allocate to interest first (up to remaining interest)
            const interestPayment = Math.min(paymentAmount, remainingInterest);

            // Remaining goes to principal
            const principalPayment = Math.max(0, paymentAmount - interestPayment);

            interestInput.value = interestPayment.toFixed(2);
            principalInput.value = principalPayment.toFixed(2);

            updateTotalDisplay();
        }

        // Update total display
        function updateTotalDisplay() {
            const principal = parseFloat(principalInput.value) || 0;
            const interest = parseFloat(interestInput.value) || 0;
            const total = principal + interest;

            totalDisplay.textContent = 'KES ' + formatCurrency(total);
        }

        // Toggle manual override
        manualOverride.addEventListener('change', function() {
            if (this.checked) {
                principalInput.removeAttribute('readonly');
                interestInput.removeAttribute('readonly');
                principalInput.classList.remove('bg-gray-50');
                interestInput.classList.remove('bg-gray-50');
                principalInput.classList.add('bg-white');
                interestInput.classList.add('bg-white');
            } else {
                principalInput.setAttribute('readonly', 'readonly');
                interestInput.setAttribute('readonly', 'readonly');
                principalInput.classList.add('bg-gray-50');
                interestInput.classList.add('bg-gray-50');
                principalInput.classList.remove('bg-white');
                interestInput.classList.remove('bg-white');
                autoAllocatePayment();
            }
        });

        // Event listeners
        amountInput.addEventListener('input', function() {
            if (!manualOverride.checked) {
                autoAllocatePayment();
            }
        });

        principalInput.addEventListener('input', function() {
            if (manualOverride.checked) {
                updateTotalDisplay();
            }
        });

        interestInput.addEventListener('input', function() {
            if (manualOverride.checked) {
                updateTotalDisplay();
            }
        });

        // Initialize on page load
        window.addEventListener('load', () => {
            if (amountInput.value) {
                autoAllocatePayment();
            } else {
                updateTotalDisplay();
            }
        });
    </script>
@endsection
