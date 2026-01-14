<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                    Record Rolling Funds Investment
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Record your investment now, update outcome later
                </p>
            </div>
            <a href="{{ route('rolling-funds.index') }}"
               class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 text-sm font-medium">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">

            @if ($errors->any())
                <div class="bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-4 rounded-xl mb-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <p class="font-semibold mb-2">Please fix the following errors:</p>
                            <ul class="list-disc list-inside text-sm space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            @php
                $mpesaAccount = $accounts->firstWhere('type', 'mpesa');
            @endphp

            @if(!$mpesaAccount)
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-yellow-700 dark:text-yellow-400 p-6 rounded-xl mb-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <p class="font-semibold text-lg mb-2">M-Pesa Account Required</p>
                            <p class="text-sm">You need an M-Pesa account to record Rolling Funds investments. Please create one first.</p>
                            <a href="{{ route('accounts.create') }}" class="inline-flex items-center gap-2 mt-3 text-sm font-semibold text-yellow-700 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-200">
                                Create M-Pesa Account
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700">
                    <div class="p-6 sm:p-8 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl">💰</span>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-200">Investment Details</h3>

                            </div>
                        </div>
                    </div>

                    <form action="{{ route('rolling-funds.store') }}" method="POST" id="rollingFundForm">
                        @csrf

                        <div class="p-6 sm:p-8 space-y-6">
                            <!-- Date -->
                            <div>
                                <label for="date" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date"
                                       name="date"
                                       id="date"
                                       value="{{ old('date', date('Y-m-d')) }}"
                                       max="{{ date('Y-m-d') }}"
                                       required
                                       class="w-full rounded-xl border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 px-4 py-3 transition-all">
                            </div>

                            <!-- M-Pesa Account (Fixed/Immutable) -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    Source Account <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="w-full rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 px-4 py-3 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <span class="text-2xl">📱</span>
                                            <div>
                                                <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $mpesaAccount->name }}</p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">Balance: KES {{ number_format($mpesaAccount->current_balance, 0) }}</p>
                                            </div>
                                        </div>
                                        <div class="bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 px-3 py-1 rounded-lg text-xs font-semibold">
                                            M-Pesa
                                        </div>
                                    </div>
                                    <input type="hidden" name="account_id" value="{{ $mpesaAccount->id }}" id="account_id">
                                </div>
                            </div>

                            <!-- Stake Amount -->
                            <div>
                                <label for="stake_amount" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                    Investment Amount <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 font-medium">KES</span>
                                    <input type="number"
                                           name="stake_amount"
                                           id="stake_amount"
                                           value="{{ old('stake_amount') }}"
                                           step="0.01"
                                           min="0.01"
                                           required
                                           placeholder="0.00"
                                           class="w-full rounded-xl border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 pl-16 pr-4 py-3 text-lg font-semibold transition-all">
                                </div>
                                <div id="fee-info" class="mt-3 text-sm text-gray-600 dark:text-gray-400 hidden">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                        <div class="flex items-start gap-2">
                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                            <div class="flex-1">
                                                <p class="font-semibold text-gray-700 dark:text-gray-300 mb-1">Transaction Fee Details</p>
                                                <div class="space-y-1 text-sm">
                                                    <div class="flex justify-between">
                                                        <span>Transaction Fee:</span>
                                                        <span class="font-semibold">KES <span id="fee-amount">0</span></span>
                                                    </div>
                                                    <div class="flex justify-between border-t border-blue-200 dark:border-blue-700 pt-1">
                                                        <span class="font-semibold">Total Deduction:</span>
                                                        <span class="font-bold">KES <span id="total-deduction">0</span></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Form Actions -->
                        <div class="px-6 sm:px-8 py-5 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-600 flex flex-col-reverse sm:flex-row justify-between items-stretch sm:items-center gap-3 rounded-b-2xl">
                            <a href="{{ route('rolling-funds.index') }}"
                               class="text-center sm:text-left text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 text-sm font-semibold px-6 py-3 sm:py-0">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02] flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Record Investment
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            const paybillCosts = @json($paybillCosts);
            const mpesaBalance = {{ $mpesaAccount->current_balance ?? 0 }};

            function calculatePaybillFee(amount) {
                for (let tier of paybillCosts) {
                    if (amount >= tier.min && amount <= tier.max) {
                        return tier.cost;
                    }
                }
                return paybillCosts[paybillCosts.length - 1].cost || 0;
            }

            function updateFeeInfo() {
                const stakeInput = document.getElementById('stake_amount');
                const feeInfo = document.getElementById('fee-info');
                const feeAmountSpan = document.getElementById('fee-amount');
                const totalDeductionSpan = document.getElementById('total-deduction');

                const stakeAmount = parseFloat(stakeInput.value) || 0;

                if (stakeAmount > 0) {
                    const fee = calculatePaybillFee(stakeAmount);
                    const total = stakeAmount + fee;

                    feeAmountSpan.textContent = fee.toFixed(2);
                    totalDeductionSpan.textContent = total.toFixed(2);
                    feeInfo.classList.remove('hidden');

                    if (mpesaBalance < total) {
                        const parent = feeInfo.querySelector('div');
                        parent.classList.remove('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
                        parent.classList.add('bg-red-50', 'dark:bg-red-900/20', 'border-red-200', 'dark:border-red-800');
                        parent.innerHTML = `
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                <div class="flex-1">
                                    <p class="font-semibold text-red-700 dark:text-red-400 mb-1">⚠️ Insufficient Balance!</p>
                                    <div class="space-y-1 text-sm text-red-600 dark:text-red-400">
                                        <div class="flex justify-between">
                                            <span>Transaction Fee:</span>
                                            <span class="font-semibold">KES ${fee.toFixed(2)}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Total Required:</span>
                                            <span class="font-semibold">KES ${total.toFixed(2)}</span>
                                        </div>
                                        <div class="flex justify-between border-t border-red-200 dark:border-red-700 pt-1">
                                            <span class="font-semibold">Available:</span>
                                            <span class="font-bold">KES ${mpesaBalance.toFixed(2)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        const parent = feeInfo.querySelector('div');
                        parent.classList.remove('bg-red-50', 'dark:bg-red-900/20', 'border-red-200', 'dark:border-red-800');
                        parent.classList.add('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
                    }
                } else {
                    feeInfo.classList.add('hidden');
                }
            }

            document.getElementById('stake_amount').addEventListener('input', updateFeeInfo);
            document.addEventListener('DOMContentLoaded', updateFeeInfo);
        </script>
    @endpush
</x-app-layout>
