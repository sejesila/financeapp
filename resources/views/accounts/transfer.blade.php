<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between mb-6">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Transfer Money') }}
            </h2>
            <a href="{{ route('accounts.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Accounts
            </a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto">

        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

            <form method="POST" action="{{ route('accounts.transferPost') }}" class="bg-white shadow rounded-lg p-6">
                @csrf

                <!-- From Account -->
                <div class="mb-4">
                    <label for="from_account_id" class="block text-gray-700 font-semibold mb-2">From Account</label>
                    <select name="from_account_id" id="from_account_id" required
                            class="w-full border border-gray-300 rounded px-4 py-2">
                        <option value="">Select source account</option>
                        @foreach($sourceAccounts as $account)
                            <option value="{{ $account->id }}" {{ old('from_account_id') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }} ({{ number_format($account->current_balance, 0, '.', ',') }})
                            </option>
                        @endforeach
                    </select>
                    @error('from_account_id')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- To Account -->
                <div class="mb-4">
                    <label for="to_account_id" class="block text-gray-700 font-semibold mb-2">To Account</label>
                    <select name="to_account_id" id="to_account_id" required
                            class="w-full border border-gray-300 rounded px-4 py-2">
                        <option value="">Select destination account</option>
                        @foreach($destinationAccounts as $account)
                            <option value="{{ $account->id }}" {{ old('to_account_id') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }} ({{ number_format($account->current_balance, 0, '.', ',') }})
                            </option>
                        @endforeach
                    </select>
                    @error('to_account_id')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Amount -->
                <div class="mb-4">
                    <label for="amount" class="block text-gray-700 font-semibold mb-2">Amount</label>
                    <input type="number" step="0.01" name="amount" id="amount" value="{{ old('amount') }}"
                           placeholder="Enter amount to transfer"
                           class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500" required>
                    @error('amount')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Date -->
                <div class="mb-4">
                    <label for="date" class="block text-gray-700 font-semibold mb-2">Date</label>
                    <input type="date" name="date" id="date" value="{{ old('date', date('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500" required>
                    @error('date')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-gray-700 font-semibold mb-2">Description (Optional)</label>
                    <input type="text" name="description" id="description" value="{{ old('description') }}"
                           placeholder="e.g., Withdraw from bank to M-Pesa"
                           class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500">
                    @error('description')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('accounts.index') }}" class="text-gray-600 hover:text-gray-800">Cancel</a>
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded hover:bg-purple-700 focus:outline-none">Transfer Money</button>
                </div>
            </form>


            <!-- Quick Info -->
        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <p class="text-sm text-blue-800">
                <strong>💡 Tip:</strong> Transfers don't affect your budget categories. They simply move money between
                your accounts.
            </p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const fromSelect = document.getElementById('from_account_id');
            const toSelect = document.getElementById('to_account_id');

            if (!fromSelect || !toSelect) return;

            function updateDestinationOptions() {
                const fromValue = fromSelect.value;

                Array.from(toSelect.options).forEach(option => {
                    if (!option.value) return; // skip placeholder

                    option.disabled = option.value === fromValue;
                    option.hidden = option.value === fromValue;
                });

                // Reset destination if it became invalid
                if (toSelect.value === fromValue) {
                    toSelect.value = '';
                }
            }

            fromSelect.addEventListener('change', updateDestinationOptions);

            // Run once on load (for old() values)
            updateDestinationOptions();
        });
    </script>

</x-app-layout>
