<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Financial Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4">
            <!-- Header -->
            <div class="mb-10">
                <h1 class="text-5xl font-bold text-gray-900">Financial Dashboard</h1>
                <p class="text-gray-600 mt-2 text-base">Welcome back! Here's your complete financial overview.</p>
            </div>

            <!-- NET WORTH SECTION -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <!-- Total Cash -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 border-l-4 border-green-500 p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-green-700 mb-2 uppercase tracking-wide">Total Cash</p>
                            <p class="text-4xl font-bold text-green-900 mb-1">
                                KES {{ number_format($totalAssets, 0, '.', ',') }}
                            </p>
                            <p class="text-xs text-green-700">Cash across all accounts</p>
                        </div>
                        <div class="text-green-500">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Total Liabilities -->
                <div class="bg-gradient-to-br from-red-50 to-red-100 border-l-4 border-red-500 p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-red-700 mb-2 uppercase tracking-wide">Total Liabilities</p>
                            <p class="text-4xl font-bold text-red-900 mb-1">
                                KES {{ number_format($totalLiabilities, 0, '.', ',') }}
                            </p>
                            <p class="text-xs text-red-700">Outstanding loan balances</p>
                        </div>
                        <div class="text-red-500">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Net Balance -->
                <div class="bg-gradient-to-br {{ $netWorth >= 0 ? 'from-blue-50 to-blue-100 border-blue-500' : 'from-orange-50 to-orange-100 border-orange-500' }} border-l-4 p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold {{ $netWorth >= 0 ? 'text-blue-700' : 'text-orange-700' }} mb-2 uppercase tracking-wide">Net Balance</p>
                            <p class="text-4xl font-bold {{ $netWorth >= 0 ? 'text-blue-900' : 'text-orange-900' }} mb-1">
                                KES {{ number_format($netWorth, 0, '.', ',') }}
                            </p>
                            <p class="text-xs {{ $netWorth >= 0 ? 'text-blue-700' : 'text-orange-700' }}">
                                Cash - Liabilities
                            </p>
                        </div>
                        <div class="{{ $netWorth >= 0 ? 'text-blue-500' : 'text-orange-500' }}">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FINANCIAL HEALTH INDICATOR -->
            @if($totalLiabilities > 0)
                <div class="mb-10 p-6 rounded-lg {{ $debtToAssetRatio > 50 ? 'bg-red-50 border-l-4 border-red-500' : ($debtToAssetRatio > 30 ? 'bg-yellow-50 border-l-4 border-yellow-500' : 'bg-green-50 border-l-4 border-green-500') }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold {{ $debtToAssetRatio > 50 ? 'text-red-900' : ($debtToAssetRatio > 30 ? 'text-yellow-900' : 'text-green-900') }} mb-2">
                                Debt-to-Cash Ratio: {{ number_format($debtToAssetRatio, 1) }}%
                            </p>
                            <p class="text-sm {{ $debtToAssetRatio > 50 ? 'text-red-700' : ($debtToAssetRatio > 30 ? 'text-yellow-700' : 'text-green-700') }}">
                                @if($debtToAssetRatio > 50)
                                    ⚠️ High debt level - consider prioritizing loan repayments
                                @elseif($debtToAssetRatio > 30)
                                    ⚡ Moderate debt level - monitor closely
                                @else
                                    ✅ Healthy debt level - well managed
                                @endif
                            </p>
                        </div>
                        <div class="w-48">
                            <div class="w-full bg-gray-300 rounded-full h-2">
                                <div class="{{ $debtToAssetRatio > 50 ? 'bg-red-500' : ($debtToAssetRatio > 30 ? 'bg-yellow-500' : 'bg-green-500') }} h-2 rounded-full"
                                     style="width: {{ min($debtToAssetRatio, 100) }}%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- QUICK STATS -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <!-- Today -->
                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                    <p class="text-xs font-bold text-gray-500 mb-3 uppercase tracking-wide">Today</p>
                    <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($totalToday, 0, '.', ',') }}</p>
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-500">KES</span>
                        <div class="bg-blue-100 p-2 rounded-full">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- This Week -->
                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
                    <p class="text-xs font-bold text-gray-500 mb-3 uppercase tracking-wide">This Week</p>
                    <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($totalThisWeek, 0, '.', ',') }}</p>
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-500">KES</span>
                        <div class="bg-green-100 p-2 rounded-full">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- This Month -->
                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
                    <p class="text-xs font-bold text-gray-500 mb-3 uppercase tracking-wide">This Month</p>
                    <p class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($totalThisMonth, 0, '.', ',') }}</p>
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-500">of {{ number_format($monthlyIncome, 0, '.', ',') }}</span>
                        <div class="bg-purple-100 p-2 rounded-full">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Remaining -->
                <div class="bg-white p-6 rounded-lg shadow border-l-4 {{ $remainingThisMonth >= 0 ? 'border-teal-500' : 'border-red-500' }}">
                    <p class="text-xs font-bold text-gray-500 mb-3 uppercase tracking-wide">Remaining</p>
                    <p class="text-3xl font-bold {{ $remainingThisMonth >= 0 ? 'text-teal-600' : 'text-red-600' }} mb-1">
                        {{ number_format($remainingThisMonth, 0, '.', ',') }}
                    </p>
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-500">{{ $monthlyIncome > 0 ? round(($remainingThisMonth / $monthlyIncome) * 100) : 0 }}% left</span>
                        <div class="{{ $remainingThisMonth >= 0 ? 'bg-teal-100' : 'bg-red-100' }} p-2 rounded-full">
                            <svg class="w-5 h-5 {{ $remainingThisMonth >= 0 ? 'text-teal-600' : 'text-red-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INCOME VS EXPENSES -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                <!-- This Month -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">This Month <span class="text-sm font-normal text-gray-500">({{ now()->format('F Y') }})</span></h2>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                            <span class="text-gray-600">Income</span>
                            <span class="text-lg font-semibold text-green-600">+{{ number_format($monthlyIncome, 0, '.', ',') }}</span>
                        </div>
                        <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                            <span class="text-gray-600">Expenses</span>
                            <span class="text-lg font-semibold text-red-600">-{{ number_format($monthlyExpenses, 0, '.', ',') }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-gray-900 font-semibold">Net</span>
                            <span class="text-2xl font-bold {{ $monthlyNet >= 0 ? 'text-blue-600' : 'text-orange-600' }}">
                                {{ $monthlyNet >= 0 ? '+' : '' }}{{ number_format($monthlyNet, 0, '.', ',') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Year to Date -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Year to Date <span class="text-sm font-normal text-gray-500">({{ now()->year }})</span></h2>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                            <span class="text-gray-600">Income</span>
                            <span class="text-lg font-semibold text-green-600">+{{ number_format($yearlyIncome, 0, '.', ',') }}</span>
                        </div>
                        <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                            <span class="text-gray-600">Expenses</span>
                            <span class="text-lg font-semibold text-red-600">-{{ number_format($yearlyExpenses, 0, '.', ',') }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-gray-900 font-semibold">Net</span>
                            <span class="text-2xl font-bold {{ $yearlyNet >= 0 ? 'text-blue-600' : 'text-orange-600' }}">
                                {{ $yearlyNet >= 0 ? '+' : '' }}{{ number_format($yearlyNet, 0, '.', ',') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MONTH COMPARISON -->
            <div class="bg-white p-6 rounded-lg shadow mb-10">
                <h3 class="text-xl font-semibold text-gray-900 mb-6">Monthly Comparison</h3>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">This Month</p>
                        <p class="text-3xl font-bold text-gray-900">{{ number_format($totalThisMonth, 0, '.', ',') }}</p>
                    </div>
                    <div class="text-center">
                        @if($monthlyComparison > 0)
                            <p class="text-red-600 font-bold text-lg mb-1">
                                ↑ {{ number_format(abs($monthlyComparison), 0, '.', ',') }}
                            </p>
                            <p class="text-sm text-red-600">+{{ $monthlyComparisonPercent }}% vs last month</p>
                        @elseif($monthlyComparison < 0)
                            <p class="text-green-600 font-bold text-lg mb-1">
                                ↓ {{ number_format(abs($monthlyComparison), 0, '.', ',') }}
                            </p>
                            <p class="text-sm text-green-600">{{ $monthlyComparisonPercent }}% vs last month</p>
                        @else
                            <p class="text-gray-600 font-semibold">No change</p>
                            <p class="text-sm text-gray-500">Same as last month</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Last Month</p>
                        <p class="text-3xl font-bold text-gray-900">{{ number_format($lastMonthTotal, 0, '.', ',') }}</p>
                    </div>
                </div>
            </div>

            <!-- ACCOUNTS & LOANS -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                <!-- Accounts -->
                <div class="bg-white shadow rounded-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-semibold text-gray-900">Accounts</h2>
                        <a href="{{ route('accounts.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">View All →</a>
                    </div>
                    <div class="space-y-3">
                        @forelse($accounts as $account)
                            <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                                <p class="font-semibold text-gray-900">{{ $account->name }}</p>
                                <span class="font-bold text-lg {{ $account->current_balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($account->current_balance, 0, '.', ',') }}
                                </span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4">No accounts yet</p>
                        @endforelse
                    </div>
                </div>

                <!-- Active Loans -->
                <div class="bg-white shadow rounded-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-semibold text-gray-900">Active Loans</h2>
                        <a href="{{ route('loans.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">View All →</a>
                    </div>
                    <div class="space-y-3">
                        @forelse($activeLoans as $loan)
                            <div class="flex justify-between items-center p-4 bg-red-50 rounded-lg">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $loan->source ?? 'Loan' }}</p>
                                    <p class="text-xs text-gray-600 mt-1">
                                        {{ $loan->account->name ?? 'N/A' }}
                                        @if($loan->due_date)
                                            • Due {{ $loan->due_date->format('M d') }}
                                        @endif
                                    </p>
                                </div>
                                <span class="font-bold text-lg text-red-600">
                                    {{ number_format($loan->balance, 0, '.', ',') }}
                                </span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4">No active loans</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- TOP EXPENSES & RECENT TRANSACTIONS -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Top Expenses This Month -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Top Expenses <span class="text-sm font-normal text-gray-500">({{ now()->format('F') }})</span></h2>
                    <div class="space-y-3">
                        @forelse($topExpenses->take(3) as $expense)
                            <div class="flex justify-between items-center pb-3 border-b border-gray-100 last:border-0">
                                <span class="text-sm text-gray-700">{{ $expense->category->name }}</span>
                                <span class="font-bold text-sm text-red-600">{{ number_format($expense->total, 0, '.', ',') }}</span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4">No expenses this month</p>
                        @endforelse
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Recent Transactions</h2>
                    <div class="space-y-3">
                        @forelse($recentTransactions->take(3) as $transaction)
                            <div class="flex justify-between items-start pb-3 border-b border-gray-100 last:border-0">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-800 text-sm">{{ $transaction->description }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $transaction->date->format('M d') }} • {{ $transaction->category->name }}</p>
                                </div>
                                <span class="font-bold text-sm ml-3 {{ $transaction->category->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $transaction->category->type === 'income' ? '+' : '-' }}{{ number_format($transaction->amount, 0, '.', ',') }}
                                </span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4">No transactions yet</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
