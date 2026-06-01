{{-- resources/views/cafeteria/index.blade.php --}}
<x-app-layout>
    <style>
        @media (max-width: 640px) {
            body { font-size: 13px; }
            h1 { font-size: 1.5rem !important; }
            h2 { font-size: 1.25rem !important; }
            h3 { font-size: 1rem !important; }
            h4 { font-size: 0.875rem !important; }
            .text-3xl { font-size: 1.5rem !important; }
            .text-2xl { font-size: 1.25rem !important; }
            .text-xl { font-size: 1.125rem !important; }
        }

        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .meal-card {
            transition: all 0.3s ease;
        }

        .meal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
        }
    </style>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Cafeteria') }}
            </h2>
            <div class="flex items-center space-x-3">
                <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 hidden sm:inline">
                    {{ now()->format('l, F j, Y') }}
                </span>

                {{-- Budget warning badge --}}
                @if(Auth::user()->isCriticalBudget())
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded-full text-xs font-semibold">
                        <span class="animate-pulse">🔴</span>
                        Budget Critical
                    </div>
                @elseif(Auth::user()->isWarningBudget())
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded-full text-xs font-semibold">
                        <span>⚠️</span>
                        Budget Warning
                    </div>
                @endif

                <a href="{{ route('cafeteria.create') }}"
                   class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
                    + New Meal
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12 bg-gradient-to-br from-slate-50 via-amber-50 to-orange-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">

            {{-- Welcome Header --}}
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-1">
                    Welcome to your Meal Account 🍽️
                </h1>
                <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400">
                    Track your meal expenses and manage your cafeteria account
                </p>
            </div>

            {{-- Month Selector --}}
            <div class="mb-6 sm:mb-8 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="{{ route('cafeteria.index', ['month' => now()->subMonth()->format('Y-m')]) }}"
                       class="p-2 hover:bg-white dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white min-w-40">
                        {{ $monthDate->format('F Y') }}
                    </span>
                    <a href="{{ route('cafeteria.index', ['month' => now()->addMonth()->format('Y-m')]) }}"
                       class="p-2 hover:bg-white dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
                <a href="{{ route('cafeteria.menu') }}" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                    Manage Menu →
                </a>
            </div>

            {{-- Statistics Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
                @php
                    $statCards = [
                        [
                            'title' => 'Today',
                            'amount' => $todayTotal,
                            'subtitle' => 'Spent today',
                            'gradient' => 'from-orange-500 to-red-600',
                            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
                        ],
                        [
                            'title' => 'This Week',
                            'amount' => $weekTotal,
                            'subtitle' => 'KES this week',
                            'gradient' => 'from-amber-500 to-orange-600',
                            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
                        ],
                        [
                            'title' => 'This Month',
                            'amount' => $monthlyTotal,
                            'subtitle' => 'KES spent',
                            'gradient' => 'from-yellow-500 to-amber-600',
                            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'
                        ],
                        [
                            'title' => 'Average Meal',
                            'amount' => $monthlyTotal > 0 ? round($monthlyTotal / ($orders->total() > 0 ? $orders->total() : 1)) : 0,
                            'subtitle' => 'Cost per meal',
                            'gradient' => 'from-red-500 to-pink-600',
                            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                        ]
                    ];
                @endphp

                @foreach($statCards as $card)
                    <div class="stat-card rounded-2xl shadow-lg p-5 sm:p-6 text-white bg-gradient-to-br {{ $card['gradient'] }}">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 rounded-xl bg-white/20 backdrop-blur-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/>
                                </svg>
                            </div>
                            <span class="text-sm font-medium opacity-90">{{ $card['title'] }}</span>
                        </div>

                        <div class="space-y-1">
                            <h3 class="text-3xl font-bold">
                                KES {{ number_format($card['amount'], 0, '.', ',') }}
                            </h3>
                            <p class="text-sm opacity-80">{{ $card['subtitle'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Monthly Budget Card --}}
            <div class="mb-6 sm:mb-8">
                @php
                    $budgetUser    = Auth::user();
                    $monthRecord   = $budgetUser->getCurrentMonthlySpendings();   // ← use record directly
                    $spent         = (float) $monthRecord->total_spent;
                    $limit         = (float) $monthRecord->limit;                 // base limit (no carryover)
                    $carryover     = (float) $monthRecord->carryover;             // signed carryover
                    $effectiveLimit = $monthRecord->effective_limit;              // limit + carryover
                    $remaining     = $monthRecord->getRemainingBudget();
                    $percentage    = $monthRecord->getSpendingPercentage();
                    $color         = $monthRecord->getBudgetStatusColor();
                    $isCritical    = $monthRecord->isCritical();
                    $isWarning     = $monthRecord->isWarning();
                    $isExceeded    = $monthRecord->isOverBudget();
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    {{-- Header --}}
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 sm:px-8 py-4 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg sm:text-xl font-bold">Monthly Budget</h3>
                                <p class="text-white/80 text-sm">{{ now()->format('F Y') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                @if($budgetUser->canEditMonthlyLimit())
                                    <button onclick="document.getElementById('budget-edit-panel').classList.toggle('hidden')"
                                            class="flex items-center gap-1.5 px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition-colors">
                                        ✏️ Edit Limit
                                    </button>
                                @else
                                    <span class="flex items-center gap-1.5 px-3 py-1.5 bg-white/10 rounded-lg text-sm text-white/60 cursor-not-allowed"
                                          title="Next change allowed from {{ $budgetUser->nextLimitEditAllowedAt() }}">
                                        🔒 Locked
                                    </span>
                                @endif
                                <span class="text-4xl">💰</span>
                            </div>
                        </div>

                        {{-- Inline edit form (hidden by default) --}}
                        @if($budgetUser->canEditMonthlyLimit())
                            <div id="budget-edit-panel" class="hidden mt-4 pt-4 border-t border-white/30">
                                <form action="{{ route('cafeteria.budget.update') }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1">
                                            <label class="block text-white/80 text-xs mb-1">New Monthly Limit (KES)</label>
                                            <input type="number"
                                                   name="cafeteria_monthly_limit"
                                                   value="{{ old('cafeteria_monthly_limit', $limit) }}"
                                                   min="1"
                                                   class="w-full px-3 py-2 rounded-lg bg-white/20 border border-white/40 text-white placeholder-white/60 text-sm focus:outline-none focus:ring-2 focus:ring-white/50"
                                                   placeholder="e.g. 15000">
                                        </div>
                                        <div class="flex gap-2 mt-5">
                                            <button type="submit"
                                                    class="px-4 py-2 bg-white text-indigo-700 font-semibold rounded-lg text-sm hover:bg-indigo-50 transition-colors">
                                                Save
                                            </button>
                                            <button type="button"
                                                    onclick="document.getElementById('budget-edit-panel').classList.add('hidden')"
                                                    class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm transition-colors">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-white/60 text-xs mt-2">
                                        ⚠️ You can only change this once per calendar month.
                                    </p>
                                </form>
                            </div>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="p-6 sm:p-8 space-y-6">

                        {{-- Alert if over budget --}}
                        @if($isExceeded)
                            <div class="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-2xl">⚠️</span>
                                    <div>
                                        <h4 class="font-bold text-red-800 dark:text-red-200 mb-1">Budget Exceeded!</h4>
                                        <p class="text-sm text-red-700 dark:text-red-300">
                                            You have spent KES {{ number_format( $monthRecord->getAmountOverBudget(), 0) }} more than your monthly limit of KES {{ number_format($effectiveLimit, 0) }}.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @elseif($isCritical)
                            <div class="p-4 bg-orange-50 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-2xl">⚡</span>
                                    <div>
                                        <h4 class="font-bold text-orange-800 dark:text-orange-200 mb-1">Budget Critical!</h4>
                                        <p class="text-sm text-orange-700 dark:text-orange-300">
                                            You've used {{ number_format($percentage, 1) }}% of your monthly budget. Only KES {{ number_format($remaining, 0) }} remaining.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @elseif($isWarning)
                            <div class="p-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <span class="text-2xl">⚠️</span>
                                    <div>
                                        <h4 class="font-bold text-yellow-800 dark:text-yellow-200 mb-1">Budget Warning</h4>
                                        <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                            You've used {{ number_format($percentage, 1) }}% of your monthly budget. KES {{ number_format($remaining, 0) }} remaining.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Budget Stats Grid --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1 font-medium">Total Spent</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    KES {{ number_format($spent, 0) }}
                                </p>
                            </div>

                            <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1 font-medium">Effective Limit</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    KES {{ number_format($effectiveLimit, 0) }}
                                </p>
                                {{-- Show carryover detail when it is non-zero --}}
                                @if($carryover != 0)
                                    <p class="text-xs mt-1 {{ $carryover > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                                        Base KES {{ number_format($limit, 0) }}
                                        {{ $carryover > 0 ? '+' : '' }}{{ number_format($carryover, 0) }} carried over
                                    </p>
                                @endif
                            </div>

                            <div class="p-4 bg-{{ $color }}-50 dark:bg-{{ $color }}-900/30 rounded-lg border border-{{ $color }}-200 dark:border-{{ $color }}-800">
                                <p class="text-xs text-{{ $color }}-700 dark:text-{{ $color }}-300 mb-1 font-medium">Remaining</p>
                                <p class="text-2xl font-bold text-{{ $color }}-900 dark:text-{{ $color }}-100">
                                    @if($isExceeded)
                                        -KES {{ number_format($monthRecord->getAmountOverBudget(), 0) }}
                                    @else
                                        KES {{ number_format($remaining, 0) }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Budget Usage</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">
                                    {{ number_format($percentage, 1) }}%
                                </span>
                            </div>
                            <div class="w-full h-4 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-{{ $color }}-400 to-{{ $color }}-600 transition-all duration-500 rounded-full"
                                     style="width: {{ min(100, $percentage) }}%"></div>
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $budgetUser->getBudgetStatus() }}
                                </p>
                            </div>
                        </div>

                        {{-- Budget Info --}}
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400 mb-1">Daily Avg <span class="text-xs font-normal">(working days)</span></p>
                                    <p class="font-bold text-gray-900 dark:text-white">
                                        KES {{ number_format($spent / $budgetUser->workingDaysElapsedThisMonth(), 0) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400 mb-1">Daily Budget Left</p>
                                    <p class="font-bold text-gray-900 dark:text-white">
                                        @php
                                            $daysLeft = $budgetUser->workingDaysRemainingThisMonth();
                                        @endphp
                                        {{ $daysLeft > 0
                                            ? 'KES ' . number_format($remaining / $daysLeft, 0) . ' / day'
                                            : '—' }}
                                    </p>
                                </div>
                            </div>
                            @if($carryover != 0)
                                <p class="mt-3 text-xs {{ $carryover > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }} flex items-center gap-1">
                                    {{ $carryover > 0 ? '✅' : '⚠️' }}
                                    {{ $carryover > 0
                                        ? 'KES ' . number_format($carryover, 0) . ' surplus carried over from last month.'
                                        : 'KES ' . number_format(abs($carryover), 0) . ' deficit carried over from last month.' }}
                                </p>
                            @endif
                            @if(!$budgetUser->canEditMonthlyLimit())
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    🔒 Budget limit locked until {{ $budgetUser->nextLimitEditAllowedAt() }}.
                                    You can update it again at the start of next month.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Two Column Layout --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">

                {{-- Top Items This Month --}}
                <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-5 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white">Top Items</h3>
                    </div>
                    @if($topItems->count() > 0)
                        <div class="space-y-3">
                            @foreach($topItems as $item)
                                @php
                                    $colors = ['red', 'orange', 'amber', 'yellow', 'lime'];
                                    $color = $colors[$loop->index % 5];
                                    $percentage = $monthlyTotal > 0 ? ($item->total_spent / $monthlyTotal) * 100 : 0;
                                @endphp
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-{{ $color }}-100 dark:bg-{{ $color }}-900 flex items-center justify-center flex-shrink-0">
                                        <span class="text-lg">🍴</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ $item->name }}</p>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mt-1">
                                            <div class="bg-{{ $color }}-500 h-2 rounded-full transition-all duration-500"
                                                 style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="font-bold text-gray-900 dark:text-white text-sm">{{ $item->total_qty }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($item->total_spent, 0) }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <span class="text-4xl mb-2">📊</span>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">No meals logged this month</p>
                        </div>
                    @endif
                </div>

                {{-- Daily Spending Chart --}}
                <div class="stat-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-5 sm:p-6 col-span-1 lg:col-span-2">
                    <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-white mb-4">Daily Spending</h3>
                    @if($dailySpend->count() > 0)
                        <div class="space-y-2">
                            @foreach($dailySpend->take(10) as $day)
                                <div class="flex items-center gap-3">
                                    <span class="w-8 text-right text-sm font-medium text-gray-600 dark:text-gray-400">
                                        {{ $day['date'] }}
                                    </span>
                                    <div class="flex-1 h-8 bg-gray-200 dark:bg-gray-700 rounded-lg overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-orange-400 to-red-500 transition-all duration-300"
                                             style="width: {{ ($day['total'] / $dailySpend->max('total')) * 100 }}%"></div>
                                    </div>
                                    <span class="w-16 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($day['total'], 0) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <span class="text-4xl mb-2">📈</span>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">No spending data available</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recent Orders --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg sm:text-xl font-bold text-gray-800 dark:text-white">Recent Meals</h3>
                        @if($orders->total() > 10)
                            <a href="#" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                                View All →
                            </a>
                        @endif
                    </div>
                </div>

                @if($orders->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Date</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Meal Time</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Items</th>
                                <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Amount</th>
                                <th class="px-4 sm:px-6 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($orders as $order)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-4 sm:px-6 py-4 text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                        {{ $order->order_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                            <span class="inline-block px-2 py-1 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200">
                                                {{ ucfirst($order->meal_time) }}
                                            </span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        <div class="space-y-1">
                                            @foreach($order->items as $item)
                                                <div class="text-xs">{{ $item->menuItem->name }} x{{ $item->quantity }}</div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-right text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                        KES {{ number_format($order->total_amount, 0) }}
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-center whitespace-nowrap">
                                        <a href="{{ route('cafeteria.show', $order) }}"
                                           class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 text-sm">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $orders->links() }}
                    </div>
                @else
                    <div class="p-12 text-center">
                        <span class="text-5xl mb-4 block">🍽️</span>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">No meals logged yet</p>
                        <a href="{{ route('cafeteria.create') }}"
                           class="inline-block px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors">
                            Log Your First Meal
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-floating-action-button />

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
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
        });
    </script>

</x-app-layout>
