@extends('layout')

@section('content')
    <div class="max-w-2xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold">Account Details</h1>
            <a href="{{ route('accounts.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Accounts
            </a>
        </div>

        <!-- Errors -->
        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Account Info -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Account Information</h2>

            <div class="space-y-3">
                <div>
                    <p class="text-sm text-gray-600">Account Name</p>
                    <p class="text-lg font-semibold">{{ $account->name }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-600">Account Type</p>
                    <p class="text-lg font-semibold capitalize">{{ str_replace('_', ' ', $account->type) }}</p>
                </div>

                @if($account->notes)
                    <div>
                        <p class="text-sm text-gray-600">Notes</p>
                        <p class="text-gray-800">{{ $account->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Balances (Read-only) -->
        <div class="mb-6 p-5 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-semibold mb-1">Available Balance</p>
                    <p class="text-3xl font-bold {{ $account->current_balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        KES {{ number_format($account->current_balance, 0, '.', ',') }}
                    </p>
                </div>

                <div class="text-right">
                    <p class="text-sm text-gray-600">Opening Balance</p>
                    <p class="text-lg font-semibold text-gray-800">
                        KES {{ number_format($account->initial_balance, 0, '.', ',') }}
                    </p>
                </div>
            </div>

            <p class="text-xs text-gray-600 mt-2">
                💡 Balances are calculated automatically from transactions.
            </p>

            <!-- Top Up Button -->
            <div class="mt-4">
                <a href="{{ route('accounts.topup', $account) }}"
                   class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 inline-block">
                    + Top Up Account
                </a>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h3 class="font-semibold text-lg mb-4">Recent Transactions</h3>

            @if($account->transactions()->count() > 0)
                <div class="space-y-2">
                    @foreach($account->transactions()->latest('date')->limit(10)->get() as $txn)
                        <a href="{{ route('transactions.show', $txn) }}"
                           class="block p-3 hover:bg-gray-50 rounded border">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-semibold">{{ $txn->description }}</p>
                                    <p class="text-sm text-gray-500">{{ $txn->date->format('M d, Y') }} • {{ $txn->category->name }}</p>
                                </div>
                                <span class="font-bold {{ $txn->category->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $txn->category->type === 'income' ? '+' : '-' }}{{ number_format($txn->amount, 0, '.', ',') }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-center py-4">No transactions yet</p>
            @endif
        </div>

    </div>
@endsection
