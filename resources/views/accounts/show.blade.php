<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Account Details') }}
            </h2>
            <a href="{{ route('accounts.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Accounts
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
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
                <h2 class="text-xl font-semibold mb-4 flex items-center gap-3">
                    <!-- Logo based on account type -->
                    <div class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-100">
                        @if(strtolower($account->type) === 'bank')
                            <img src="{{ asset('images/imbank.jpeg') }}" alt="I&M Bank" class="w-8 h-8 object-contain">
                        @elseif(strtolower($account->type) === 'mpesa')
                            <img src="{{ asset('images/mpesa.png') }}" alt="M-Pesa" class="w-8 h-8 object-contain">
                        @elseif(strtolower($account->type) === 'airtel_money')
                            <img src="{{ asset('images/airtel-money.png') }}" alt="Airtel Money" class="w-8 h-8 object-contain">
                        @else
                            <span class="font-bold text-gray-700">{{ substr($account->name, 0, 1) }}</span>
                        @endif
                    </div>

                    <p class="text-lg font-semibold">{{ $account->name }}</p>
                </h2>

                <div class="space-y-3">

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
    </div>
</x-app-layout>
