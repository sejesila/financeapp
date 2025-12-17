<!-- resources/views/transactions/create.blade.php -->
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between mb-6">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Add New Transaction') }}
            </h2>
            <a href="{{ route('transactions.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Transactions
            </a>
        </div>
    </x-slot>
    <div class="max-w-2xl mx-auto mt-10 p-6 bg-white shadow-xl rounded-2xl">


        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('transactions.store') }}" method="POST" class="space-y-5">
            @csrf

            <!-- Date -->
            <div>
                <label class="block font-semibold mb-1">Date</label>
                <input
                    type="date"
                    name="date"
                    value="{{ old('date', date('Y-m-d')) }}"
                    class="w-full border p-2 rounded focus:outline-none focus:border-indigo-500"
                    required
                >
            </div>

            <!-- Description -->
            <div>
                <label class="block font-semibold mb-1">Description</label>
                <input
                    type="text"
                    name="description"
                    value="{{ old('description') }}"
                    placeholder="e.g. Supermarket shopping"
                    class="w-full border p-2 rounded focus:outline-none focus:border-indigo-500"
                    required
                >
            </div>

            <!-- Amount -->
            <div>
                <label class="block font-semibold mb-1">Amount</label>
                <input
                    type="number"
                    step="0.01"
                    name="amount"
                    value="{{ old('amount') }}"
                    class="w-full border p-2 rounded focus:outline-none focus:border-indigo-500"
                    required
                >
            </div>

            <!-- Category -->
            <div>
                <label class="block font-semibold mb-1">Category</label>
                <select
                    name="category_id"
                    class="w-full border p-2 rounded focus:outline-none focus:border-indigo-500"
                    required
                >
                    <option value="">-- Select Category --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Account -->
            <div>
                <label class="block font-semibold mb-1">Account</label>
                <select name="account_id" class="w-full border p-2 rounded focus:outline-none focus:border-indigo-500"
                        required>
                    <option value="">-- Select Account --</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                            @if($account->type == 'cash')
                                💵
                            @endif
                            @if($account->type == 'mpesa')
                                📱
                            @endif
                            @if($account->type == 'airtel_money')
                                📲
                            @endif
                            @if($account->type == 'bank')
                                🏦
                            @endif
                            {{ $account->name }} ({{ number_format($account->current_balance, 0, '.', ',') }})
                        </option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-500 mt-1">Payment method will be auto-set based on account type</p>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-between">
                <a
                    href="{{ route('transactions.index') }}"
                    class="text-gray-600 hover:text-gray-800"
                >
                    Cancel
                </a>
                <button
                    type="submit"
                    class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700"
                >
                    Save Transaction
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
