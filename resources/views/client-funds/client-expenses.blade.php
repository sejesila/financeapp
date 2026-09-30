<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-semibold text-base sm:text-xl text-gray-800 dark:text-gray-200 truncate">
                    {{ $clientName }} — Expense History
                </h2>
                <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                    Across all funds for this client
                </p>
            </div>
            <a href="{{ route('client-funds.index', ['client' => $clientName, 'show_completed' => 1]) }}"
               class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-800 whitespace-nowrap">← Back</a>
        </div>
    </x-slot>

    <div class="py-4 sm:py-8">
        <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4">

            {{-- Totals --}}
            <div class="grid grid-cols-3 gap-2 sm:gap-4">
                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Real Expenses</p>
                    <p class="text-lg sm:text-2xl font-bold text-orange-600">{{ number_format($totals['real'], 0) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Borrowed</p>
                    <p class="text-lg sm:text-2xl font-bold text-red-600">{{ number_format($totals['borrowed'], 0) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Entries</p>
                    <p class="text-lg sm:text-2xl font-bold text-gray-700 dark:text-gray-200">{{ $totals['count'] }}</p>
                </div>
            </div>

            {{-- Filters --}}
            <form method="GET" class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4 flex flex-col sm:flex-row gap-2 sm:items-end">
                <div class="flex-1">
                    <label class="block text-xs mb-1">From</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                           class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                </div>
                <div class="flex-1">
                    <label class="block text-xs mb-1">To</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                           class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                </div>
                <div class="flex-1">
                    <label class="block text-xs mb-1">Show</label>
                    <select name="filter"
                            class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <option value="">All</option>
                        <option value="real" {{ request('filter') === 'real' ? 'selected' : '' }}>Real expenses</option>
                        <option value="borrowed" {{ request('filter') === 'borrowed' ? 'selected' : '' }}>Borrowed only</option>
                    </select>
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm font-medium">
                    Apply
                </button>
            </form>

            {{-- By category --}}
            @if($byCategory->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3 sm:p-4">
                    <h3 class="text-sm sm:text-base font-semibold mb-2">By Category</h3>
                    <div class="space-y-1">
                        @foreach($byCategory as $name => $amount)
                            <div class="flex justify-between text-xs sm:text-sm">
                                <span class="text-gray-600 dark:text-gray-400">{{ $name }}</span>
                                <span class="font-medium">{{ number_format($amount, 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- History --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base sm:text-lg font-semibold">Expenses</h3>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($expenses as $expense)
                        @php
                            $fund = $fundsById->get($expense->client_fund_id);
                            $linked = $linkedTransactions->get($expense->transaction_id);
                        @endphp
                        <div class="p-3 sm:p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <div class="flex justify-between items-start gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap mb-1">
                                        <span class="text-xs text-gray-500">{{ $expense->date->format('M d, Y') }}</span>
                                        @if($expense->is_borrowed)
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">🚩 Borrowed</span>
                                        @elseif($linked?->category)
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                                {{ $linked->category->name }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-800 dark:text-gray-200 break-words">{{ $expense->description }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        @if($fund)
                                            <a href="{{ route('client-funds.show', $fund) }}" class="text-indigo-600 hover:text-indigo-800">
                                                {{ \Illuminate\Support\Str::limit($fund->purpose, 40) }}
                                            </a>
                                        @endif
                                        @if($linked?->account) · Paid from {{ $linked->account->name }} @endif
                                    </p>
                                </div>
                                <p class="font-bold text-base sm:text-lg flex-shrink-0 {{ $expense->is_borrowed ? 'text-red-600' : 'text-orange-600' }}">
                                    - {{ number_format($expense->amount, 0) }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 sm:p-8 text-center text-gray-500 text-sm">No expenses found.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
