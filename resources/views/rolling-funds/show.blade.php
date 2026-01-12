<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                    Session Details
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $rollingFund->date->format('F d, Y') }}
                </p>
            </div>
            <a href="{{ route('rolling-funds.index') }}"
               class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                ← Back to Sessions
            </a>
        </div>
    </x-slot>

    <div class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">

            @if(session('success'))
                <div class="bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 p-3 rounded-lg mb-6 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-3 rounded-lg mb-6 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Session Status Banner -->
            @if($rollingFund->status === 'pending')
                <div class="mb-6 rounded-lg p-6 bg-yellow-50 dark:bg-yellow-900/20 border-2 border-yellow-200 dark:border-yellow-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-4xl">⏳</span>
                                <div>
                                    <h3 class="text-2xl font-bold text-yellow-700 dark:text-yellow-400">
                                        Outcome Pending
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Record the outcome when you know the result
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Investment</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                KES {{ number_format($rollingFund->stake_amount, 0) }}
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-6 rounded-lg p-6 {{ $rollingFund->isWin() ? 'bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-800' : ($rollingFund->outcome === 'break_even' ? 'bg-gray-50 dark:bg-gray-700/50 border-2 border-gray-200 dark:border-gray-600' : 'bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800') }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-4xl">
                                    @if($rollingFund->isWin()) 📈
                                    @elseif($rollingFund->outcome === 'break_even') ➖
                                    @else 📉
                                    @endif
                                </span>
                                <div>
                                    <h3 class="text-2xl font-bold {{ $rollingFund->isWin() ? 'text-green-700 dark:text-green-400' : ($rollingFund->outcome === 'break_even' ? 'text-gray-700 dark:text-gray-300' : 'text-red-700 dark:text-red-400') }}">
                                        {{ $rollingFund->isWin() ? 'Positive Session' : ($rollingFund->outcome === 'break_even' ? 'Break Even' : 'Negative Session') }}
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Completed on {{ $rollingFund->completed_date->format('M d, Y') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Net Result</p>
                            <p class="text-3xl font-bold {{ $rollingFund->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $rollingFund->net_result >= 0 ? '+' : '' }}KES {{ number_format($rollingFund->net_result, 0) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                {{ $rollingFund->profit_percentage >= 0 ? '+' : '' }}{{ $rollingFund->profit_percentage }}% return
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Quick Action: Record Outcome -->
            @if($rollingFund->status === 'pending')
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                        💰 Record Outcome
                    </h3>
                    <button onclick="openModal('outcomeModal')"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium">
                        Record Win or Loss
                    </button>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Session Information -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                        📋 Session Information
                    </h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Status</dt>
                            <dd class="text-base font-medium">
                                <span class="px-3 py-1 text-sm rounded-full {{ $rollingFund->status === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' }}">
                                    {{ ucfirst($rollingFund->status) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Date</dt>
                            <dd class="text-base font-medium text-gray-900 dark:text-gray-100">
                                {{ $rollingFund->date->format('l, F d, Y') }}
                            </dd>
                        </div>
                        @if($rollingFund->completed_date)
                            <div>
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Completed Date</dt>
                                <dd class="text-base font-medium text-gray-900 dark:text-gray-100">
                                    {{ $rollingFund->completed_date->format('l, F d, Y') }}
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Source Account</dt>
                            <dd class="text-base font-medium text-gray-900 dark:text-gray-100">
                                {{ $rollingFund->account->name }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Financial Summary -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                        💰 Financial Summary
                    </h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Investment Amount</dt>
                            <dd class="text-base font-medium text-gray-900 dark:text-gray-100">
                                KES {{ number_format($rollingFund->stake_amount, 2) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Returns Received</dt>
                            <dd class="text-base font-medium text-gray-900 dark:text-gray-100">
                                @if($rollingFund->status === 'completed')
                                    KES {{ number_format($rollingFund->winnings, 2) }}
                                @else
                                    <span class="text-yellow-600 dark:text-yellow-400">Pending</span>
                                @endif
                            </dd>
                        </div>
                        @if($rollingFund->status === 'completed')
                            <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                <dt class="text-sm text-gray-600 dark:text-gray-400 mb-1">Net Profit/Loss</dt>
                                <dd class="text-2xl font-bold {{ $rollingFund->net_result >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $rollingFund->net_result >= 0 ? '+' : '' }}KES {{ number_format($rollingFund->net_result, 2) }}
                                </dd>
                                <dd class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Return on Investment: {{ $rollingFund->profit_percentage >= 0 ? '+' : '' }}{{ number_format($rollingFund->profit_percentage, 2) }}%
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Notes Section -->
            @if($rollingFund->notes)
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center gap-2">
                        📝 Notes
                    </h3>
                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $rollingFund->notes }}</p>
                </div>
            @endif

            <!-- Related Transactions -->
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
                        📊 Related Transactions
                    </h3>
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
                                ->orderBy('date', 'desc')  // Changed to 'desc' for descending order
                                ->get();
                        @endphp

                        @forelse($relatedTransactions as $transaction)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $transaction->description }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $transaction->category->name }}
                                        @if($transaction->is_transaction_fee)
                                            <span class="ml-1 text-xs bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 px-2 py-0.5 rounded">
                                Transaction Fee
                            </span>
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-base font-semibold {{ $transaction->category->type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $transaction->category->type === 'income' ? '+' : '-' }}KES {{ number_format($transaction->amount, 0) }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                                No transactions found for this session.
                            </p>
                        @endforelse
                    </div>
                </div>


                <!-- Actions -->
            <div class="mt-6 flex gap-3 justify-end">
                <form action="{{ route('rolling-funds.destroy', $rollingFund) }}" method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this session? This will also remove all related transactions and update your account balance.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium">
                        🗑️ Delete Session
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- Outcome Recording Modal -->
    @if($rollingFund->status === 'pending')
        <div id="outcomeModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Record Session Outcome</h3>

                <form method="POST" action="{{ route('rolling-funds.record-outcome', $rollingFund) }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                            Returns Amount (KES) <span class="text-red-500">*</span>
                        </label>
                        <input type="number"
                               step="0.01"
                               name="winnings"
                               id="winnings"
                               min="0"
                               placeholder="Enter the amount you received back"
                               required
                               class="w-full border rounded-lg px-4 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Investment was: KES {{ number_format($rollingFund->stake_amount, 0) }}
                        </p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                            Completed Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               name="completed_date"
                               value="{{ date('Y-m-d') }}"
                               min="{{ $rollingFund->date->format('Y-m-d') }}"
                               max="{{ date('Y-m-d') }}"
                               required
                               class="w-full border rounded-lg px-4 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                            Additional Notes (Optional)
                        </label>
                        <textarea name="outcome_notes"
                                  rows="3"
                                  placeholder="Any notes about the outcome..."
                                  class="w-full border rounded-lg px-4 py-2 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="button"
                                onclick="closeModal('outcomeModal')"
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium">
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
            if (event.target.classList.contains('bg-opacity-50')) {
                event.target.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>
