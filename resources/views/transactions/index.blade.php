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

        {{-- Transaction Fees Summary --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-semibold text-yellow-800 mb-1">💸 Fees Today</h3>
                        <p class="text-xl font-bold text-yellow-900">
                            {{ number_format($totalFeesToday, 0, '.', ',') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-semibold text-orange-800 mb-1">💸 Fees This Month</h3>
                        <p class="text-xl font-bold text-orange-900">
                            {{ number_format($totalFeesThisMonth, 0, '.', ',') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-semibold text-red-800 mb-1">💸 Total Fees</h3>
                        <p class="text-xl font-bold text-red-900">
                            {{ number_format($totalFeesAll, 0, '.', ',') }}
                        </p>
                    </div>
                </div>
            </div>
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

            {{-- Show/Hide Fees Toggle --}}
            <div class="flex items-center gap-2 pt-2 border-t">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox"
                           onchange="window.location.href='{{ route('transactions.index', array_merge(request()->all(), ['show_fees' => $showFees ? '0' : '1'])) }}'"
                           {{ $showFees ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Show Transaction Fees</span>
                </label>
                <span class="text-xs text-gray-500">({{ $showFees ? 'Currently showing fees' : 'Fees hidden' }})</span>
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
                    <input type="hidden" name="show_fees" value="{{ $showFees ? '1' : '0' }}">

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
                    <tr class="hover:bg-gray-50 {{ $t->is_transaction_fee ? 'bg-yellow-50' : '' }}">
                        <td class="px-3 py-2 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($t->date)->format('M d, Y') }}
                        </td>

                        <td class="px-3 py-2">
                            @if($t->is_transaction_fee)
                                <span class="inline-flex items-center gap-1 text-xs text-yellow-700">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9 9a1 1 0 012 0v4a1 1 0 11-2 0V9zm1-5a1 1 0 100 2 1 1 0 000-2z"/>
                                    </svg>
                                    FEE
                                </span>
                            @endif
                            {{ $t->description }}
                            @if(!$t->is_transaction_fee && $t->hasFee())
                                <span class="ml-2 text-xs text-gray-500">
                                    (+{{ number_format($t->feeTransaction->amount, 0) }} fee)
                                </span>
                            @endif
                        </td>

                        <td class="px-3 py-2 text-right">
                            <div class="flex flex-col items-end">
                                <span class="font-semibold {{ $t->category->type === 'income' ? 'text-green-600' : ($t->is_transaction_fee ? 'text-yellow-700' : 'text-red-600') }}">
                                    {{ $t->category->type === 'income' ? '+' : '-' }}
                                    {{ number_format($t->amount, 0) }}
                                </span>
                                @if(!$t->is_transaction_fee && $t->hasFee())
                                    <span class="text-xs text-gray-500">
                                        Total: {{ number_format($t->total_amount, 0) }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="px-3 py-2 hidden sm:table-cell">
                            {{ $t->account->name ?? '—' }}
                        </td>

                        <td class="px-3 py-2 hidden md:table-cell">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs
                                {{ $t->category->type === 'income'
                                    ? 'bg-green-100 text-green-800'
                                    : ($t->is_transaction_fee ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
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
                        {{ number_format($transactions->sum('amount'), 0) }}
                    </span>
                </p>
            </div>
        @endif

        {{-- Pagination - Improved for Mobile and Desktop --}}
        <div class="pt-4 border-t">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                {{-- Results Summary --}}
                <div class="text-sm text-gray-700 dark:text-gray-300 order-2 sm:order-1">
                    <p>
                        Showing
                        <span class="font-medium">{{ $transactions->firstItem() ?? 0 }}</span>
                        to
                        <span class="font-medium">{{ $transactions->lastItem() ?? 0 }}</span>
                        of
                        <span class="font-medium">{{ $transactions->total() }}</span>
                        transactions
                    </p>
                </div>

                {{-- Pagination Links --}}
                <div class="order-1 sm:order-2 w-full sm:w-auto">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>

        {{-- Custom Pagination Styles for Better Mobile Experience --}}
        <style>
            /* Make pagination more mobile-friendly */
            nav[role="navigation"] {
                width: 100%;
            }

            @media (max-width: 640px) {
                /* Hide page numbers on very small screens, keep only prev/next */
                nav[role="navigation"] span[aria-current="page"],
                nav[role="navigation"] a[rel]:not([rel="prev"]):not([rel="next"]) {
                    display: none !important;
                }

                /* Make prev/next buttons larger on mobile */
                nav[role="navigation"] a[rel="prev"],
                nav[role="navigation"] a[rel="next"],
                nav[role="navigation"] span[aria-disabled="true"] {
                    padding: 0.75rem 1.25rem !important;
                    font-size: 0.875rem !important;
                }

                /* Center pagination on mobile */
                nav[role="navigation"] > div {
                    justify-content: center !important;
                }
            }

            @media (min-width: 641px) {
                /* Show all page numbers on desktop */
                nav[role="navigation"] span,
                nav[role="navigation"] a {
                    min-width: 2.5rem;
                }
            }

            /* Improve button spacing */
            nav[role="navigation"] .flex {
                gap: 0.25rem;
            }

            /* Better hover states */
            nav[role="navigation"] a:hover {
                background-color: rgb(79 70 229) !important;
                border-color: rgb(79 70 229) !important;
                color: white !important;
            }

            /* Active page styling */
            nav[role="navigation"] span[aria-current="page"] {
                background-color: rgb(79 70 229) !important;
                border-color: rgb(79 70 229) !important;
                color: white !important;
            }

            /* Disabled state */
            nav[role="navigation"] span[aria-disabled="true"] {
                opacity: 0.5;
                cursor: not-allowed;
            }
        </style>

    </div>
    <x-floating-action-button :quickAccount="$accounts->first()" />
</x-app-layout>
