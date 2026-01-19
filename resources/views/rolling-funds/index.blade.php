<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
                        <span class="text-2xl">🎲</span>
                    </div>
                    <div>
                        <h2 class="font-bold text-xl sm:text-2xl text-gray-900 dark:text-gray-100">
                            Rolling Funds Tracker
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Monitor your investment performance
                        </p>
                    </div>
                </div>
            </div>
            <a href="{{ route('rolling-funds.create') }}"
               class="group w-full sm:w-auto bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-xl font-semibold text-sm text-center shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center justify-center gap-2">
                <svg class="w-5 h-5 transition-transform group-hover:rotate-90 duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Session
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Alert Messages -->
            @if(session('success'))
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-l-4 border-green-500 dark:border-green-400 p-4 rounded-xl shadow-md animate-slide-in">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-green-500 dark:bg-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border-l-4 border-red-500 dark:border-red-400 p-4 rounded-xl shadow-md animate-slide-in">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-red-500 dark:bg-red-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- Key Metrics - Enhanced Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                <!-- Total Invested Card -->
                <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-indigo-500/10 dark:from-blue-500/5 dark:to-indigo-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                <span class="text-2xl">💼</span>
                            </div>
                            <div class="text-xs font-semibold text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/30 px-2 py-1 rounded-lg">
                                TOTAL
                            </div>
                        </div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Total Invested</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100">
                            {{ number_format($stats['total_staked'], 0) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">KES</p>
                    </div>
                </div>

                <!-- Total Returns Card -->
                <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/10 to-green-500/10 dark:from-emerald-500/5 dark:to-green-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                <span class="text-2xl">💰</span>
                            </div>
                            <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/30 px-2 py-1 rounded-lg">
                                RETURNS
                            </div>
                        </div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Total Returns</p>
                        <p class="text-2xl sm:text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                            {{ number_format($stats['total_winnings'], 0) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">KES</p>
                    </div>
                </div>

                <!-- Net P/L Card -->
                <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="absolute inset-0 bg-gradient-to-br {{ $stats['net_profit_loss'] >= 0 ? 'from-green-500/10 to-emerald-500/10 dark:from-green-500/5 dark:to-emerald-500/5' : 'from-red-500/10 to-rose-500/10 dark:from-red-500/5 dark:to-rose-500/5' }} opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br {{ $stats['net_profit_loss'] >= 0 ? 'from-green-500 to-emerald-600' : 'from-red-500 to-rose-600' }} rounded-xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                <span class="text-2xl">{{ $stats['net_profit_loss'] >= 0 ? '📈' : '📉' }}</span>
                            </div>
                            <div class="text-xs font-semibold {{ $stats['net_profit_loss'] >= 0 ? 'text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-900/30' : 'text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/30' }} px-2 py-1 rounded-lg">
                                {{ $stats['net_profit_loss'] >= 0 ? 'PROFIT' : 'LOSS' }}
                            </div>
                        </div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Net P/L</p>
                        <p class="text-2xl sm:text-3xl font-bold {{ $stats['net_profit_loss'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $stats['net_profit_loss'] >= 0 ? '+' : '' }}{{ number_format($stats['net_profit_loss'], 0) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">KES</p>
                    </div>
                </div>

                <!-- Success Rate Card -->
                <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/10 to-indigo-500/10 dark:from-purple-500/5 dark:to-indigo-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-300">
                                <span class="text-2xl">🎯</span>
                            </div>
                            <div class="text-xs font-semibold text-purple-600 dark:text-purple-400 bg-purple-100 dark:bg-purple-900/30 px-2 py-1 rounded-lg">
                                RATE
                            </div>
                        </div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Success Rate</p>
                        <p class="text-2xl sm:text-3xl font-bold text-purple-600 dark:text-purple-400">
                            {{ $stats['win_rate'] }}%
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">{{ $stats['wins'] }}W / {{ $stats['losses'] }}L</p>
                    </div>
                </div>
            </div>

            <!-- Performance Highlights -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                <!-- Best Session -->
                <div class="relative bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-lg overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-black/10 rounded-full -ml-12 -mb-12"></div>
                    <div class="relative p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-3xl">🏆</span>
                                    <p class="text-sm font-bold text-white/90 uppercase tracking-wide">Best Session</p>
                                </div>
                                <p class="text-4xl font-bold text-white mb-1">
                                    +{{ number_format($stats['biggest_win'], 0) }}
                                </p>
                                <p class="text-sm text-white/80 font-medium">KES</p>
                            </div>
                            <div class="text-6xl opacity-20">📈</div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-white/20">
                            <p class="text-xs text-white/70 uppercase tracking-wide font-semibold">Maximum Gain</p>
                        </div>
                    </div>
                </div>

                <!-- Worst Session -->
                <div class="relative bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl shadow-lg overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-black/10 rounded-full -ml-12 -mb-12"></div>
                    <div class="relative p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-3xl">⚠️</span>
                                    <p class="text-sm font-bold text-white/90 uppercase tracking-wide">Worst Session</p>
                                </div>
                                <p class="text-4xl font-bold text-white mb-1">
                                    {{ number_format($stats['biggest_loss'], 0) }}
                                </p>
                                <p class="text-sm text-white/80 font-medium">KES</p>
                            </div>
                            <div class="text-6xl opacity-20">📉</div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-white/20">
                            <p class="text-xs text-white/70 uppercase tracking-wide font-semibold">Maximum Loss</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Sessions Alert -->
            @if($stats['pending_count'] > 0)
                <div class="relative bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-2xl shadow-md overflow-hidden border-2 border-amber-300 dark:border-amber-700">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-amber-400/10 rounded-full -mr-32 -mt-32"></div>
                    <div class="relative p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-start gap-3 sm:gap-4 flex-1 w-full">
                                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0">
                                    <span class="text-2xl sm:text-3xl">⏳</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-base sm:text-lg font-bold text-amber-900 dark:text-amber-300 mb-1">
                                        {{ $stats['pending_count'] }} Pending Session{{ $stats['pending_count'] > 1 ? 's' : '' }}
                                    </p>
                                    <p class="text-sm text-amber-700 dark:text-amber-400">
                                        Total pending: <span class="font-bold">KES {{ number_format($stats['pending_amount'], 0) }}</span>
                                    </p>
                                    <p class="text-xs text-amber-600 dark:text-amber-500 mt-2">
                                        Update outcomes to track your complete performance
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'pending']) }}"
                               class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-xl text-sm font-bold shadow-lg hover:shadow-xl transition-all duration-300 text-center flex-shrink-0">
                                View All
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filters Section -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-100 dark:border-gray-700">
                <div class="p-4 sm:p-5 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-gray-100">Filter Sessions</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Refine your view</p>
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5 space-y-4 sm:space-y-5">
                    <!-- Status Filters -->
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Status</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('rolling-funds.index') }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 {{ $filter === 'all' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                All Sessions
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'pending']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'pending' ? 'bg-amber-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>⏳</span> Pending
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'completed']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'completed' ? 'bg-blue-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>✓</span> Completed
                            </a>
                        </div>
                    </div>

                    <!-- Time-based Filters -->
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Time Period</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('rolling-funds.index', ['filter' => 'last_7_days']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'last_7_days' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📅</span> <span class="hidden xs:inline">Last</span> 7d
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'last_30_days']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'last_30_days' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📅</span> <span class="hidden xs:inline">Last</span> 30d
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'last_90_days']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'last_90_days' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📅</span> <span class="hidden xs:inline">Last</span> 90d
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'this_month']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'this_month' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📅</span> This Month
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'this_year']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'this_year' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📆</span> This Year
                            </a>
                        </div>
                    </div>

                    <!-- Performance Filters -->
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Performance</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('rolling-funds.index', ['filter' => 'wins']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'wins' ? 'bg-green-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📈</span> Positive
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'losses']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'losses' ? 'bg-red-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>📉</span> Negative
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'break_even']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'break_even' ? 'bg-gray-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>➖</span> Break Even
                            </a>
                            <a href="{{ route('rolling-funds.index',['filter' => 'high_wins']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'high_wins' ? 'bg-emerald-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>🚀</span> High Wins
                            </a>
                            <a href="{{ route('rolling-funds.index', ['filter' => 'significant_losses']) }}"
                               class="px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl text-xs sm:text-sm font-semibold transition-all duration-300 flex items-center gap-1.5 {{ $filter === 'significant_losses' ? 'bg-rose-500 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                <span>⚠️</span> Big Losses
                            </a>
                        </div>
                    </div>

                    <!-- Custom Date Range -->
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Custom Range</p>
                        <form method="GET" action="{{ route('rolling-funds.index') }}" class="flex flex-col sm:flex-row flex-wrap gap-2 items-stretch sm:items-end">
                            <input type="hidden" name="filter" value="custom_range">
                            <div class="flex-1 min-w-[140px]">
                                <label class="text-xs text-gray-600 dark:text-gray-400 font-medium block mb-1">From</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}"
                                       class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            <div class="flex-1 min-w-[140px]">
                                <label class="text-xs text-gray-600 dark:text-gray-400 font-medium block mb-1">To</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}"
                                       class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                        class="flex-1 sm:flex-none px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg text-sm font-semibold shadow-md hover:shadow-lg transition-all duration-300">
                                    Apply
                                </button>
                                @if($filter === 'custom_range')
                                    <a href="{{ route('rolling-funds.index') }}"
                                       class="flex-1 sm:flex-none px-4 py-2 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-semibold transition-all duration-300 text-center">
                                        Clear
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sessions List -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md overflow-hidden border border-gray-100 dark:border-gray-700">
                <div class="p-4 sm:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800 dark:to-gray-800/50">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0">
                                <span class="text-xl sm:text-2xl">📊</span>
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Session History
                                </h3>
                                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                    {{ $wagers->total() }} session{{ $wagers->total() !== 1 ? 's' : '' }} recorded
                                </p>
                            </div>
                        </div>
                        @if($wagers->hasPages())
                            <div class="text-right">
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                    Page {{ $wagers->currentPage() }} of {{ $wagers->lastPage() }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($wagers as $wager)
                        <div class="group p-4 sm:p-6 hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent dark:hover:from-gray-700/20 dark:hover:to-transparent transition-all duration-200">
                            <div class="flex flex-col lg:flex-row items-start lg:items-start justify-between gap-4 lg:gap-6">
                                <div class="flex-1 min-w-0 w-full">
                                    <div class="flex items-start gap-3 mb-3 flex-wrap">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-md flex-shrink-0 {{ $wager->status === 'pending' ? 'bg-gradient-to-br from-amber-400 to-orange-500' : ($wager->isWin() ? 'bg-gradient-to-br from-green-500 to-emerald-600' : ($wager->outcome === 'break_even' ? 'bg-gradient-to-br from-gray-400 to-gray-500' : 'bg-gradient-to-br from-red-500 to-rose-600')) }}">
                                            <span class="text-xl">
                                                @if($wager->status === 'pending')
                                                    ⏳
                                                @elseif($wager->isWin())
                                                    📈
                                                @elseif($wager->outcome === 'break_even')
                                                    ➖
                                                @else
                                                    📉
                                                @endif
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ route('rolling-funds.show', $wager) }}"
                                                   class="font-bold text-sm sm:text-base text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors break-words">
                                                    {{ $wager->platform ?? 'Rolling Funds' }}
                                                </a>
                                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2.5 py-1 rounded-lg whitespace-nowrap">
                                                    {{ $wager->date->format('M d, Y') }}
                                                </span>
                                                @if($wager->status === 'pending')
                                                    <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-3 py-1 rounded-full font-bold">
                                                        Pending
                                                    </span>
                                                @endif
                                                @if($wager->account)
                                                    <span class="text-xs bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 px-3 py-1 rounded-full font-semibold">
                                                        {{ $wager->account->name }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-2 ml-0 sm:ml-13">
                                        <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-xs sm:text-sm">
                                            <div class="flex items-center gap-2">
                                                <span class="text-gray-500 dark:text-gray-400 font-medium">Invested:</span>
                                                <span class="font-bold text-gray-900 dark:text-gray-100">KES {{ number_format($wager->stake_amount, 0) }}</span>
                                            </div>
                                            <span class="text-gray-400 hidden sm:inline">•</span>
                                            <div class="flex items-center gap-2">
                                                <span class="text-gray-500 dark:text-gray-400 font-medium">Returns:</span>
                                                @if($wager->status === 'completed')
                                                    <span class="font-bold text-gray-900 dark:text-gray-100">KES {{ number_format($wager->winnings, 0) }}</span>
                                                @else
                                                    <span class="text-amber-600 dark:text-amber-400 font-bold">Pending</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if($wager->game_type)
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <span class="font-semibold">Type:</span> {{ $wager->game_type }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="w-full lg:w-auto lg:text-right flex-shrink-0">
                                    @if($wager->status === 'completed')
                                        <div class="bg-gradient-to-br {{ $wager->net_result >= 0 ? 'from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20' : 'from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20' }} rounded-xl p-4 shadow-sm">
                                            <p class="text-xl sm:text-2xl font-bold {{ $wager->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $wager->net_result >= 0 ? '+' : '' }}{{ number_format($wager->net_result, 0) }}
                                            </p>
                                            <p class="text-xs font-bold {{ $wager->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} mt-1">
                                                {{ $wager->profit_percentage >= 0 ? '+' : '' }}{{ $wager->profit_percentage }}%
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">KES</p>
                                        </div>
                                    @else
                                        <div class="bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-xl p-4 shadow-sm">
                                            <p class="text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400">
                                                Pending
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                Awaiting outcome
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-3 ml-0 sm:ml-13">
                                <a href="{{ route('rolling-funds.show', $wager) }}"
                                   class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors group">
                                    View Details
                                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                                @if($wager->status === 'pending')
                                    <a href="{{ route('rolling-funds.show', $wager) }}"
                                       class="inline-flex items-center gap-1.5 text-xs font-bold text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Record Outcome
                                    </a>
                                @endif
                                <form action="{{ route('rolling-funds.destroy', $wager) }}" method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this session? This will also remove all related transactions.')"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-bold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 sm:p-16 text-center">
                            <div class="mb-6">
                                <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-3xl flex items-center justify-center mx-auto shadow-lg">
                                    <span class="text-4xl sm:text-5xl">📊</span>
                                </div>
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-gray-800 dark:text-gray-200 mb-2">
                                No Sessions Yet
                            </h3>
                            <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
                                Start tracking your rolling funds investments to monitor your performance and returns.
                            </p>
                            <a href="{{ route('rolling-funds.create') }}"
                               class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 sm:px-8 py-3 sm:py-3.5 rounded-xl font-bold text-sm shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Record Your First Session
                            </a>
                        </div>
                    @endforelse
                </div>

                @if($wagers->hasPages())
                    <div class="p-4 sm:p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        {{ $wagers->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>

    <style>
        @keyframes slide-in {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .animate-slide-in {
            animation: slide-in 0.3s ease-out;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.5);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(107, 114, 128, 0.7);
        }

        /* Dark mode scrollbar */
        .dark ::-webkit-scrollbar-thumb {
            background: rgba(75, 85, 99, 0.5);
        }

        .dark ::-webkit-scrollbar-thumb:hover {
            background: rgba(55, 65, 81, 0.7);
        }

        /* Extra small breakpoint for xs: prefix */
        @media (min-width: 475px) {
            .xs\:inline {
                display: inline;
            }
        }
    </style>
</x-app-layout>
