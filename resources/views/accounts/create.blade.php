<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between mb-6">
        <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create New Account') }}
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

        <form method="POST" action="{{ route('accounts.store') }}" class="bg-white shadow rounded-lg p-6">
            @csrf

            <div class="mb-4">
                <label for="name" class="block text-gray-700 font-semibold mb-2">Account Name</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name') }}"
                    placeholder="e.g., M-Pesa, Cash Wallet, KCB Bank"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                    required
                >
            </div>

            <div class="mb-4">
                <label for="type" class="block text-gray-700 font-semibold mb-2">Account Type</label>
                <select
                    name="type"
                    id="type"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                    required
                >
                    <option value="">Select Type</option>
                    <option value="cash" {{ old('type') == 'cash' ? 'selected' : '' }}>💵 Cash</option>
                    <option value="mpesa" {{ old('type') == 'mpesa' ? 'selected' : '' }}>📱 M-Pesa</option>
                    <option value="airtel_money" {{ old('type') == 'airtel_money' ? 'selected' : '' }}>📱 Airtel Money
                    </option>
                    <option value="bank" {{ old('type') == 'bank' ? 'selected' : '' }}>🏦 Bank Account</option>
                    <option value="savings">Savings Account</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="initial_balance" class="block text-gray-700 font-semibold mb-2">Initial Balance</label>
                <input
                    type="number"
                    step="0.01"
                    name="initial_balance"
                    id="initial_balance"
                    value="{{ old('initial_balance', 0) }}"
                    placeholder="How much is currently in this account?"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                    required
                >
                <p class="text-sm text-gray-500 mt-1">Enter the current balance in this account</p>
            </div>

            <div class="mb-6">
                <label for="notes" class="block text-gray-700 font-semibold mb-2">Notes (Optional)</label>
                <textarea
                    name="notes"
                    id="notes"
                    rows="3"
                    placeholder="Add any notes about this account..."
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                >{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-between">
                <a href="{{ route('accounts.index') }}" class="text-gray-600 hover:text-gray-800">
                    Cancel
                </a>
                <button
                    type="submit"
                    class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700 focus:outline-none"
                >
                    Create Account
                </button>
            </div>
        </form>
    </div>
    </x-app-layout>
