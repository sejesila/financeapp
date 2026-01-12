<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 flex items-center gap-2">
                    <span class="text-2xl">🎲</span>
                    Rolling Funds Tracker
                </h2>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Track your investment sessions and returns
                </p>
            </div>
            <a href="{{ route('rolling-funds.create') }}"
               class="w-full sm:w-auto bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-5 py-2.5 rounded-xl font-semibold text-sm text-center shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02] flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Record Session
            </a>
        </div>
    </x-slot>

    <div class="py-4 sm:py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">

            @if(session('success'))
                <div class="bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 p-4 rounded-xl mb-6 flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-4 rounded-xl mb-6 flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl shadow-md p-4 sm:p-5 hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-2xl">💼</span>
                        <p class="text-xs font-semibold text-gray-600 dark:text-gray-400">Total Invested</p>
                    </div>
                    <p class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-gray-200">
                        {{ number_format($stats['total_staked'], 0) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">KES</p>
                </div>

                <div class="bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl shadow-md p-4 sm:p-5 hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-2xl">💰</span>
                        <p class="text-xs font-semibold text-gray-600 dark:text-gray-400">Total Returns</p>
                    </div>
                    <p class="text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ number_format($stats['total_winnings'], 0) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">KES</p>
                </div>

                <div class="bg-gradient-to-br {{ $stats['net_profit_loss'] >= 0 ? 'from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-green-200 dark:border-green-800' : 'from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border-red-200 dark:border-red-800' }} border rounded-2xl shadow-md p-4 sm:p-5 hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-2xl">{{ $stats['net_profit_loss'] >= 0 ? '📈' : '📉' }}</span>
                        <p class="text-xs font-semibold text-gray-600 dark:text-gray-400">Net P/L</p>
                    </div>
                    <p class="text-xl sm:text-2xl font-bold {{ $stats['net_profit_loss'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $stats['net_profit_loss'] >= 0 ? '+' : '' }}{{ number_format($stats['net_profit_loss'], 0) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">KES</p>
                </div>

                <div class="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 border border-purple-200 dark:border-purple-800 rounded-2xl shadow-md p-4 sm:p-5 hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-2xl">🎯</span>
                        <p class="text-xs font-semibold text-gray-600 dark:text-gray-400">Success Rate</p>
                    </div>
                    <p class="text-xl sm:text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ $stats['win_rate'] }}%
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $stats['wins'] }}W / {{ $stats['losses'] }}L</p>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-6">
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border border-green-200 dark:border-green-800 rounded-2xl p-4 sm:p-5 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-2xl">🏆</span>
                                <p class="text-sm font-semibold text-gray-600 dark:text-gray-400">Best Session</p>
                            </div>
                            <p class="text-2xl sm:text-3xl font-bold text-green-600 dark:text-green-400">
                                +{{ number_format($stats['biggest_win'], 0) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">KES</p>
                        </div>
                        <div class="text-5xl opacity-20">📈</div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border border-red-200 dark:border-red-800 rounded-2xl p-4 sm:p-5 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-2xl">⚠️</span>
                                <p class="text-sm font-semibold text-gray-600 dark:text-gray-400">Worst Session</p>
                            </div>
                            <p class="text-2xl sm:text-3xl font-bold text-red-600 dark:text-red-400">
                                {{ number_format($stats['biggest_loss'], 0) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">KES</p>
                        </div>
                        <div class="text-5xl opacity-20">📉</div>
                    </div>
                </div>
            </div>

            <!-- Pending Sessions Alert -->
            @if($stats['pending_count'] > 0)
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 border-2 border-yellow-200 dark:border-yellow-800 rounded-2xl p-5 mb-6 shadow-md">
                    <div class="flex items-start gap-3">
                        <span class="text-3xl">⏳</span>
                        <div class="flex-1">
                            <p class="text-base font-bold text-yellow-800 dark:text-yellow-300 mb-1">
                                {{ $stats['pending_count'] }} Pending Session{{ $stats['pending_count'] > 1 ? 's' : '' }}
                            </p>
                            <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                Total pending amount: <span class="font-semibold">KES {{ number_format($stats['pending_amount'], 0) }}</span>
                            </p>
                        </div>
                        <a href="{{ route('rolling-funds.index', ['filter' => 'pending']) }}"
                           class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-semibold transition-colors">
                            View
                        </a>
                    </div>
                </div>
            @endif

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md p-4 mb-6 border border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Filter Sessions</h3>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('rolling-funds.index') }}"
                       class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $filter === 'all' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        All Sessions
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'pending']) }}"
                       class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $filter === 'pending' ? 'bg-yellow-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        ⏳ Pending
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'wins']) }}"
                       class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $filter === 'wins' ? 'bg-green-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        📈 Positive
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'losses']) }}"
                       class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $filter === 'losses' ? 'bg-red-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        📉 Negative
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'this_month']) }}"
                       class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $filter === 'this_month' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        📅 This Month
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'this_year']) }}"
                       class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $filter === 'this_year' ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        📆 This Year
                    </a>
                </div>
            </div>

            <!-- Sessions List -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md overflow-hidden border border-gray-100 dark:border-gray-700">
                <div class="p-4 sm:p-5 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-gray-50 to-white dark:from-gray-700/50 dark:to-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                <span class="text-xl">📊</span>
                                Session History
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $wagers->total() }} session{{ $wagers->total() !== 1 ? 's' : '' }} recorded
                            </p>
                        </div>
                        @if($wagers->total() > 0)
                            <div class="text-right">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Page {{ $wagers->currentPage() }} of {{ $wagers->lastPage() }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($wagers as $wager)
                        <div class="p-4 sm:p-5 hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent dark:hover:from-gray-700/30 dark:hover:to-transparent transition-all">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                                        <span class="text-2xl">
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
                                        <a href="{{ route('rolling-funds.show', $wager) }}"
                                           class="font-semibold text-sm sm:text-base text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                            {{ $wager->platform ?? 'Rolling Funds' }}
                                        </a>
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-md">
                                            {{ $wager->date->format('M d, Y') }}
                                        </span>
                                        @if($wager->status === 'pending')
                                            <span class="text-xs bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 px-2.5 py-1 rounded-full font-semibold">
                                                Pending
                                            </span>
                                        @endif
                                        @if($wager->account)
                                            <span class="text-xs bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 px-2.5 py-1 rounded-full font-medium">
                                                {{ $wager->account->name }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                                            <span class="font-medium">Invested:</span> KES {{ number_format($wager->stake_amount, 0) }}
                                            <span class="mx-2">•</span>
                                            <span class="font-medium">Returns:</span>
                                            @if($wager->status === 'completed')
                                                KES {{ number_format($wager->winnings, 0) }}
                                            @else
                                                <span class="text-yellow-600 dark:text-yellow-400 font-semibold">Pending</span>
                                            @endif
                                        </p>
                                        @if($wager->game_type)
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <span class="font-medium">Type:</span> {{ $wager->game_type }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="text-right flex-shrink-0">
                                    @if($wager->status === 'completed')
                                        <p class="text-lg sm:text-xl font-bold {{ $wager->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $wager->net_result >= 0 ? '+' : '' }}{{ number_format($wager->net_result, 0) }}
                                        </p>
                                        <p class="text-xs font-semibold {{ $wager->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} mt-1">
                                            {{ $wager->profit_percentage >= 0 ? '+' : '' }}{{ $wager->profit_percentage }}%
                                        </p>
                                    @else
                                        <p class="text-lg sm:text-xl font-bold text-yellow-600 dark:text-yellow-400">
                                            Pending
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Awaiting outcome
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 flex gap-3">
                                <a href="{{ route('rolling-funds.show', $wager) }}"
                                   class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors">
                                    View Details →
                                </a>
                                @if($wager->status === 'pending')
                                    <a href="{{ route('rolling-funds.show', $wager) }}"
                                       class="text-xs font-semibold text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 transition-colors">
                                        Record Outcome ✓
                                    </a>
                                @endif
                                <form action="{{ route('rolling-funds.destroy', $wager) }}" method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this session? This will also remove all related transactions.')"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors">
                                        Delete 🗑️
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 sm:p-16 text-center">
                            <div class="text-6xl mb-4">📊</div>
                            <p class="text-gray-500 dark:text-gray-400 text-base font-medium mb-4">
                                No rolling funds sessions recorded yet.
                            </p>
                            <a href="{{ route('rolling-funds.create') }}"
                               class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02]">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Record Your First Session
                            </a>
                        </div>
                    @endforelse
                </div>

                @if($wagers->hasPages())
                    <div class="p-4 sm:p-5 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                        {{ $wagers->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
