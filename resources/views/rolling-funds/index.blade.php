<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200">
                    🎲 Rolling Funds Tracker
                </h2>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    Track your investment sessions and returns
                </p>
            </div>
            <a href="{{ route('rolling-funds.create') }}"
               class="w-full sm:w-auto bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium text-center">
                + Record Session
            </a>
        </div>
    </x-slot>

    <div class="py-4 sm:py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">

            @if(session('success'))
                <div class="bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 p-3 rounded-lg mb-4 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-3 rounded-lg mb-4 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total Invested</p>
                    <p class="text-lg sm:text-2xl font-bold text-gray-700 dark:text-gray-300">
                        KES {{ number_format($stats['total_staked'], 0) }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total Returns</p>
                    <p class="text-lg sm:text-2xl font-bold text-blue-600 dark:text-blue-400">
                        KES {{ number_format($stats['total_winnings'], 0) }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Net P/L</p>
                    <p class="text-lg sm:text-2xl font-bold {{ $stats['net_profit_loss'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $stats['net_profit_loss'] >= 0 ? '+' : '' }}KES {{ number_format($stats['net_profit_loss'], 0) }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Success Rate</p>
                    <p class="text-lg sm:text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                        {{ $stats['win_rate'] }}%
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stats['wins'] }}W / {{ $stats['losses'] }}L</p>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-6">
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 sm:p-4">
                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mb-1">Best Session</p>
                    <p class="text-xl sm:text-2xl font-bold text-green-600 dark:text-green-400">
                        +KES {{ number_format($stats['biggest_win'], 0) }}
                    </p>
                </div>

                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 sm:p-4">
                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mb-1">Worst Session</p>
                    <p class="text-xl sm:text-2xl font-bold text-red-600 dark:text-red-400">
                        KES {{ number_format($stats['biggest_loss'], 0) }}
                    </p>
                </div>
            </div>

            <!-- Pending Sessions Alert (if any) -->
            @if($stats['pending_count'] > 0)
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 sm:p-4 mb-6">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">⏳</span>
                        <div>
                            <p class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
                                {{ $stats['pending_count'] }} Pending Session{{ $stats['pending_count'] > 1 ? 's' : '' }}
                            </p>
                            <p class="text-xs text-yellow-700 dark:text-yellow-400">
                                Total pending amount: KES {{ number_format($stats['pending_amount'], 0) }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4 mb-4">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('rolling-funds.index') }}"
                       class="px-3 py-1.5 rounded text-xs sm:text-sm font-medium transition {{ $filter === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        All Sessions
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'pending']) }}"
                       class="px-3 py-1.5 rounded text-xs sm:text-sm font-medium transition {{ $filter === 'pending' ? 'bg-yellow-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        Pending
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'wins']) }}"
                       class="px-3 py-1.5 rounded text-xs sm:text-sm font-medium transition {{ $filter === 'wins' ? 'bg-green-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        Positive
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'losses']) }}"
                       class="px-3 py-1.5 rounded text-xs sm:text-sm font-medium transition {{ $filter === 'losses' ? 'bg-red-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        Negative
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'this_month']) }}"
                       class="px-3 py-1.5 rounded text-xs sm:text-sm font-medium transition {{ $filter === 'this_month' ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        This Month
                    </a>
                    <a href="{{ route('rolling-funds.index', ['filter' => 'this_year']) }}"
                       class="px-3 py-1.5 rounded text-xs sm:text-sm font-medium transition {{ $filter === 'this_year' ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                        This Year
                    </a>
                </div>
            </div>

            <!-- Sessions List -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-800 dark:text-gray-200">
                        Session History
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $wagers->total() }} session{{ $wagers->total() !== 1 ? 's' : '' }} recorded
                    </p>
                </div>

                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($wagers as $wager)
                        <div class="p-3 sm:p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span class="text-lg">
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
                                           class="font-medium text-sm sm:text-base text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                            {{ $wager->platform ?? 'Rolling Funds' }}
                                        </a>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $wager->date->format('M d, Y') }}
                                        </span>
                                        @if($wager->status === 'pending')
                                            <span class="text-xs bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 px-2 py-0.5 rounded font-medium">
                                                Pending
                                            </span>
                                        @endif
                                        @if($wager->account)
                                            <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded">
                                                {{ $wager->account->name }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                                        Invested: KES {{ number_format($wager->stake_amount, 0) }} •
                                        Returns:
                                        @if($wager->status === 'completed')
                                            KES {{ number_format($wager->winnings, 0) }}
                                        @else
                                            <span class="text-yellow-600 dark:text-yellow-400 font-medium">Pending</span>
                                        @endif
                                    </p>
                                    @if($wager->game_type)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Type: {{ $wager->game_type }}</p>
                                    @endif
                                </div>

                                <div class="text-right flex-shrink-0">
                                    @if($wager->status === 'completed')
                                        <p class="text-base sm:text-lg font-bold {{ $wager->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $wager->net_result >= 0 ? '+' : '' }}{{ number_format($wager->net_result, 0) }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $wager->profit_percentage >= 0 ? '+' : '' }}{{ $wager->profit_percentage }}%
                                        </p>
                                    @else
                                        <p class="text-base sm:text-lg font-bold text-yellow-600 dark:text-yellow-400">
                                            Pending
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Awaiting outcome
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-2 flex gap-3">
                                <a href="{{ route('rolling-funds.show', $wager) }}"
                                   class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                    View Details
                                </a>
                                @if($wager->status === 'pending')
                                    <a href="{{ route('rolling-funds.show', $wager) }}"
                                       class="text-xs text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 font-medium">
                                        Record Outcome
                                    </a>
                                @endif
                                <form action="{{ route('rolling-funds.destroy', $wager) }}" method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this session? This will also remove all related transactions.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 sm:p-12 text-center">
                            <div class="text-5xl mb-4">📊</div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
                                No rolling funds sessions recorded yet.
                            </p>
                            <a href="{{ route('rolling-funds.create') }}"
                               class="inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium">
                                Record Your First Session
                            </a>
                        </div>
                    @endforelse
                </div>

                @if($wagers->hasPages())
                    <div class="p-3 sm:p-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $wagers->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
