<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Record Interest — {{ $account->name }}
            </h2>
            <a href="{{ route('accounts.show', $account) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                ← Back
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Current Balance Card --}}
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl shadow-lg p-6 mb-8 border border-green-200 dark:border-green-800">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold mb-2">Current Balance</p>
                <p class="text-4xl font-bold text-green-600 dark:text-green-400">
                    KES {{ number_format($account->current_balance, 0, '.', ',') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-3 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Interest earned will be added on top of this amount
                </p>
            </div>

            {{-- Form Card --}}
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6 sm:p-8">

                @if($errors->any())
                    <div class="bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-4 rounded-lg mb-6">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('accounts.interest.store', $account) }}" class="space-y-6">
                    @csrf

                    <!-- Interest Amount (Required) -->
                    <div>
                        <label for="amount" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">
                            Interest Amount (KES) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400 font-medium">
                                KES
                            </span>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="amount"
                                id="amount"
                                value="{{ old('amount') }}"
                                placeholder="0.00"
                                class="w-full pl-12 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                required
                            >
                        </div>
                        @error('amount')
                        <p class="text-red-500 text-sm mt-2 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18.101 12.93a6 6 0 11-11.802-3.605l2.05-2.05a.75.75 0 10-1.061-1.061l-3.5 3.5a.75.75 0 000 1.061l3.5 3.5a.75.75 0 101.061-1.061l-2.05-2.05A5.972 5.972 0 0118 7v5.93zm-6.54-9.28a.75.75 0 00-1.061 1.061l2.05 2.05A5.972 5.972 0 006 13v-5.07a6 6 0 0111.802-3.605l-2.05 2.05a.75.75 0 11-1.061-1.061l3.5-3.5a.75.75 0 000-1.061l-3.5-3.5z" clip-rule="evenodd"/>
                            </svg>
                            {{ $message }}
                        </p>
                        @enderror
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            The amount of interest you earned on your savings
                        </p>
                    </div>

                    <!-- Interest Rate (Optional, for reference) -->
                    <div>
                        <label for="rate" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">
                            Interest Rate (%) <span class="text-xs font-normal text-gray-500 dark:text-gray-400">Optional</span>
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                name="rate"
                                id="rate"
                                value="{{ old('rate') }}"
                                placeholder="e.g., 2.5"
                                class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2.5 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            >
                            <button
                                type="button"
                                onclick="calculateInterest()"
                                class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 px-5 py-2.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 text-sm font-semibold transition border border-gray-300 dark:border-gray-600"
                            >
                                📊 Calculate
                            </button>
                        </div>
                        @error('rate')
                        <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            For reference only. If you know the annual rate, click Calculate and we'll compute the interest amount based on your current balance.
                        </p>
                    </div>

                    <!-- Date -->
                    <div>
                        <label for="date" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">
                            Date Interest Was Earned <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="date"
                            id="date"
                            value="{{ old('date', date('Y-m-d')) }}"
                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2.5 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            required
                        >
                        @error('date')
                        <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            Usually the last day of the month or quarter
                        </p>
                    </div>

                    <!-- Description (Optional) -->
                    <div>
                        <label for="description" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">
                            Description <span class="text-xs font-normal text-gray-500 dark:text-gray-400">Optional</span>
                        </label>
                        <input
                            type="text"
                            name="description"
                            id="description"
                            value="{{ old('description') }}"
                            placeholder="e.g., Q1 2024 Interest, January Monthly Interest"
                            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2.5 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        >
                        @error('description')
                        <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            Leave blank to auto-generate description
                        </p>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mt-8">
                        <div class="flex gap-3">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11 9a1 1 0 1100 2 1 1 0 000-2zm3 0a1 1 0 1100 2 1 1 0 000-2zm3 0a1 1 0 1100 2 1 1 0 000-2z" clip-rule="evenodd"/>
                            </svg>
                            <div class="text-sm text-blue-900 dark:text-blue-200">
                                <p class="font-semibold">How to record interest:</p>
                                <ol class="list-decimal list-inside space-y-1 mt-2 text-xs">
                                    <li>Enter the interest amount you received</li>
                                    <li>Select the date it was credited (usually month/quarter end)</li>
                                    <li>Optionally add your interest rate for reference</li>
                                    <li>Click "Record Interest"</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('accounts.show', $account) }}"
                           class="flex-1 text-center px-4 py-3 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold transition">
                            Cancel
                        </a>
                        <button
                            type="submit"
                            class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold transition shadow-md hover:shadow-lg">
                            📈 Record Interest
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
        function calculateInterest() {
            const balance = {{ $account->current_balance }};
            const rateInput = document.getElementById('rate');
            const amountInput = document.getElementById('amount');
            const rate = parseFloat(rateInput.value);

            if (!rate || rate <= 0) {
                alert('Please enter a valid interest rate (e.g., 2.5 for 2.5%)');
                rateInput.focus();
                return;
            }

            // Calculate simple interest for one year
            const annualInterest = balance * (rate / 100);

            // For monthly, divide by 12
            const monthlyInterest = annualInterest / 12;

            // Show confirmation
            const confirmed = confirm(
                `Balance: KES ${balance.toLocaleString()}\n` +
                `Rate: ${rate}% per annum\n` +
                `Monthly Interest: KES ${monthlyInterest.toFixed(2)}\n` +
                `Annual Interest: KES ${annualInterest.toFixed(2)}\n\n` +
                `Use monthly interest amount?`
            );

            if (confirmed) {
                amountInput.value = monthlyInterest.toFixed(2);
                amountInput.focus();
            }
        }
    </script>
</x-app-layout>
