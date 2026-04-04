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
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full
                            {{ session('transaction_type') === 'expense' ? 'bg-red-100 dark:bg-red-900/30' : (session('transaction_type') === 'liability' ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-green-100 dark:bg-green-900/30') }}">
                            @if(session('transaction_type') === 'expense')
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                </svg>
                            @elseif(session('transaction_type') === 'liability')
                                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @else
                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                </svg>
                            @endif
                        </div>

                        <h3 class="text-base font-bold text-gray-900 dark:text-white text-center mt-3">
                            Transaction Successful!
                        </h3>

                        <p class="text-center text-gray-600 dark:text-gray-400 mt-2 text-sm font-medium">
                            {{ session('account_name') }}
                        </p>

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
                    from { opacity: 0; transform: scale(0.95); }
                    to   { opacity: 1; transform: scale(1); }
                }
                .animate-fade-in { animation: fadeIn 0.2s ease-out; }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const modal    = document.getElementById('balanceModal');
                    const closeBtn = document.getElementById('closeModal');

                    function closeModal() {
                        modal.style.transition = 'opacity 0.5s ease-out';
                        modal.style.opacity    = '0';
                        setTimeout(function () { modal.style.display = 'none'; }, 500);
                    }

                    if (modal && closeBtn) {
                        modal.style.opacity = '1';
                        setTimeout(closeModal, 4000);
                        closeBtn.addEventListener('click', closeModal);
                        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
                        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
                    }
                });
            </script>
        @endif

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
            @php
                $stats = [
                    ['This Week',  $totalThisWeek,  'green'],
                    ['Last Week',  $totalLastWeek,  'yellow'],
                    ['This Month', $totalThisMonth, 'teal'],
                    ['Last Month', $totalLastMonth, 'pink'],
                    ['This Year',  $totalThisYear,  'indigo'],
                    ['Last Year',  $totalLastYear,  'orange'],
                    ['All Time',   $totalAll,       'purple'],
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
                <h3 class="text-xs font-semibold text-yellow-800 mb-1">💸 Fees Today</h3>
                <p class="text-xl font-bold text-yellow-900">{{ number_format($totalFeesToday, 0, '.', ',') }}</p>
            </div>
            <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                <h3 class="text-xs font-semibold text-orange-800 mb-1">💸 Fees This Month</h3>
                <p class="text-xl font-bold text-orange-900">{{ number_format($totalFeesThisMonth, 0, '.', ',') }}</p>
            </div>
            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                <h3 class="text-xs font-semibold text-red-800 mb-1">💸 Total Fees</h3>
                <p class="text-xl font-bold text-red-900">{{ number_format($totalFeesAll, 0, '.', ',') }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="rounded-lg border bg-white dark:bg-gray-800 dark:border-gray-700 p-4 shadow-sm space-y-4">

            {{-- Quick Filters --}}
            <div class="flex flex-wrap gap-2 text-xs sm:text-sm">
                @php
                    $quickFilters = [
                        'all'        => 'All',
                        'today'      => 'Today',
                        'yesterday'  => 'Yesterday',
                        'this_week'  => 'This Week',
                        'last_week'  => 'Last Week',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'this_year'  => 'This Year',
                    ];
                    for ($y = $maxYear - 1; $y >= $minYear; $y--) {
                        $quickFilters['year_' . $y] = (string) $y;
                    }
                @endphp

                @foreach($quickFilters as $key => $label)
                    <a href="{{ route('transactions.index', array_merge(request()->except('filter', 'page'), ['filter' => $key])) }}"
                       class="px-3 py-1.5 sm:px-4 sm:py-2 rounded-md
                           {{ $filter === $key
                               ? 'bg-indigo-600 text-white'
                               : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- Show/Hide Fees Toggle --}}
            <div class="flex items-center gap-2 pt-2 border-t dark:border-gray-700">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox"
                           onchange="window.location.href='{{ route('transactions.index', array_merge(request()->except('show_fees', 'page'), ['show_fees' => $showFees ? '0' : '1'])) }}'"
                           {{ $showFees ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Show Transaction Fees</span>
                </label>
                <span class="text-xs text-gray-500">({{ $showFees ? 'Currently showing fees' : 'Fees hidden' }})</span>
            </div>

            {{-- Advanced Filters --}}
            <details {{ in_array($filter, ['custom']) || $search || $categoryId || $accountId ? 'open' : '' }}>
                <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300">
                    Advanced Filters
                </summary>

                <form method="GET" action="{{ route('transactions.index') }}"
                      class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    <input type="hidden" name="filter"    value="custom">
                    <input type="hidden" name="show_fees" value="{{ $showFees ? '1' : '0' }}">
                    <input type="hidden" name="sort"      value="{{ $sortColumn }}">
                    <input type="hidden" name="direction" value="{{ $sortDirection }}">

                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-gray-300">Search</label>
                        <input type="text" name="search" value="{{ $search }}"
                               placeholder="Description..."
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-gray-300">Category</label>
                        <select name="category_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                            <option value="">All</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected($categoryId == $category->id)>
                                    {{ $category->name }} ({{ ucfirst($category->type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-gray-300">Account</label>
                        <select name="account_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                            <option value="">All</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected($accountId == $account->id)>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1 dark:text-gray-300">Date Range</label>
                        <div class="flex gap-2">
                            <input type="date" name="start_date" value="{{ $startDate }}"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                            <input type="date" name="end_date" value="{{ $endDate }}"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                        </div>
                    </div>

                    <div class="col-span-full flex flex-wrap gap-2">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">
                            Apply
                        </button>
                        <a href="{{ route('transactions.index') }}"
                           class="rounded-md bg-gray-200 dark:bg-gray-700 dark:text-gray-300 px-4 py-2 text-sm hover:bg-gray-300 dark:hover:bg-gray-600">
                            Reset
                        </a>
                    </div>
                </form>
            </details>
        </div>

        {{-- Transactions Table --}}
        <div class="overflow-x-auto rounded-lg border bg-white dark:bg-gray-800 dark:border-gray-700 shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                <tr>
                    @php
                        $columns = [
                            'date'        => ['Date',        'px-3 py-2 text-left'],
                            'description' => ['Description', 'px-3 py-2 text-left'],
                            'amount'      => ['Amount',      'px-3 py-2 text-right'],
                            'account'     => ['Account',     'px-3 py-2 text-left hidden sm:table-cell'],
                            'category'    => ['Category',    'px-3 py-2 text-left hidden md:table-cell'],
                        ];
                    @endphp

                    @foreach($columns as $col => [$label, $thClass])
                        @php
                            $isActive = $sortColumn === $col;
                            $newDir   = ($isActive && $sortDirection === 'asc') ? 'desc' : 'asc';
                            $sortUrl  = request()->fullUrlWithQuery(['sort' => $col, 'direction' => $newDir, 'page' => 1]);
                        @endphp
                        <th class="{{ $thClass }}">
                            <a href="{{ $sortUrl }}"
                               class="inline-flex items-center gap-1 hover:text-indigo-600 transition-colors group">
                                {{ $label }}
                                <span class="text-xs leading-none">
                                        @if($isActive)
                                        <span class="text-indigo-600 font-bold">
                                                {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                                            </span>
                                    @else
                                        <span class="text-gray-400 group-hover:text-indigo-400">↕</span>
                                    @endif
                                    </span>
                            </a>
                        </th>
                    @endforeach

                    <th class="px-3 py-2 text-center">Action</th>
                </tr>
                </thead>

                <tbody class="divide-y dark:divide-gray-700">
                @forelse($transactions as $t)
                    @php
                        $isFee    = $t->is_transaction_fee;
                        $isSplit  = $t->is_split;
                        $isIncome = $t->category->type === 'income';
                    @endphp

                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50
                                   {{ $isFee   ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}
                                   {{ $isSplit ? 'bg-indigo-50/30 dark:bg-indigo-900/10' : '' }}">

                        {{-- Date --}}
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700 dark:text-gray-300">
                            {{ \Carbon\Carbon::parse($t->date)->format('M d, Y') }}
                        </td>

                        {{-- Description --}}
                        <td class="px-3 py-2 text-gray-800 dark:text-gray-200">
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if($isFee)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 dark:bg-yellow-900/40 px-1.5 py-0.5 text-xs font-medium text-yellow-700 dark:text-yellow-400">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9 9a1 1 0 012 0v4a1 1 0 11-2 0V9zm1-5a1 1 0 100 2 1 1 0 000-2z"/>
                                            </svg>
                                            FEE
                                        </span>
                                @endif

                                @if($isSplit)
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-1.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-400">
                                            ⇄ Split
                                        </span>
                                @endif

                                <span>{{ $t->description }}</span>

                                @if(!$isFee && !$isSplit && $t->hasFee())
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                            (+{{ number_format($t->total_amount - $t->amount, 0) }} fee)
                                        </span>
                                @endif
                            </div>

                            {{-- Mobile-only: split account summary --}}
                            @if($isSplit)
                                <div class="sm:hidden mt-1 flex flex-wrap gap-1">
                                    @foreach($t->splits as $split)
                                        <span class="inline-flex items-center gap-1 rounded bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 text-xs text-gray-600 dark:text-gray-400">
                                                {{ $split->account->name ?? '—' }}
                                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ number_format($split->amount, 0) }}</span>
                                                @if($split->related_fee_transaction_id && $split->feeTransaction)
                                                <span class="text-yellow-600 dark:text-yellow-400">+{{ number_format($split->feeTransaction->amount, 0) }}</span>
                                            @endif
                                            </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>

                        {{-- Amount --}}
                        <td class="px-3 py-2 text-right">
                            <div class="flex flex-col items-end gap-0.5">
                                    <span class="font-semibold tabular-nums
                                        {{ $isIncome
                                            ? 'text-green-600 dark:text-green-400'
                                            : ($isFee
                                                ? 'text-yellow-700 dark:text-yellow-400'
                                                : 'text-red-600 dark:text-red-400') }}">
                                        {{ $isIncome ? '+' : '−' }}{{ number_format($t->amount, 0) }}
                                    </span>

                                @if($isSplit)
                                    {{-- Show total including all split fees --}}
                                    @php
                                        $splitFeeTotal = $t->splits->sum(fn($s) => $s->feeTransaction?->amount ?? 0);
                                    @endphp
                                    @if($splitFeeTotal > 0)
                                        <span class="text-xs text-gray-400 dark:text-gray-500 tabular-nums">
                                                Total: {{ number_format($t->amount + $splitFeeTotal, 0) }}
                                            </span>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ $t->splits->count() }} accounts
                                            </span>
                                    @endif
                                @elseif(!$isFee && $t->hasFee())
                                    <span class="text-xs text-gray-400 dark:text-gray-500 tabular-nums">
                                            Total: {{ number_format($t->total_amount, 0) }}
                                        </span>
                                @endif
                            </div>
                        </td>

                        {{-- Account (hidden on mobile) --}}
                        <td class="px-3 py-2 hidden sm:table-cell text-gray-700 dark:text-gray-300">
                            @if($isSplit)
                                <div class="space-y-1.5">
                                    @foreach($t->splits as $split)
                                        <div class="flex items-center justify-between gap-4">
                                                <span class="text-xs text-gray-600 dark:text-gray-400">
                                                    {{ $split->account->name ?? '—' }}
                                                </span>
                                            <div class="flex items-center gap-1 text-xs tabular-nums">
                                                    <span class="font-medium text-gray-800 dark:text-gray-200">
                                                        {{ number_format($split->amount, 0) }}
                                                    </span>
                                                @if($split->related_fee_transaction_id && $split->feeTransaction)
                                                    <span class="text-yellow-600 dark:text-yellow-500" title="Includes fee">
                                                            +{{ number_format($split->feeTransaction->amount, 0) }}
                                                        </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

{{--                                    --}}{{-- Split totals footer --}}
{{--                                    @if($t->splits->count() > 1)--}}
{{--                                        @php $splitFeeTotal = $t->splits->sum(fn($s) => $s->feeTransaction?->amount ?? 0); @endphp--}}
{{--                                        <div class="border-t border-gray-200 dark:border-gray-600 pt-1 flex items-center justify-between gap-4">--}}
{{--                                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total</span>--}}
{{--                                            <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 tabular-nums">--}}
{{--                                                    {{ number_format($t->amount + $splitFeeTotal, 0) }}--}}
{{--                                                </span>--}}
{{--                                        </div>--}}
{{--                                    @endif--}}
                                </div>
                            @else
                                {{ $t->account->name ?? '—' }}
                            @endif
                        </td>

                        {{-- Category (hidden on mobile and tablet) --}}
                        <td class="px-3 py-2 hidden md:table-cell">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium
                                    {{ $isIncome
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : ($isFee
                                            ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                                            : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400') }}">
                                    {{ $t->category->name }}
                                </span>
                        </td>

                        {{-- Actions --}}
                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('transactions.show', $t) }}"
                               class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                            No transactions found.
                            <a href="{{ route('transactions.create') }}"
                               class="text-indigo-600 dark:text-indigo-400 hover:underline ml-1">
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
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 border dark:border-gray-700 p-4">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Filtered Total:
                    <span class="text-indigo-600 dark:text-indigo-400 ml-1">
                        KES {{ number_format($transactions->sum('amount'), 0) }}
                    </span>
                    <span class="text-xs text-gray-400 dark:text-gray-500 font-normal ml-2">
                        ({{ $transactions->total() }} transactions on {{ $transactions->lastPage() }} {{ Str::plural('page', $transactions->lastPage()) }}, showing page {{ $transactions->currentPage() }})
                    </span>
                </p>
            </div>
        @endif

        {{-- Pagination --}}
        <div class="pt-4 border-t dark:border-gray-700">
            @if($transactions->hasPages())
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-sm text-gray-700 dark:text-gray-300 order-2 sm:order-1">
                        Showing
                        <span class="font-medium">{{ $transactions->firstItem() ?? 0 }}</span>
                        to
                        <span class="font-medium">{{ $transactions->lastItem() ?? 0 }}</span>
                        of
                        <span class="font-medium">{{ number_format($transactions->total()) }}</span>
                        transactions
                    </div>
                    <div class="order-1 sm:order-2 w-full sm:w-auto">
                        <div class="flex justify-center sm:justify-end">
                            {{ $transactions->onEachSide(1)->links() }}
                        </div>
                    </div>
                </div>
            @else
                <div class="text-sm text-gray-700 dark:text-gray-300 text-center">
                    Showing
                    <span class="font-medium">{{ $transactions->count() }}</span>
                    {{ Str::plural('transaction', $transactions->count()) }}
                </div>
            @endif
        </div>

    </div>

    <style>
        nav[role="navigation"] p.text-sm { display: none !important; }
        nav[role="navigation"] { width: 100%; }
        nav[role="navigation"] > div { display: flex; align-items: center; gap: 0.25rem; }
        nav[role="navigation"] a,
        nav[role="navigation"] span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2.5rem; padding: 0.5rem 0.75rem;
            font-size: 0.875rem; font-weight: 500;
            border: 1px solid #e5e7eb; border-radius: 0.375rem; transition: all 0.2s;
        }
        nav[role="navigation"] a { background-color: white; color: #374151; text-decoration: none; }
        nav[role="navigation"] a:hover:not([rel="prev"]):not([rel="next"]) {
            background-color: #f3f4f6 !important; border-color: #d1d5db !important; color: #111827 !important;
        }
        nav[role="navigation"] span[aria-current="page"] {
            background-color: #e5e7eb !important; border-color: #d1d5db !important;
            color: #111827 !important; font-weight: 600;
        }
        nav[role="navigation"] span[aria-disabled="true"] {
            background-color: #f9fafb; color: #d1d5db; cursor: not-allowed; opacity: 0.6;
        }
        nav[role="navigation"] a[rel="prev"],
        nav[role="navigation"] a[rel="next"] { padding: 0.5rem 1rem; font-weight: 500; }
        nav[role="navigation"] a[rel="prev"]:hover,
        nav[role="navigation"] a[rel="next"]:hover {
            background-color: #4f46e5 !important; border-color: #4f46e5 !important; color: white !important;
        }
        nav[role="navigation"] span[aria-disabled="true"]:not([role]) {
            border: none; background: transparent; padding: 0.5rem 0.25rem;
        }
        @media (max-width: 640px) {
            nav[role="navigation"] a[href]:not([rel="prev"]):not([rel="next"]) { display: none !important; }
            nav[role="navigation"] span[aria-current="page"] { display: inline-flex !important; }
            nav[role="navigation"] span[aria-disabled="true"]:not([role]) { display: none !important; }
            nav[role="navigation"] a[rel="prev"],
            nav[role="navigation"] a[rel="next"] {
                padding: 0.625rem 1rem !important; font-size: 0.875rem !important; flex: 1; max-width: 120px;
            }
            nav[role="navigation"] > div { justify-content: center !important; gap: 0.5rem; }
        }
        @media (min-width: 641px) { nav[role="navigation"] > div { justify-content: flex-end; } }
        @media (prefers-color-scheme: dark) {
            nav[role="navigation"] a { background-color: #374151; color: #e5e7eb; border-color: #4b5563; }
            nav[role="navigation"] a:hover:not([rel="prev"]):not([rel="next"]) {
                background-color: #4b5563 !important; border-color: #6b7280 !important;
            }
            nav[role="navigation"] span[aria-current="page"] {
                background-color: #4b5563 !important; border-color: #6b7280 !important; color: #f3f4f6 !important;
            }
            nav[role="navigation"] span[aria-disabled="true"] { background-color: #1f2937; color: #6b7280; }
        }
    </style>

    <x-floating-action-button :quickAccount="$accounts->first()" />
</x-app-layout>
