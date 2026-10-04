{{-- resources/views/dashboard/index.blade.php --}}
<x-app-layout>
    <style>
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
            <h2 class="font-semibold text-base sm:text-lg text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <div class="flex items-center space-x-3">
                <span class="text-xs text-gray-600 dark:text-gray-400 hidden sm:inline">
                    {{ now()->format('l, F j, Y') }}
                </span>
                <span class="text-xs text-gray-600 dark:text-gray-400 sm:hidden">
                    {{ now()->format('M j, Y') }}
                </span>
            </div>
        </div>
    </x-slot>

    <div
        class="py-4 sm:py-8 bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">

            {{-- Welcome Header --}}
            <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-white mb-0.5">
                        Welcome back, {{ Auth::user()->name }}! 👋
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                        Here's your complete financial overview for {{ now()->format('F Y') }}.
                    </p>
                </div>

                {{-- Toggle Balance Buttons --}}
                <div class="flex flex-wrap gap-2">
                    {{-- Main Balance Toggle --}}
                    <button id="toggleBalance"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg shadow-md hover:shadow-lg transition-all duration-200">
                        <svg id="eyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                        <span id="toggleText" class="font-medium text-xs">Show Balance</span>
                    </button>

                    {{-- Savings Balance Toggle (only when there are savings to hide) --}}
                    @if((float) $totalSavings != 0)
                        <button id="toggleSavingsBalance"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg shadow-md hover:shadow-lg transition-all duration-200">
                            <svg id="savingsEyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                            <span id="savingsToggleText" class="font-medium text-xs">Show Savings</span>
                        </button>
                    @endif

                    {{-- Wallets Balance Toggle --}}
                    @if($walletAccounts->count() > 0)
                        <button id="toggleWalletsBalance"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg shadow-md hover:shadow-lg transition-all duration-200">
                            <svg id="walletsEyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                            <span id="walletsToggleText" class="font-medium text-xs">Show Wallets</span>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Main Financial Overview Cards (zero-value Savings / Liabilities are hidden) --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
                @php
                    $mainCards = [
                        [
                            'title' => 'Total Cash',
                            'amount' => $totalCash,
                            'subtitle' => 'Across ' . $accounts->count() . ' account' . ($accounts->count() != 1 ? 's' : ''),
                            'gradient' => 'from-emerald-500 to-green-600',
                            'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
                            'type' => 'cash',
                        ],
                        [
                            'title' => 'Total Savings',
                            'amount' => $totalSavings,
                            'subtitle' => 'Across all savings accounts',
                            'gradient' => 'from-teal-500 to-cyan-600',
                            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                            'type' => 'savings',
                            'hide' => (float) $totalSavings == 0,
                        ],
                        [
                            'title' => 'Total Liabilities',
                            'amount' => $totalLiabilities,
                            'subtitle' => $activeLoans->count() . ' active loan' . ($activeLoans->count() != 1 ? 's' : ''),
                            'gradient' => 'from-rose-500 to-pink-600',
                            'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                            'type' => 'cash',
                            'hide' => (float) $totalLiabilities == 0,
                        ],
                        [
                            'title' => 'Net Balance',
                            'amount' => $netWorth,
                            'subtitle' => (float) $totalLiabilities > 0
                                ? 'Debt ratio: ' . number_format($debtToAssetRatio, 1) . '%'
                                : 'No debt',
                            'gradient' => $netWorth >= 0 ? 'from-blue-500 to-indigo-600' : 'from-orange-500 to-amber-600',
                            'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
                            'type' => 'cash',
                        ],
                    ];

                    if ($walletAccounts->count() > 0) {
                        $mainCards[] = [
                            'title' => 'Total Wallets',
                            'amount' => $totalWallets,
                            'subtitle' => 'Across ' . $walletAccounts->count() . ' wallet' . ($walletAccounts->count() != 1 ? 's' : ''),
                            'gradient' => 'from-sky-500 to-blue-600',
                            'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z',
                            'type' => 'wallet',
                        ];
                    }
                @endphp

                @foreach($mainCards as $card)
                    @continue(!empty($card['hide']))

                    @if($card['type'] === 'savings')
                        <div class="stat-card rounded-xl shadow-lg p-4 sm:p-5 text-white bg-gradient-to-br {{ $card['gradient'] }} savings-balance-hidden">
                            <div class="flex items-center justify-between mb-3">
                                <div class="p-2 rounded-lg bg-white/20 backdrop-blur-sm">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-medium opacity-90">{{ $card['title'] }}</span>
                            </div>

                            <div class="space-y-0.5">
                                <h3 class="text-lg sm:text-2xl font-bold savings-balance-amount">
                                    KES {{ number_format($card['amount'], 0, '.', ',') }}
                                </h3>
                                <div class="flex items-center gap-2 savings-balance-hidden-placeholder hidden">
                                    <h3 class="text-lg sm:text-2xl font-bold">KES</h3>
                                    <div class="w-16 h-5 sm:h-6 bg-white/20 rounded animate-pulse"></div>
                                </div>
                                <p class="text-[11px] sm:text-xs opacity-80 savings-balance-amount">{{ $card['subtitle'] }}</p>
                                <p class="text-[11px] sm:text-xs opacity-80 savings-balance-hidden-placeholder hidden">Hidden</p>
                            </div>
                        </div>
                    @elseif($card['type'] === 'wallet')
                        <div class="stat-card rounded-xl shadow-lg p-4 sm:p-5 text-white bg-gradient-to-br {{ $card['gradient'] }} wallet-balance-hidden">
                            <div class="flex items-center justify-between mb-3">
                                <div class="p-2 rounded-lg bg-white/20 backdrop-blur-sm">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-medium opacity-90">{{ $card['title'] }}</span>
                            </div>

                            <div class="space-y-0.5">
                                <h3 class="text-lg sm:text-2xl font-bold wallet-balance-amount">
                                    KES {{ number_format($card['amount'], 0, '.', ',') }}
                                </h3>
                                <div class="flex items-center gap-2 wallet-balance-hidden-placeholder hidden">
                                    <h3 class="text-lg sm:text-2xl font-bold">KES</h3>
                                    <div class="w-16 h-5 sm:h-6 bg-white/20 rounded animate-pulse"></div>
                                </div>
                                <p class="text-[11px] sm:text-xs opacity-80 wallet-balance-amount">{{ $card['subtitle'] }}</p>
                                <p class="text-[11px] sm:text-xs opacity-80 wallet-balance-hidden-placeholder hidden">Hidden</p>
                            </div>
                        </div>
                    @else
                        <div class="stat-card rounded-xl shadow-lg p-4 sm:p-5 text-white bg-gradient-to-br {{ $card['gradient'] }} balance-hidden">
                            <div class="flex items-center justify-between mb-3">
                                <div class="p-2 rounded-lg bg-white/20 backdrop-blur-sm">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-medium opacity-90">{{ $card['title'] }}</span>
                            </div>

                            <div class="space-y-0.5">
                                <h3 class="text-lg sm:text-2xl font-bold balance-amount">
                                    KES {{ number_format($card['amount'], 0, '.', ',') }}
                                </h3>
                                <div class="flex items-center gap-2 balance-hidden hidden">
                                    <h3 class="text-lg sm:text-2xl font-bold">KES</h3>
                                    <div class="w-16 h-5 sm:h-6 bg-white/20 rounded animate-pulse"></div>
                                </div>
                                <p class="text-[11px] sm:text-xs opacity-80 balance-amount">{{ $card['subtitle'] }}</p>
                                <p class="text-[11px] sm:text-xs opacity-80 balance-hidden hidden">Hidden</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Quick Stats Grid (Today / This Week are hidden when nothing was spent) --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
                @php
                    $quickStats = [
                        ['label' => 'Today', 'value' => $totalToday, 'subtitle' => 'KES spent today', 'color' => 'blue', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'hide' => (float) $totalToday == 0],
                        ['label' => 'This Week', 'value' => $totalThisWeek, 'subtitle' => 'KES this week', 'color' => 'green', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'hide' => (float) $totalThisWeek == 0],
                        ['label' => 'This Month', 'value' => $totalThisMonth, 'subtitle' => 'of ' . number_format($monthlyIncome, 0) . ' income', 'color' => 'purple', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                        ['label' => 'Remaining', 'value' => $remainingThisMonth, 'subtitle' => ($monthlyIncome > 0 ? round(($remainingThisMonth / $monthlyIncome) * 100) . '% left' : '0%'), 'color' => $remainingThisMonth >= 0 ? 'teal' : 'red', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ];
                @endphp

                @foreach($quickStats as $stat)
                    @continue(!empty($stat['hide']))

                    <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-3 sm:p-4">
                        <div class="flex items-center justify-between mb-1.5">
                            <span
                                class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ $stat['label'] }}</span>
                            <div class="p-1.5 bg-{{ $stat['color'] }}-100 dark:bg-{{ $stat['color'] }}-900 rounded-lg">
                                <svg class="w-3.5 h-3.5 text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-300"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="{{ $stat['icon'] }}"/>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-base sm:text-xl font-bold {{ $stat['label'] === 'Remaining' && $remainingThisMonth < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                            {{ number_format($stat['value'], 0, '.', ',') }}
                        </h3>
                        <p class="text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $stat['subtitle'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Monthly Overview (cards with no data at all are hidden) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
                @php
                    $overviewCards = [
                        [
                            'title' => now()->format('F Y'),
                            'hide' => (float) $monthlyIncome == 0 && (float) $monthlyExpenses == 0,
                            'rows' => [
                                ['label' => 'Income', 'value' => $monthlyIncome, 'color' => 'green', 'progress' => 100],
                                ['label' => 'Expenses', 'value' => $monthlyExpenses, 'color' => 'red', 'progress' => $monthlyIncome > 0 ? min(($monthlyExpenses / $monthlyIncome) * 100, 100) : 0],
                                ['label' => 'Net', 'value' => $monthlyNet, 'color' => $monthlyNet >= 0 ? 'green' : 'red', 'isBold' => true],
                            ],
                        ],
                        [
                            'title' => 'Month Comparison',
                            'hide' => (float) $lastMonthTotal == 0 && (float) $totalThisMonth == 0,
                            'rows' => [
                                ['label' => 'Last Month', 'value' => $lastMonthTotal, 'plain' => true],
                                ['label' => 'This Month', 'value' => $totalThisMonth, 'plain' => true],
                                ['label' => 'Change', 'value' => $monthlyComparison, 'percent' => $monthlyComparisonPercent, 'color' => $monthlyComparison <= 0 ? 'green' : 'red'],
                            ],
                        ],
                        [
                            'title' => now()->year . ' Summary',
                            'hide' => (float) $yearlyIncome == 0 && (float) $yearlyExpenses == 0,
                            'rows' => [
                                ['label' => 'Total Income', 'value' => $yearlyIncome, 'color' => 'green'],
                                ['label' => 'Total Expenses', 'value' => $yearlyExpenses, 'color' => 'red'],
                                ['label' => 'Net Yearly', 'value' => $yearlyNet, 'color' => $yearlyNet >= 0 ? 'green' : 'red'],
                            ],
                        ],
                    ];
                @endphp

                @foreach($overviewCards as $card)
                    @continue(!empty($card['hide']))

                    <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-5">
                        <h3 class="text-sm sm:text-base font-bold text-gray-800 dark:text-white mb-3">{{ $card['title'] }}</h3>
                        <div class="space-y-3">
                            @foreach($card['rows'] as $row)
                                <div
                                    class="{{ isset($row['isBold']) ? 'pt-2 border-t border-gray-200 dark:border-gray-700' : '' }}">
                                    <div
                                        class="flex justify-between items-center {{ isset($row['progress']) ? 'mb-1' : '' }}">
                                        <span
                                            class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 {{ isset($row['isBold']) ? 'font-medium text-gray-700 dark:text-gray-300' : '' }}">
                                            {{ $row['label'] }}
                                        </span>
                                        <span
                                            class="text-{{ $row['color'] ?? 'gray' }}-600 dark:text-{{ $row['color'] ?? 'gray' }}-400 {{ isset($row['isBold']) || isset($row['plain']) ? 'text-sm sm:text-base font-bold' : 'text-xs sm:text-sm font-semibold' }}">
                                            {{ isset($row['plain']) ? 'KES ' : (isset($row['color']) && $row['color'] === 'green' ? '+' : (isset($row['color']) && $row['color'] === 'red' && $row['value'] < 0 ? '' : (isset($row['color']) && $row['color'] === 'red' ? '-' : ''))) }}{{ number_format(abs($row['value']), 0) }}
                                        </span>
                                    </div>
                                    @if(isset($row['progress']))
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                            <div
                                                class="bg-{{ $row['color'] }}-500 h-1.5 rounded-full transition-all duration-500"
                                                style="width: {{ $row['progress'] }}%"></div>
                                        </div>
                                    @endif
                                    @if(isset($row['percent']))
                                        <span
                                            class="inline-block mt-1 px-2 py-0.5 text-[11px] font-medium rounded-full bg-{{ $row['color'] }}-100 text-{{ $row['color'] }}-800">
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
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white">Accounts</h3>
                            <p class="text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 mt-0.5">
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
                               class="text-xs sm:text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium transition-colors">
                                Manage →
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
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
                                    class="bg-gradient-to-br {{ $accountGradients[$loop->index % count($accountGradients)] }} rounded-lg p-3 text-white shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 {{ $isLowBalance ? 'opacity-60' : '' }} balance-hidden">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="px-1.5 py-0.5 rounded-full text-[10px] sm:text-xs font-medium bg-white/20 backdrop-blur-sm">
                                                {{ ucfirst($account->type ?? 'Account') }}
                                            </span>
                                            @if($isLowBalance)
                                                <span
                                                    class="px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-yellow-400/90 text-yellow-900">
                                                    ⚠️
                                                </span>
                                            @endif
                                        </div>
                                        <div
                                            class="w-6 h-6 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="{{ $accountIcons[$account->type] ?? $accountIcons['default'] }}"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <h4 class="font-bold text-sm mb-2 truncate">{{ $account->name }}</h4>

                                    <div>
                                        <p class="text-[10px] sm:text-xs opacity-70 mb-0.5">Balance</p>
                                        <p class="text-base sm:text-lg font-bold balance-amount">KES {{ number_format($account->current_balance, 0, '.', ',') }}</p>
                                        <div class="flex items-center gap-2 balance-hidden hidden">
                                            <p class="text-base sm:text-lg font-bold">KES ••••••</p>
                                        </div>
                                        @if($account->current_balance < 0)
                                            <p class="text-[11px] mt-1 opacity-70 flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                     viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2"
                                                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                                Overdrawn
                                            </p>
                                        @elseif($isLowBalance)
                                            <p class="text-[11px] mt-1 opacity-70 flex items-center gap-1">
                                                Low Balance
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div
                                    class="hidden sm:flex mt-2 gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <a href="{{ route('accounts.show', $account) }}"
                                       class="flex-1 text-center px-2 py-1.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                        View
                                    </a>
                                    @if($isLowBalance)
                                        <a href="{{ route('accounts.topup', $account) }}"
                                           class="flex-1 text-center px-2 py-1.5 bg-green-100 dark:bg-green-900 rounded-lg text-xs font-medium text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800 transition-colors">
                                            Top Up
                                        </a>
                                    @else
                                        <a href="{{ route('transactions.create', ['account_id' => $account->id]) }}"
                                           class="flex-1 text-center px-2 py-1.5 bg-blue-100 dark:bg-blue-900 rounded-lg text-xs font-medium text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
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
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                @php
                                    $accountStats = [
                                        ['label' => 'Total', 'value' => $activeAccounts->sum('current_balance'), 'color' => 'gray'],
                                        ['label' => 'Highest', 'value' => $activeAccounts->max('current_balance'), 'color' => 'green'],
                                        ['label' => 'Lowest', 'value' => $activeAccounts->min('current_balance'), 'color' => 'orange'],
                                        ['label' => 'Average', 'value' => $activeAccounts->avg('current_balance'), 'color' => 'blue']
                                    ];
                                @endphp

                                @foreach($accountStats as $stat)
                                    <div class="text-center balance-hidden">
                                        <p class="text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 mb-0.5">{{ $stat['label'] }}</p>
                                        <p class="text-sm font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400 balance-amount">
                                            KES {{ number_format($stat['value'], 0) }}
                                        </p>
                                        <p class="text-sm font-bold text-{{ $stat['color'] }}-600 dark:text-{{ $stat['color'] }}-400 balance-hidden hidden">
                                            KES ••••••
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Wallets Section --}}
            @if($walletAccounts->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-5 mb-4 sm:mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white">Wallets</h3>
                            <p class="text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ $walletAccounts->count() }} wallet{{ $walletAccounts->count() != 1 ? 's' : '' }}
                            </p>
                        </div>
                        <a href="{{ route('accounts.index') }}"
                           class="text-xs sm:text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 font-medium transition-colors">
                            Manage →
                        </a>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                        @php
                            $walletGradients = ['from-sky-500 to-blue-600', 'from-cyan-500 to-blue-600', 'from-blue-500 to-indigo-600'];
                            $walletIcon = 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z';
                        @endphp

                        @foreach($walletAccounts as $account)
                            <div class="relative group">
                                <div
                                    class="bg-gradient-to-br {{ $walletGradients[$loop->index % count($walletGradients)] }} rounded-lg p-3 text-white shadow-md hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 wallet-balance-hidden">
                                    <div class="flex justify-between items-start mb-2">
                                        <span
                                            class="px-1.5 py-0.5 rounded-full text-[10px] sm:text-xs font-medium bg-white/20 backdrop-blur-sm">
                                            Wallet
                                        </span>
                                        <div
                                            class="w-6 h-6 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="{{ $walletIcon }}"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <h4 class="font-bold text-sm mb-2 truncate">{{ $account->name }}</h4>

                                    <div>
                                        <p class="text-[10px] sm:text-xs opacity-70 mb-0.5">Balance</p>
                                        <p class="text-base sm:text-lg font-bold wallet-balance-amount">KES {{ number_format($account->current_balance, 0, '.', ',') }}</p>
                                        <div class="flex items-center gap-2 wallet-balance-hidden-placeholder hidden">
                                            <p class="text-base sm:text-lg font-bold">KES ••••••</p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="hidden sm:flex mt-2 gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <a href="{{ route('accounts.show', $account) }}"
                                       class="flex-1 text-center px-2 py-1.5 bg-gray-100 dark:bg-gray-700 rounded-lg text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                        View
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Three Column Layout (a card with nothing to show is hidden; the rest fill the row) --}}
            @php
                $showTopExpenses = $topExpenses->count() > 0;
                // Loans the user has GIVEN out (not the ones they've borrowed). The
                // LoanGiven model's own global scope limits this to the current user.
                $loansGiven = \App\Models\LoanGiven::where('status', 'active')
                    ->orderByRaw('due_date IS NULL')
                    ->orderBy('due_date')
                    ->get();
                $showLoansGiven = $loansGiven->isNotEmpty();
                $loansGivenOutstanding = $loansGiven->sum('outstanding_amount');
                $loansGivenOverdue = $loansGiven->filter(fn ($l) => $l->isOverdue());
                $showRecent = $recentTransactions->count() > 0;

                $visibleColumns = (int) $showTopExpenses + (int) $showLoansGiven + (int) $showRecent;

                $columnClass = [
                    1 => 'lg:grid-cols-1',
                    2 => 'lg:grid-cols-2',
                    3 => 'lg:grid-cols-3',
                ][$visibleColumns] ?? '';
            @endphp

            @if($visibleColumns > 0)
                <div class="grid grid-cols-1 {{ $columnClass }} gap-3 sm:gap-4 mb-4 sm:mb-6">

                    {{-- Top Expenses --}}
                    @if($showTopExpenses)
                        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-5">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-sm sm:text-base font-bold text-gray-800 dark:text-white">Top Categories</h3>
                                @if($topExpenses->count() > 3)
                                    <a href="{{ route('transactions.index') }}"
                                       class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">View All →</a>
                                @endif
                            </div>
                            <div class="space-y-2.5">
                                @foreach($topExpenses->take(3) as $expense)
                                    @php
                                        $colors = ['blue', 'purple', 'pink', 'indigo', 'cyan'];
                                        $color = $colors[$loop->index % 5];
                                        $percentage = $totalThisMonth > 0 ? ($expense->total / $totalThisMonth) * 100 : 0;
                                    @endphp
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="w-8 h-8 rounded-lg bg-{{ $color }}-100 dark:bg-{{ $color }}-900 flex items-center justify-center flex-shrink-0">
                                            <span class="text-sm">{{ $expense->category->icon ?? '💰' }}</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-gray-900 dark:text-white text-xs sm:text-sm truncate">{{ $expense->category->name }}</p>
                                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-1">
                                                <div
                                                    class="bg-{{ $color }}-500 h-1.5 rounded-full transition-all duration-500"
                                                    style="width: {{ $percentage }}%"></div>
                                            </div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="font-bold text-gray-900 dark:text-white text-xs sm:text-sm">{{ number_format($expense->total, 0) }}</p>
                                            <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ round($percentage) }}%</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Loans Given (replaces the old "Active Loans" / borrowed card) --}}
                    @if($showLoansGiven)
                        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-5">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-sm sm:text-base font-bold text-gray-800 dark:text-white">Loans Given</h3>
                                <a href="{{ route('loans-given.index') }}"
                                   class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">View All →</a>
                            </div>

                            {{-- Summary --}}
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Outstanding</p>
                                    <p class="text-sm sm:text-base font-bold text-indigo-600 dark:text-indigo-400">
                                        KES {{ number_format($loansGivenOutstanding, 0) }}
                                    </p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">Active</p>
                                    <p class="text-sm sm:text-base font-bold text-gray-900 dark:text-white">
                                        {{ $loansGiven->count() }} loan{{ $loansGiven->count() != 1 ? 's' : '' }}
                                    </p>
                                    @if($loansGivenOverdue->count() > 0)
                                        <p class="text-[11px] font-medium text-red-600 dark:text-red-400">
                                            {{ $loansGivenOverdue->count() }} overdue
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Next due (overdue first) --}}
                            <div class="space-y-2">
                                @foreach($loansGiven->take(3) as $loanGiven)
                                    @php
                                        $isLate = $loanGiven->isOverdue();
                                        $daysLate = $loanGiven->due_date
                                            ? abs((int) now()->startOfDay()->diffInDays($loanGiven->due_date->copy()->startOfDay(), true))
                                            : null;
                                    @endphp
                                    <a href="{{ route('loans-given.show', $loanGiven->id) }}"
                                       class="flex items-center justify-between gap-2 border border-gray-200 dark:border-gray-700 rounded-lg p-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 dark:text-white text-xs sm:text-sm truncate">{{ $loanGiven->borrower_name }}</p>
                                            <p class="text-[11px] sm:text-xs {{ $isLate ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                                @if(!$loanGiven->due_date)
                                                    No due date
                                                @elseif($isLate)
                                                    Overdue {{ $daysLate }}d
                                                @else
                                                    Due {{ $loanGiven->due_date->format('M d') }}
                                                @endif
                                            </p>
                                        </div>
                                        <p class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                            KES {{ number_format($loanGiven->outstanding_amount, 0) }}
                                        </p>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- RECENT TRANSACTIONS --}}
                    @if($showRecent)
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-5">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-3 gap-2">
                                <h3 class="text-sm sm:text-base font-bold text-gray-800 dark:text-white">Recent
                                    Transactions</h3>
                                @if($recentTransactions->count() > 3)
                                    <a href="{{ route('transactions.index') }}"
                                       class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 self-start sm:self-auto">View
                                        All →</a>
                                @endif
                            </div>
                            <div class="space-y-1.5 sm:space-y-2">
                                @foreach($recentTransactions->take(3) as $transaction)
                                    <div
                                        class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                        <div class="flex items-center space-x-2 flex-1 min-w-0">
                                            @if($transaction->category->type == 'income')
                                                <div
                                                    class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center flex-shrink-0">
                                                    <span
                                                        class="text-xs sm:text-sm">{{ $transaction->category->icon ?? '💰' }}</span>
                                                </div>
                                            @else
                                                <div
                                                    class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center flex-shrink-0">
                                                    <span
                                                        class="text-xs sm:text-sm">{{ $transaction->category->icon ?? '💰' }}</span>
                                                </div>
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-gray-900 dark:text-white text-xs sm:text-sm truncate">{{ $transaction->category->name }}</p>
                                                <p class="text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 truncate">{{ \Carbon\Carbon::parse($transaction->date)->format('M d') }}</p>
                                            </div>
                                        </div>
                                        <div class="text-right ml-2 flex-shrink-0">
                                            @if($transaction->category->type == 'income')
                                                <p class="font-bold text-green-600 dark:text-green-400 text-xs sm:text-sm whitespace-nowrap">
                                                    +{{ number_format($transaction->amount, 0) }}
                                                </p>
                                            @else
                                                <p class="font-bold text-red-600 dark:text-red-400 text-xs sm:text-sm whitespace-nowrap">
                                                    -{{ number_format($transaction->amount, 0) }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- JavaScript for Toggle --}}
    <script>
        // ── State (persisted independently) ──────────────────────────────────
        let balancesVisible = localStorage.getItem('balancesVisible') === 'true';
        let savingsVisible = localStorage.getItem('savingsVisible') === 'true';
        let walletsVisible = localStorage.getItem('walletsVisible') === 'true';
        let lowBalanceAccountsVisible = localStorage.getItem('lowBalanceAccountsVisible') === 'true';

        // ── Main cash toggle ─────────────────────────────────────────
        function updateBalanceVisibility() {
            document.querySelectorAll('.balance-hidden').forEach(container => {
                const amount = container.querySelector('.balance-amount');
                const placeholder = container.querySelector('.balance-hidden');
                if (amount && placeholder) {
                    amount.classList.toggle('hidden', !balancesVisible);
                    placeholder.classList.toggle('hidden', balancesVisible);
                }
            });
            document.getElementById('toggleText').textContent = balancesVisible ? 'Hide Balance' : 'Show Balance';
            document.getElementById('eyeIcon').innerHTML = balancesVisible
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
        }

        // ── Savings-only toggle (the button / card only exist when savings > 0) ──
        function updateSavingsVisibility() {
            document.querySelectorAll('.savings-balance-hidden').forEach(container => {
                const amount = container.querySelector('.savings-balance-amount');
                const placeholder = container.querySelector('.savings-balance-hidden-placeholder');
                if (amount && placeholder) {
                    amount.classList.toggle('hidden', !savingsVisible);
                    placeholder.classList.toggle('hidden', savingsVisible);
                }
            });
            const textEl = document.getElementById('savingsToggleText');
            const iconEl = document.getElementById('savingsEyeIcon');
            if (textEl) textEl.textContent = savingsVisible ? 'Hide Savings' : 'Show Savings';
            if (iconEl) {
                iconEl.innerHTML = savingsVisible
                    ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>'
                    : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
            }
        }

        // ── Wallets-only toggle ───────────────────────────────────────────────
        function updateWalletVisibility() {
            document.querySelectorAll('.wallet-balance-hidden').forEach(container => {
                const amount = container.querySelector('.wallet-balance-amount');
                const placeholder = container.querySelector('.wallet-balance-hidden-placeholder') || container.querySelector('.wallet-balance-hidden');
                if (amount && placeholder) {
                    amount.classList.toggle('hidden', !walletsVisible);
                    placeholder.classList.toggle('hidden', walletsVisible);
                }
            });
            const toggleTextEl = document.getElementById('walletsToggleText');
            const eyeIconEl = document.getElementById('walletsEyeIcon');
            if (toggleTextEl) toggleTextEl.textContent = walletsVisible ? 'Hide Wallets' : 'Show Wallets';
            if (eyeIconEl) {
                eyeIconEl.innerHTML = walletsVisible
                    ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>'
                    : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
            }
        }

        // ── Low-balance accounts toggle ───────────────────────────────────────
        function toggleDashboardLowBalanceAccounts() {
            lowBalanceAccountsVisible = !lowBalanceAccountsVisible;
            localStorage.setItem('lowBalanceAccountsVisible', lowBalanceAccountsVisible);
            updateLowBalanceAccountsVisibility();
        }

        function updateLowBalanceAccountsVisibility() {
            const iconEl = document.getElementById('toggle-dashboard-accounts-icon');
            const textEl = document.getElementById('toggle-dashboard-accounts-text');

            document.querySelectorAll('.dashboard-low-balance-account').forEach(el =>
                el.classList.toggle('hidden', !lowBalanceAccountsVisible)
            );
            if (iconEl) iconEl.textContent = lowBalanceAccountsVisible ? '🙈' : '👁️';
            if (textEl) textEl.textContent = lowBalanceAccountsVisible ? 'Hide low' : 'Show low';
        }

        // ── Init on load ──────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            // Set up balance toggle
            const toggleBtn = document.getElementById('toggleBalance');
            toggleBtn.addEventListener('click', function () {
                balancesVisible = !balancesVisible;
                localStorage.setItem('balancesVisible', balancesVisible);
                updateBalanceVisibility();
            });

            // Set up savings toggle (only present when there are savings)
            const toggleSavingsBtn = document.getElementById('toggleSavingsBalance');
            if (toggleSavingsBtn) {
                toggleSavingsBtn.addEventListener('click', function () {
                    savingsVisible = !savingsVisible;
                    localStorage.setItem('savingsVisible', savingsVisible);
                    updateSavingsVisibility();
                });
            }

            // Set up wallets toggle (only present when the user has wallet accounts)
            const toggleWalletsBtn = document.getElementById('toggleWalletsBalance');
            if (toggleWalletsBtn) {
                toggleWalletsBtn.addEventListener('click', function () {
                    walletsVisible = !walletsVisible;
                    localStorage.setItem('walletsVisible', walletsVisible);
                    updateWalletVisibility();
                });
            }

            // Initialize visibility on load
            updateBalanceVisibility();
            updateSavingsVisibility();
            updateWalletVisibility();
            updateLowBalanceAccountsVisibility();
        });
    </script>

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
                window.addEventListener('scroll', function () {
                    if (window.pageYOffset > 300) {
                        scrollBtn.classList.remove('opacity-0', 'pointer-events-none');
                        scrollBtn.classList.add('opacity-100');
                    } else {
                        scrollBtn.classList.add('opacity-0', 'pointer-events-none');
                        scrollBtn.classList.remove('opacity-100');
                    }
                });

                scrollBtn.addEventListener('click', function () {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        });
    </script>

</x-app-layout>
