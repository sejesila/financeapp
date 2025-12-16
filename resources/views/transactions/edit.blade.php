@extends('layout')

@section('content')
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold">Edit Transaction</h1>
            <a href="{{ route('transactions.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Transactions
            </a>
        </div>

        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="bg-white shadow rounded-lg p-6">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="date" class="block text-gray-700 font-semibold mb-2">Date</label>
                <input
                    type="date"
                    name="date"
                    id="date"
                    value="{{ old('date', $transaction->date) }}"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                    required
                >
            </div>

            <div class="mb-4">
                <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
                <input
                    type="text"
                    name="description"
                    id="description"
                    value="{{ old('description', $transaction->description) }}"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                    required
                >
            </div>

            <div class="mb-4">
                <label for="amount" class="block text-gray-700 font-semibold mb-2">Amount</label>
                <input
                    type="number"
                    step="0.01"
                    name="amount"
                    id="amount"
                    value="{{ old('amount', $transaction->amount) }}"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                    required
                >
            </div>

            <div class="mb-4">
                <label for="payment_method" class="block text-gray-700 font-semibold mb-2">Payment Method</label>
                <select
                    name="payment_method"
                    id="payment_method"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                    required
                >
                    <option value="">Select Method</option>
                    <option value="Cash" {{ old('payment_method', $transaction->payment_method) == 'Cash' ? 'selected' : '' }}>Cash</option>
                    <option value="Mpesa" {{ old('payment_method', $transaction->payment_method) == 'Mpesa' ? 'selected' : '' }}>Mpesa</option>
                    <option value="Bank to Mpesa" {{ old('payment_method', $transaction->payment_method) == 'Bank to Mpesa' ? 'selected' : '' }}>Bank to Mpesa</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="account_id" class="block text-gray-700 font-semibold mb-2">Account</label>
                <select
                    name="account_id"
                    id="account_id"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                    required
                >
                    <option value="">Select Account</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ old('account_id', $transaction->account_id) == $account->id ? 'selected' : '' }}>
                            @if($account->type == 'cash') 💵 @endif
                            @if($account->type == 'mpesa') 📱 @endif
                            @if($account->type == 'airtel_money') 📲 @endif
                            @if($account->type == 'bank') 🏦 @endif
                            {{ $account->name }} ({{ number_format($account->current_balance, 0, '.', ',') }})
                        </option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-500 mt-1">Payment method will be auto-set based on account type</p>
            </div>

            <div class="mb-6">
                <label for="category_id" class="block text-gray-700 font-semibold mb-2">Category</label>
                <select
                    name="category_id"
                    id="category_id"
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:border-indigo-500"
                    required
                >
                    <option value="">Select Category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $transaction->category_id) == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center justify-between">
                <button
                    type="submit"
                    class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700 focus:outline-none"
                >
                    Update Transaction
                </button>

                <a
                    href="{{ route('transactions.index') }}"
                    class="text-gray-600 hover:text-gray-800"
                >
                    Cancel
                </a>
            </div>
        </form>

        <!-- Delete Form -->
        <form
            method="POST"
            action="{{ route('transactions.destroy', $transaction) }}"
            class="mt-6"
            onsubmit="return confirm('Are you sure you want to delete this transaction? This action cannot be undone.');"
        >
            @csrf
            @method('DELETE')

            <button
                type="submit"
                class="w-full bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 focus:outline-none"
            >
                Delete Transaction
            </button>
        </form>
    </div>
@endsection
