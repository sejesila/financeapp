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

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                    {{ session('error') }}
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
                            @elseif(str_contains(strtolower($account->name), 'etica'))
                                    <img src="{{ asset('images/etica.webp') }}" alt="Etica" class="w-8 h-8 object-contain">
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
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                        <a href="{{ route('accounts.topup', $account) }}"
                           class="bg-green-600 text-white px-6 py-2.5 rounded-lg hover:bg-green-700 transition text-center font-medium shadow-md">
                            + Top Up Account
                        </a>
                        @if($account->type === 'savings' && !$interestRecordedToday)
                            <a href="{{ route('accounts.interest.form', $account) }}"
                               class="bg-emerald-600 text-white px-6 py-2.5 rounded-lg hover:bg-emerald-700 transition text-center font-medium shadow-md">
                                📈 Record Interest
                            </a>
                        @endif
                    </div>
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
                @if($account->type === 'savings')
                    @php
                        $stats = [
                            ['label' => 'Total Transactions', 'value' => number_format($totalTransactions), 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color' => 'blue'],
                            ['label' => 'Total Interest', 'value' => 'KES ' . number_format($savingsStats->total_interest ?? 0, 0), 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'emerald'],
                            ['label' => 'Total Expenses', 'value' => 'KES ' . number_format($savingsStats->total_expenses ?? 0, 0), 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'red'],
                            ['label' => 'All-Time Net', 'value' => 'KES ' . number_format($savingsNetAllTime, 0), 'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z', 'color' => $savingsNetAllTime >= 0 ? 'emerald' : 'red'],
                        ];
                    @endphp
                @else
                    @php
                        $stats = [
                            ['label' => 'Total Transactions', 'value' => number_format($totalTransactions), 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color' => 'blue'],
                            ['label' => 'Total Income', 'value' => 'KES ' . number_format($totalIncome, 0), 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'green'],
                            ['label' => 'Total Expenses', 'value' => 'KES ' . number_format($totalExpenses, 0), 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'red'],
                            ['label' => 'This Month', 'value' => 'KES ' . number_format($thisMonthTotal, 0), 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'color' => 'purple'],
                        ];
                    @endphp
                @endif

                @foreach($stats as $stat)
                    @php
                        $colorMap = [
                            'blue'    => 'bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300',
                            'emerald' => 'bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-300',
                            'green'   => 'bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300',
                            'red'     => 'bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-300',
                            'purple'  => 'bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-300',
                        ];
                        $classes = $colorMap[$stat['color']] ?? $colorMap['blue'];
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="{{ $classes }} p-2 rounded-lg">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ $stat['value'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Savings Analytics --}}
            @if($account->type === 'savings')
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">📊 Interest & Expenses Breakdown</h3>

                        <div class="flex flex-wrap items-center gap-3 ml-auto">
                            {{-- Period Dropdown --}}
                            <div class="relative" x-data="{ open: false }">
                                <button
                                    @click="open = !open"
                                    @click.outside="open = false"
                                    class="flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition cursor-pointer"
                                >
                                    <span class="font-medium capitalize">{{ $selectedPeriod }}</span>
                                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div
                                    x-show="open"
                                    x-transition
                                    class="absolute left-0 mt-2 w-40 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50"
                                    style="display: none;"
                                >
                                    @foreach(['weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $label)
                                        <a
                                            href="{{ request()->fullUrlWithQuery(['period' => $value, 'year' => $selectedYear, 'tab' => $activeTab]) }}"
                                            class="block px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition {{ $selectedPeriod === $value ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-gray-700 dark:text-gray-300' }}"
                                        >
                                            {{ $label }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Year Dropdown (hidden for yearly view) --}}
                            @if($selectedPeriod !== 'yearly')
                                <div class="relative" x-data="{ open: false }">
                                    <button
                                        @click="open = !open"
                                        @click.outside="open = false"
                                        class="flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition cursor-pointer"
                                    >
                                        <span class="font-medium">{{ $selectedYear }}</span>
                                        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div
                                        x-show="open"
                                        x-transition
                                        class="absolute left-0 mt-2 w-40 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50 max-h-48 overflow-y-auto"
                                        style="display: none;"
                                    >
                                        @foreach($availableYears as $yr)
                                            <a
                                                href="{{ request()->fullUrlWithQuery(['period' => $selectedPeriod, 'year' => $yr, 'tab' => $activeTab]) }}"
                                                class="block px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition {{ $selectedYear == $yr ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-gray-700 dark:text-gray-300' }}"
                                            >
                                                {{ $yr }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($interestByPeriod->isEmpty() && $expensesByPeriod->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-6">No data for this period.</p>
                    @else
                        @php
                            // Merge all period labels from both collections.
                            $allLabels   = $interestByPeriod->pluck('period_label')
                                ->merge($expensesByPeriod->pluck('period_label'))
                                ->unique()->values();

                            // Key by period_label so Blade can look up a row by label.
                            $interestMap = $interestByPeriod->keyBy('period_label');
                            $expensesMap = $expensesByPeriod->keyBy('period_label');
                        @endphp

                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">
                                        {{ $selectedPeriod === 'weekly' ? 'Week' : ($selectedPeriod === 'yearly' ? 'Year' : 'Month') }}
                                    </th>
                                    <th class="px-4 py-3 text-right font-medium text-emerald-700 dark:text-emerald-400">Interest (KES)</th>
                                    <th class="px-4 py-3 text-right font-medium text-red-700 dark:text-red-400">Expenses (KES)</th>
                                    <th class="px-4 py-3 text-right font-medium text-indigo-700 dark:text-indigo-400">Net (KES)</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @php $grandInterest = 0; $grandExpenses = 0; @endphp
                                @foreach($allLabels as $label)
                                    @php
                                        $interest  = $interestMap[$label]->total ?? 0;
                                        $expenses  = $expensesMap[$label]->total ?? 0;
                                        $net       = $interest - $expenses;
                                        $grandInterest += $interest;
                                        $grandExpenses += $expenses;

                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $label }}</td>
                                        <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400 font-medium">
                                            {{ $interest > 0 ? number_format($interest, 0) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-red-600 dark:text-red-400 font-medium">
                                            {{ $expenses > 0 ? number_format($expenses, 0) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold {{ $net >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ ($net >= 0 ? '+' : '') . number_format($net, 0) }}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700 font-semibold text-gray-800 dark:text-gray-200">
                                <tr>
                                    <td class="px-4 py-3">Total</td>
                                    <td class="px-4 py-3 text-right text-emerald-700 dark:text-emerald-400">{{ number_format($grandInterest, 0) }}</td>
                                    <td class="px-4 py-3 text-right text-red-700 dark:text-red-400">{{ number_format($grandExpenses, 0) }}</td>
                                    <td class="px-4 py-3 text-right {{ ($grandInterest - $grandExpenses) >= 0 ? 'text-indigo-700 dark:text-indigo-400' : 'text-red-700 dark:text-red-400' }}">
                                        {{ (($grandInterest - $grandExpenses) >= 0 ? '+' : '') . number_format($grandInterest - $grandExpenses, 0) }}
                                    </td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Tabs + Search --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">

                {{-- Tab Bar --}}
                <div class="flex flex-wrap border-b border-gray-200 dark:border-gray-700 mb-5 gap-1">

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

                    <a href="{{ request()->fullUrlWithQuery(['tab' => 'transfers', 'tr_page' => 1]) }}"
                       class="px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors
                              {{ $activeTab === 'transfers'
                                  ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400'
                                  : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        Transfers
                        <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full
                                     {{ $activeTab === 'transfers' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                            {{ number_format($transfers->total()) }}
                        </span>
                    </a>

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
                            <a href="{{ route('accounts.show', ['account' => $account->slug, 'tab' => $activeTab]) }}"
                               class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                        @endif
                    </div>
                    @if(!empty($search))
                        @php
                            $resultCount = match($activeTab) {
                                'topups'    => $topUps->total(),
                                'transfers' => $transfers->total(),
                                default     => $transactions->total(),
                            };
                        @endphp
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                            Results for "<span class="font-medium text-gray-700 dark:text-gray-300">{{ $search }}</span>"
                            — {{ number_format($resultCount) }} {{ Str::plural('result', $resultCount) }}
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
                                                'tab'     => 'transactions',
                                                'tx_sort' => $col,
                                                'tx_dir'  => $newDir,
                                                'tx_page' => 1,
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
                                    <th class="px-4 py-3"></th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($transactions as $txn)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors
                                               {{ $txn->is_transaction_fee ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400 text-xs">
                                            {{ $txn->date->format('M d, Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $txn->category->icon ?? '💸' }}</span>
                                                <span class="text-gray-900 dark:text-white font-medium">
                                                    @if(!empty($search))
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
                                                <p class="text-xs text-gray-400 mt-0.5 ml-6">
                                                    +{{ number_format($txn->feeTransaction->amount, 0) }} fee
                                                </p>
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
                                        <td class="px-4 py-3"></td>
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
                                <a href="{{ route('transactions.create', ['account_id' => $account->id]) }}"
                                   class="inline-block mt-2 bg-indigo-600 text-white px-5 py-2 rounded-lg hover:bg-indigo-700 transition text-sm">
                                    Add Transaction
                                </a>
                            @endif
                        </div>
                    @endif

                    {{-- ═══════════ TOP UPS TAB ═══════════ --}}
                @elseif($activeTab === 'topups')
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
                                    <th class="px-4 py-3"></th>
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
                                        {{-- Date cell --}}
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400 text-xs">
                                            @if($account->type === 'savings' && ($txn->is_grouped ?? false))
                                                {{ $txn->date->format('M Y') }}
                                            @else
                                                {{ $txn->date->format('M d, Y') }}
                                            @endif
                                        </td>

                                        {{-- Reverse button — only for non-grouped rows within the 2-minute window --}}
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            @if(!($txn->is_grouped ?? false) && $txn->created_at->diffInMinutes(now()) <= 2)
                                                <a href="{{ route('accounts.topup.reverse.form', ['account' => $account, 'transaction' => $txn]) }}"
                                                   title="Reverse this top-up"
                                                   class="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                                    </svg>
                                                    Reverse
                                                </a>
                                            @endif
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

                    {{-- ═══════════ TRANSFERS TAB ═══════════ --}}
                @else
                    @php
                        $trColumns = [
                            'date'        => 'Date',
                            'description' => 'Description',
                            'amount'      => 'Amount',
                        ];
                    @endphp

                    @if($transfers->count() > 0)
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                <tr>
                                    @foreach($trColumns as $col => $label)
                                        @php
                                            $isActive = $transferSort === $col;
                                            $newDir   = ($isActive && $transferDirection === 'asc') ? 'desc' : 'asc';
                                            $url      = request()->fullUrlWithQuery([
                                                'tab'     => 'transfers',
                                                'tr_sort' => $col,
                                                'tr_dir'  => $newDir,
                                                'tr_page' => 1,
                                            ]);
                                        @endphp
                                        <th class="px-4 py-3 text-left font-medium">
                                            <a href="{{ $url }}"
                                               class="inline-flex items-center gap-1 hover:text-blue-600 transition-colors group">
                                                {{ $label }}
                                                <span class="text-xs">
                                                    @if($isActive)
                                                        <span class="text-blue-600 font-bold">{{ $transferDirection === 'asc' ? '↑' : '↓' }}</span>
                                                    @else
                                                        <span class="text-gray-400 group-hover:text-blue-400">↕</span>
                                                    @endif
                                                </span>
                                            </a>
                                        </th>
                                    @endforeach
                                    <th class="px-4 py-3 text-left font-medium">Direction</th>
                                    <th class="px-4 py-3 text-left font-medium hidden sm:table-cell">Counterpart</th>
                                        <th class="px-4 py-3"></th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($transfers as $transfer)
                                    @php
                                        $isOut       = $transfer->from_account_id === $account->id;
                                        $counterpart = $isOut ? $transfer->toAccount : $transfer->fromAccount;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400 text-xs">
                                            {{ $transfer->date->format('M d, Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-white">
                                            @if(!empty($search))
                                                {!! str_ireplace($search, '<mark class="bg-yellow-100 dark:bg-yellow-800 rounded px-0.5">' . e($search) . '</mark>', e($transfer->description ?: '—')) !!}
                                            @else
                                                {{ $transfer->description ?: '—' }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-semibold tabular-nums
                                                   {{ $isOut ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                            @if(!empty($search))
                                                {!! str_ireplace($search, '<mark class="bg-yellow-100 dark:bg-yellow-800 rounded px-0.5">' . e($search) . '</mark>', e(($isOut ? '−' : '+') . number_format($transfer->amount, 0))) !!}
                                            @else
                                                {{ $isOut ? '−' : '+' }}{{ number_format($transfer->amount, 0) }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium
                                                         {{ $isOut
                                                             ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                             : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' }}">
                                                {{ $isOut ? '↑ Out' : '↓ In' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 hidden sm:table-cell text-gray-600 dark:text-gray-400 text-sm">
                                            {{ $counterpart->name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            @if($transfer->created_at->diffInMinutes(now()) <= 60)
                                                <a href="{{ route('accounts.transfer.reverse.form', ['account' => $account, 'transfer' => $transfer]) }}"
                                                   title="Reverse this transfer"
                                                   class="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                                    </svg>
                                                    Reverse
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $transfers->links() }}</div>

                    @else
                        <div class="text-center py-12">
                            @if(!empty($search))
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                                </svg>
                                <p class="text-gray-500 font-medium mb-1">No transfers found</p>
                                <p class="text-gray-400 text-sm">No match for "{{ $search }}"</p>
                                <a href="{{ route('accounts.show', ['account' => $account, 'tab' => 'transfers']) }}"
                                   class="inline-block mt-3 text-sm text-blue-600 hover:underline">Clear search</a>
                            @else
                                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                                <p class="text-gray-500 font-medium mb-1">No transfers yet</p>
                                <a href="{{ route('transfers.create') }}"
                                   class="inline-block mt-2 bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
                                    Make a Transfer
                                </a>
                            @endif
                        </div>
                    @endif

                @endif {{-- end tab switch --}}

            </div>{{-- end card --}}

        </div>
    </div>
</x-app-layout>
