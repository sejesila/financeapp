<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Record Client Fund') }}
            </h2>
            <a href="{{ route('client-funds.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm">
                ← Back to Client Funds
            </a>
        </div>
    </x-slot>

    <div class="py-3 sm:py-8">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="bg-red-100 text-red-700 p-3 sm:p-4 rounded mb-6 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($accounts->isEmpty())
                <div class="bg-yellow-100 text-yellow-800 p-4 rounded mb-6">
                    <p class="font-semibold">⚠️ No Eligible Accounts Found</p>
                    <p class="text-sm mt-2">
                        Client funds can only be received in M-Pesa or Bank accounts.
                        Please create one of these account types first.
                    </p>
                    <a href="{{ route('accounts.create') }}" class="text-blue-600 hover:text-blue-800 text-sm font-semibold mt-3 inline-block">
                        → Create Account
                    </a>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 sm:p-6">
                <div class="mb-6">
                    <h3 class="text-base sm:text-lg font-semibold mb-2">What is Client Fund Management?</h3>
                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                        Use this to track money that clients send you to purchase items or services on their behalf.
                        You can track expenses, calculate your profit (if any), and ensure accurate accounting.
                    </p>
                </div>

                <form method="POST" action="{{ route('client-funds.store') }}">
                    @csrf

                    <!-- Client Name -->
                    <div class="mb-4">
                        <label for="client_name" class="block text-sm sm:text-base text-gray-700 dark:text-gray-300 font-semibold mb-2">
                            Client/Company Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="client_name"
                            id="client_name"
                            value="{{ old('client_name') }}"
                            placeholder="e.g., John Doe, ABC Company, My Workplace"
                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 sm:px-4 py-2 text-sm focus:outline-none focus:border-indigo-500"
                            required
                            {{ $accounts->isEmpty() ? 'disabled' : '' }}
                        >
                        <p class="text-xs text-gray-500 mt-1">Who sent you this money?</p>
                    </div>

                    <!-- Fund Type -->
                    <div class="mb-4">
                        <label for="type" class="block text-sm sm:text-base text-gray-700 dark:text-gray-300 font-semibold mb-2">
                            Fund Type <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-3">
                            <label class="flex items-start p-3 border dark:border-gray-600 rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 {{ $accounts->isEmpty() ? 'opacity-50 cursor-not-allowed' : '' }}"
                                   onclick="if (!{{ $accounts->isEmpty() ? 'true' : 'false' }}) document.getElementById('commission').checked = true">
                                <input
                                    type="radio"
                                    name="type"
                                    id="commission"
                                    value="commission"
                                    {{ old('type') === 'commission' ? 'checked' : '' }}
                                    class="mt-1"
                                    required
                                    {{ $accounts->isEmpty() ? 'disabled' : '' }}
                                >
                                <div class="ml-3">
                                    <span class="font-semibold text-sm">💰 With Profit/Commission</span>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        You'll make a profit on this transaction (e.g., buying a laptop with markup)
                                    </p>
                                </div>
                            </label>

                            <label class="flex items-start p-3 border dark:border-gray-600 rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 {{ $accounts->isEmpty() ? 'opacity-50 cursor-not-allowed' : '' }}"
                                   onclick="if (!{{ $accounts->isEmpty() ? 'true' : 'false' }}) document.getElementById('no_profit').checked = true">
                                <input
                                    type="radio"
                                    name="type"
                                    id="no_profit"
                                    value="no_profit"
                                    {{ old('type') === 'no_profit' ? 'checked' : '' }}
                                    class="mt-1"
                                    required
                                    {{ $accounts->isEmpty() ? 'disabled' : '' }}
                                >
                                <div class="ml-3">
                                    <span class="font-semibold text-sm">🔄 No Profit</span>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        No profit expected (e.g., workplace errands, favor for friend)
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Amount Received -->
                    <div class="mb-4">
                        <label for="amount_received" class="block text-sm sm:text-base text-gray-700 dark:text-gray-300 font-semibold mb-2">
                            Amount Received <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            name="amount_received"
                            id="amount_received"
                            value="{{ old('amount_received') }}"
                            placeholder="100000"
                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 sm:px-4 py-2 text-sm focus:outline-none focus:border-indigo-500"
                            required
                            {{ $accounts->isEmpty() ? 'disabled' : '' }}
                        >
                        <p class="text-xs text-gray-500 mt-1">How much did the client send you?</p>
                    </div>

                    <!-- Account -->
                    <div class="mb-4">
                        <label for="account_id" class="block text-sm sm:text-base text-gray-700 dark:text-gray-300 font-semibold mb-2">
                            Received In Account <span class="text-red-500">*</span>
                        </label>
                        <select
                            name="account_id"
                            id="account_id"
                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 sm:px-4 py-2 text-sm focus:outline-none focus:border-indigo-500"
                            required
                            {{ $accounts->isEmpty() ? 'disabled' : '' }}
                        >
                            <option value="">{{ $accounts->isEmpty() ? 'No eligible accounts available' : 'Select Account' }}</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                    @if($account->type == 'mpesa') 📱 @endif
                                    @if($account->type == 'bank') 🏦 @endif
                                    {{ $account->name }} (KES {{ number_format($account->current_balance, 0, '.', ',') }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                            💡 Client funds can only be received in M-Pesa or Bank accounts
                        </p>
                    </div>

                    <!-- Purpose -->
                    <div class="mb-4">
                        <label for="purpose" class="block text-sm sm:text-base text-gray-700 dark:text-gray-300 font-semibold mb-2">
                            Purpose <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="purpose"
                            id="purpose"
                            value="{{ old('purpose') }}"
                            placeholder="e.g., Laptop purchase, Office supplies, Event planning"
                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 sm:px-4 py-2 text-sm focus:outline-none focus:border-indigo-500"
                            required
                            {{ $accounts->isEmpty() ? 'disabled' : '' }}
                        >
                        <p class="text-xs text-gray-500 mt-1">What will you use this money for?</p>
                    </div>

                    <!-- Received Date -->
                    <div class="mb-4">
                        <label for="received_date" class="block text-sm sm:text-base text-gray-700 dark:text-gray-300 font-semibold mb-2">
                            Date Received <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="received_date"
                            id="received_date"
                            value="{{ old('received_date', date('Y-m-d')) }}"
                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 sm:px-4 py-2 text-sm focus:outline-none focus:border-indigo-500"
                            required
                            {{ $accounts->isEmpty() ? 'disabled' : '' }}
                        >
                    </div>

                    <!-- Notes -->
                    <div class="mb-6">
                        <label for="notes" class="block text-sm sm:text-base text-gray-700 dark:text-gray-300 font-semibold mb-2">
                            Additional Notes (Optional)
                        </label>
                        <textarea
                            name="notes"
                            id="notes"
                            rows="3"
                            placeholder="Any additional details about this transaction..."
                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded px-3 sm:px-4 py-2 text-sm focus:outline-none focus:border-indigo-500"
                            {{ $accounts->isEmpty() ? 'disabled' : '' }}
                        >{{ old('notes') }}</textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <a href="{{ route('client-funds.index') }}"
                           class="text-center sm:text-left text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 text-sm">
                            Cancel
                        </a>
                        <button
                            type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded focus:outline-none text-sm w-full sm:w-auto {{ $accounts->isEmpty() ? 'opacity-50 cursor-not-allowed' : '' }}"
                            {{ $accounts->isEmpty() ? 'disabled' : '' }}
                        >
                            {{ $accounts->isEmpty() ? 'No Eligible Accounts' : 'Record Client Fund' }}
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
