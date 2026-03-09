<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Account Details') }}
            </h2>
            <a href="{{ route('accounts.index') }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                ← Back to Accounts
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Success Messages --}}
            @if(session('success'))
                <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Errors --}}
            @if($errors->any())
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Account Info Card --}}
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6 mb-6">
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900">
                            @if(strtolower($account->type) === 'bank')
                                <img src="{{ asset('images/imbank.jpeg') }}" alt="I&M Bank" class="w-12 h-12 object-contain rounded-full">
                            @elseif(strtolower($account->type) === 'mpesa')
                                <img src="{{ asset('images/mpesa.png') }}" alt="M-Pesa" class="w-12 h-12 object-contain">
                            @elseif(strtolower($account->type) === 'airtel_money')
                                <img src="{{ asset('images/airtel-money.png') }}" alt="Airtel Money" class="w-12 h-12 object-contain">
                            @else
                                <span class="font-bold text-2xl text-gray-700 dark:text-gray-300">{{ substr($account->name, 0, 1) }}</span>
                            @endif
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $account->name }}</h2>
                        </div>
                    </div>
{{--                    <a href="{{ route('accounts.edit', $account) }}"--}}
{{--                       class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 text-sm font-medium">--}}
{{--                        Edit--}}
{{--                    </a>--}}
                </div>

                @if($account->notes)
                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold mb-1">Notes</p>
                        <p class="text-gray-800 dark:text-gray-200">{{ $account->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Balance Card --}}
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl shadow-lg p-6 mb-6 border border-blue-200 dark:border-blue-800">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold mb-2">Available Balance</p>
                        <p class="text-4xl font-bold {{ $account->current_balance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            KES {{ number_format($account->current_balance, 0, '.', ',') }}
                        </p>

                        @if($account->current_balance < 0)
                            <p class="text-sm text-red-600 dark:text-red-400 mt-2 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                Account is overdrawn
                            </p>
                        @endif
                    </div>

                    <a href="{{ route('accounts.topup', $account) }}"
                       class="bg-green-600 text-white px-6 py-2.5 rounded-lg hover:bg-green-700 transition text-center font-medium shadow-md">
                        + Top Up Account
                    </a>
                </div>
            </div>

            {{-- Transaction Statistics --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                @php
                    $stats = [
                        ['label' => 'Total Transactions', 'value' => number_format($totalTransactions),            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color' => 'blue'],
                        ['label' => 'Total Income',        'value' => 'KES ' . number_format($totalIncome, 0),    'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'green'],
                        ['label' => 'Total Expenses',      'value' => 'KES ' . number_format($totalExpenses, 0),  'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'red'],
                        ['label' => 'This Month',          'value' => 'KES ' . number_format($thisMonthTotal, 0), 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',                                                                                                                                                                                                                                                                   'color' => 'purple'],
                    ];
                @endphp

                @foreach($stats as $stat)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="p-2 bg-{{ $stat['color'] }}-100 dark:bg-{{ $stat['color'] }}-900 rounded-lg">
                                <svg class="w-4 h-4 text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ $stat['value'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Transaction History --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                    <h3 class="font-bold text-xl text-gray-900 dark:text-white">Transaction History</h3>
                    <a href="{{ route('transactions.index', ['account_id' => $account->id]) }}"
                       class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-medium whitespace-nowrap">
                        View All →
                    </a>
                </div>

                {{-- Search Bar --}}
                <form method="GET" action="{{ route('accounts.show', $account) }}" class="mb-5">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                            </svg>
                        </div>
                        <input type="text"
                               name="search"
                               value="{{ $search ?? '' }}"
                               placeholder="Search transactions..."
                               class="w-full pl-9 pr-10 py-2 text-sm rounded-lg border border-gray-300
                                      dark:border-gray-600 dark:bg-gray-700 dark:text-white
                                      focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @if(!empty($search))
                            <a href="{{ route('accounts.show', $account) }}"
                               class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                        @endif
                    </div>
                    @if(!empty($search))
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            Showing results for
                            "<span class="font-medium text-gray-700 dark:text-gray-300">{{ $search }}</span>"
                            — {{ number_format($transactions->total()) }} {{ Str::plural('result', $transactions->total()) }}
                        </p>
                    @endif
                </form>

                @if($transactions->count() > 0)
                    <div class="space-y-2">
                        @foreach($transactions as $txn)
                            <a href="{{ route('transactions.show', $txn) }}"
                               class="block p-4 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 transition group">
                                <div class="flex justify-between items-start gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-lg">{{ $txn->category->icon ?? '💰' }}</span>
                                            <p class="font-semibold text-gray-900 dark:text-white truncate">
                                                @if(!empty($search))
                                                    {!! str_ireplace(
                                                        $search,
                                                        '<mark class="bg-yellow-100 dark:bg-yellow-800 text-gray-900 dark:text-white rounded px-0.5">' . e($search) . '</mark>',
                                                        e($txn->description)
                                                    ) !!}
                                                @else
                                                    {{ $txn->description }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                                {{ $txn->date->format('M d, Y') }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                                </svg>
                                                {{ $txn->category->name }}
                                            </span>
                                            @if($txn->is_transaction_fee)
                                                <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded-full text-xs font-medium">
                                                    Fee
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <span class="text-lg font-bold {{ $txn->category->type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $txn->category->type === 'income' ? '+' : '-' }}{{ number_format($txn->amount, 0, '.', ',') }}
                                        </span>
                                        @if($txn->feeTransaction)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                +{{ number_format($txn->feeTransaction->amount, 0) }} fee
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-6">
                        {{ $transactions->links() }}
                    </div>

                @else
                    <div class="text-center py-12">
                        @if(!empty($search))
                            <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-lg font-medium mb-2">No results found</p>
                            <p class="text-gray-400 dark:text-gray-500 text-sm mb-4">
                                No transactions matching "<span class="font-medium">{{ $search }}</span>"
                            </p>
                            <a href="{{ route('accounts.show', $account) }}"
                               class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition text-sm">
                                Clear search
                            </a>
                        @else
                            <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-lg font-medium mb-2">No transactions yet</p>
                            <p class="text-gray-400 dark:text-gray-500 text-sm mb-4">Start by adding a transaction or topping up your account</p>
                            <a href="{{ route('accounts.topup', $account) }}"
                               class="inline-block bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition">
                                Add Transaction
                            </a>
                        @endif
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
