{{-- resources/views/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Financial Dashboard') }}
            </h2>
            <div class="flex items-center space-x-3">
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ now()->format('l, F j, Y') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-12 bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Welcome Message --}}
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">
                    Welcome back, {{ Auth::user()->name }}! 👋
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Here's your complete financial overview.
                </p>
            </div>

            {{-- NET WORTH CARDS --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                {{-- Total Cash Card --}}
                <a href="#accounts-section" class="block transform transition-all duration-300 hover:scale-105">
                    <x-card
                        title="Total Cash"
                        value="KES {{ number_format($totalAssets, 0, '.', ',') }}"
                        subtitle="Cash across all accounts"
                        color="green"
                        border="green-500"
                        textColor="green-900"
                        icon='<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>'
                    />
                </a>

                {{-- Total Liabilities Card --}}
                <a href="#loans-section" class="block transform transition-all duration-300 hover:scale-105">
                    <x-card
                        title="Total Liabilities"
                        value="KES {{ number_format($totalLiabilities, 0, '.', ',') }}"
                        subtitle="Outstanding loan balances"
                        color="red"
                        border="red-500"
                        textColor="red-900"
                        icon='<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>'
                    />
                </a>

                {{-- Net Balance Card --}}
                <a href="#income-expenses-section" class="block transform transition-all duration-300 hover:scale-105">
                    <x-card
                        title="Net Balance"
                        value="KES {{ number_format($netWorth, 0, '.', ',') }}"
                        subtitle="Cash - Liabilities"
                        color="{{ $netWorth >= 0 ? 'blue' : 'orange' }}"
                        border="{{ $netWorth >= 0 ? 'blue-500' : 'orange-500' }}"
                        textColor="{{ $netWorth >= 0 ? 'blue-900' : 'orange-900' }}"
                        icon='<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>'
                    />
                </a>
            </div>

            {{-- QUICK STATS --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                {{-- Today's Spending --}}
                <x-quick-stat
                    label="Today"
                    value="{{ number_format($totalToday, 0, '.', ',') }}"
                    subtext="KES"
                    color="blue"
                    :positive="true"
                />

                {{-- This Week --}}
                <x-quick-stat
                    label="This Week"
                    value="{{ number_format($totalThisWeek, 0, '.', ',') }}"
                    subtext="KES"
                    color="green"
                    :positive="true"
                />

                {{-- This Month --}}
                <x-quick-stat
                    label="This Month"
                    value="{{ number_format($totalThisMonth, 0, '.', ',') }}"
                    subtext="of {{ number_format($monthlyIncome, 0, '.', ',') }}"
                    color="purple"
                    :positive="true"
                />

                {{-- Remaining --}}
                <x-quick-stat
                    label="Remaining"
                    value="{{ number_format($remainingThisMonth, 0, '.', ',') }}"
                    subtext="{{ $monthlyIncome > 0 ? round(($remainingThisMonth / $monthlyIncome) * 100) . '% left' : '0%' }}"
                    color="{{ $remainingThisMonth >= 0 ? 'teal' : 'red' }}"
                    :positive="$remainingThisMonth >= 0"
                />
            </div>

            {{-- INCOME VS EXPENSES CARD --}}
            <div class="grid grid-cols-1 gap-6 mb-8">
                <x-income-expense
                    period="{{ now()->format('F Y') }}"
                    :income="$monthlyIncome"
                    :expenses="$monthlyExpenses"
                    :net="$monthlyNet"
                    href="#income-expenses-section"
                />
            </div>

            {{-- Compact Stats Row --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                {{-- Budget Progress Card --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                        Budget Progress
                    </h3>
                    @php
                        $budgetUsed = $monthlyIncome > 0 ? round(($monthlyExpenses / $monthlyIncome) * 100) : 0;
                    @endphp
                    <div class="flex justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Used</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $budgetUsed }}%</span>
                    </div>
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden mb-3">
                        <div class="h-full bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full transition-all duration-1000"
                             style="width: {{ min($budgetUsed, 100) }}%"></div>
                    </div>
                    <div class="text-center pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-xs text-gray-600 dark:text-gray-400">Available</span>
                        <p class="text-xl font-bold text-{{ $remainingThisMonth >= 0 ? 'green' : 'red' }}-600">
                            KES {{ number_format(abs($remainingThisMonth), 0, '.', ',') }}
                        </p>
                    </div>
                </div>

                {{-- Financial Health Score Card --}}
                <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Health Score
                    </h3>
                    @php
                        $debtToAssetRatio = $totalAssets > 0 ? ($totalLiabilities / $totalAssets) * 100 : 0;
                        $savingsRate = $monthlyIncome > 0 ? (($monthlyIncome - $monthlyExpenses) / $monthlyIncome) * 100 : 0;
                        $healthScore = max(0, min(100, 100 - $debtToAssetRatio + $savingsRate));
                        $healthScore = round($healthScore);
                    @endphp
                    <div class="text-center mb-3">
                        <div class="text-5xl font-bold text-white mb-1">{{ $healthScore }}</div>
                        <div class="text-sm text-white">
                            @if($healthScore >= 80)
                                Excellent! 🎉
                            @elseif($healthScore >= 60)
                                Good 👍
                            @elseif($healthScore >= 40)
                                Fair 📊
                            @else
                                Needs Attention 💡
                            @endif
                        </div>
                    </div>
                    <div class="space-y-2 text-white text-sm">
                        <div class="flex justify-between">
                            <span class="opacity-90">Debt Ratio:</span>
                            <span class="font-semibold">{{ number_format($debtToAssetRatio, 1) }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="opacity-90">Savings:</span>
                            <span class="font-semibold">{{ number_format($savingsRate, 1) }}%</span>
                        </div>
                    </div>
                </div>

                {{-- Monthly Summary Card --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        This Month
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Income</span>
                            <span class="text-lg font-bold text-green-600">{{ number_format($monthlyIncome, 0, '.', ',') }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Expenses</span>
                            <span class="text-lg font-bold text-red-600">{{ number_format($monthlyExpenses, 0, '.', ',') }}</span>
                        </div>
                        <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Net</span>
                                <span class="text-xl font-bold text-{{ $monthlyNet >= 0 ? 'blue' : 'orange' }}-600">
                                    {{ number_format($monthlyNet, 0, '.', ',') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- DETAILED SECTIONS --}}

            {{-- Accounts Section --}}
            <div id="accounts-section" class="mb-8 scroll-mt-20">
                <x-accounts :accounts="$accounts" href="#accounts-section"/>
            </div>

            {{-- Loans Section --}}
            <div id="loans-section" class="mb-8 scroll-mt-20">
                <x-active-loans :loans="$activeLoans" href="#loans-section"/>
            </div>

            {{-- Top Expenses Section --}}
            <div id="top-expenses-section" class="mb-8 scroll-mt-20">
                <x-top-expenses :expenses="$topExpenses" href="#top-expenses-section"/>
            </div>

            {{-- Recent Transactions Section --}}
            <div id="recent-transactions-section" class="mb-8 scroll-mt-20">
                <x-recent-transactions :transactions="$recentTransactions" href="#recent-transactions-section"/>
            </div>

            {{-- Quick Actions Footer --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Quick Actions</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <a href="{{ route('transactions.create') }}" class="flex flex-col items-center justify-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900 dark:to-blue-800 rounded-xl hover:shadow-lg transition-all duration-300 group">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-300 mb-1 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span class="text-xs font-semibold text-blue-900 dark:text-blue-100">Add Transaction</span>
                    </a>

                    <a href="{{ route('accounts.create') }}" class="flex flex-col items-center justify-center p-4 bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900 dark:to-green-800 rounded-xl hover:shadow-lg transition-all duration-300 group">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-300 mb-1 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="text-xs font-semibold text-green-900 dark:text-green-100">New Account</span>
                    </a>

                    <a href="{{ route('loans.create') }}" class="flex flex-col items-center justify-center p-4 bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900 dark:to-red-800 rounded-xl hover:shadow-lg transition-all duration-300 group">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-300 mb-1 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-xs font-semibold text-red-900 dark:text-red-100">Add Loan</span>
                    </a>

                    <a href="{{ route('reports.index') }}" class="flex flex-col items-center justify-center p-4 bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900 dark:to-purple-800 rounded-xl hover:shadow-lg transition-all duration-300 group">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-300 mb-1 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="text-xs font-semibold text-purple-900 dark:text-purple-100">View Reports</span>
                    </a>
                </div>
            </div>

        </div>
    </div>

    {{-- Add smooth scroll behavior --}}
    <style>
        html {
            scroll-behavior: smooth;
        }

        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fade-in 0.6s ease-out;
        }
    </style>
</x-app-layout>
