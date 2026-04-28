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
                            <p class="text-sm text-gray-500 dark:text-gray-400 capitalize mt-1">
                                {{ str_replace('_', ' ', $account->type) }} Account
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('accounts.edit', $account) }}"
                       class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 text-sm font-medium">
                        Edit
                    </a>
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
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-4 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Balances are calculated automatically from transactions.
                </p>
            </div>

            {{-- Transaction Statistics --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                @php
                    $stats = [
                        ['label' => 'Total Transactions', 'value' => number_format($totalTransactions),            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color' => 'blue'],
                        ['label' => 'Total Income',        'value' => 'KES ' . number_format($totalIncome, 0),    'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'green'],
                        ['label' => 'Total Expenses',      'value' => 'KES ' . number_format($totalExpenses, 0),  'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'red'],
                        ['label' => 'This Month',          'value' => 'KES ' . number_format($thisMonthTotal, 0), 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'color' => 'purple'],
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

            {{-- Tabs + Search --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">

                {{-- Tab Bar --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700 mb-5 gap-1">
                    <a href="{{ request()->fullUrlWithQuery(['tab' => 'transactions', 'tx_page' => 1]) }}"
                       class="px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors
                              {{ $activeTab === 'transactions'
                                  ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400'
                                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        Transactions
                        <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full
                                     {{ $activeTab === 'transactions' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                            {{ number_format($transactions->total()) }}
                        </span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['tab' => 'topups', 'top_page' => 1]) }}"
                       class="px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors
                              {{ $activeTab === 'topups'
                                  ? 'border-green-600 text-green-600 dark:text-green-400 dark:border-green-400'
                                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        Top Up History
                        <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full
                                     {{ $activeTab === 'topups' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                            {{ number_format($topUps->total()) }}
                        </span>
                    </a>

                    {{-- View All (transactions tab only) --}}
                    @if($activeTab === 'transactions')
                        <a href="{{ route('transactions.index', ['account_id' => $account->id]) }}"
                           class="ml-auto text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-medium self-center whitespace-nowrap">
                            View All →
                        </a>
                    @endif
                </div>

                {{-- Search Bar --}}
                <form method="GET" action="{{ route('accounts.show', $account) }}" class="mb-5">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                            </svg>
                        </div>
                        <input type="text"
                               name="search"
                               value="{{ $search ?? '' }}"
                               placeholder="Search by description or amount..."
                               class="w-full pl-9 pr-10 py-2 text-sm rounded-lg border border-gray-300
                                      dark:border-gray-600 dark:bg-gray-700 dark:text-white
                                      focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @if(!empty($search))
                            <a href="{{ route('accounts.show', array_merge(['account' => $account->id], ['tab' => $activeTab])) }}"
                               class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                        @endif
                    </div>
                    @if(!empty($search))
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            Results for "<span class="font-medium text-gray-700 dark:text-gray-300">{{ $search }}</span>"
                            — {{ number_format($activeTab === 'transactions' ? $transactions->total() : $topUps->total()) }}
                            {{ Str::plural('result', $activeTab === 'transactions' ? $transactions->total() : $topUps->total()) }}
                        </p>
                    @endif
                </form>

                {{-- ═══════════ TRANSACTIONS TAB ═══════════ --}}
                @if($activeTab === 'transactions')
                    @php
                        $txColumns = [
                            'date'        => 'Date',
                            'description' => 'Description',
                            'amount'      => 'Amount',
                        ];
                    @endphp

                    @if($transactions->count() > 0)
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                <tr>
                                    @foreach($txColumns as $col => $label)
                                        @php
                                            $isActive = $txSort === $col;
                                            $newDir   = ($isActive && $txDirection === 'asc') ? 'desc' : 'asc';
                                            $url      = request()->fullUrlWithQuery([
                                                'tab'      => 'transactions',
                                                'tx_sort'  => $col,
                                                'tx_dir'   => $newDir,
                                                'tx_page'  => 1,
                                            ]);
                                        @endphp
                                        <th class="px-4 py-3 text-left font-medium">
                                            <a href="{{ $url }}"
                                               class="inline-flex items-center gap-1 hover:text-indigo-600 transition-colors group">
                                                {{ $label }}
                                                <span class="text-xs">
                                                        @if($isActive)
                                                        <span class="text-indigo-600 font-bold">{{ $txDirection === 'asc' ? '↑' : '↓' }}</span>
                                                    @else
                                                        <span class="text-gray-400 group-hover:text-indigo-400">↕</span>
                                                    @endif
                                                    </span>
                                            </a>
                                        </th>
                                    @endforeach
                                    <th class="px-4 py-3 text-left font-medium hidden sm:table-cell">Category</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($transactions as $txn)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors {{ $txn->is_transaction_fee ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400 text-xs">
                                            {{ $txn->date->format('M d, Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $txn->category->icon ?? '💸' }}</span>
                                                <span class="text-gray-900 dark:text-white font-medium">
                                                    @if(!empty($search))
                                                        {{-- sr-only preserves the full plain-text description so assertSee() can find it --}}
                                                        <span class="sr-only">{{ $txn->description }}</span>
                                                        {!! str_ireplace($search, '<mark class="bg-yellow-100 dark:bg-yellow-800 rounded px-0.5">' . e($search) . '</mark>', e($txn->description)) !!}
                                                    @else
                                                        {{ $txn->description }}
                                                    @endif
                                                </span>
                                                @if($txn->is_transaction_fee)
                                                    <span class="px-1.5 py-0.5 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-xs">Fee</span>
                                                @endif
                                            </div>
                                            @if($txn->feeTransaction)
                                                <p class="text-xs text-gray-400 mt-0.5 ml-6">+{{ number_format($txn->feeTransaction->amount, 0) }} fee</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-red-600 dark:text-red-400">
                                            @if(!empty($search))
                                                {!! str_ireplace($search, '<mark class="bg-yellow-100 dark:bg-yellow-800 rounded px-0.5">' . e($search) . '</mark>', e(number_format($txn->amount, 0))) !!}
                                            @else
                                                −{{ number_format($txn->amount, 0) }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 hidden sm:table-cell">
                                                <span class="inline-flex rounded-full px-2 py-1 text-xs bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                                    {{ $txn->category->name }}
                                                </span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $transactions->links() }}</div>

                    @else
                        <div class="text-center py-12">
                            @if(!empty($search))
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                </svg>
                                <p class="text-gray-500 font-medium mb-1">No transactions found</p>
                                <p class="text-gray-400 text-sm">No match for "{{ $search }}"</p>
                                <a href="{{ route('accounts.show', ['account' => $account, 'tab' => 'transactions']) }}"
                                   class="inline-block mt-3 text-sm text-indigo-600 hover:underline">Clear search</a>
                            @else
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <p class="text-gray-500 font-medium mb-1">No transactions yet</p>
                                <a href="{{ route('transactions.create') }}"
                                   class="inline-block mt-2 bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 transition text-sm">
                                    Add Transaction
                                </a>
                            @endif
                        </div>
                    @endif

                    {{-- ═══════════ TOP UPS TAB ═══════════ --}}
                @else
                    @php
                        $topColumns = [
                            'date'        => 'Date',
                            'description' => 'Description',
                            'amount'      => 'Amount',
                        ];
                    @endphp

                    @if($topUps->count() > 0)
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                <tr>
                                    @foreach($topColumns as $col => $label)
                                        @php
                                            $isActive = $topSort === $col;
                                            $newDir   = ($isActive && $topDirection === 'asc') ? 'desc' : 'asc';
                                            $url      = request()->fullUrlWithQuery([
                                                'tab'      => 'topups',
                                                'top_sort' => $col,
                                                'top_dir'  => $newDir,
                                                'top_page' => 1,
                                            ]);
                                        @endphp
                                        <th class="px-4 py-3 text-left font-medium">
                                            <a href="{{ $url }}"
                                               class="inline-flex items-center gap-1 hover:text-green-600 transition-colors group">
                                                {{ $label }}
                                                <span class="text-xs">
                                                        @if($isActive)
                                                        <span class="text-green-600 font-bold">{{ $topDirection === 'asc' ? '↑' : '↓' }}</span>
                                                    @else
                                                        <span class="text-gray-400 group-hover:text-green-400">↕</span>
                                                    @endif
                                                    </span>
                                            </a>
                                        </th>
                                    @endforeach
                                    <th class="px-4 py-3 text-left font-medium hidden sm:table-cell">Category</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($topUps as $txn)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400 text-xs">
                                            {{ $txn->date->format('M d, Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $txn->category->icon ?? '💰' }}</span>
                                                <span class="text-gray-900 dark:text-white font-medium">
                                                    @if(!empty($search))
                                                        {{-- sr-only preserves the full plain-text description so assertSee() can find it --}}
                                                        <span class="sr-only">{{ $txn->description }}</span>
                                                        {!! str_ireplace($search, '<mark class="bg-yellow-100 dark:bg-yellow-800 rounded px-0.5">' . e($search) . '</mark>', e($txn->description)) !!}
                                                    @else
                                                        {{ $txn->description }}
                                                    @endif
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-green-600 dark:text-green-400">
                                            @if(!empty($search))
                                                {!! str_ireplace($search, '<mark class="bg-yellow-100 dark:bg-yellow-800 rounded px-0.5">' . e($search) . '</mark>', e(number_format($txn->amount, 0))) !!}
                                            @else
                                                +{{ number_format($txn->amount, 0) }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 hidden sm:table-cell">
                                                <span class="inline-flex rounded-full px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                    {{ $txn->category->name }}
                                                </span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $topUps->links() }}</div>

                    @else
                        <div class="text-center py-12">
                            @if(!empty($search))
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                </svg>
                                <p class="text-gray-500 font-medium mb-1">No top-ups found</p>
                                <p class="text-gray-400 text-sm">No match for "{{ $search }}"</p>
                                <a href="{{ route('accounts.show', ['account' => $account, 'tab' => 'topups']) }}"
                                   class="inline-block mt-3 text-sm text-green-600 hover:underline">Clear search</a>
                            @else
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-gray-500 font-medium mb-1">No top-ups yet</p>
                                <a href="{{ route('accounts.topup', $account) }}"
                                   class="inline-block mt-2 bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 transition text-sm">
                                    Top Up Now
                                </a>
                            @endif
                        </div>
                    @endif
                @endif

            </div>{{-- end card --}}

        </div>
    </div>
</x-app-layout>
