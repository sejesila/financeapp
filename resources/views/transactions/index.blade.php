<x-app-layout>
    {{-- Header --}}
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg sm:text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Transactions
                </h2>
                <p class="text-xs sm:text-sm text-gray-500">
                    View and manage all income & expenses
                </p>
            </div>

            <a href="{{ route('transactions.create') }}"
               class="inline-flex w-full sm:w-auto items-center justify-center gap-2
                      rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white
                      hover:bg-indigo-700">
                + New Transaction
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-3 sm:px-6 py-6 space-y-6">

        {{-- Flash Messages --}}
        @foreach (['success' => 'green', 'error' => 'red'] as $key => $color)
            @if(session($key) && !session('show_balance_modal'))
                <div class="rounded-md bg-{{ $color }}-100 px-4 py-3 text-{{ $color }}-700 text-sm">
                    {{ session($key) }}
                </div>
            @endif
        @endforeach

        {{-- Balance Modal --}}
        @if(session('show_balance_modal'))
            <div id="balanceModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
                <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md mx-auto animate-fade-in">
                    <div class="p-6">
                        {{-- Icon --}}
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full
                        {{ session('transaction_type') === 'expense' ? 'bg-red-100' : (session('transaction_type') === 'liability' ? 'bg-yellow-100' : 'bg-green-100') }}">
                            @if(session('transaction_type') === 'expense')
                                <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                </svg>
                            @elseif(session('transaction_type') === 'liability')
                                <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            @else
                                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                </svg>
                            @endif
                        </div>

                        {{-- Title --}}
                        <h3 class="text-xl font-bold text-gray-900 text-center mt-4">
                            Transaction Successful!
                        </h3>

                        {{-- Account Name --}}
                        <p class="text-center text-gray-600 mt-2 font-medium">
                            {{ session('account_name') }}
                        </p>

                        {{-- Balance Details --}}
                        <div class="mt-6 space-y-3 bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Previous Balance:</span>
                                <span class="font-semibold text-gray-900">KES {{ session('old_balance') }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Transaction:</span>
                                <span class="font-semibold {{ session('transaction_type') === 'expense' ? 'text-red-600' : 'text-green-600' }}">
                                {{ session('transaction_type') === 'expense' ? '−' : '+' }} KES {{ session('transaction_amount') }}
                            </span>
                            </div>

                            <div class="border-t border-gray-200 pt-3 flex justify-between items-center">
                                <span class="text-base font-bold text-gray-900">New Balance:</span>
                                <span class="font-bold text-2xl {{ floatval(str_replace(',', '', session('new_balance'))) < 0 ? 'text-red-600' : 'text-green-600' }}">
                                KES {{ session('new_balance') }}
                            </span>
                            </div>
                        </div>

                        {{-- Close Button --}}
                        <div class="mt-6">
                            <button id="closeModal"
                                    class="w-full px-4 py-3 bg-indigo-600 text-white text-base font-medium rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: scale(0.95);
                    }
                    to {
                        opacity: 1;
                        transform: scale(1);
                    }
                }
                .animate-fade-in {
                    animation: fadeIn 0.2s ease-out;
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const modal = document.getElementById('balanceModal');
                    const closeBtn = document.getElementById('closeModal');

                    if (modal && closeBtn) {
                        // Close on button click
                        closeBtn.addEventListener('click', function() {
                            modal.style.display = 'none';
                        });

                        // Close on outside click
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) {
                                modal.style.display = 'none';
                            }
                        });

                        // Close on Escape key
                        document.addEventListener('keydown', function(e) {
                            if (e.key === 'Escape') {
                                modal.style.display = 'none';
                            }
                        });
                    }
                });
            </script>
        @endif

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
            @php
                $stats = [
                    ['Today', $totalToday, 'blue'],
                    ['Yesterday', $totalYesterday, 'orange'],
                    ['This Week', $totalThisWeek, 'green'],
                    ['Last Week', $totalLastWeek, 'yellow'],
                    ['This Month', $totalThisMonth, 'teal'],
                    ['Last Month', $totalLastMonth, 'pink'],
                    ['All Time', $totalAll, 'purple'],
                ];
            @endphp

            @foreach($stats as [$label, $value, $color])
                <div class="bg-{{ $color }}-100 p-3 rounded-lg border border-{{ $color }}-300">
                    <h3 class="text-xs font-semibold text-{{ $color }}-800 mb-1">{{ $label }}</h3>
                    <p class="text-lg sm:text-xl font-bold text-{{ $color }}-900">
                        {{ number_format($value, 0, '.', ',') }}
                    </p>
                </div>
            @endforeach
        </div>

        {{-- Filters --}}
        <div class="rounded-lg border bg-white p-4 shadow-sm space-y-4">

            {{-- Quick Filters --}}
            <div class="flex flex-wrap gap-2 text-xs sm:text-sm">
                @foreach([
                    'all' => 'All',
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    'this_week' => 'This Week',
                    'last_week' => 'Last Week',
                    'this_month' => 'This Month',
                    'last_month' => 'Last Month',
                    'this_year' => 'This Year',
                ] as $key => $label)
                    <a href="{{ route('transactions.index', ['filter' => $key]) }}"
                       class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-md
                       {{ $filter === $key
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- Advanced Filters --}}
            <details {{ $filter === 'custom' || $search || $categoryId || $accountId ? 'open' : '' }}>
                <summary class="cursor-pointer text-sm font-medium text-gray-700">
                    Advanced Filters
                </summary>

                <form method="GET"
                      action="{{ route('transactions.index') }}"
                      class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    <input type="hidden" name="filter" value="custom">

                    <div>
                        <label class="block text-xs font-medium mb-1">Search</label>
                        <input type="text" name="search" value="{{ $search }}"
                               class="w-full rounded-md border-gray-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1">Category</label>
                        <select name="category_id"
                                class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">All</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected($categoryId == $category->id)>
                                    {{ $category->name }} ({{ ucfirst($category->type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1">Account</label>
                        <select name="account_id"
                                class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">All</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected($accountId == $account->id)>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1">Date Range</label>
                        <div class="flex gap-2">
                            <input type="date" name="start_date" value="{{ $startDate }}"
                                   class="w-full rounded-md border-gray-300 text-sm">
                            <input type="date" name="end_date" value="{{ $endDate }}"
                                   class="w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>

                    <div class="col-span-full flex flex-wrap gap-2">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">
                            Apply
                        </button>
                        <a href="{{ route('transactions.index') }}"
                           class="rounded-md bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300">
                            Reset
                        </a>
                    </div>
                </form>
            </details>
        </div>

        {{-- Transactions Table --}}
        <div class="overflow-x-auto rounded-lg border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-3 py-2 text-left">Date</th>
                    <th class="px-3 py-2 text-left">Description</th>
                    <th class="px-3 py-2 text-right">Amount</th>
                    <th class="px-3 py-2 text-left hidden sm:table-cell">Account</th>
                    <th class="px-3 py-2 text-left hidden md:table-cell">Category</th>
                    <th class="px-3 py-2 text-center">Action</th>
                </tr>
                </thead>

                <tbody class="divide-y">
                @forelse($transactions as $t)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($t->date)->format('M d, Y') }}
                        </td>

                        <td class="px-3 py-2">{{ $t->description }}</td>

                        <td class="px-3 py-2 text-right font-semibold
                            {{ $t->category->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $t->category->type === 'income' ? '+' : '-' }}
                            {{ number_format($t->amount) }}
                        </td>

                        <td class="px-3 py-2 hidden sm:table-cell">
                            {{ $t->account->name ?? '—' }}
                        </td>

                        <td class="px-3 py-2 hidden md:table-cell">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs
                                {{ $t->category->type === 'income'
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-red-100 text-red-800' }}">
                                {{ $t->category->name }}
                            </span>
                        </td>

                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('transactions.show', $t) }}"
                               class="text-indigo-600 hover:underline text-sm">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                            No transactions found.
                            <a href="{{ route('transactions.create') }}"
                               class="text-indigo-600 hover:underline ml-1">
                                Add one
                            </a>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Filtered Total --}}
        @if($filter !== 'all')
            <div class="rounded-lg bg-gray-50 p-4">
                <p class="font-semibold">
                    Filtered Total:
                    <span class="text-indigo-600">
                        {{ number_format($transactions->sum('amount')) }}
                    </span>
                </p>
            </div>
        @endif

        {{-- Pagination --}}
        <div class="pt-4 border-t overflow-x-auto">
            {{ $transactions->links() }}
        </div>

    </div>
    <x-floating-action-button :quickAccount="$accounts->first()" />
</x-app-layout>
