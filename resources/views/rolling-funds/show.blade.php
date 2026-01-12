<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                    Session Details
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $rollingFund->date->format('F d, Y') }}
                </p>
            </div>
            <a href="{{ route('rolling-funds.index') }}"
               class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 text-sm font-medium">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Sessions
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">

            @if(session('success'))
                <div class="bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 p-4 rounded-xl mb-6 text-sm flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-4 rounded-xl mb-6 text-sm flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <!-- Session Status Banner -->
            @if($rollingFund->status === 'pending')
                <div class="mb-6 rounded-2xl p-6 sm:p-8 bg-gradient-to-br from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 border-2 border-yellow-200 dark:border-yellow-800 shadow-lg">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <span class="text-5xl">⏳</span>
                            <div>
                                <h3 class="text-2xl sm:text-3xl font-bold text-yellow-700 dark:text-yellow-400 mb-1">
                                    Outcome Pending
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Record the outcome when you know the result
                                </p>
                            </div>
                        </div>
                        <div class="text-left sm:text-right bg-white/60 dark:bg-gray-800/60 rounded-xl p-4 backdrop-blur-sm">
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Investment</p>
                            <p class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100">
                                KES {{ number_format($rollingFund->stake_amount, 0) }}
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-6 rounded-2xl p-6 sm:p-8 {{ $rollingFund->isWin() ? 'bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-2 border-green-200 dark:border-green-800' : ($rollingFund->outcome === 'break_even' ? 'bg-gradient-to-br from-gray-50 to-slate-50 dark:from-gray-700/50 dark:to-slate-700/50 border-2 border-gray-200 dark:border-gray-600' : 'bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border-2 border-red-200 dark:border-red-800') }} shadow-lg">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <span class="text-5xl">
                                @if($rollingFund->isWin()) 📈
                                @elseif($rollingFund->outcome === 'break_even') ➖
                                @else 📉
                                @endif
                            </span>
                            <div>
                                <h3 class="text-2xl sm:text-3xl font-bold {{ $rollingFund->isWin() ? 'text-green-700 dark:text-green-400' : ($rollingFund->outcome === 'break_even' ? 'text-gray-700 dark:text-gray-300' : 'text-red-700 dark:text-red-400') }} mb-1">
                                    {{ $rollingFund->isWin() ? 'Positive Session' : ($rollingFund->outcome === 'break_even' ? 'Break Even' : 'Negative Session') }}
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Completed on {{ $rollingFund->completed_date->format('M d, Y') }}
                                </p>
                            </div>
                        </div>
                        <div class="text-left sm:text-right bg-white/60 dark:bg-gray-800/60 rounded-xl p-4 backdrop-blur-sm">
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Net Result</p>
                            <p class="text-3xl sm:text-4xl font-bold {{ $rollingFund->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $rollingFund->net_result >= 0 ? '+' : '' }}KES {{ number_format($rollingFund->net_result, 0) }}
                            </p>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">
                                {{ $rollingFund->profit_percentage >= 0 ? '+' : '' }}{{ $rollingFund->profit_percentage }}% return
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Quick Action: Record Outcome -->
            @if($rollingFund->status === 'pending')
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md p-6 mb-6 border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-2xl">💰</span>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            Record Outcome
                        </h3>
                    </div>
                    <button onclick="openModal('outcomeModal')"
                            class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3.5 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02]">
                        Record Win or Loss
                    </button>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Session Information -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md p-6 border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="text-2xl">📋</span>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            Session Information
                        </h3>
                    </div>
                    <dl class="space-y-4">
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</dt>
                            <dd>
                                <span class="px-3 py-1.5 text-xs font-semibold rounded-full {{ $rollingFund->status === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' }}">
                                    {{ ucfirst($rollingFund->status) }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Date</dt>
                            <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100 text-right">
                                {{ $rollingFund->date->format('M d, Y') }}
                            </dd>
                        </div>
                        @if($rollingFund->completed_date)
                            <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Completed</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100 text-right">
                                    {{ $rollingFund->completed_date->format('M d, Y') }}
                                </dd>
                            </div>
                        @endif
                        <div class="flex items-center justify-between py-3">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Source Account</dt>
                            <dd class="text-sm font-semibold text-gray-900 dark:text-gray-100 text-right">
                                {{ $rollingFund->account->name }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Financial Summary -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md p-6 border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="text-2xl">💰</span>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            Financial Summary
                        </h3>
                    </div>
                    <dl class="space-y-4">
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Investment</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                KES {{ number_format($rollingFund->stake_amount, 0) }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between py-3 {{ $rollingFund->status === 'completed' ? 'border-b border-gray-100 dark:border-gray-700' : '' }}">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Returns</dt>
                            <dd class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                @if($rollingFund->status === 'completed')
                                    KES {{ number_format($rollingFund->winnings, 0) }}
                                @else
                                    <span class="text-yellow-600 dark:text-yellow-400 text-xs font-semibold">Pending</span>
                                @endif
                            </dd>
                        </div>
                        @if($rollingFund->status === 'completed')
                            <div class="pt-4 bg-gradient-to-br {{ $rollingFund->net_result >= 0 ? 'from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20' : 'from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20' }} rounded-xl p-4">
                                <dt class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Net Profit/Loss</dt>
                                <dd class="text-3xl font-bold {{ $rollingFund->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $rollingFund->net_result >= 0 ? '+' : '' }}KES {{ number_format($rollingFund->net_result, 0) }}
                                </dd>
                                <dd class="text-sm font-medium text-gray-600 dark:text-gray-400 mt-2">
                                    ROI: {{ $rollingFund->profit_percentage >= 0 ? '+' : '' }}{{ number_format($rollingFund->profit_percentage, 2) }}%
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Notes Section -->
            @if($rollingFund->notes)
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md p-6 mb-6 border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-2xl">📝</span>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            Notes
                        </h3>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">{{ $rollingFund->notes }}</p>
                </div>
            @endif

            <!-- Related Transactions -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md p-6 mb-6 border border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-5">
                    <span class="text-2xl">📊</span>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                        Related Transactions
                    </h3>
                </div>
                <div class="space-y-3">
                    @php
                        $relatedTransactions = \App\Models\Transaction::where('user_id', auth()->id())
                            ->where('account_id', $rollingFund->account_id)
                            ->where(function($query) use ($rollingFund) {
                                $query->where('date', $rollingFund->date);
                                if ($rollingFund->status === 'completed' && $rollingFund->completed_date) {
                                    $query->orWhere('date', $rollingFund->completed_date);
                                }
                            })
                            ->where(function($query) {
                                $query->where('payment_method', 'Rolling Funds')
                                    ->orWhere(function($subQuery) {
                                        $subQuery->where('is_transaction_fee', true)
                                            ->where('description', 'like', '%Rolling Funds%');
                                    });
                            })
                            ->with('category')
                            ->orderBy('date', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->get();
                    @endphp

                    @forelse($relatedTransactions as $transaction)
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100/50 dark:from-gray-700/50 dark:to-gray-700/30 rounded-xl hover:shadow-md transition-shadow duration-200 border border-gray-200 dark:border-gray-600">
                            <div class="flex-1 min-w-0 pr-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ $transaction->description }}
                                </p>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-2 py-1 rounded-md">
                                        {{ $transaction->category->name }}
                                    </span>
                                    @if($transaction->is_transaction_fee)
                                        <span class="text-xs font-semibold bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-400 px-2 py-1 rounded-md">
                                            Transaction Fee
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $transaction->date->format('M d') }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-lg font-bold {{ $transaction->category->type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $transaction->category->type === 'income' ? '+' : '-' }}{{ number_format($transaction->amount, 0) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">KES</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <div class="text-6xl mb-4">📭</div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                                No transactions found for this session
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
                            class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl font-semibold shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-[1.02] flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        <div id="outcomeModal" class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-6 sm:p-8 max-w-md w-full border border-gray-200 dark:border-gray-700 transform transition-all">
                <div class="flex items-center gap-3 mb-6">
                    <span class="text-3xl">💰</span>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200">Record Session Outcome</h3>
                </div>

                <form method="POST" action="{{ route('rolling-funds.record-outcome', $rollingFund) }}">
                    @csrf

                    <div class="mb-5">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Returns Amount (KES) <span class="text-red-500">*</span>
                        </label>
                        <input type="number"
                               step="0.01"
                               name="winnings"
                               id="winnings"
                               min="0"
                               placeholder="Enter the amount you received back"
                               required
                               class="w-full border-2 border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                        <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <p class="text-xs font-medium text-blue-700 dark:text-blue-400">
                                💡 Investment was: KES {{ number_format($rollingFund->stake_amount, 0) }}
                            </p>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Completed Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               name="completed_date"
                               value="{{ date('Y-m-d') }}"
                               min="{{ $rollingFund->date->format('Y-m-d') }}"
                               max="{{ date('Y-m-d') }}"
                               required
                               class="w-full border-2 border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">
                            Additional Notes (Optional)
                        </label>
                        <textarea name="outcome_notes"
                                  rows="3"
                                  placeholder="Any notes about the outcome..."
                                  class="w-full border-2 border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all resize-none"></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="button"
                                onclick="closeModal('outcomeModal')"
                                class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-6 py-3 rounded-xl font-semibold transition-all duration-200 transform hover:scale-[1.02]">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02]">
                            Record Outcome
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('backdrop-blur-sm')) {
                event.target.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>
