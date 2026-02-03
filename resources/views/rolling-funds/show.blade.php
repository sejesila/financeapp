<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
                        <span class="text-2xl">🎲</span>
                    </div>
                    <div>
                        <h2 class="font-bold text-xl sm:text-2xl text-gray-900 dark:text-gray-100">
                            Session Details
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">
                            {{ $rollingFund->date->format('F d, Y') }}
                        </p>
                    </div>
                </div>
            </div>
            <a href="{{ route('rolling-funds.index') }}"
               class="group inline-flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 text-sm font-semibold transition-colors bg-gray-100 dark:bg-gray-700 px-4 py-2.5 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600">
                <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Sessions
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto space-y-6">

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

            <!-- Session Status Banner -->
            @if($rollingFund->status === 'pending')
                <div class="relative rounded-2xl p-8 bg-gradient-to-br from-amber-50 via-yellow-50 to-orange-50 dark:from-amber-900/20 dark:via-yellow-900/20 dark:to-orange-900/20 border-2 border-amber-300 dark:border-amber-700 shadow-xl overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-amber-400/10 rounded-full -mr-32 -mt-32"></div>
                    <div class="absolute bottom-0 left-0 w-48 h-48 bg-orange-400/10 rounded-full -ml-24 -mb-24"></div>
                    <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                        <div class="flex items-start gap-5">
                            <div class="w-16 h-16 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0 animate-pulse">
                                <span class="text-4xl">⏳</span>
                            </div>
                            <div>
                                <h3 class="text-3xl font-bold text-amber-900 dark:text-amber-300 mb-2">
                                    Outcome Pending
                                </h3>
                                <p class="text-sm text-amber-700 dark:text-amber-400 font-medium">
                                    Record the outcome when you know the result
                                </p>
                            </div>
                        </div>
                        <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border border-amber-200 dark:border-amber-700">
                            <p class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-2">Investment</p>
                            <p class="text-4xl font-bold text-gray-900 dark:text-gray-100">
                                {{ number_format($rollingFund->stake_amount, 0) }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-semibold mt-1">KES</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="relative rounded-2xl p-8 {{ $rollingFund->isWin() ? 'bg-gradient-to-br from-green-50 via-emerald-50 to-teal-50 dark:from-green-900/20 dark:via-emerald-900/20 dark:to-teal-900/20 border-2 border-green-300 dark:border-green-700' : ($rollingFund->outcome === 'break_even' ? 'bg-gradient-to-br from-gray-50 via-slate-50 to-zinc-50 dark:from-gray-700/50 dark:via-slate-700/50 dark:to-zinc-700/50 border-2 border-gray-300 dark:border-gray-600' : 'bg-gradient-to-br from-red-50 via-rose-50 to-pink-50 dark:from-red-900/20 dark:via-rose-900/20 dark:to-pink-900/20 border-2 border-red-300 dark:border-red-700') }} shadow-xl overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 {{ $rollingFund->isWin() ? 'bg-green-400/10' : ($rollingFund->outcome === 'break_even' ? 'bg-gray-400/10' : 'bg-red-400/10') }} rounded-full -mr-32 -mt-32"></div>
                    <div class="absolute bottom-0 left-0 w-48 h-48 {{ $rollingFund->isWin() ? 'bg-emerald-400/10' : ($rollingFund->outcome === 'break_even' ? 'bg-slate-400/10' : 'bg-rose-400/10') }} rounded-full -ml-24 -mb-24"></div>
                    <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                        <div class="flex items-start gap-5">
                            <div class="w-16 h-16 {{ $rollingFund->isWin() ? 'bg-gradient-to-br from-green-500 to-emerald-600' : ($rollingFund->outcome === 'break_even' ? 'bg-gradient-to-br from-gray-500 to-slate-600' : 'bg-gradient-to-br from-red-500 to-rose-600') }} rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0">
                                <span class="text-4xl">
                                    @if($rollingFund->isWin()) 📈
                                    @elseif($rollingFund->outcome === 'break_even') ➖
                                    @else 📉
                                    @endif
                                </span>
                            </div>
                            <div>
                                <h3 class="text-3xl font-bold {{ $rollingFund->isWin() ? 'text-green-800 dark:text-green-300' : ($rollingFund->outcome === 'break_even' ? 'text-gray-800 dark:text-gray-300' : 'text-red-800 dark:text-red-300') }} mb-2">
                                    {{ $rollingFund->isWin() ? 'Positive Session' : ($rollingFund->outcome === 'break_even' ? 'Break Even' : 'Negative Session') }}
                                </h3>
                                <p class="text-sm {{ $rollingFund->isWin() ? 'text-green-700 dark:text-green-400' : ($rollingFund->outcome === 'break_even' ? 'text-gray-700 dark:text-gray-400' : 'text-red-700 dark:text-red-400') }} font-medium">
                                    Completed on {{ $rollingFund->completed_date->format('M d, Y') }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-2xl p-6 shadow-lg border {{ $rollingFund->isWin() ? 'border-green-200 dark:border-green-700' : ($rollingFund->outcome === 'break_even' ? 'border-gray-200 dark:border-gray-600' : 'border-red-200 dark:border-red-700') }}">
                            <p class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-2">Net Result</p>
                            <p class="text-4xl font-bold {{ $rollingFund->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $rollingFund->net_result >= 0 ? '+' : '' }}{{ number_format($rollingFund->net_result, 0) }}
                            </p>
                            <p class="text-sm font-bold text-gray-600 dark:text-gray-400 mt-2">
                                {{ $rollingFund->profit_percentage >= 0 ? '+' : '' }}{{ $rollingFund->profit_percentage }}% ROI
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold mt-1">KES</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Quick Action: Record Outcome -->
            @if($rollingFund->status === 'pending')
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-md">
                            <span class="text-xl">💰</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            Record Outcome
                        </h3>
                    </div>
                    <button onclick="openModal('outcomeModal')"
                            class="group w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-4 rounded-xl font-bold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-[1.02] flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Record Win or Loss
                    </button>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Session Information -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 border border-gray-100 dark:border-gray-700 hover:shadow-xl transition-shadow">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                            <span class="text-xl">📋</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            Session Information
                        </h3>
                    </div>
                    <dl class="space-y-4">
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                            <dt class="text-sm font-semibold text-gray-600 dark:text-gray-400">Status</dt>
                            <dd>
                                <span class="px-4 py-2 text-xs font-bold rounded-full shadow-sm {{ $rollingFund->status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' }}">
                                    {{ ucfirst($rollingFund->status) }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                            <dt class="text-sm font-semibold text-gray-600 dark:text-gray-400">Date</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                                {{ $rollingFund->date->format('M d, Y') }}
                            </dd>
                        </div>
                        @if($rollingFund->completed_date)
                            <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-semibold text-gray-600 dark:text-gray-400">Completed</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                                    {{ $rollingFund->completed_date->format('M d, Y') }}
                                </dd>
                            </div>
                        @endif
                        <div class="flex items-center justify-between py-3">
                            <dt class="text-sm font-semibold text-gray-600 dark:text-gray-400">Source Account</dt>
                            <dd class="text-sm font-bold text-indigo-600 dark:text-indigo-400 text-right">
                                {{ $rollingFund->account->name }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Financial Summary -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 border border-gray-100 dark:border-gray-700 hover:shadow-xl transition-shadow">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-md">
                            <span class="text-xl">💰</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            Financial Summary
                        </h3>
                    </div>
                    <dl class="space-y-4">
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                            <dt class="text-sm font-semibold text-gray-600 dark:text-gray-400">Investment</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                KES {{ number_format($rollingFund->stake_amount, 0) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between py-3 {{ $rollingFund->status === 'completed' ? 'border-b border-gray-100 dark:border-gray-700' : '' }}">
                            <dt class="text-sm font-semibold text-gray-600 dark:text-gray-400">Returns</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                @if($rollingFund->status === 'completed')
                                    KES {{ number_format($rollingFund->winnings, 0) }}
                                @else
                                    <span class="text-amber-600 dark:text-amber-400 text-xs font-bold bg-amber-100 dark:bg-amber-900/30 px-3 py-1 rounded-full">Pending</span>
                                @endif
                            </dd>
                        </div>
                        @if($rollingFund->status === 'completed')
                            <div class="pt-4 bg-gradient-to-br {{ $rollingFund->net_result >= 0 ? 'from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border border-green-200 dark:border-green-800' : 'from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border border-red-200 dark:border-red-800' }} rounded-xl p-5 shadow-sm">
                                <dt class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-3">Net Profit/Loss</dt>
                                <dd class="text-3xl font-bold {{ $rollingFund->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $rollingFund->net_result >= 0 ? '+' : '' }}KES {{ number_format($rollingFund->net_result, 0) }}
                                </dd>
                                <dd class="text-sm font-bold text-gray-600 dark:text-gray-400 mt-2 flex items-center gap-2">
                                    <span>ROI:</span>
                                    <span class="{{ $rollingFund->profit_percentage >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $rollingFund->profit_percentage >= 0 ? '+' : '' }}{{ number_format($rollingFund->profit_percentage, 2) }}%
                                    </span>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Notes Section -->
            @if($rollingFund->notes)
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 border border-gray-100 dark:border-gray-700 hover:shadow-xl transition-shadow">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center shadow-md">
                            <span class="text-xl">📝</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            Notes
                        </h3>
                    </div>
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100/50 dark:from-gray-700/30 dark:to-gray-700/10 rounded-xl p-5 border border-gray-200 dark:border-gray-600">
                        <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed text-sm">{{ $rollingFund->notes }}</p>
                    </div>
                </div>
            @endif

            <!-- Related Transactions -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 border border-gray-100 dark:border-gray-700 hover:shadow-xl transition-shadow">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-xl flex items-center justify-center shadow-md">
                        <span class="text-xl">📊</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            Related Transactions
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            All transactions linked to this session
                        </p>
                    </div>
                </div>
                <div class="space-y-3">
                    @php
                        // ✅ FIXED: Use model relationship with proper scope handling
                        // This method is defined in the RollingFund model and handles:
                        // - Loading category and account relationships
                        // - Excluding soft-deleted transactions
                        // - Proper ordering by date and creation time
                        // - Proper query building without direct global scope issues
                        $relatedTransactions = $rollingFund->relatedTransactions()->get();
                    @endphp

                    @forelse($relatedTransactions as $transaction)
                        <div class="group flex items-center justify-between p-5 bg-gradient-to-r from-gray-50 to-transparent dark:from-gray-700/30 dark:to-transparent rounded-xl hover:shadow-md hover:from-gray-100 dark:hover:from-gray-700/50 transition-all duration-200 border border-gray-200 dark:border-gray-600">
                            <div class="flex-1 min-w-0 pr-4">
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate mb-2">
                                    {{ $transaction->description }}
                                </p>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600">
                                        {{ $transaction->category->name }}
                                    </span>
                                    @if($transaction->is_transaction_fee)
                                        <span class="text-xs font-bold bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-400 px-3 py-1.5 rounded-lg shadow-sm">
                                            💳 Transaction Fee
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-500 dark:text-gray-500 font-medium">
                                        {{ $transaction->date->format('M d, Y') }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="bg-gradient-to-br {{ $transaction->category->type === 'income' ? 'from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20' : 'from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20' }} rounded-xl px-4 py-3 shadow-sm">
                                    <p class="text-xl font-bold {{ $transaction->category->type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $transaction->category->type === 'income' ? '+' : '-' }}{{ number_format($transaction->amount, 0) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-semibold mt-1">KES</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-16">
                            <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                                <span class="text-5xl">📭</span>
                            </div>
                            <h4 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-2">No Transactions Found</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                                No transactions are linked to this session yet
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
            <!-- Actions -->
            <div class="flex gap-3 justify-end">
                <form action="{{ route('rolling-funds.destroy', $rollingFund) }}" method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this session? This will also remove all related transactions and update your account balance.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="group bg-red-600 hover:bg-red-700 text-white px-6 py-3.5 rounded-xl font-bold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center gap-2">
                        <svg class="w-5 h-5 transform group-hover:rotate-12 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete Session
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- Outcome Recording Modal -->
    @if($rollingFund->status === 'pending')
        <div id="outcomeModal" class="hidden fixed inset-0 bg-gray-900/70 backdrop-blur-md flex items-center justify-center z-50 p-4 animate-fade-in">
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl p-8 max-w-md w-full border border-gray-200 dark:border-gray-700 transform transition-all scale-95 hover:scale-100">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
                        <span class="text-3xl">💰</span>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Record Outcome</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Update this session's results</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('rolling-funds.record-outcome', $rollingFund) }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">
                            Returns Amount (KES) <span class="text-red-500">*</span>
                        </label>
                        <input type="number"
                               step="0.01"
                               name="winnings"
                               id="winnings"
                               min="0"
                               placeholder="Enter the amount you received back"
                               required
                               class="w-full border-2 border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all font-medium text-lg">
                        <div class="mt-3 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
                            <p class="text-sm font-bold text-blue-700 dark:text-blue-400 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Original investment: KES {{ number_format($rollingFund->stake_amount, 0) }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">
                            Completed Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               name="completed_date"
                               value="{{ date('Y-m-d') }}"
                               min="{{ $rollingFund->date->format('Y-m-d') }}"
                               max="{{ date('Y-m-d') }}"
                               required
                               class="w-full border-2 border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3.5 dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all font-medium">
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">
                            Additional Notes (Optional)
                        </label>
                        <textarea name="outcome_notes"
                                  rows="3"
                                  placeholder="Any notes about the outcome..."
                                  class="w-full border-2 border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all resize-none"></textarea>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="button"
                                onclick="closeModal('outcomeModal')"
                                class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-6 py-3.5 rounded-xl font-bold transition-all duration-300 transform hover:scale-105">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3.5 rounded-xl font-bold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                            Save Outcome
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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

        @keyframes fade-in {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .animate-slide-in {
            animation: slide-in 0.3s ease-out;
        }

        .animate-fade-in {
            animation: fade-in 0.2s ease-out;
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

        .dark ::-webkit-scrollbar-thumb {
            background: rgba(75, 85, 99, 0.5);
        }

        .dark ::-webkit-scrollbar-thumb:hover {
            background: rgba(55, 65, 81, 0.7);
        }
    </style>

    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            // Add focus to first input
            setTimeout(() => {
                const firstInput = modal.querySelector('input[type="number"]');
                if (firstInput) firstInput.focus();
            }, 100);
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Close modal on backdrop click
        window.onclick = function(event) {
            if (event.target.classList.contains('backdrop-blur-md')) {
                event.target.classList.add('hidden');
            }
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('outcomeModal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeModal('outcomeModal');
                }
            }
        });
    </script>
</x-app-layout>
