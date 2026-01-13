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
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-auto animate-fade-in">
                    <div class="p-6">
                        {{-- Icon --}}
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full
                {{ session('transaction_type') === 'expense' ? 'bg-red-100 dark:bg-red-900/30' : (session('transaction_type') === 'liability' ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-green-100 dark:bg-green-900/30') }}">
                            @if(session('transaction_type') === 'expense')
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                </svg>
                            @elseif(session('transaction_type') === 'liability')
                                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            @else
                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                </svg>
                            @endif
                        </div>

                        {{-- Title --}}
                        <h3 class="text-base font-bold text-gray-900 dark:text-white text-center mt-3">
                            Transaction Successful!
                        </h3>

                        {{-- Account Name --}}
                        <p class="text-center text-gray-600 dark:text-gray-400 mt-2 text-sm font-medium">
                            {{ session('account_name') }}
                        </p>

                        {{-- Balance Details --}}
                        <div class="mt-4 space-y-2 bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Previous Balance:</span>
                                <span class="font-semibold text-sm text-gray-900 dark:text-white">KES {{ session('old_balance') }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Transaction:</span>
                                <span class="font-semibold text-sm {{ session('transaction_type') === 'expense' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            {{ session('transaction_type') === 'expense' ? '−' : '+' }} KES {{ session('transaction_amount') }}
                        </span>
                            </div>

                            <div class="border-t border-gray-200 dark:border-gray-600 pt-2 flex justify-between items-center">
                                <span class="text-sm font-bold text-gray-900 dark:text-white">New Balance:</span>
                                <span class="font-bold text-lg {{ floatval(str_replace(',', '', session('new_balance'))) < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            KES {{ session('new_balance') }}
                        </span>
                            </div>
                        </div>

                        {{-- Close Button --}}
                        <div class="mt-4">
                            <button id="closeModal"
                                    class="w-full px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
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

                    function closeModal() {
                        // Add fade-out animation
                        modal.style.transition = 'opacity 0.5s ease-out';
                        modal.style.opacity = '0';

                        // Hide after animation completes
                        setTimeout(function() {
                            modal.style.display = 'none';
                        }, 1000);
                    }

                    if (modal && closeBtn) {
                        // Set initial opacity for smooth transition
                        modal.style.opacity = '1';

                        // Auto-close after 1 second
                        setTimeout(function() {
                            closeModal();
                        }, 1000);

                        // Close on button click (if user clicks before auto-close)
                        closeBtn.addEventListener('click', function() {
                            closeModal();
                        });

                        // Close on outside click
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) {
                                closeModal();
                            }
                        });

                        // Close on Escape key
                        document.addEventListener('keydown', function(e) {
                            if (e.key === 'Escape') {
                                closeModal();
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
            @if($transactions->hasPages())
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    {{-- Results Summary --}}
                    <div class="text-sm text-gray-700 dark:text-gray-300 order-2 sm:order-1">
                        <p>
                            Showing
                            <span class="font-medium">{{ $transactions->firstItem() ?? 0 }}</span>
                            to
                            <span class="font-medium">{{ $transactions->lastItem() ?? 0 }}</span>
                            of
                            <span class="font-medium">{{ number_format($transactions->total()) }}</span>
                            transactions
                        </p>
                    </div>

                    {{-- Pagination Links --}}
                    <div class="order-1 sm:order-2 w-full sm:w-auto">
                        <div class="flex justify-center sm:justify-end">
                            {{ $transactions->onEachSide(1)->links() }}
                        </div>
                    </div>
                </div>
            @else
                {{-- Show summary even when no pagination --}}
                <div class="text-sm text-gray-700 dark:text-gray-300 text-center">
                    <p>
                        Showing
                        <span class="font-medium">{{ $transactions->count() }}</span>
                        {{ Str::plural('transaction', $transactions->count()) }}
                    </p>
                </div>
            @endif
        </div>

        {{-- Custom Pagination Styles for Better Mobile Experience --}}
        <style>
            /* Hide Laravel's default "Showing X to Y of Z results" text */
            nav[role="navigation"] p.text-sm {
                display: none !important;
            }

            /* Pagination Container */
            nav[role="navigation"] {
                width: 100%;
            }

            nav[role="navigation"] > div {
                display: flex;
                align-items: center;
                gap: 0.25rem;
            }

            /* Pagination Links Base Styles */
            nav[role="navigation"] a,
            nav[role="navigation"] span {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 2.5rem;
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
                font-weight: 500;
                border: 1px solid #e5e7eb;
                border-radius: 0.375rem;
                transition: all 0.2s;
            }

            /* Links (clickable pages) */
            nav[role="navigation"] a {
                background-color: white;
                color: #374151;
                text-decoration: none;
            }

            nav[role="navigation"] a:hover:not([rel="prev"]):not([rel="next"]) {
                background-color: #f3f4f6 !important;
                border-color: #d1d5db !important;
                color: #111827 !important;
            }

            /* Active Page - More Subtle Styling */
            nav[role="navigation"] span[aria-current="page"] {
                background-color: #e5e7eb !important;
                border-color: #d1d5db !important;
                color: #111827 !important;
                font-weight: 600;
            }

            /* Disabled State */
            nav[role="navigation"] span[aria-disabled="true"] {
                background-color: #f9fafb;
                color: #d1d5db;
                cursor: not-allowed;
                opacity: 0.6;
            }

            /* Previous/Next Buttons */
            nav[role="navigation"] a[rel="prev"],
            nav[role="navigation"] a[rel="next"] {
                padding: 0.5rem 1rem;
                font-weight: 500;
            }

            nav[role="navigation"] a[rel="prev"]:hover,
            nav[role="navigation"] a[rel="next"]:hover {
                background-color: #4f46e5 !important;
                border-color: #4f46e5 !important;
                color: white !important;
            }

            /* Ellipsis */
            nav[role="navigation"] span[aria-disabled="true"]:not([role]) {
                border: none;
                background: transparent;
                padding: 0.5rem 0.25rem;
            }

            /* Mobile Responsive */
            @media (max-width: 640px) {
                /* Hide page numbers on mobile, show only prev/next and current */
                nav[role="navigation"] a[href]:not([rel="prev"]):not([rel="next"]) {
                    display: none !important;
                }

                /* Keep current page visible */
                nav[role="navigation"] span[aria-current="page"] {
                    display: inline-flex !important;
                }

                /* Hide ellipsis on mobile */
                nav[role="navigation"] span[aria-disabled="true"]:not([role]) {
                    display: none !important;
                }

                /* Make prev/next buttons larger on mobile */
                nav[role="navigation"] a[rel="prev"],
                nav[role="navigation"] a[rel="next"] {
                    padding: 0.625rem 1rem !important;
                    font-size: 0.875rem !important;
                    flex: 1;
                    max-width: 120px;
                }

                /* Center pagination on mobile */
                nav[role="navigation"] > div {
                    justify-content: center !important;
                    gap: 0.5rem;
                }
            }

            @media (min-width: 641px) {
                /* Desktop: Show all elements */
                nav[role="navigation"] > div {
                    justify-content: flex-end;
                }
            }

            /* Dark Mode Support */
            @media (prefers-color-scheme: dark) {
                nav[role="navigation"] a {
                    background-color: #374151;
                    color: #e5e7eb;
                    border-color: #4b5563;
                }

                nav[role="navigation"] a:hover:not([rel="prev"]):not([rel="next"]) {
                    background-color: #4b5563 !important;
                    border-color: #6b7280 !important;
                }

                nav[role="navigation"] span[aria-current="page"] {
                    background-color: #4b5563 !important;
                    border-color: #6b7280 !important;
                    color: #f3f4f6 !important;
                }

                nav[role="navigation"] span[aria-disabled="true"] {
                    background-color: #1f2937;
                    color: #6b7280;
                }
            }
        </style>

    </div>
    <x-floating-action-button :quickAccount="$accounts->first()" />
</x-app-layout>
