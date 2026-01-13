{{-- resources/views/dashboard/index.blade.php --}}
<x-app-layout>
    <style>
        @media (max-width: 640px) {
            body {
                font-size: 13px;
            }

            h1 {
                font-size: 1.5rem !important;
            }

            h2 {
                font-size: 1.25rem !important;
            }

            h3 {
                font-size: 1rem !important;
            }

            h4 {
                font-size: 0.875rem !important;
            }

            .text-3xl {
                font-size: 1.5rem !important;
            }

            .text-2xl {
                font-size: 1.25rem !important;
            }

            .text-xl {
                font-size: 1.125rem !important;
            }
        }

        .balance-blur {
            filter: blur(8px);
            transition: filter 0.3s;
        }

        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <div class="flex items-center space-x-3">
                <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 hidden sm:inline">
                    {{ now()->format('l, F j, Y') }}
                </span>
                <span class="text-xs text-gray-600 dark:text-gray-400 sm:hidden">
                    {{ now()->format('M j, Y') }}
                </span>
            </div>
        </div>
    </x-slot>

    <div
        class="py-6 sm:py-12 bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">

            {{-- Welcome Header --}}
            <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-1">
                        Welcome back, {{ Auth::user()->name }}! 👋
                    </h1>
                    <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400">
                        Here's your complete financial overview for {{ now()->format('F Y') }}.
                    </p>
                </div>

                {{-- Toggle Balance Button --}}
                <button id="toggleBalance"
                        class="self-start sm:self-auto flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl shadow-md hover:shadow-lg transition-all duration-200">
                    <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                    <span id="toggleText" class="font-medium text-sm">Show Balance</span>
                </button>
            </div>

            {{-- Main Financial Overview Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                @php
                    $mainCards = [
                        [
                            'title' => 'Total Cash',
                            'amount' => $totalAssets,
                            'subtitle' => 'Across ' . $accounts->count() . ' account' . ($accounts->count() != 1 ? 's' : ''),
                            'gradient' => 'from-emerald-500 to-green-600',
                            'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'
                        ],
                        [
                            'title' => 'Total Liabilities',
                            'amount' => $totalLiabilities,
                            'subtitle' => $activeLoans->count() . ' active loan' . ($activeLoans->count() != 1 ? 's' : ''),
                            'gradient' => 'from-rose-500 to-pink-600',
                            'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'
                        ],
                        [
                            'title' => 'Net Balance',
                            'amount' => $netWorth,
                            'subtitle' => 'Debt ratio: ' . number_format($debtToAssetRatio, 1) . '%',
                            'gradient' => $netWorth >= 0 ? 'from-blue-500 to-indigo-600' : 'from-orange-500 to-amber-600',
                            'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'
                        ]
                    ];
                @endphp

                @foreach($mainCards as $card)
                    <div
                        class="stat-card rounded-2xl shadow-lg p-5 sm:p-6 text-white bg-gradient-to-br {{ $card['gradient'] }}">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="{{ $card['icon'] }}"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium opacity-90">{{ $card['title'] }}</span>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-3xl font-bold balance-amount">
                                KES {{ number_format($card['amount'], 0, '.', ',') }}</h3>
                            <div class="flex items-center gap-2 balance-hidden hidden">
                                <h3 class="text-3xl font-bold">KES</h3>
                                <div class="w-20 h-8 bg-white/20 rounded animate-pulse"></div>
                            </div>
                            <p class="text-sm opacity-80 balance-amount">{{ $card['subtitle'] }}</p>
                            <p class="text-sm opacity-80 balance-hidden hidden">Hidden</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Quick Stats Grid --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
                @php
                    $quickStats = [
                        ['label' => 'Today', 'value' => $totalToday, 'subtitle' => 'KES spent today', 'color' => 'blue', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['label' => 'This Week', 'value' => $totalThisWeek, 'subtitle' => 'KES this week', 'color' => 'green', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                        ['label' => 'This Month', 'value' => $totalThisMonth, 'subtitle' => 'of ' . number_format($monthlyIncome, 0) . ' income', 'color' => 'purple', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                        ['label' => 'Remaining', 'value' => $remainingThisMonth, 'subtitle' => ($monthlyIncome > 0 ? round(($remainingThisMonth / $monthlyIncome) * 100) . '% left' : '0%'), 'color' => $remainingThisMonth >= 0 ? 'teal' : 'red', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z']
                    ];
                @endphp

                @foreach($quickStats as $stat)
                    <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-2">
                            <span
                                class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400">{{ $stat['label'] }}</span>
                            <div class="p-2 bg-{{ $stat['color'] }}-100 dark:bg-{{ $stat['color'] }}-900 rounded-lg">
                                <svg class="w-4 h-4 text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-300"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="{{ $stat['icon'] }}"/>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold {{ $stat['label'] === 'Remaining' && $remainingThisMonth < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                            {{ number_format($stat['value'], 0, '.', ',') }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $stat['subtitle'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Monthly Overview --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                @php
                    $overviewCards = [
                        [
                            'title' => now()->format('F Y'),
                            'rows' => [
                                ['label' => 'Income', 'value' => $monthlyIncome, 'color' => 'green', 'progress' => 100],
                                ['label' => 'Expenses', 'value' => $monthlyExpenses, 'color' => 'red', 'progress' => $monthlyIncome > 0 ? min(($monthlyExpenses / $monthlyIncome) * 100, 100) : 0],
                                ['label' => 'Net', 'value' => $monthlyNet, 'color' => $monthlyNet >= 0 ? 'green' : 'red', 'isBold' => true]
                            ]
                        ],
                        [
                            'title' => 'Month Comparison',
                            'rows' => [
                                ['label' => 'Last Month', 'value' => $lastMonthTotal, 'plain' => true],
                                ['label' => 'This Month', 'value' => $totalThisMonth, 'plain' => true],
                                ['label' => 'Change', 'value' => $monthlyComparison, 'percent' => $monthlyComparisonPercent, 'color' => $monthlyComparison <= 0 ? 'green' : 'red']
                            ]
                        ],
                        [
                            'title' => now()->year . ' Summary',
                            'rows' => [
                                ['label' => 'Total Income', 'value' => $yearlyIncome, 'color' => 'green'],
                                ['label' => 'Total Expenses', 'value' => $yearlyExpenses, 'color' => 'red'],
                                ['label' => 'Net Yearly', 'value' => $yearlyNet, 'color' => $yearlyNet >= 0 ? 'green' : 'red']
                            ]
                        ]
                    ];
                @endphp

                @foreach($overviewCards as $card)
                    <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-5 sm:p-6">
                        <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white mb-4">{{ $card['title'] }}</h3>
                        <div class="space-y-4">
                            @foreach($card['rows'] as $row)
                                <div
                                    class="{{ isset($row['isBold']) ? 'pt-2 border-t border-gray-200 dark:border-gray-700' : '' }}">
                                    <div
                                        class="flex justify-between items-center {{ isset($row['progress']) ? 'mb-1' : '' }}">
                                        <span
                                            class="text-sm text-gray-600 dark:text-gray-400 {{ isset($row['isBold']) ? 'font-medium text-gray-700 dark:text-gray-300' : '' }}">
                                            {{ $row['label'] }}
                                        </span>
                                        <span
                                            class="text-{{ $row['color'] ?? 'gray' }}-600 dark:text-{{ $row['color'] ?? 'gray' }}-400 {{ isset($row['isBold']) || isset($row['plain']) ? 'text-lg font-bold' : 'text-sm font-semibold' }}">
                                            {{ isset($row['plain']) ? 'KES ' : (isset($row['color']) && $row['color'] === 'green' ? '+' : (isset($row['color']) && $row['color'] === 'red' && $row['value'] < 0 ? '' : (isset($row['color']) && $row['color'] === 'red' ? '-' : ''))) }}{{ number_format(abs($row['value']), 0) }}
                                        </span>
                                    </div>
                                    @if(isset($row['progress']))
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div
                                                class="bg-{{ $row['color'] }}-500 h-2 rounded-full transition-all duration-500"
                                                style="width: {{ $row['progress'] }}%"></div>
                                        </div>
                                    @endif
                                    @if(isset($row['percent']))
                                        <span
                                            class="inline-block mt-1 px-2 py-1 text-xs font-medium rounded-full bg-{{ $row['color'] }}-100 text-{{ $row['color'] }}-800">
                                            {{ $row['percent'] > 0 ? '+' : '' }}{{ $row['percent'] }}%
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Accounts Section --}}
            @if($accounts->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-5 sm:p-6 mb-6 sm:mb-8">
                    <div class="flex justify-between items-center mb-5">
                        <div>
                            <h3 class="text-lg sm:text-xl font-bold text-gray-800 dark:text-white">Accounts</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $accounts->count() }} account{{ $accounts->count() != 1 ? 's' : '' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button onclick="toggleDashboardLowBalanceAccounts()"
                                    class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 flex items-center gap-1">
                                <span id="toggle-dashboard-accounts-icon">👁️</span>
                                <span id="toggle-dashboard-accounts-text" class="hidden sm:inline">Show low</span>
                            </button>
                            <a href="{{ route('accounts.index') }}"
                               class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium transition-colors">
                                Manage →
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @php
                            $accountGradients = ['from-blue-500 to-blue-600', 'from-purple-500 to-purple-600', 'from-pink-500 to-pink-600', 'from-indigo-500 to-indigo-600', 'from-teal-500 to-teal-600', 'from-orange-500 to-orange-600'];
                            $accountIcons = [
                                'bank' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                                'cash' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
                                'default' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                            ];
                        @endphp

                        @foreach($accounts as $account)
                            @php
                                $isLowBalance = $account->current_balance < 1;
                            @endphp
                            <div
                                class="relative group {{ $isLowBalance ? 'dashboard-low-balance-account hidden' : '' }}">
                                <div
                                    class="bg-gradient-to-br {{ $accountGradients[$loop->index % count($accountGradients)] }} rounded-xl p-4 text-white shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 {{ $isLowBalance ? 'opacity-60' : '' }}">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="px-2 py-1 rounded-full text-xs font-medium bg-white/20 backdrop-blur-sm">
                                                {{ ucfirst($account->type ?? 'Account') }}
                                            </span>
                                            @if($isLowBalance)
                                                <span
                                                    class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-400/90 text-yellow-900">
                                                    ⚠️
                                                </span>
                                            @endif
                                        </div>
                                        <div
                                            class="w-8 h-8 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="{{ $accountIcons[$account->type] ?? $accountIcons['default'] }}"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <h4 class="font-bold text-base mb-3 truncate">{{ $account->name }}</h4>

                                    <div>
                                        <p class="text-xs opacity-70 mb-1">Balance</p>
                                        <p class="text-xl font-bold balance-amount">{{ number_format($account->current_balance, 0, '.', ',') }}</p>
                                        <div class="flex items-center gap-2 balance-hidden hidden">
                                            <p class="text-xl font-bold">***</p>
                                        </div>
                                        @if($account->current_balance < 0)
                                            <p class="text-xs mt-1 opacity-70 flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                                Overdrawn
                                            </p>
                                        @elseif($isLowBalance)
                                            <p class="text-xs mt-1 opacity-70 flex items-center gap-1">
                                                Low Balance
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div
                                    class="hidden sm:flex mt-2 gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <a href="{{ route('accounts.show', $account) }}"
                                       class="flex-1 text-center px-2 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                        View
                                    </a>
                                    @if($isLowBalance)
                                        <a href="{{ route('accounts.topup', $account) }}"
                                           class="flex-1 text-center px-2 py-2 bg-green-100 dark:bg-green-900 rounded-lg text-xs font-medium text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800 transition-colors">
                                            Top Up
                                        </a>
                                    @else
                                        <a href="{{ route('transactions.create', ['account_id' => $account->id]) }}"
                                           class="flex-1 text-center px-2 py-2 bg-blue-100 dark:bg-blue-900 rounded-lg text-xs font-medium text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                                            Add Transaction
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Account Summary Stats --}}
                    @php
                        $activeAccounts = $accounts->filter(fn($account) => $account->current_balance >= 1);
                    @endphp
                    @if($activeAccounts->count() > 0)
                        <div class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                @php
                                    $accountStats = [
                                        ['label' => 'Total', 'value' => $activeAccounts->sum('current_balance'), 'color' => 'gray'],
                                        ['label' => 'Highest', 'value' => $activeAccounts->max('current_balance'), 'color' => 'green'],
                                        ['label' => 'Lowest', 'value' => $activeAccounts->min('current_balance'), 'color' => 'orange'],
                                        ['label' => 'Average', 'value' => $activeAccounts->avg('current_balance'), 'color' => 'blue']
                                    ];
                                @endphp

                                @foreach($accountStats as $stat)
                                    <div class="text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $stat['label'] }}</p>
                                        <p class="text-base font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400 balance-amount">
                                            {{ number_format($stat['value'], 0) }}
                                        </p>
                                        <p class="text-base font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400 balance-hidden hidden">
                                            ***</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Three Column Layout --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">

                {{-- Top Expenses --}}
                <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-5 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white">Top Categories</h3>
                        @if($topExpenses->count() > 3)
                            <a href="{{ route('transactions.index') }}"
                               class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">View All →</a>
                        @endif
                    </div>
                    @if($topExpenses->count() > 0)
                        <div class="space-y-3">
                            @foreach($topExpenses->take(3) as $expense)
                                @php
                                    $colors = ['blue', 'purple', 'pink', 'indigo', 'cyan'];
                                    $color = $colors[$loop->index % 5];
                                    $percentage = $totalThisMonth > 0 ? ($expense->total / $totalThisMonth) * 100 : 0;
                                @endphp
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-lg bg-{{ $color }}-100 dark:bg-{{ $color }}-900 flex items-center justify-center flex-shrink-0">
                                        <span class="text-lg">{{ $expense->category->icon ?? '💰' }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ $expense->category->name }}</p>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-1">
                                            <div
                                                class="bg-{{ $color }}-500 h-2 rounded-full transition-all duration-500"
                                                style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="font-bold text-gray-900 dark:text-white text-sm">{{ number_format($expense->total, 0) }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ round($percentage) }}
                                            %</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <span class="text-4xl mb-2">📊</span>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">No expenses this month</p>
                        </div>
                    @endif
                </div>

                {{-- Active Loans --}}
                <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-5 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white">Active Loans</h3>

                        @if($activeLoans->count() > 3)
                            <a href="{{ route('loans.index') }}"
                               class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 self-start sm:self-auto">View
                                All →</a>
                        @endif
                    </div>
                    @if($activeLoans->count() > 0)
                        <div class="space-y-3">
                            @foreach($activeLoans->take(3) as $loan)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 sm:p-4">
                                    <div class="flex flex-col xs:flex-row xs:justify-between xs:items-start gap-2">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-semibold text-gray-900 dark:text-white text-sm sm:text-base truncate">{{ $loan->account->name ?? 'Loan' }}</h4>
                                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                                                Due: {{ \Carbon\Carbon::parse($loan->due_date)->format('M d, Y') }}</p>
                                        </div>
                                        <div class="text-left xs:text-right xs:ml-2 flex-shrink-0">
                                            <p class="text-lg sm:text-xl font-bold text-red-600 dark:text-red-400">{{ number_format($loan->balance, 0) }}</p>
                                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ number_format($loan->interest_rate, 1) }}
                                                %</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-center">
                            <span class="text-4xl sm:text-5xl mb-2">😊</span>
                            <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base">You have no loans</p>
                        </div>
                    @endif
                </div>

                {{-- RECENT TRANSACTIONS --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 gap-2">
                        <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white">Recent
                            Transactions</h3>
                        @if($recentTransactions->count() > 3)
                            <a href="{{ route('transactions.index') }}"
                               class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 self-start sm:self-auto">View
                                All →</a>
                        @endif
                    </div>
                    @if($recentTransactions->count() > 0)
                        <div class="space-y-2 sm:space-y-3">
                            @foreach($recentTransactions->take(3) as $transaction)
                                <div
                                    class="flex items-center justify-between py-2 sm:py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                    <div class="flex items-center space-x-2 sm:space-x-3 flex-1 min-w-0">
                                        @if($transaction->category->type == 'income')
                                            <div
                                                class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center flex-shrink-0">
                                                <span
                                                    class="text-sm sm:text-base">{{ $transaction->category->icon ?? '💰' }}</span>
                                            </div>
                                        @else
                                            <div
                                                class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center flex-shrink-0">
                                                <span
                                                    class="text-sm sm:text-base">{{ $transaction->category->icon ?? '💰' }}</span>
                                            </div>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="font-medium text-gray-900 dark:text-white text-sm sm:text-base truncate">{{ $transaction->category->name }}</p>
                                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 truncate">{{ \Carbon\Carbon::parse($transaction->date)->format('M d') }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right ml-2 sm:ml-4 flex-shrink-0">
                                        @if($transaction->category->type == 'income')
                                            <p class="font-bold text-green-600 dark:text-green-400 text-sm sm:text-base whitespace-nowrap">
                                                +{{ number_format($transaction->amount, 0) }}
                                            </p>
                                        @else
                                            <p class="font-bold text-red-600 dark:text-red-400 text-sm sm:text-base whitespace-nowrap">
                                                -{{ number_format($transaction->amount, 0) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-center">
                            <span class="text-4xl sm:text-5xl mb-2">📝</span>
                            <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base">No transactions yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Toggle --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('toggleBalance');
            const toggleText = document.getElementById('toggleText');
            const eyeIcon = document.getElementById('eyeIcon');
            const balanceAmounts = document.querySelectorAll('.balance-amount');
            const balanceHidden = document.querySelectorAll('.balance-hidden');

// Check localStorage for saved preference (default is hidden)
            let isVisible = localStorage.getItem('balanceVisible') === 'true';

// Set initial state
            updateVisibility();

            toggleBtn.addEventListener('click', function () {
                isVisible = !isVisible;
                localStorage.setItem('balanceVisible', isVisible);
                updateVisibility();
            });

            function updateVisibility() {
                if (isVisible) {
                    balanceAmounts.forEach(el => el.classList.remove('hidden'));
                    balanceHidden.forEach(el => el.classList.add('hidden'));
                    toggleText.textContent = 'Hide Balance';
                    eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
                } else {
                    balanceAmounts.forEach(el => el.classList.add('hidden'));
                    balanceHidden.forEach(el => el.classList.remove('hidden'));
                    toggleText.textContent = 'Show Balance';
                    eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
                }
            }
        });
    </script>
    {{-- Add FAB at the end, before closing x-app-layout --}}
    <x-floating-action-button :quickAccount="$accounts->first()"/>

    {{-- Scroll to Top Button --}}
    <button id="scrollToTop"
            class="fixed bottom-20 right-4 sm:bottom-8 sm:right-8 bg-indigo-600 hover:bg-indigo-700 text-white p-3 sm:p-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 opacity-0 pointer-events-none z-40"
            aria-label="Scroll to top">
        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
        </svg>
    </button>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const scrollBtn = document.getElementById('scrollToTop');

            if (scrollBtn) {
// Show/hide button based on scroll position
                window.addEventListener('scroll', function () {
                    if (window.pageYOffset > 300) {
                        scrollBtn.classList.remove('opacity-0', 'pointer-events-none');
                        scrollBtn.classList.add('opacity-100');
                    } else {
                        scrollBtn.classList.add('opacity-0', 'pointer-events-none');
                        scrollBtn.classList.remove('opacity-100');
                    }
                });

// Smooth scroll to top - FIXED: Changed scrollTopBtn to scrollBtn
                scrollBtn.addEventListener('click', function () {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        });
    </script>
    <script>
        // Dashboard-specific low balance toggle
        let dashboardLowBalanceAccountsVisible = localStorage.getItem('dashboardLowBalanceAccountsVisible') === 'true';

        function toggleDashboardLowBalanceAccounts() {
            dashboardLowBalanceAccountsVisible = !dashboardLowBalanceAccountsVisible;
            localStorage.setItem('dashboardLowBalanceAccountsVisible', dashboardLowBalanceAccountsVisible);
            updateDashboardLowBalanceAccountsVisibility();
        }

        function updateDashboardLowBalanceAccountsVisibility() {
            const lowBalanceAccounts = document.querySelectorAll('.dashboard-low-balance-account');
            const toggleIcon = document.getElementById('toggle-dashboard-accounts-icon');
            const toggleText = document.getElementById('toggle-dashboard-accounts-text');

            lowBalanceAccounts.forEach(account => {
                if (dashboardLowBalanceAccountsVisible) {
                    account.classList.remove('hidden');
                } else {
                    account.classList.add('hidden');
                }
            });

            if (toggleIcon && toggleText) {
                if (dashboardLowBalanceAccountsVisible) {
                    toggleIcon.textContent = '🙈';
                    toggleText.textContent = 'Hide low';
                } else {
                    toggleIcon.textContent = '👁️';
                    toggleText.textContent = 'Show low';
                }
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            updateDashboardLowBalanceAccountsVisibility();
        });
    </script>

</x-app-layout>
