{{-- resources/views/dashboard/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <div class="flex items-center space-x-3">
                <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 hidden sm:inline">{{ now()->format('l, F j, Y') }}</span>
                <span class="text-xs text-gray-600 dark:text-gray-400 sm:hidden">{{ now()->format('M j, Y') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12 bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">

            {{-- Welcome Message --}}
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-1">
                    Welcome back, {{ Auth::user()->name }}! 👋
                </h1>
                <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400">
                    Here's your complete financial overview for {{ now()->format('F Y') }}.
                </p>
            </div>

            {{-- Toggle Button --}}
            <div class="flex justify-end mb-4">
                <button
                    id="toggleBalance"
                    class="flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                >
                    <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                    <span id="toggleText">Show Balance</span>
                </button>
            </div>

            {{-- NET WORTH OVERVIEW - 3 Main Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                {{-- Total Cash Card --}}
                <div class="rounded-xl shadow-lg p-4 sm:p-6 text-white" style="background: linear-gradient(to bottom right, rgb(34, 197, 94), rgb(5, 150, 105));">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="p-2 sm:p-3 rounded-lg" style="background-color: rgba(255, 255, 255, 0.2);">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <span class="text-xs sm:text-sm font-medium" style="opacity: 0.9;">Total Cash</span>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-2xl sm:text-3xl font-bold balance-amount hidden">KES {{ number_format($totalAssets, 0, '.', ',') }}</h3>
                        <div class="flex items-center gap-2 balance-hidden">
                            <h3 class="text-2xl sm:text-3xl font-bold">KES</h3>
                            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </div>
                        <p class="text-xs sm:text-sm" style="opacity: 0.8;">Across {{ $accounts->count() }} account{{ $accounts->count() != 1 ? 's' : '' }}</p>
                    </div>
                </div>

                {{-- Total Liabilities Card --}}
                <div class="rounded-xl shadow-lg p-4 sm:p-6 text-white" style="background: linear-gradient(to bottom right, rgb(239, 68, 68), rgb(225, 29, 72));">
                    <div class="flex items-center justify-between mb-3 sm:mb-4">
                        <div class="p-2 sm:p-3 rounded-lg" style="background-color: rgba(255, 255, 255, 0.2);">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                        </div>
                        <span class="text-xs sm:text-sm font-medium" style="opacity: 0.9;">Total Liabilities</span>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-2xl sm:text-3xl font-bold balance-amount hidden">KES {{ number_format($totalLiabilities, 0, '.', ',') }}</h3>
                        <div class="flex items-center gap-2 balance-hidden">
                            <h3 class="text-2xl sm:text-3xl font-bold">KES</h3>
                            <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </div>
                        <p class="text-xs sm:text-sm" style="opacity: 0.8;">{{ $activeLoans->count() }} active loan{{ $activeLoans->count() != 1 ? 's' : '' }}</p>
                    </div>
                </div>

                {{-- Net Worth Card --}}
                @if($netWorth >= 0)
                    <div class="rounded-xl shadow-lg p-4 sm:p-6 text-white" style="background: linear-gradient(to bottom right, rgb(59, 130, 246), rgb(79, 70, 229));">
                        @else
                            <div class="rounded-xl shadow-lg p-4 sm:p-6 text-white" style="background: linear-gradient(to bottom right, rgb(249, 115, 22), rgb(245, 158, 11));">
                                @endif
                                <div class="flex items-center justify-between mb-3 sm:mb-4">
                                    <div class="p-2 sm:p-3 rounded-lg" style="background-color: rgba(255, 255, 255, 0.2);">
                                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <span class="text-xs sm:text-sm font-medium" style="opacity: 0.9;">Net Balance</span>
                                </div>
                                <div class="space-y-1">
                                    <h3 class="text-2xl sm:text-3xl font-bold balance-amount hidden">KES {{ number_format($netWorth, 0, '.', ',') }}</h3>
                                    <div class="flex items-center gap-2 balance-hidden">
                                        <h3 class="text-2xl sm:text-3xl font-bold">KES</h3>
                                        <svg class="w-6 h-6 sm:w-7 sm:h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                        </svg>
                                    </div>
                                    <p class="text-xs sm:text-sm balance-amount hidden" style="opacity: 0.8;">Debt ratio: {{ number_format($debtToAssetRatio, 1) }}%</p>
                                    <p class="text-xs sm:text-sm balance-hidden" style="opacity: 0.8;">Debt ratio: Hidden</p>
                                </div>
                            </div>
                    </div>
                    {{-- QUICK STATS - 4 Cards --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
                        {{-- Today --}}
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400">Today</span>
                                <div class="p-1.5 sm:p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                    <svg class="w-3 h-3 sm:w-4 sm:h-4 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalToday, 0, '.', ',') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">KES spent today</p>
                        </div>

                        {{-- This Week --}}
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400">This Week</span>
                                <div class="p-1.5 sm:p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                                    <svg class="w-3 h-3 sm:w-4 sm:h-4 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalThisWeek, 0, '.', ',') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">KES this week</p>
                        </div>

                        {{-- This Month --}}
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400">This Month</span>
                                <div class="p-1.5 sm:p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                                    <svg class="w-3 h-3 sm:w-4 sm:h-4 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalThisMonth, 0, '.', ',') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">of {{ number_format($monthlyIncome, 0, '.', ',') }} income</p>
                        </div>

                        {{-- Remaining --}}
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400">Remaining</span>
                                @if($remainingThisMonth >= 0)
                                    <div class="p-1.5 sm:p-2 bg-teal-100 dark:bg-teal-900 rounded-lg">
                                        <svg class="w-3 h-3 sm:w-4 sm:h-4 text-teal-600 dark:text-teal-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="p-1.5 sm:p-2 bg-red-100 dark:bg-red-900 rounded-lg">
                                        <svg class="w-3 h-3 sm:w-4 sm:h-4 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            @if($remainingThisMonth >= 0)
                                <h3 class="text-lg sm:text-2xl font-bold text-teal-600 dark:text-teal-400">{{ number_format($remainingThisMonth, 0, '.', ',') }}</h3>
                            @else
                                <h3 class="text-lg sm:text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($remainingThisMonth, 0, '.', ',') }}</h3>
                            @endif
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $monthlyIncome > 0 ? round(($remainingThisMonth / $monthlyIncome) * 100) . '% left' : '0%' }}</p>
                        </div>
                    </div>

                    {{-- MONTHLY OVERVIEW --}}
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                        {{-- Monthly Income/Expense Summary --}}
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                            <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white mb-4">{{ now()->format('F Y') }}</h3>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Income</span>
                                        <span class="text-xs sm:text-sm font-semibold text-green-600 dark:text-green-400">+{{ number_format($monthlyIncome, 0) }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: 100%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Expenses</span>
                                        <span class="text-xs sm:text-sm font-semibold text-red-600 dark:text-red-400">-{{ number_format($monthlyExpenses, 0) }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-red-500 h-2 rounded-full" style="width: {{ $monthlyIncome > 0 ? min(($monthlyExpenses / $monthlyIncome) * 100, 100) : 0 }}%"></div>
                                    </div>
                                </div>
                                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300">Net</span>
                                        @if($monthlyNet >= 0)
                                            <span class="text-base sm:text-lg font-bold text-green-600 dark:text-green-400">
                                        +{{ number_format($monthlyNet, 0) }}
                                    </span>
                                        @else
                                            <span class="text-base sm:text-lg font-bold text-red-600 dark:text-red-400">
                                        {{ number_format($monthlyNet, 0) }}
                                    </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Monthly Comparison --}}
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                            <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white mb-4">Month Comparison</h3>
                            <div class="space-y-3">
                                <div>
                                    <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Last Month</span>
                                    <p class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white">KES {{ number_format($lastMonthTotal, 0) }}</p>
                                </div>
                                <div>
                                    <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">This Month</span>
                                    <p class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white">KES {{ number_format($totalThisMonth, 0) }}</p>
                                </div>
                                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Change</span>
                                    <div class="flex items-center space-x-2 mt-1 flex-wrap">
                                        @if($monthlyComparison <= 0)
                                            <p class="text-lg sm:text-xl font-bold text-green-600">
                                                {{ $monthlyComparison > 0 ? '+' : '' }}{{ number_format($monthlyComparison, 0) }}
                                            </p>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                        {{ $monthlyComparisonPercent > 0 ? '+' : '' }}{{ $monthlyComparisonPercent }}%
                                    </span>
                                        @else
                                            <p class="text-lg sm:text-xl font-bold text-red-600">
                                                +{{ number_format($monthlyComparison, 0) }}
                                            </p>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                        +{{ $monthlyComparisonPercent }}%
                                    </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Yearly Summary --}}
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                            <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white mb-4">{{ now()->year }} Summary</h3>
                            <div class="space-y-3">
                                <div>
                                    <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Total Income</span>
                                    <p class="text-lg sm:text-xl font-bold text-green-600 dark:text-green-400">KES {{ number_format($yearlyIncome, 0) }}</p>
                                </div>
                                <div>
                                    <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Total Expenses</span>
                                    <p class="text-lg sm:text-xl font-bold text-red-600 dark:text-red-400">KES {{ number_format($yearlyExpenses, 0) }}</p>
                                </div>
                                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Net Yearly</span>
                                    @if($yearlyNet >= 0)
                                        <p class="text-lg sm:text-xl font-bold text-green-600 dark:text-green-400">
                                            KES {{ number_format($yearlyNet, 0) }}
                                        </p>
                                    @else
                                        <p class="text-lg sm:text-xl font-bold text-red-600 dark:text-red-400">
                                            KES {{ number_format($yearlyNet, 0) }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ACCOUNTS SECTION --}}
                    @if($accounts->count() > 0)
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6 mb-6 sm:mb-8">
                            <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white mb-4">Accounts Overview</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                                @foreach($accounts as $account)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 sm:p-4 hover:shadow-md transition-shadow">
                                        <h4 class="font-semibold text-sm sm:text-base text-gray-900 dark:text-white mb-2">{{ $account->name }}</h4>
                                        <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">KES {{ number_format($account->current_balance, 0) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- THREE COLUMN LAYOUT: TOP EXPENSES | ACTIVE LOANS | RECENT TRANSACTIONS --}}
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">

                        {{-- TOP EXPENSES SECTION --}}
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white">Top Spending Categories</h3>
                                @if($topExpenses->count() > 3)
                                    <a href="{{ route('transactions.index') }}" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 whitespace-nowrap">View All →</a>
                                @endif
                            </div>
                            @if($topExpenses->count() > 0)
                                <div class="space-y-3">
                                    @php
                                        $colors = ['blue', 'purple', 'pink', 'indigo', 'cyan'];
                                    @endphp
                                    @foreach($topExpenses->take(3) as $expense)
                                        @php
                                            $colorIndex = $loop->index % 5;
                                            $color = $colors[$colorIndex];
                                        @endphp
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-2 sm:space-x-3 flex-1 min-w-0">
                                                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-{{ $color }}-100 dark:bg-{{ $color }}-900 flex items-center justify-center flex-shrink-0">
                                                    <span class="text-base sm:text-lg">{{ $expense->category->icon ?? '💰' }}</span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-medium text-gray-900 dark:text-white text-xs sm:text-sm truncate">{{ $expense->category->name }}</p>
                                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-1">
                                                        <div class="bg-{{ $color }}-500 h-2 rounded-full" style="width: {{ $totalThisMonth > 0 ? ($expense->total / $totalThisMonth) * 100 : 0 }}%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right ml-2 flex-shrink-0">
                                                <p class="font-bold text-gray-900 dark:text-white text-xs sm:text-sm">{{ number_format($expense->total, 0) }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $totalThisMonth > 0 ? round(($expense->total / $totalThisMonth) * 100) : 0 }}%</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center py-8 text-center">
                                    <span class="text-3xl sm:text-4xl mb-2">📊</span>
                                    <p class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">No expenses this month</p>
                                </div>
                            @endif
                        </div>

                        {{-- ACTIVE LOANS SECTION --}}
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 gap-2">
                                <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white">Active Loans</h3>
                                @if($activeLoans->count() > 3)
                                    <a href="{{ route('loans.index') }}" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 self-start sm:self-auto">View All →</a>
                                @endif
                            </div>
                            @if($activeLoans->count() > 0)
                                <div class="space-y-3">
                                    @foreach($activeLoans->take(3) as $loan)
                                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 sm:p-4">
                                            <div class="flex flex-col xs:flex-row xs:justify-between xs:items-start gap-2">
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm sm:text-base truncate">{{ $loan->account->name ?? 'Loan' }}</h4>
                                                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Due: {{ \Carbon\Carbon::parse($loan->due_date)->format('M d, Y') }}</p>
                                                </div>
                                                <div class="text-left xs:text-right xs:ml-2 flex-shrink-0">
                                                    <p class="text-lg sm:text-xl font-bold text-red-600 dark:text-red-400">{{ number_format($loan->balance, 0) }}</p>
                                                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ number_format($loan->interest_rate, 1) }}%</p>
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
                                <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white">Recent Transactions</h3>
                                @if($recentTransactions->count() > 3)
                                    <a href="{{ route('transactions.index') }}" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 self-start sm:self-auto">View All →</a>
                                @endif
                            </div>
                            @if($recentTransactions->count() > 0)
                                <div class="space-y-2 sm:space-y-3">
                                    @foreach($recentTransactions->take(3) as $transaction)
                                        <div class="flex items-center justify-between py-2 sm:py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                            <div class="flex items-center space-x-2 sm:space-x-3 flex-1 min-w-0">
                                                @if($transaction->category->type == 'income')
                                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center flex-shrink-0">
                                                        <span class="text-sm sm:text-base">{{ $transaction->category->icon ?? '💰' }}</span>
                                                    </div>
                                                @else
                                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center flex-shrink-0">
                                                        <span class="text-sm sm:text-base">{{ $transaction->category->icon ?? '💰' }}</span>
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
    </div>
    {{-- JavaScript for Toggle --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleBalance');
            const toggleText = document.getElementById('toggleText');
            const eyeIcon = document.getElementById('eyeIcon');
            const balanceAmounts = document.querySelectorAll('.balance-amount');
            const balanceHidden = document.querySelectorAll('.balance-hidden');

            // Check localStorage for saved preference (default is hidden)
            let isVisible = localStorage.getItem('balanceVisible') === 'true';

            // Set initial state
            updateVisibility();

            toggleBtn.addEventListener('click', function() {
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


</x-app-layout>
